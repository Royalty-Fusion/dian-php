<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Signer;

/**
 * XAdES-EPES enveloped signer compliant with DIAN Anexo Técnico V1.9 and the
 * official signature policy v2.
 *
 * Produces the exact structure found in DIAN-approved production documents
 * (see resources/fixtures): one ds:Signature placed in the last empty
 * ext:ExtensionContent, with three references —
 *   1. URI=""               the whole document (enveloped-signature transform)
 *   2. URI="#…-keyinfo"     ds:KeyInfo
 *   3. URI="#…-signedprops" xades:SignedProperties
 * — inclusive C14N, RSA-SHA256 and SHA-256 digests.
 *
 * The signer is verified in the test-suite with {@see SignatureVerifier},
 * which in turn is verified against real DIAN-approved signatures.
 */
class XadesSigner
{
    /** Official DIAN signature policy v2 */
    public const POLICY_URL          = 'https://facturaelectronica.dian.gov.co/politicadefirma/v2/politicadefirmav2.pdf';
    public const POLICY_HASH_SHA256  = 'dMoMvtcG5aIzgYo0tIsSQeVJBDnUnfSOfBpxXrmor0Y=';
    public const POLICY_DESCRIPTION  = 'Política de firma para facturas electrónicas de la República de Colombia';

    public const ROLE_SUPPLIER    = 'supplier';
    public const ROLE_THIRD_PARTY = 'third party';
    public const ROLE_CUSTOMER    = 'customer';

    private const NS_DS    = 'http://www.w3.org/2000/09/xmldsig#';
    private const NS_XADES = 'http://uri.etsi.org/01903/v1.3.2#';
    private const NS_EXT   = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';

    private const C14N      = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';
    private const RSA_SHA256 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
    private const SHA256    = 'http://www.w3.org/2001/04/xmlenc#sha256';
    private const ENVELOPED = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';

    private ?Certificate $certificate = null;
    private ?string $certPath;
    private ?string $password;
    private ?string $signerRole;
    private ?\DateTimeInterface $fixedSigningTime = null;

    /**
     * @param  string|null  $signerRole  Emits <xades:SignerRole>. Use ROLE_SUPPLIER when the
     *  issuer signs with its own certificate (default) or ROLE_THIRD_PARTY when a
     *  proveedor tecnológico signs on behalf of the issuer. Null omits the block.
     */
    public function __construct(?string $certPath = null, ?string $password = null, ?string $signerRole = self::ROLE_SUPPLIER)
    {
        $this->certPath   = $certPath;
        $this->password   = $password;
        $this->signerRole = $signerRole;
    }

    public static function fromCertificate(Certificate $certificate, ?string $signerRole = self::ROLE_SUPPLIER): self
    {
        $signer = new self(null, null, $signerRole);
        $signer->certificate = $certificate;

        return $signer;
    }

    /** Freezes xades:SigningTime — only meant for reproducible tests. */
    public function setSigningTime(?\DateTimeInterface $time): self
    {
        $this->fixedSigningTime = $time;
        return $this;
    }

    public function getCertificate(): Certificate
    {
        if ($this->certificate === null) {
            if ($this->certPath === null) {
                throw new \RuntimeException('No se configuró un certificado para firmar.');
            }
            $this->certificate = Certificate::fromPkcs12File($this->certPath, (string) $this->password);
        }

        return $this->certificate;
    }

