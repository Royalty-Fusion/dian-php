<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Ws;

use RoyaltyFusion\DianPhp\Signer\Certificate;

/**
 * Builds SOAP 1.2 envelopes for the DIAN web service (WcfDianCustomerServices)
 * with WS-Addressing and WS-Security, as required by DIAN in both
 * habilitación and producción:
 *
 *   soap:Header
 *     wsse:Security
 *       wsu:Timestamp (Created / Expires)
 *       wsse:BinarySecurityToken (X.509v3 of the facturador)
 *       ds:Signature  — RSA-SHA256 over wsa:To, exclusive C14N
 *     wsa:Action  http://wcf.dian.colombia/IWcfDianCustomerServices/<Operation>
 *     wsa:To      endpoint URL (signed element)
 *   soap:Body
 *     wcf:<Operation> …
 */
final class WsSecurity
{
    public const NS_SOAP = 'http://www.w3.org/2003/05/soap-envelope';
    public const NS_WCF  = 'http://wcf.dian.colombia';
    public const NS_WSA  = 'http://www.w3.org/2005/08/addressing';
    public const NS_WSSE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
    public const NS_WSU  = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
    public const NS_DS   = 'http://www.w3.org/2000/09/xmldsig#';
    public const NS_EC   = 'http://www.w3.org/2001/10/xml-exc-c14n#';

    private const X509_TOKEN = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3';
    private const BASE64     = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary';
    private const EXC_C14N   = 'http://www.w3.org/2001/10/xml-exc-c14n#';

    private ?Certificate $certificate;
    private int $ttlSeconds;
    private ?\DateTimeInterface $clock = null;

    /**
     * @param int $ttlSeconds Lifetime of wsu:Timestamp. The generous default
     *                        absorbs clock skew between your server and DIAN.
     */
    public function __construct(?Certificate $certificate, int $ttlSeconds = 60000)
    {
        $this->certificate = $certificate;
        $this->ttlSeconds  = $ttlSeconds;
    }

    /** Freezes the timestamp (tests only). */
    public function setClock(?\DateTimeInterface $clock): self
    {
        $this->clock = $clock;
        return $this;
    }

    /**
     * @param string $operation   e.g. "SendBillSync"
     * @param string $bodyContent XML of the wcf:<Operation> children (already escaped)
     */
    public function envelope(string $operation, string $bodyContent, string $endpoint): string
    {
        $action = 'http://wcf.dian.colombia/IWcfDianCustomerServices/' . $operation;

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $envelope = $dom->createElementNS(self::NS_SOAP, 'soap:Envelope');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wcf', self::NS_WCF);
        $dom->appendChild($envelope);

        $header = $dom->createElementNS(self::NS_SOAP, 'soap:Header');
        $header->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wsa', self::NS_WSA);
        $envelope->appendChild($header);

        $toId = 'ID-' . self::id();
        $security = null;
        if ($this->certificate !== null) {
            $security = $dom->createElementNS(self::NS_WSSE, 'wsse:Security');
            $security->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wsu', self::NS_WSU);
            $header->appendChild($security);
        }

        $header->appendChild($dom->createElementNS(self::NS_WSA, 'wsa:Action', $action));
        $to = $dom->createElementNS(self::NS_WSA, 'wsa:To', $endpoint);
        $to->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wsu', self::NS_WSU);
        $to->setAttributeNS(self::NS_WSU, 'wsu:Id', $toId);
        $header->appendChild($to);

        $body = $dom->createElementNS(self::NS_SOAP, 'soap:Body');
        $envelope->appendChild($body);
        $operationNode = $dom->createElementNS(self::NS_WCF, 'wcf:' . $operation);
        $body->appendChild($operationNode);
        if ($bodyContent !== '') {
            $fragment = $dom->createDocumentFragment();
            $wrapped  = '<w xmlns:wcf="' . self::NS_WCF . '">' . $bodyContent . '</w>';
            $tmp      = new \DOMDocument();
            if (!$tmp->loadXML($wrapped)) {
                throw new \InvalidArgumentException('Contenido SOAP inválido para ' . $operation);
            }
            foreach ($tmp->documentElement?->childNodes ?? [] as $child) {
                $fragment->appendChild($dom->importNode($child, true));
            }
            $operationNode->appendChild($fragment);
        }

        if ($security !== null && $this->certificate !== null) {
            $this->sign($dom, $security, $to, $toId, $this->certificate);
        }

        return (string) $dom->saveXML($dom->documentElement);
    }

