<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\AttachedDocument;

/**
 * Parser for AttachedDocument XMLs received from suppliers.
 *
 * Real-world use case: your ERP receives a B2B invoice by email; the file
 * is an AttachedDocument that bundles the signed Invoice and DIAN's
 * ApplicationResponse. This parser:
 *
 *   1. Reads the envelope metadata (sender, receiver, CUFE, parent doc id).
 *   2. Extracts the inner Invoice XML (still signed) for archival / accounting.
 *   3. Extracts the embedded ApplicationResponse XML to verify DIAN approval.
 */
final class AttachedDocumentParser
{
    /**
     * @param  string  $xml  AttachedDocument XML (string or file contents).
     * @throws \RuntimeException when the document is not a well-formed AttachedDocument.
     */
    public function parse(string $xml): AttachedDocument
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml)) {
            libxml_use_internal_errors($previous);
            throw new \RuntimeException('Invalid XML: cannot be parsed.');
        }
        libxml_use_internal_errors($previous);

        $root = $dom->documentElement;
        if ($root === null || $root->localName !== 'AttachedDocument') {
            throw new \RuntimeException('Root element is not <AttachedDocument>.');
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ad',  'urn:oasis:names:specification:ubl:schema:xsd:AttachedDocument-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $doc = new AttachedDocument();

        $doc->setId($this->one($xpath, '/ad:AttachedDocument/cbc:ID'));
        $doc->setParentDocumentId($this->one($xpath, '/ad:AttachedDocument/cbc:ParentDocumentID'));
        $doc->setCustomizationId($this->one($xpath, '/ad:AttachedDocument/cbc:CustomizationID'));
        $doc->setProfileId($this->one($xpath, '/ad:AttachedDocument/cbc:ProfileID'));
        $doc->setProfileExecutionId($this->one($xpath, '/ad:AttachedDocument/cbc:ProfileExecutionID') ?: '1');

        $issueDate = $this->one($xpath, '/ad:AttachedDocument/cbc:IssueDate');
        $issueTime = $this->one($xpath, '/ad:AttachedDocument/cbc:IssueTime');
        if ($issueDate !== '') {
            $stamp = $issueTime !== ''
                ? $issueDate . 'T' . $issueTime
                : $issueDate . 'T00:00:00';
            try {
                $doc->setIssueDate(new \DateTimeImmutable($stamp));
            } catch (\Throwable) {
                $doc->setIssueDate(new \DateTimeImmutable($issueDate));
            }
        }

        // Embedded payloads — DIAN wraps both XMLs inside CDATA blocks.
        $descriptions = $xpath->query('//cac:Attachment/cac:ExternalReference/cbc:Description');
        if ($descriptions !== false) {
            foreach ($descriptions as $i => $node) {
                $payload = trim($node->textContent);
                if ($payload === '') {
                    continue;
                }
                // The first <Description> is the Invoice / CreditNote / DebitNote;
                // the second (nested under ParentDocumentLineReference) is the
                // ApplicationResponse.
                if ($i === 0) {
                    $doc->setSignedInvoiceXml($payload);
                } else {
                    $doc->setApplicationResponseXml($payload);
                }
            }
        }

        return $doc;
    }

    /**
     * Convenience: returns true when the embedded ApplicationResponse reports
     * a DIAN-aprobada status (<cbc:ResponseCode>02</cbc:ResponseCode> or
     * "Aceptación tácita"). Returns false when no AR or status != 02.
     */
    public function isAccepted(AttachedDocument $doc): bool
    {
        $ar = $doc->getApplicationResponseXml();
        if ($ar === '') {
            return false;
        }
        return (bool) preg_match('/<cbc:ResponseCode[^>]*>\s*0?2\s*<\/cbc:ResponseCode>/', $ar);
    }

    private function one(\DOMXPath $xpath, string $expression): string
    {
        $nodes = $xpath->query($expression);
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }
        return trim((string) $nodes->item(0)->textContent);
    }
}
