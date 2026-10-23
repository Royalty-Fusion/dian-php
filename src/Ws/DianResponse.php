<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Ws;

/**
 * Parsed answer of any DIAN web-service operation.
 *
 * DIAN (WCF) changes namespace prefixes between operations (b:, c:, a:…), so
 * every field is read by local-name. Handles:
 *   - DianResponse (SendBillSync, GetStatus, GetStatusZip): IsValid,
 *     StatusCode, StatusDescription, StatusMessage, ErrorMessage, XmlBase64Bytes…
 *   - UploadDocumentResponse (SendBillAsync / SendTestSetAsync): ZipKey,
 *     ErrorMessageList.
 *   - SOAP 1.2 faults (e.g. WS-Security rejected, wrong action).
 */
final class DianResponse
{
    private int $httpStatus;
    private string $raw;
    private ?string $fault = null;
    private ?bool $isValid = null;
    private string $statusCode = '';
    private string $statusDescription = '';
    private string $statusMessage = '';
    /** @var list<string> */
    private array $errors = [];
    private string $zipKey = '';
    private string $documentKey = '';
    private string $xmlFileName = '';
    private string $applicationResponseXml = '';
    private ?\DOMXPath $xpath = null;

    public function __construct(int $httpStatus, string $raw)
    {
        $this->httpStatus = $httpStatus;
        $this->raw        = $raw;
        $this->parse();
    }

    public static function transportError(string $message): self
    {
        $response        = new self(0, '');
        $response->fault = $message;

        return $response;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getRaw(): string
    {
        return $this->raw;
    }

    /** SOAP fault or transport error text; null when DIAN answered normally. */
    public function getFault(): ?string
    {
        return $this->fault;
    }

    /** True when DIAN validated the document (IsValid = true). */
    public function isValid(): bool
    {
        return $this->fault === null && $this->isValid === true;
    }

    /** Async upload accepted: DIAN returned a ZipKey and no errors. */
    public function isAccepted(): bool
    {
        return $this->fault === null && $this->zipKey !== '' && $this->rejections() === [];
    }

    /**
     * Document still being processed by DIAN (async flow). DIAN answers
     * StatusCode 66 / "en proceso" until the validation finishes.
     */
    public function isProcessing(): bool
    {
        return $this->statusCode === '66'
            || stripos($this->statusDescription, 'en proceso') !== false
            || stripos($this->statusDescription, 'procesando') !== false;
    }

    public function getStatusCode(): string
    {
        return $this->statusCode;
    }

    public function getStatusDescription(): string
    {
        return $this->statusDescription;
    }

    public function getStatusMessage(): string
    {
        return $this->statusMessage;
    }

    /** @return list<string> every ErrorMessage string returned by DIAN */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return list<string> messages that reject the document ("Rechazo") */
    public function rejections(): array
    {
        return array_values(array_filter(
            $this->errors,
            static fn (string $e): bool => stripos($e, 'Notificaci') === false
        ));
    }

    /** @return list<string> informative messages ("Notificación") that do not reject */
    public function notifications(): array
    {
        return array_values(array_filter(
            $this->errors,
            static fn (string $e): bool => stripos($e, 'Notificaci') !== false
        ));
    }

    public function getZipKey(): string
    {
        return $this->zipKey;
    }

    /** CUFE/CUDE DIAN associated with the document (XmlDocumentKey). */
    public function getDocumentKey(): string
    {
        return $this->documentKey;
    }

    public function getXmlFileName(): string
    {
        return $this->xmlFileName;
    }

    /** ApplicationResponse signed by DIAN (decoded from XmlBase64Bytes / zip). */
    public function getApplicationResponseXml(): string
    {
        return $this->applicationResponseXml;
    }

    /**
     * Value of the first element with that local-name, for operations whose
     * payload is not covered by the typed getters.
     */
    public function value(string $localName): string
    {
        if ($this->xpath === null) {
            return '';
        }

        return trim((string) $this->xpath->evaluate("string((//*[local-name()='{$localName}'])[1])"));
    }

    /**
     * Records under every element with that local-name, flattened to
     * [childLocalName => value] — e.g. records('NumberRangeResponse').
     *
     * @return list<array<string,string>>
     */
    public function records(string $localName): array
    {
        if ($this->xpath === null) {
            return [];
        }
        $out = [];
        foreach ($this->xpath->query("//*[local-name()='{$localName}']") ?: [] as $node) {
            $row = [];
            foreach ($node->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    $row[$child->localName] = trim($child->textContent);
                }
            }
            $out[] = $row;
        }

        return $out;
    }

