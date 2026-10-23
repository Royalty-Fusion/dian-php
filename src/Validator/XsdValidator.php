<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Validator;

/**
 * XSD validation against the UBL 2.1 + DIAN extension schemas bundled in
 * resources/xsd (OASIS UBL 2.1, DIAN_UBL_Structures, XMLDSig, XAdES 1.3.2/1.4.1).
 *
 * The root schema is chosen from the document element (Invoice, CreditNote,
 * DebitNote, ApplicationResponse, AttachedDocument) and the DIAN namespace is
 * imported as well, so sts:DianExtensions is validated too. Everything is
 * resolved from disk: no network access is ever needed.
 *
 * Every bundled schema is validated in CI against real DIAN-approved
 * documents (resources/fixtures).
 */
final class XsdValidator
{
    private const DIAN_NS = 'dian:gov:co:facturaelectronica:Structures-2-1';

    private string $xsdDir;
    private ?string $forcedXsd;

    /**
     * @param string|null $xsdPath null = bundled schemas with automatic selection;
     *                             a directory = alternative schema tree with the
     *                             same layout; a file = that exact root XSD.
     */
    public function __construct(?string $xsdPath = null)
    {
        $default = dirname(__DIR__, 2) . '/resources/xsd';
        if ($xsdPath !== null && is_file($xsdPath)) {
            $this->xsdDir    = $default;
            $this->forcedXsd = $xsdPath;
        } else {
            $this->xsdDir    = $xsdPath ?? $default;
            $this->forcedXsd = null;
        }
    }

    public function validate(string $xml): ValidationResult
    {
        $result = new ValidationResult();

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NONET)) {
            foreach (libxml_get_errors() as $error) {
                $result->add('XML_001', 'XML mal formado: ' . trim($error->message), 'línea ' . $error->line);
            }
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return $result;
        }

        $schema = $this->schemaFor($dom);
        if ($schema === null) {
            $result->add(
                'XSD_000',
                'No hay esquema XSD para el elemento raíz <' . $dom->documentElement?->localName . '>; validación XSD omitida.',
                '',
                'warning'
            );
            libxml_use_internal_errors($previous);
            return $result;
        }

        $valid = $this->forcedXsd !== null
            ? $dom->schemaValidate($schema, LIBXML_SCHEMA_CREATE)
            : $dom->schemaValidateSource($schema, LIBXML_SCHEMA_CREATE);

        if (!$valid) {
            foreach (libxml_get_errors() as $error) {
                if (self::isLibxmlIntegerLimit($error->message)) {
                    continue;
                }
                $result->add(
                    'XSD_' . str_pad((string) $error->code, 4, '0', STR_PAD_LEFT),
                    self::humanize(trim($error->message)),
                    'línea ' . $error->line
                );
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $result;
    }

    /** Returns a path (forced mode) or the source of a wrapper schema. */
    private function schemaFor(\DOMDocument $dom): ?string
    {
        if ($this->forcedXsd !== null) {
            return $this->forcedXsd;
        }

        $root = $dom->documentElement;
        if ($root === null) {
            return null;
        }
        $main = "{$this->xsdDir}/maindoc/UBL-{$root->localName}-2.1.xsd";
        $dian = "{$this->xsdDir}/common/DIAN_UBL_Structures.xsd";
        if (!is_file($main)) {
            return null;
        }

        $imports = sprintf(
            '<xsd:import namespace="%s" schemaLocation="%s"/>',
            htmlspecialchars((string) $root->namespaceURI, ENT_XML1 | ENT_QUOTES),
            self::fileUri($main)
        );
        if (is_file($dian)) {
            $imports .= sprintf(
                '<xsd:import namespace="%s" schemaLocation="%s"/>',
                self::DIAN_NS,
                self::fileUri($dian)
            );
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema" targetNamespace="urn:royaltyfusion:dian-php:xsd-driver">'
            . $imports
            . '</xsd:schema>';
    }

    private static function fileUri(string $path): string
    {
        $real = realpath($path) ?: $path;

        return 'file://' . (str_starts_with($real, '/') ? '' : '/') . str_replace(['\\', ' '], ['/', '%20'], $real);
    }

    /**
     * libxml validates xs:integer with a fixed precision (about 24 digits), but
     * certificate serial numbers are up to 20 bytes (49 decimal digits) and the
     * schema allows any integer. Those messages are false positives.
     */
    private static function isLibxmlIntegerLimit(string $message): bool
    {
        return str_contains($message, 'X509SerialNumber')
            && preg_match("/'(\\d{20,})' is not a valid value of the atomic type 'xs:integer'/", $message) === 1;
    }

    /** Shortens libxml messages: "{urn:...:CommonBasicComponents-2}ID" → "cbc:ID". */
    private static function humanize(string $message): string
    {
        return (string) preg_replace(
            [
                '/\{urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2\}/',
                '/\{urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2\}/',
                '/\{urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2\}/',
                '/\{dian:gov:co:facturaelectronica:Structures-2-1\}/',
            ],
            ['cbc:', 'cac:', 'ext:', 'sts:'],
            $message
        );
    }
}
