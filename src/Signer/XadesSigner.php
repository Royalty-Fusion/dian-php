<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Signer;

use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

/**
 * XAdES-EPES signer compliant with DIAN Anexo Técnico V1.9.
 *
 * Signature flow:
 *   1. Load PKCS#12 certificate from disk.
 *   2. Build the XAdES Object (QualifyingProperties → SignedProperties).
 *   3. Add the enveloped signature reference plus the SignedProperties reference.
 *   4. Sign with RSA-SHA256 and inject the resulting ds:Signature inside the
 *      second ext:ExtensionContent placeholder of the UBL document.
 *
 * NOTE: Phase 1 of the roadmap will further harden canonicalization rules
 * and KeyInfo handling against the latest DIAN reference XAdES samples.
 */
class XadesSigner
{
    /** Official DIAN signature policy v2 */
    public const POLICY_URL          = 'https://facturaelectronica.dian.gov.co/politicadefirma/v2/politicadefirmav2.pdf';
    public const POLICY_HASH_SHA256  = 'dMoQAOR5HscatV9QLJ864wS6u2bM=';
    public const POLICY_DESCRIPTION  = 'Política de firma para facturas electrónicas de la República de Colombia';

    public const ROLE_SUPPLIER    = 'supplier';
    public const ROLE_THIRD_PARTY = 'third party';
    public const ROLE_CUSTOMER    = 'customer';

    private string $certPath;
    private string $password;
    private ?string $signerRole;

    /**
     * @param  string|null  $signerRole  When non-null, emits a
     *  <xades:SignerRole><xades:ClaimedRoles> block. Use ROLE_THIRD_PARTY when
     *  signing on behalf of the issuer (typical for facturadores tecnológicos
     *  like Siigo), ROLE_SUPPLIER when the issuer signs with its own cert.
     */
    public function __construct(string $certPath, string $password, ?string $signerRole = null)
    {
        $this->certPath   = $certPath;
        $this->password   = $password;
        $this->signerRole = $signerRole;
    }