    /** One-line human summary, used as Result error message. */
    public function summary(): string
    {
        if ($this->fault !== null) {
            return $this->fault;
        }
        $parts = array_filter([
            $this->statusCode !== '' ? "[{$this->statusCode}]" : '',
            $this->statusDescription,
            $this->statusMessage !== $this->statusDescription ? $this->statusMessage : '',
        ]);
        $summary = trim(implode(' ', $parts));
        if ($this->rejections() !== []) {
            $summary .= ($summary !== '' ? ' — ' : '') . implode(' | ', $this->rejections());
        }

        return $summary !== '' ? $summary : 'Respuesta DIAN sin estado (HTTP ' . $this->httpStatus . ').';
    }

    private function parse(): void
    {
        if (trim($this->raw) === '') {
            if ($this->httpStatus >= 400 || $this->httpStatus === 0) {
                $this->fault = 'HTTP ' . $this->httpStatus . ' sin contenido.';
            }
            return;
        }

        $dom      = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded   = $dom->loadXML($this->raw, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            $this->fault = 'HTTP ' . $this->httpStatus . ': respuesta no XML: ' . substr(strip_tags($this->raw), 0, 300);
            return;
        }

        $this->xpath = new \DOMXPath($dom);
        $x = $this->xpath;

        $faults = $x->query("//*[local-name()='Fault']");
        $fault  = $faults !== false ? $faults->item(0) : null;
        if ($fault !== null) {
            $reason = trim((string) $x->evaluate("string(.//*[local-name()='Reason']/*[local-name()='Text'])", $fault));
            $code   = trim((string) $x->evaluate("string(.//*[local-name()='Subcode']/*[local-name()='Value'])", $fault))
                ?: trim((string) $x->evaluate("string(.//*[local-name()='Code']/*[local-name()='Value'])", $fault));
            $this->fault = 'SOAP Fault' . ($code !== '' ? " ({$code})" : '') . ': ' . ($reason !== '' ? $reason : trim($fault->textContent));
            return;
        }

        $isValid = $this->value('IsValid');
        if ($isValid !== '') {
            $this->isValid = strtolower($isValid) === 'true';
        }
        $this->statusCode        = $this->value('StatusCode');
        $this->statusDescription = $this->value('StatusDescription');
        $this->statusMessage     = $this->value('StatusMessage');
        $this->zipKey            = $this->value('ZipKey');
        $this->documentKey       = $this->value('XmlDocumentKey');
        $this->xmlFileName       = $this->value('XmlFileName');

        foreach ($x->query("//*[local-name()='ErrorMessage' or local-name()='ErrorMessageList']//*[local-name()='string' or local-name()='processedMessage']") ?: [] as $node) {
            $text = trim($node->textContent);
            if ($text !== '') {
                $this->errors[] = $text;
            }
        }
        foreach ($x->query("//*[local-name()='XmlParamsResponseTrackId']") ?: [] as $node) {
            $msg = trim((string) $x->evaluate("string(*[local-name()='ProcessedMessage'])", $node));
            if ($msg !== '') {
                $this->errors[] = $msg;
            }
        }

        $b64 = $this->value('XmlBase64Bytes');
        if ($b64 === '') {
            // GetStatusZip answers the base64 zip directly in its *Result element.
            $direct = trim((string) $x->evaluate("string(//*[local-name()='GetStatusZipResult'])"));
            if ($direct !== '' && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $direct) === 1) {
                $b64 = $direct;
            }
        }
        if ($b64 !== '') {
            $this->applicationResponseXml = self::decodePayload($b64);
        }
    }

    /** Base64 of either a zip (first .xml inside) or the XML itself. */
    private static function decodePayload(string $b64): string
    {
        $bytes = base64_decode(preg_replace('/\s+/', '', $b64) ?? '', true);
        if ($bytes === false || $bytes === '') {
            return '';
        }
        if (!str_starts_with($bytes, "PK")) {
            return $bytes;
        }

        $file = tempnam(sys_get_temp_dir(), 'dian_resp_');
        if ($file === false) {
            return '';
        }
        try {
            file_put_contents($file, $bytes);
            $zip = new \ZipArchive();
            if ($zip->open($file) !== true) {
                return '';
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (str_ends_with(strtolower($name), '.xml')) {
                    $xml = (string) $zip->getFromIndex($i);
                    $zip->close();
                    return $xml;
                }
            }
            $zip->close();
            return '';
        } finally {
            @unlink($file);
        }
    }
}
