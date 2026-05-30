<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Validator;

/**
 * XSD schema validator backed by libxml::schemaValidate().
 *
 * DIAN publishes the UBL 2.1 + extension XSDs at
 * https://www.dian.gov.co/impuestos/factura-electronica/Documents/UBL-2.1.zip
 * — drop the unzipped tree under resources/xsd/ and point this validator at
 * the appropriate root XSD (UBLInvoice-2.1.xsd / UBLCreditNote-2.1.xsd /
 * UBLDebitNote-2.1.xsd).
 *
 * When the XSD path is missing, validate() returns an empty (valid) result
 * with a single warning so the caller can decide to skip XSD checks in CI/tests.
 */
final class XsdValidator
{
    private string $xsdPath;

    public function __construct(string $xsdPath)
    {
        $this->xsdPath = $xsdPath;
    }

    public function validate(string $xml): ValidationResult
    {
        $result = new ValidationResult();

        if (!file_exists($this->xsdPath)) {
            $result->add(
                'XSD_000',
                "XSD file not found at {$this->xsdPath} — XSD validation skipped.",
                '',
                'warning'
            );
            return $result;
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        if (!$dom->schemaValidate($this->xsdPath)) {
            foreach (libxml_get_errors() as $error) {
                $result->add(
                    'XSD_' . str_pad((string) $error->code, 3, '0', STR_PAD_LEFT),
                    trim($error->message),
                    'line ' . $error->line
                );
            }
            libxml_clear_errors();
        }

        libxml_use_internal_errors($previous);
        return $result;
    }
}
