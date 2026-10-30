<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Reception;

use RoyaltyFusion\DianPhp\AttachedDocument\AttachedDocumentParser;
use RoyaltyFusion\DianPhp\Signer\SignatureVerifier;

/**
 * Reads a supplier document (AttachedDocument or bare Invoice / CreditNote /
 * DebitNote XML) into a ReceivedDocument, verifying the XAdES signatures and
 * the DIAN ApplicationResponse.
 */
final class ReceivedDocumentReader
{
    private const NS = [
        'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
    ];

    public function __construct(private readonly bool $verifySignatures = true)
    {
    }

    /** @throws \RuntimeException when the XML is not a supported document */
    public function read(string $xml): ReceivedDocument
    {
        $xml = self::stripBom($xml);
        $applicationResponse = '';
        $root = self::load($xml)->documentElement;
        if ($root !== null && $root->localName === 'AttachedDocument') {
            $parser   = new AttachedDocumentParser();
            $attached = $parser->parse($xml);
            $xml      = self::stripBom($attached->getSignedInvoiceXml());
            $applicationResponse = $attached->getApplicationResponseXml();
            if ($xml === '') {
                throw new \RuntimeException('El AttachedDocument no contiene el documento electrónico.');
            }
        }

        $dom  = self::load($xml);
        $type = $dom->documentElement?->localName ?? '';
        if (!in_array($type, ['Invoice', 'CreditNote', 'DebitNote'], true)) {
            throw new \RuntimeException("Documento no soportado: <{$type}> (se espera Invoice, CreditNote o DebitNote).");
        }

        $x = new \DOMXPath($dom);
        foreach (self::NS as $prefix => $uri) {
            $x->registerNamespace($prefix, $uri);
        }
        $v = static fn (string $path, ?\DOMNode $ctx = null): string => trim((string) $x->evaluate("string({$path})", $ctx));
        $f = static fn (string $path, ?\DOMNode $ctx = null): float => (float) $v($path, $ctx);

        $root = '/*';
        $typeCode = match ($type) {
            'Invoice'    => $v("{$root}/cbc:InvoiceTypeCode"),
            'CreditNote' => $v("{$root}/cbc:CreditNoteTypeCode") ?: '91',
            default      => '92',
        };

        $party = static function (string $base) use ($v): array {
            $scheme = "{$base}/cac:Party/cac:PartyTaxScheme";

            return [
                'nit'               => $v("{$scheme}/cbc:CompanyID"),
                'dv'                => $v("{$scheme}/cbc:CompanyID/@schemeID"),
                'tipoDocumento'     => $v("{$scheme}/cbc:CompanyID/@schemeName"),
                'name'              => $v("{$scheme}/cbc:RegistrationName") ?: $v("{$base}/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName"),
                'email'             => $v("{$base}/cac:Party/cac:Contact/cbc:ElectronicMail"),
                'responsabilidades' => $v("{$scheme}/cbc:TaxLevelCode"),
                'taxSchemeId'       => $v("{$scheme}/cac:TaxScheme/cbc:ID"),
                'taxSchemeName'     => $v("{$scheme}/cac:TaxScheme/cbc:Name"),
            ];
        };
        $supplier = $party("{$root}/cac:AccountingSupplierParty");
        $customer = $party("{$root}/cac:AccountingCustomerParty");

        $totalsPath = $type === 'DebitNote' ? 'cac:RequestedMonetaryTotal' : 'cac:LegalMonetaryTotal';
        $totals = [
            'lineExtension' => $f("{$root}/{$totalsPath}/cbc:LineExtensionAmount"),
            'taxExclusive'  => $f("{$root}/{$totalsPath}/cbc:TaxExclusiveAmount"),
            'taxInclusive'  => $f("{$root}/{$totalsPath}/cbc:TaxInclusiveAmount"),
            'allowances'    => $f("{$root}/{$totalsPath}/cbc:AllowanceTotalAmount"),
            'charges'       => $f("{$root}/{$totalsPath}/cbc:ChargeTotalAmount"),
            'prepaid'       => $f("{$root}/{$totalsPath}/cbc:PrepaidAmount"),
            'payable'       => $f("{$root}/{$totalsPath}/cbc:PayableAmount"),
        ];

        $sumByCode = static function (string $tag) use ($x, $v, $f, $root): array {
            $out = [];
            foreach ($x->query("{$root}/cac:{$tag}/cac:TaxSubtotal") ?: [] as $sub) {
                $code = $v('cac:TaxCategory/cac:TaxScheme/cbc:ID', $sub);
                $out[$code] = round(($out[$code] ?? 0.0) + $f('cbc:TaxAmount', $sub), 2);
            }

            return $out;
        };

        $lines = [];
        foreach ($x->query("{$root}/cac:InvoiceLine | {$root}/cac:CreditNoteLine | {$root}/cac:DebitNoteLine") ?: [] as $line) {
            $found = $x->query('cbc:InvoicedQuantity | cbc:CreditedQuantity | cbc:DebitedQuantity', $line);
            $qty   = $found === false ? null : $found->item(0);
            $lines[] = [
                'description'   => $v('cac:Item/cbc:Description', $line),
                'quantity'      => (float) ($qty?->textContent ?? 0),
                'unitCode'      => $qty instanceof \DOMElement ? $qty->getAttribute('unitCode') : '',
                'price'         => $f('cac:Price/cbc:PriceAmount', $line),
                'lineExtension' => $f('cbc:LineExtensionAmount', $line),
                'code'          => $v('cac:Item/cac:StandardItemIdentification/cbc:ID', $line) ?: $v('cac:Item/cac:SellersItemIdentification/cbc:ID', $line),
            ];
        }

        $references = [];
        foreach ($x->query("{$root}/cac:BillingReference/cac:InvoiceDocumentReference") ?: [] as $ref) {
            $references[] = ['number' => $v('cbc:ID', $ref), 'uuid' => $v('cbc:UUID', $ref), 'date' => $v('cbc:IssueDate', $ref)];
        }

        $problems = [];
        if ($this->verifySignatures) {
            $verifier = new SignatureVerifier();
            $problems = $verifier->verify($xml);
            if ($applicationResponse !== '') {
                foreach ($verifier->verify($applicationResponse) as $problem) {
                    $problems[] = 'ApplicationResponse DIAN: ' . $problem;
                }
            }
        }

        return new ReceivedDocument(
            type: $type,
            typeCode: $typeCode,
            number: $v("{$root}/cbc:ID"),
            uuid: $v("{$root}/cbc:UUID"),
            uuidSchemeName: $v("{$root}/cbc:UUID/@schemeName"),
            customizationId: $v("{$root}/cbc:CustomizationID"),
            environment: $v("{$root}/cbc:ProfileExecutionID"),
            issueDate: self::date($v("{$root}/cbc:IssueDate"), $v("{$root}/cbc:IssueTime")),
            dueDate: self::date($v("{$root}/cbc:DueDate") ?: $v("{$root}/cac:PaymentMeans/cbc:PaymentDueDate")),
            currency: $v("{$root}/cbc:DocumentCurrencyCode") ?: 'COP',
            supplier: $supplier,
            customer: $customer,
            totals: $totals,
            taxes: $sumByCode('TaxTotal'),
            withholdings: $sumByCode('WithholdingTaxTotal'),
            lines: $lines,
            paymentMethod: $v("{$root}/cac:PaymentMeans/cbc:ID"),
            paymentMeans: $v("{$root}/cac:PaymentMeans/cbc:PaymentMeansCode"),
            billingReferences: $references,
            signedXml: $xml,
            applicationResponseXml: $applicationResponse,
            dianApproved: self::approved($applicationResponse),
            signatureProblems: $problems,
        );
    }

    /** DIAN ApplicationResponse with ResponseCode 02 (documento validado). */
    private static function approved(string $applicationResponse): bool
    {
        if ($applicationResponse === '') {
            return false;
        }
        $dom = self::load($applicationResponse);
        $x   = new \DOMXPath($dom);
        $x->registerNamespace('cac', self::NS['cac']);
        $x->registerNamespace('cbc', self::NS['cbc']);
        $code = trim((string) $x->evaluate('string(//cac:DocumentResponse/cac:Response/cbc:ResponseCode)'));

        return in_array($code, ['02', '2'], true);
    }

    private static function date(string $date, string $time = ''): ?\DateTimeImmutable
    {
        if ($date === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($date . ($time !== '' ? 'T' . $time : ''), new \DateTimeZone('America/Bogota'));
        } catch (\Exception) {
            return null;
        }
    }

    private static function load(string $xml): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom      = new \DOMDocument();
        $ok       = $xml !== '' && $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$ok) {
            throw new \RuntimeException('XML inválido: no se pudo leer el documento.');
        }

        return $dom;
    }

    private static function stripBom(string $xml): string
    {
        return trim(str_starts_with($xml, "\xEF\xBB\xBF") ? substr($xml, 3) : $xml);
    }
}
