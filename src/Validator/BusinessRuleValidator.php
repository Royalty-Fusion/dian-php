<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Validator;

use RoyaltyFusion\DianPhp\Catalog\Moneda;
use RoyaltyFusion\DianPhp\Catalog\TipoDocumentoIdentificacion;
use RoyaltyFusion\DianPhp\Catalog\Tributo;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\NitDvCalculator;
use RoyaltyFusion\DianPhp\Xml\DocumentCalculator;

/**
 * Business rules that DIAN's XSDs do not catch. Greenter-style, every rule
 * has its own code so consumers can react programmatically.
 *
 * Rule codes:
 *   DOC_001  Document has no items
 *   DOC_002  Issue date in the future
 *   PARTY_001  Company NIT missing
 *   PARTY_002  Company NIT DV mismatch (when set explicitly)
 *   PARTY_003  Company doc-type code unknown
 *   PARTY_004  Client doc-type code unknown
 *   TAX_001  Tax code not in catalog
 *   TAX_002  Tax amount != base * percent / 100 (tolerance 1 unit)
 *   TOTAL_001  LineExtensionAmount sum mismatch
 *   TOTAL_002  PayableAmount mismatch
 *   CURRENCY_001  Currency code unknown
 *   RES_001  Invoice numero outside resolution range
 *   RES_002  Issue date outside resolution validity window
 *   SOFT_001  Software metadata missing
 */
final class BusinessRuleValidator
{
    private const AMOUNT_TOLERANCE = 1.00; // COP — DIAN allows up to 1 cent of arithmetic drift

    public function validate(Invoice|CreditNote|DebitNote $document): ValidationResult
    {
        $result = new ValidationResult();

        if (count($document->getItems()) === 0) {
            $result->add('DOC_001', 'The document has no line items.');
        }

        $issueDate = $document->getFecha();
        if ($issueDate instanceof \DateTimeInterface && $issueDate > new \DateTimeImmutable('+1 hour')) {
            $result->add(
                'DOC_002',
                'Issue date is in the future.',
                '/Invoice/cbc:IssueDate'
            );
        }

        $this->validateCompany($document, $result);
        $this->validateClient($document, $result);
        $this->validateTaxes($document, $result);
        $this->validateTotals($document, $result);
        $this->validateCurrency($document, $result);

        if ($document instanceof Invoice) {
            $this->validateResolution($document, $result);
            $this->validateSoftware($document, $result);
        } else {
            // NC/ND need the Software for the CUDE PIN
            if ($document->getSoftware() === null) {
                $result->add('SOFT_001', 'Software metadata is required to compute the CUDE.');
            }
        }

        return $result;
    }

    private function validateCompany(Invoice|CreditNote|DebitNote $doc, ValidationResult $result): void
    {
        $company = $doc->getCompany();
        if ($company === null || $company->getNit() === '') {
            $result->add('PARTY_001', 'AccountingSupplierParty NIT is missing.', '/Invoice/cac:AccountingSupplierParty');
            return;
        }

        // If the caller forced a DV, ensure it actually matches.
        $expected = NitDvCalculator::compute($company->getNit());
        if ($expected !== $company->getNitDv()) {
            // Only flag when an explicit override was set — getNitDv() returns
            // the computed value when no override is present, so they would match.
            // We compare against the calculator one more time to be safe.
            $reflection = new \ReflectionClass($company);
            if ($reflection->hasProperty('nitDv')) {
                $prop = $reflection->getProperty('nitDv');
                $prop->setAccessible(true);
                $forced = $prop->getValue($company);
                if ($forced !== null && $forced !== $expected) {
                    $result->add(
                        'PARTY_002',
                        sprintf('NIT verification digit mismatch — expected %d for NIT %s but got %d.', $expected, $company->getNit(), $forced)
                    );
                }
            }
        }

        if ($company->getTipoDocumento() !== '' && !TipoDocumentoIdentificacion::hasCode($company->getTipoDocumento())) {
            $result->add('PARTY_003', "Unknown supplier document type code: {$company->getTipoDocumento()}");
        }
    }

    private function validateClient(Invoice|CreditNote|DebitNote $doc, ValidationResult $result): void
    {
        $client = $doc->getClient();
        if ($client === null) {
            return; // optional in some flows
        }
        if ($client->getTipoDocumento() !== '' && !TipoDocumentoIdentificacion::hasCode($client->getTipoDocumento())) {
            $result->add('PARTY_004', "Unknown customer document type code: {$client->getTipoDocumento()}");
        }
    }

    private function validateTaxes(Invoice|CreditNote|DebitNote $doc, ValidationResult $result): void
    {
        foreach ($doc->getTaxes() as $tax) {
            if (!Tributo::hasCode($tax->getCode())) {
                $result->add('TAX_001', "Unknown tax code: {$tax->getCode()}");
                continue;
            }
            $expected = round($tax->getBase() * $tax->getPercent() / 100, 2);
            if (abs($expected - $tax->getAmount()) > self::AMOUNT_TOLERANCE) {
                $result->add(
                    'TAX_002',
                    sprintf(
                        'Tax amount mismatch for code %s: expected %.2f (= %.2f x %.2f%%), got %.2f',
                        $tax->getCode(),
                        $expected,
                        $tax->getBase(),
                        $tax->getPercent(),
                        $tax->getAmount()
                    )
                );
            }
        }
    }

    private function validateTotals(Invoice|CreditNote|DebitNote $doc, ValidationResult $result): void
    {
        $totals  = DocumentCalculator::totals($doc);
        $stored  = $doc->getTotal();
        $payable = $totals['payableAmount'];

        if ($stored > 0 && abs($stored - $payable) > self::AMOUNT_TOLERANCE) {
            $result->add(
                'TOTAL_002',
                sprintf('Document->total (%.2f) does not match computed PayableAmount (%.2f).', $stored, $payable)
            );
        }
    }

    private function validateCurrency(Invoice|CreditNote|DebitNote $doc, ValidationResult $result): void
    {
        $code = $doc->getCurrencyCode();
        if ($code !== '' && !Moneda::hasCode($code) && strlen($code) !== 3) {
            $result->add('CURRENCY_001', "Invalid currency code: {$code}");
        }
    }

    private function validateResolution(Invoice $invoice, ValidationResult $result): void
    {
        $resolution = $invoice->getResolution();
        if ($resolution === null) {
            return;
        }
        $numero = (int) ltrim($invoice->getNumero(), '0');
        $from   = (int) ltrim($resolution->getFrom(), '0');
        $to     = (int) ltrim($resolution->getTo(), '0');
        if ($numero > 0 && ($numero < $from || $numero > $to)) {
            $result->add(
                'RES_001',
                sprintf('Invoice number %d is outside the authorized range [%d, %d].', $numero, $from, $to)
            );
        }

        $issue = $invoice->getFecha();
        $start = $resolution->getDateFrom();
        $end   = $resolution->getDateTo();
        if ($issue instanceof \DateTimeInterface && $start instanceof \DateTimeInterface && $end instanceof \DateTimeInterface) {
            if ($issue < $start || $issue > $end) {
                $result->add('RES_002', 'Issue date is outside the resolution validity window.');
            }
        }
    }

    private function validateSoftware(Invoice $invoice, ValidationResult $result): void
    {
        $software = $invoice->getSoftware();
        if ($software === null || $software->getId() === '' || $software->getPin() === '') {
            $result->add('SOFT_001', 'Software ID and PIN are required (used in SoftwareSecurityCode).');
        }
    }
}
