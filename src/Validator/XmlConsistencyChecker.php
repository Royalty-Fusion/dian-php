<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Validator;

use RoyaltyFusion\DianPhp\Model\NitDvCalculator;
use RoyaltyFusion\DianPhp\Xml\CufeGenerator;

/**
 * Re-checks a rendered (and optionally signed) UBL document the way DIAN does
 * on reception: it only trusts the XML, never the PHP objects.
 *
 *   XML_010  CUFE/CUDE/CUDS recomputed from the XML ≠ cbc:UUID   (FAD06)
 *   XML_011  SoftwareSecurityCode recomputed ≠ the one in the XML
 *   XML_020  Σ líneas ≠ LineExtensionAmount
 *   XML_021  TaxInclusiveAmount ≠ LineExtensionAmount + tributos
 *   XML_022  PayableAmount ≠ TaxInclusive − descuentos + cargos − anticipos
 *   XML_023  TaxTotal ≠ Σ TaxSubtotal / TaxAmount ≠ base × tarifa
 *   XML_024  tributos del documento ≠ Σ tributos de las líneas
 *   XML_025  valor de línea ≠ cantidad × precio − descuentos + cargos
 *   XML_030  LineCountNumeric ≠ número de líneas
 *   XML_031  ProfileExecutionID ≠ UUID@schemeID / ambiente esperado
 *   XML_032  IssueTime sin zona horaria -05:00
 *   XML_033  QRCode no contiene el CUFE/CUDE
 *   XML_034  DV del emisor incorrecto
 */
final class XmlConsistencyChecker
{
    private const TOLERANCE = 0.01;