    /**
     * @throws \RuntimeException when the certificate cannot be loaded.
     */
    public function sign(string $xml): string
    {
        if (!file_exists($this->certPath)) {
            throw new \RuntimeException('Certificate file not found: ' . $this->certPath);
        }

        $pkcs12 = file_get_contents($this->certPath);
        if ($pkcs12 === false) {
            throw new \RuntimeException('Unable to read certificate file.');
        }

        $certs = [];
        if (!openssl_pkcs12_read($pkcs12, $certs, $this->password)) {
            throw new \RuntimeException('Could not read the certificate. Check password or format.');
        }

        $privateKey = $certs['pkey'];
        $publicKey  = $certs['cert'];

        $certData = openssl_x509_parse($publicKey);
        if (!$certData) {
            throw new \RuntimeException('Failed to parse public certificate.');
        }

        // Issuer DN string
        $issuerParts = [];
        foreach ($certData['issuer'] as $key => $value) {
            if (is_array($value)) {
                $value = implode(',', $value);
            }
            $issuerParts[] = "$key=$value";
        }
        $issuerName   = implode(', ', $issuerParts);
        $serialNumber = $certData['serialNumber'];

        // SHA-256 digest of the DER-encoded certificate
        $cleanCert  = str_replace(
            ['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\r", "\n", ' '],
            '',
            $publicKey
        );
        $certBinary = base64_decode($cleanCert);
        $certDigest = base64_encode(hash('sha256', $certBinary, true));

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->loadXML($xml);

        $sigId            = 'xmldsig-signature-id';
        $signedPropsId    = 'xmldsig-signedproperties-id';
        $keyInfoId        = 'xmldsig-keyinfo-id';

        $objDSig = new XMLSecurityDSig();
        $objDSig->setCanonicalMethod(XMLSecurityDSig::C14N);

        // Reference 1: enveloped document
        $objDSig->addReference(
            $dom,
            XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['force_uri' => true]
        );

        $sigNode = $objDSig->createNewSignNode();
        $sigNode->setAttribute('Id', $sigId);

        // XAdES SignedProperties scaffolding
        $objectNode    = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Object');
        $qualProps     = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:QualifyingProperties');
        $qualProps->setAttribute('Target', '#' . $sigId);

        $signedProps   = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SignedProperties');
        $signedProps->setAttribute('Id', $signedPropsId);

        $signedSigProps = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SignedSignatureProperties');

        $signingTime = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SigningTime', date('c'));
        $signedSigProps->appendChild($signingTime);

        // SigningCertificate
        $signingCertificate = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SigningCertificate');
        $certNode           = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:Cert');

        $certDigestNode = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:CertDigest');
        $digestMethod   = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $certDigestNode->appendChild($digestMethod);
        $certDigestNode->appendChild($dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:DigestValue', $certDigest));
        $certNode->appendChild($certDigestNode);

        $issuerSerial = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:IssuerSerial');
        $issuerSerial->appendChild($dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:X509IssuerName', $issuerName));
        $issuerSerial->appendChild($dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:X509SerialNumber', $serialNumber));
        $certNode->appendChild($issuerSerial);

        $signingCertificate->appendChild($certNode);
        $signedSigProps->appendChild($signingCertificate);

        // SignaturePolicyIdentifier
        $sigPolicyId       = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SignaturePolicyIdentifier');
        $sigPolicyIdNode   = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SignaturePolicyId');
        $sigPolicyDetails  = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SigPolicyId');
        $sigPolicyDetails->appendChild($dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:Identifier', self::POLICY_URL));
        $sigPolicyDetails->appendChild($dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:Description', self::POLICY_DESCRIPTION));
        $sigPolicyIdNode->appendChild($sigPolicyDetails);

        $sigPolicyHash = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SigPolicyHash');
        $policyDigest  = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:DigestMethod');
        $policyDigest->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $sigPolicyHash->appendChild($policyDigest);
        $sigPolicyHash->appendChild($dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:DigestValue', self::POLICY_HASH_SHA256));
        $sigPolicyIdNode->appendChild($sigPolicyHash);

        $sigPolicyId->appendChild($sigPolicyIdNode);
        $signedSigProps->appendChild($sigPolicyId);

        // Optional SignerRole — recommended for third-party facturadores tecnológicos
        if ($this->signerRole !== null) {
            $signerRole   = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SignerRole');
            $claimedRoles = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:ClaimedRoles');
            $claimedRoles->appendChild(
                $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:ClaimedRole', $this->signerRole)
            );
            $signerRole->appendChild($claimedRoles);
            $signedSigProps->appendChild($signerRole);
        }

        $signedProps->appendChild($signedSigProps);
        $qualProps->appendChild($signedProps);
        $objectNode->appendChild($qualProps);
        $sigNode->appendChild($objectNode);

        // RSA-SHA256 key
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $objKey->loadKey($privateKey, false);

        // Reference 2: SignedProperties
        $objDSig->addReference(
            $sigNode,
            XMLSecurityDSig::SHA256,
            null,
            [
                'type'      => 'http://uri.etsi.org/01903#SignedProperties',
                'id_name'   => 'Id',
                'overwrite' => false,
            ]
        );

        $objDSig->sign($objKey);
        $objDSig->add509Cert($publicKey, true, false, ['issuerSerial' => true, 'subjectName' => true]);

        // Set ID on KeyInfo using the proper XMLDSig namespace
        $keyInfoNode = $sigNode->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'KeyInfo')->item(0);
        if ($keyInfoNode instanceof \DOMElement) {
            $keyInfoNode->setAttribute('Id', $keyInfoId);
        }

        // Inject ds:Signature inside the second ext:ExtensionContent placeholder
        $extNodes = $dom->getElementsByTagNameNS(
            'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
            'ExtensionContent'
        );

        if ($extNodes->length > 1) {
            $extContent = $extNodes->item(1);
            $extContent->appendChild($dom->importNode($sigNode, true));
        } else {
            $dom->documentElement->appendChild($dom->importNode($sigNode, true));
        }

        return $dom->saveXML();
    }
}