    private function sign(\DOMDocument $dom, \DOMElement $security, \DOMElement $to, string $toId, Certificate $cert): void
    {
        $now     = $this->clock !== null
            ? \DateTimeImmutable::createFromInterface($this->clock)
            : new \DateTimeImmutable('now');
        $now     = $now->setTimezone(new \DateTimeZone('UTC'));
        $expires = $now->modify('+' . $this->ttlSeconds . ' seconds');

        $timestamp = $dom->createElementNS(self::NS_WSU, 'wsu:Timestamp');
        $timestamp->setAttributeNS(self::NS_WSU, 'wsu:Id', 'TS-' . self::id());
        $timestamp->appendChild($dom->createElementNS(self::NS_WSU, 'wsu:Created', $now->format('Y-m-d\TH:i:s.v\Z')));
        $timestamp->appendChild($dom->createElementNS(self::NS_WSU, 'wsu:Expires', $expires->format('Y-m-d\TH:i:s.v\Z')));
        $security->appendChild($timestamp);

        $tokenId = 'X509-' . self::id();
        $token   = $dom->createElementNS(self::NS_WSSE, 'wsse:BinarySecurityToken', $cert->getCertificateBase64());
        $token->setAttribute('EncodingType', self::BASE64);
        $token->setAttribute('ValueType', self::X509_TOKEN);
        $token->setAttributeNS(self::NS_WSU, 'wsu:Id', $tokenId);
        $security->appendChild($token);

        $signature = $dom->createElementNS(self::NS_DS, 'ds:Signature');
        $signature->setAttribute('Id', 'SIG-' . self::id());
        $security->appendChild($signature);

        $signedInfo = $dom->createElementNS(self::NS_DS, 'ds:SignedInfo');
        $signature->appendChild($signedInfo);

        $c14n = $dom->createElementNS(self::NS_DS, 'ds:CanonicalizationMethod');
        $c14n->setAttribute('Algorithm', self::EXC_C14N);
        $c14n->appendChild($this->inclusiveNamespaces($dom, 'wsa soap wcf'));
        $signedInfo->appendChild($c14n);

        $method = $dom->createElementNS(self::NS_DS, 'ds:SignatureMethod');
        $method->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256');
        $signedInfo->appendChild($method);

        $reference = $dom->createElementNS(self::NS_DS, 'ds:Reference');
        $reference->setAttribute('URI', '#' . $toId);
        $transforms = $dom->createElementNS(self::NS_DS, 'ds:Transforms');
        $transform  = $dom->createElementNS(self::NS_DS, 'ds:Transform');
        $transform->setAttribute('Algorithm', self::EXC_C14N);
        $transform->appendChild($this->inclusiveNamespaces($dom, 'soap wcf'));
        $transforms->appendChild($transform);
        $reference->appendChild($transforms);
        $digestMethod = $dom->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $reference->appendChild($digestMethod);
        $digest = base64_encode(hash('sha256', (string) $to->C14N(true, false, null, ['soap', 'wcf']), true));
        $reference->appendChild($dom->createElementNS(self::NS_DS, 'ds:DigestValue', $digest));
        $signedInfo->appendChild($reference);

        $raw = '';
        $canonicalSignedInfo = (string) $signedInfo->C14N(true, false, null, ['wsa', 'soap', 'wcf']);
        if (!openssl_sign($canonicalSignedInfo, $raw, $cert->getPrivateKeyPem(), OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('No fue posible firmar la cabecera WS-Security.');
        }
        $signature->appendChild($dom->createElementNS(self::NS_DS, 'ds:SignatureValue', base64_encode($raw)));

        $keyInfo = $dom->createElementNS(self::NS_DS, 'ds:KeyInfo');
        $keyInfo->setAttribute('Id', 'KI-' . self::id());
        $str = $dom->createElementNS(self::NS_WSSE, 'wsse:SecurityTokenReference');
        $str->setAttributeNS(self::NS_WSU, 'wsu:Id', 'STR-' . self::id());
        $ref = $dom->createElementNS(self::NS_WSSE, 'wsse:Reference');
        $ref->setAttribute('URI', '#' . $tokenId);
        $ref->setAttribute('ValueType', self::X509_TOKEN);
        $str->appendChild($ref);
        $keyInfo->appendChild($str);
        $signature->appendChild($keyInfo);
    }

    private function inclusiveNamespaces(\DOMDocument $dom, string $prefixes): \DOMElement
    {
        $node = $dom->createElementNS(self::NS_EC, 'ec:InclusiveNamespaces');
        $node->setAttribute('PrefixList', $prefixes);

        return $node;
    }

    private static function id(): string
    {
        return strtoupper(bin2hex(random_bytes(16)));
    }
}