    /**
     * @param string|null $keyOrPin    clave técnica (factura) o PIN del software (notas, DS);
     *                                 null skips the CUFE/CUDE check
     * @param string|null $softwarePin PIN to verify SoftwareSecurityCode; null skips it
     * @param string|null $environment expected ProfileExecutionID ('1' or '2')
     */
    public function check(string $xml, ?string $keyOrPin = null, ?string $softwarePin = null, ?string $environment = null): ValidationResult
    {
        $result = new ValidationResult();
        $dom    = new \DOMDocument();
        if (!@$dom->loadXML($xml)) {
            $result->add('XML_001', 'El XML no está bien formado.');
            return $result;
        }
        $x = new \DOMXPath($dom);
        $x->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $x->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $x->registerNamespace('sts', 'dian:gov:co:facturaelectronica:Structures-2-1');

        $root = $dom->documentElement?->localName ?? '';
        if (!in_array($root, ['Invoice', 'CreditNote', 'DebitNote'], true)) {
            return $result;
        }
        $lineTag  = $root . 'Line';
        $qtyTag   = ['Invoice' => 'InvoicedQuantity', 'CreditNote' => 'CreditedQuantity', 'DebitNote' => 'DebitedQuantity'][$root];
        $totalTag = $root === 'DebitNote' ? 'RequestedMonetaryTotal' : 'LegalMonetaryTotal';
        $s = static fn (string $path, ?\DOMNode $ctx = null): string => trim((string) $x->evaluate("string({$path})", $ctx));
        $n = static fn (string $path, ?\DOMNode $ctx = null): float => (float) $s($path, $ctx);

        $id         = $s('/*/cbc:ID');
        $uuid       = $s('/*/cbc:UUID');
        $scheme     = $s('/*/cbc:UUID/@schemeName');
        $profile    = $s('/*/cbc:ProfileExecutionID');
        $issueDate  = $s('/*/cbc:IssueDate');
        $issueTime  = $s('/*/cbc:IssueTime');
        $lineExt    = $n("/*/cac:{$totalTag}/cbc:LineExtensionAmount");
        $taxIncl    = $n("/*/cac:{$totalTag}/cbc:TaxInclusiveAmount");
        $allowances = $n("/*/cac:{$totalTag}/cbc:AllowanceTotalAmount");
        $charges    = $n("/*/cac:{$totalTag}/cbc:ChargeTotalAmount");
        $prepaid    = $n("/*/cac:{$totalTag}/cbc:PrepaidAmount");
        $payable    = $n("/*/cac:{$totalTag}/cbc:PayableAmount");
        $isSupport  = str_starts_with($scheme, 'CUDS');

        // Document-level taxes by code.
        $docTaxes = [];
        foreach ($x->query('/*/cac:TaxTotal') ?: [] as $taxTotal) {
            $sum = 0.0;
            foreach ($x->query('cac:TaxSubtotal', $taxTotal) ?: [] as $sub) {
                $base    = $n('cbc:TaxableAmount', $sub);
                $amount  = $n('cbc:TaxAmount', $sub);
                $percent = $n('cac:TaxCategory/cbc:Percent', $sub);
                $code    = $s('cac:TaxCategory/cac:TaxScheme/cbc:ID', $sub);
                $sum    += $amount;
                $docTaxes[$code] = ($docTaxes[$code] ?? 0.0) + $amount;
                if ($percent > 0 && abs(round($base * $percent / 100, 2) - $amount) > 1.0) {
                    $result->add('XML_023', sprintf('Tributo %s: %.2f no es %.2f × %s%%.', $code, $amount, $base, $percent), '/*/cac:TaxTotal');
                }
            }
            if (abs($sum - $n('cbc:TaxAmount', $taxTotal)) > self::TOLERANCE) {
                $result->add('XML_023', 'Un cac:TaxTotal no es igual a la suma de sus cac:TaxSubtotal.', '/*/cac:TaxTotal');
            }
        }

        // Lines.
        $lines     = $x->query("/*/cac:{$lineTag}") ?: new \DOMNodeList();
        $sumLines  = 0.0;
        $lineTaxes = [];
        foreach ($lines as $i => $line) {
            $amount  = $n('cbc:LineExtensionAmount', $line);
            $sumLines += $amount;
            $expected = $n("cbc:{$qtyTag}", $line) * $n('cac:Price/cbc:PriceAmount', $line);
            foreach ($x->query('cac:AllowanceCharge', $line) ?: [] as $ac) {
                $expected += $s('cbc:ChargeIndicator', $ac) === 'true' ? $n('cbc:Amount', $ac) : -$n('cbc:Amount', $ac);
            }
            if ($n('cac:Price/cbc:PriceAmount', $line) > 0 && abs(round($expected, 2) - $amount) > self::TOLERANCE) {
                $result->add('XML_025', sprintf('Línea %d: %.2f ≠ cantidad × precio − descuentos + cargos (%.2f).', $i + 1, $amount, $expected));
            }
            foreach ($x->query('cac:TaxTotal/cac:TaxSubtotal', $line) ?: [] as $sub) {
                $code = $s('cac:TaxCategory/cac:TaxScheme/cbc:ID', $sub);
                $lineTaxes[$code] = ($lineTaxes[$code] ?? 0.0) + $n('cbc:TaxAmount', $sub);
            }
        }

        if (abs($sumLines - $lineExt) > self::TOLERANCE) {
            $result->add('XML_020', sprintf('Σ líneas (%.2f) ≠ LineExtensionAmount (%.2f).', $sumLines, $lineExt));
        }
        $totalTaxes = array_sum($docTaxes);
        if (abs(($lineExt + $totalTaxes) - $taxIncl) > self::TOLERANCE) {
            $result->add('XML_021', sprintf('TaxInclusiveAmount (%.2f) ≠ LineExtensionAmount + tributos (%.2f).', $taxIncl, $lineExt + $totalTaxes));
        }
        $expectedPayable = $taxIncl - $allowances + $charges - $prepaid;
        if (abs($expectedPayable - $payable) > self::TOLERANCE) {
            $result->add('XML_022', sprintf('PayableAmount (%.2f) ≠ TaxInclusive − descuentos + cargos − anticipos (%.2f).', $payable, $expectedPayable));
        }
        if ($lineTaxes !== []) {
            foreach ($docTaxes + $lineTaxes as $code => $_) {
                if (abs(($docTaxes[$code] ?? 0.0) - ($lineTaxes[$code] ?? 0.0)) > 1.0) {
                    $result->add('XML_024', sprintf('Tributo %s: documento %.2f ≠ líneas %.2f.', $code, $docTaxes[$code] ?? 0.0, $lineTaxes[$code] ?? 0.0));
                }
            }
        }

        if ((int) $s('/*/cbc:LineCountNumeric') !== $lines->length) {
            $result->add('XML_030', 'LineCountNumeric no corresponde al número de líneas.');
        }
        $schemeId = $s('/*/cbc:UUID/@schemeID');
        if ($schemeId !== '' && $schemeId !== $profile) {
            $result->add('XML_031', "UUID@schemeID ({$schemeId}) ≠ ProfileExecutionID ({$profile}).");
        }
        if ($environment !== null && $profile !== $environment) {
            $result->add('XML_031', "ProfileExecutionID {$profile} no corresponde al ambiente configurado ({$environment}).");
        }
        if (!str_ends_with($issueTime, '-05:00')) {
            $result->add('XML_032', "IssueTime '{$issueTime}' debe estar en hora colombiana (-05:00).", '/*/cbc:IssueTime', 'warning');
        }
        $qr = $s('//sts:QRCode');
        if ($qr !== '' && !str_contains($qr, $uuid)) {
            $result->add('XML_033', 'El sts:QRCode no contiene el CUFE/CUDE del documento.');
        }

        $supplierNit = $s('/*/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID');
        $supplierDv  = $s('/*/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID/@schemeID');
        $customerId  = $s('/*/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID');
        if ($s('/*/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID/@schemeName') === '31'
            && $supplierDv !== '' && (string) NitDvCalculator::compute($supplierNit) !== $supplierDv) {
            $result->add('XML_034', "DV del emisor ({$supplierDv}) incorrecto para el NIT {$supplierNit}.");
        }

        if ($keyOrPin !== null && !$isSupport) {
            $computed = hash('sha384', CufeGenerator::compose(
                $id,
                $issueDate,
                $issueTime,
                $lineExt,
                $docTaxes['01'] ?? 0.0,
                $docTaxes['04'] ?? 0.0,
                $docTaxes['03'] ?? 0.0,
                $payable,
                $supplierNit,
                $customerId,
                $keyOrPin,
                $profile
            ));
            if ($computed !== $uuid) {
                $result->add('XML_010', "El {$scheme} del XML no coincide con el recalculado desde sus propios valores (la DIAN lo rechaza con FAD06). Revise la clave técnica/PIN y el ambiente.", '/*/cbc:UUID');
            }
        }
        if ($keyOrPin !== null && $isSupport) {
            // DS: seller = AccountingSupplierParty, acquirer (issuer) = AccountingCustomerParty.
            $computed = hash('sha384', $id . $issueDate . $issueTime
                . number_format($lineExt, 2, '.', '') . '01' . number_format($docTaxes['01'] ?? 0.0, 2, '.', '')
                . number_format($payable, 2, '.', '') . $supplierNit . $customerId . $keyOrPin . $profile);
            if ($computed !== $uuid) {
                $result->add('XML_010', 'El CUDS del XML no coincide con el recalculado desde sus valores.', '/*/cbc:UUID');
            }
        }

        if ($softwarePin !== null) {
            $softwareId = $s('//sts:SoftwareID');
            $code       = $s('//sts:SoftwareSecurityCode');
            if ($code !== hash('sha384', $softwareId . $softwarePin . $id)) {
                $result->add('XML_011', 'SoftwareSecurityCode no corresponde a SoftwareID + PIN + número del documento.');
            }
        }

        return $result;
    }
}