    /**
     * Signs a UBL document and returns the signed XML.
     *
     * @throws \RuntimeException when the XML is malformed, has no signature slot
     *                           or the certificate cannot be used.
     */
    public function sign(string $xml): string
    {
        $cert = $this->getCertificate();

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput       = false;
        libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors(false);
        if (!$loaded) {
            $first = $errors[0] ?? null;
            throw new \RuntimeException(
                'El XML a firmar no está bien formado'
                . ($first ? sprintf(' (línea %d: %s)', $first->line, trim($first->message)) : '')
            );
        }

        $target = $this->findSignatureSlot($dom);

        // 1) Digest of the document before the signature exists — identical to
        //    applying the enveloped-signature transform afterwards.
        $docDigest = $this->digest((string) $dom->C14N(false, false));

        $uid           = self::uuid();
        $sigId         = "xmldsig-{$uid}";
        $refDocId      = "{$sigId}-ref0";
        $keyInfoId     = "{$sigId}-keyinfo";
        $signedPropsId = "{$sigId}-signedprops";

        $signature = $dom->createElementNS(self::NS_DS, 'ds:Signature');
        $signature->setAttribute('Id', $sigId);
        $target->appendChild($signature);

        $signedInfo = $this->ds($dom, 'SignedInfo');
        $signature->appendChild($signedInfo);
        $signedInfo->appendChild($this->ds($dom, 'CanonicalizationMethod', null, ['Algorithm' => self::C14N]));
        $signedInfo->appendChild($this->ds($dom, 'SignatureMethod', null, ['Algorithm' => self::RSA_SHA256]));

        $refDoc = $this->reference($dom, '', $docDigest, ['Id' => $refDocId], true);
        $signedInfo->appendChild($refDoc);
        $refKeyInfo = $this->reference($dom, '#' . $keyInfoId, '');
        $signedInfo->appendChild($refKeyInfo);
        $refProps = $this->reference($dom, '#' . $signedPropsId, '', ['Type' => 'http://uri.etsi.org/01903#SignedProperties']);
        $signedInfo->appendChild($refProps);

        $signatureValue = $this->ds($dom, 'SignatureValue', null, ['Id' => "{$sigId}-sigvalue"]);
        $signature->appendChild($signatureValue);

        $keyInfo = $this->ds($dom, 'KeyInfo', null, ['Id' => $keyInfoId]);
        $x509    = $this->ds($dom, 'X509Data');
        $x509->appendChild($this->ds($dom, 'X509Certificate', $cert->getCertificateBase64()));
        $keyInfo->appendChild($x509);
        $signature->appendChild($keyInfo);

        $object = $this->ds($dom, 'Object');
        $signature->appendChild($object);
        $qualifying = $this->xades($dom, 'QualifyingProperties', null, ['Target' => '#' . $sigId]);
        $object->appendChild($qualifying);
        $signedProps = $this->xades($dom, 'SignedProperties', null, ['Id' => $signedPropsId]);
        $qualifying->appendChild($signedProps);
        $signedProps->appendChild($this->signedSignatureProperties($dom, $cert));

        $dataObj = $this->xades($dom, 'SignedDataObjectProperties');
        $format  = $this->xades($dom, 'DataObjectFormat', null, ['ObjectReference' => '#' . $refDocId]);
        $format->appendChild($this->xades($dom, 'MimeType', 'text/xml'));
        $format->appendChild($this->xades($dom, 'Encoding', 'UTF-8'));
        $dataObj->appendChild($format);
        $signedProps->appendChild($dataObj);

        // 2) Digests of KeyInfo and SignedProperties, computed in their final
        //    position so inherited namespaces are part of the canonical form.
        $this->setDigest($refKeyInfo, $this->digest((string) $keyInfo->C14N(false, false)));
        $this->setDigest($refProps, $this->digest((string) $signedProps->C14N(false, false)));

        // 3) Sign the canonical SignedInfo.
        $raw = '';
        if (!openssl_sign((string) $signedInfo->C14N(false, false), $raw, $cert->getPrivateKeyPem(), OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('OpenSSL no pudo firmar el documento: ' . (openssl_error_string() ?: 'error desconocido'));
        }
        $signatureValue->appendChild($dom->createTextNode(base64_encode($raw)));

        return (string) $dom->saveXML();
    }

    private function signedSignatureProperties(\DOMDocument $dom, Certificate $cert): \DOMElement
    {
        $props = $this->xades($dom, 'SignedSignatureProperties');

        $time = $this->fixedSigningTime
            ?? new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota'));
        $props->appendChild($this->xades($dom, 'SigningTime', $time->format('Y-m-d\TH:i:sP')));

        $signingCert = $this->xades($dom, 'SigningCertificate');
        $certNode    = $this->xades($dom, 'Cert');
        $certDigest  = $this->xades($dom, 'CertDigest');
        $certDigest->appendChild($this->ds($dom, 'DigestMethod', null, ['Algorithm' => self::SHA256]));
        $certDigest->appendChild($this->ds($dom, 'DigestValue', $cert->getDigest()));
        $certNode->appendChild($certDigest);
        $issuerSerial = $this->xades($dom, 'IssuerSerial');
        $issuerSerial->appendChild($this->ds($dom, 'X509IssuerName', $cert->getIssuerName()));
        $issuerSerial->appendChild($this->ds($dom, 'X509SerialNumber', $cert->getSerialNumber()));
        $certNode->appendChild($issuerSerial);
        $signingCert->appendChild($certNode);
        $props->appendChild($signingCert);

        $policy   = $this->xades($dom, 'SignaturePolicyIdentifier');
        $policyId = $this->xades($dom, 'SignaturePolicyId');
        $sigPolicyId = $this->xades($dom, 'SigPolicyId');
        $sigPolicyId->appendChild($this->xades($dom, 'Identifier', self::POLICY_URL));
        $sigPolicyId->appendChild($this->xades($dom, 'Description', self::POLICY_DESCRIPTION));
        $policyId->appendChild($sigPolicyId);
        $policyHash = $this->xades($dom, 'SigPolicyHash');
        $policyHash->appendChild($this->ds($dom, 'DigestMethod', null, ['Algorithm' => self::SHA256]));
        $policyHash->appendChild($this->ds($dom, 'DigestValue', self::POLICY_HASH_SHA256));
        $policyId->appendChild($policyHash);
        $policy->appendChild($policyId);
        $props->appendChild($policy);

        if ($this->signerRole !== null) {
            $role    = $this->xades($dom, 'SignerRole');
            $claimed = $this->xades($dom, 'ClaimedRoles');
            $claimed->appendChild($this->xades($dom, 'ClaimedRole', $this->signerRole));
            $role->appendChild($claimed);
            $props->appendChild($role);
        }

        return $props;
    }

    /**
     * DIAN documents carry the signature in the last empty ext:ExtensionContent:
     * the 2nd one for Invoice/CreditNote/DebitNote/DS/Nómina, the only one for
     * AttachedDocument/ApplicationResponse.
     */
    private function findSignatureSlot(\DOMDocument $dom): \DOMElement
    {
        $nodes = $dom->getElementsByTagNameNS(self::NS_EXT, 'ExtensionContent');
        for ($i = $nodes->length - 1; $i >= 0; $i--) {
            $candidate = $nodes->item($i);
            if ($candidate instanceof \DOMElement
                && trim($candidate->textContent) === ''
                && $candidate->getElementsByTagName('*')->length === 0) {
                // Drop whitespace-only text so the slot stays byte-identical after
                // the enveloped transform removes the signature.
                while ($candidate->firstChild !== null) {
                    $candidate->removeChild($candidate->firstChild);
                }
                return $candidate;
            }
        }

        throw new \RuntimeException(
            'El documento no tiene un <ext:ExtensionContent/> vacío donde insertar la firma.'
        );
    }

    /** @param array<string,string> $attrs */
    private function reference(\DOMDocument $dom, string $uri, string $digest, array $attrs = [], bool $enveloped = false): \DOMElement
    {
        $ref = $this->ds($dom, 'Reference', null, $attrs + ['URI' => $uri]);
        if ($enveloped) {
            $transforms = $this->ds($dom, 'Transforms');
            $transforms->appendChild($this->ds($dom, 'Transform', null, ['Algorithm' => self::ENVELOPED]));
            $ref->appendChild($transforms);
        }
        $ref->appendChild($this->ds($dom, 'DigestMethod', null, ['Algorithm' => self::SHA256]));
        $ref->appendChild($this->ds($dom, 'DigestValue', $digest));

        return $ref;
    }

    private function setDigest(\DOMElement $reference, string $digest): void
    {
        foreach ($reference->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'DigestValue') {
                $child->appendChild($child->ownerDocument->createTextNode($digest));
                return;
            }
        }
    }

    private function digest(string $canonical): string
    {
        return base64_encode(hash('sha256', $canonical, true));
    }

    /** @param array<string,string> $attrs */
    private function ds(\DOMDocument $dom, string $name, ?string $text = null, array $attrs = []): \DOMElement
    {
        return $this->element($dom, self::NS_DS, 'ds:' . $name, $text, $attrs);
    }

    /** @param array<string,string> $attrs */
    private function xades(\DOMDocument $dom, string $name, ?string $text = null, array $attrs = []): \DOMElement
    {
        return $this->element($dom, self::NS_XADES, 'xades:' . $name, $text, $attrs);
    }

    /** @param array<string,string> $attrs */
    private function element(\DOMDocument $dom, string $ns, string $qName, ?string $text, array $attrs): \DOMElement
    {
        $el = $dom->createElementNS($ns, $qName);
        foreach ($attrs as $k => $v) {
            $el->setAttribute($k, $v);
        }
        if ($text !== null && $text !== '') {
            $el->appendChild($dom->createTextNode($text));
        }

        return $el;
    }

    private static function uuid(): string
    {
        $b    = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
