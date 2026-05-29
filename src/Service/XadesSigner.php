<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Service;

use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

class XadesSigner
{
    private string $certPath;
    private string $password;

    // Official DIAN Signature Policy Constants
    private const POLICY_URL = 'https://facturaelectronica.dian.gov.co/politicadefirma/v2/politicadefirmav2.pdf';
    private const POLICY_HASH_SHA256 = 'dMoQAOR5HscatV9QLJ864wS6u2bM='; // Standard Base64 SHA-256 for DIAN policy
    private const POLICY_DESC = 'Política de firma para facturas electrónicas de la República de Colombia';

    public function __construct(string $certPath, string $password)
    {
        $this->certPath = $certPath;
        $this->password = $password;
    }

    /**
     * Signs the given XML string using XAdES-EPES standard compliant with DIAN (Anexo V1.9)
     * 
     * @param string $xml
     * @return string
     * @throws \Exception
     */
    public function sign(string $xml): string
    {
        if (!file_exists($this->certPath)) {
            throw new \Exception('Certificate file not found: ' . $this->certPath);
        }

        $pkcs12 = file_get_contents($this->certPath);
        $certs = [];
        if (!openssl_pkcs12_read($pkcs12, $certs, $this->password)) {
            throw new \Exception('Could not read the certificate. Check password or format.');
        }

        $privateKey = $certs['pkey'];
        $publicKey = $certs['cert'];

        // Get certificate details for XAdES signature properties
        $certData = openssl_x509_parse($publicKey);
        if (!$certData) {
            throw new \Exception('Failed to parse public certificate.');
        }

        // Issuer Details
        $issuerParts = [];
        foreach ($certData['issuer'] as $key => $value) {
            if (is_array($value)) {
                $value = implode(',', $value);
            }
            $issuerParts[] = "$key=$value";
        }
        $issuerName = implode(', ', $issuerParts);
        $serialNumber = $certData['serialNumber'];

        // Public Certificate Digest (Base64 of SHA-256)
        $cleanCert = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\r", "\n", ' '], '', $publicKey);
        $certBinary = base64_decode($cleanCert);
        $certDigest = base64_encode(hash('sha256', $certBinary, true));

        // Load document
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->loadXML($xml);

        // Generate Unique IDs
        $sigId = 'xmldsig-signature-id';
        $signedPropsId = 'xmldsig-signedproperties-id';
        $keyInfoId = 'xmldsig-keyinfo-id';

        // 1. Setup XMLSecurityDSig
        $objDSig = new XMLSecurityDSig();
        $objDSig->setCanonicalMethod(XMLSecurityDSig::C14N);

        // Reference 1: Enveloped Document
        $objDSig->addReference(
            $dom,
            XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['force_uri' => true]
        );

        // 2. Prepare the ds:Signature DOM structure
        $sigNode = $objDSig->createNewSignNode();
        $sigNode->setAttribute('Id', $sigId);

        // 3. Construct XAdES Object Node
        $objectNode = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Object');
        
        $qualProperties = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:QualifyingProperties');
        $qualProperties->setAttribute('Target', '#' . $sigId);
        
        $signedProps = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SignedProperties');
        $signedProps->setAttribute('Id', $signedPropsId);

        $signedSigProps = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SignedSignatureProperties');
        
        // SigningTime
        $signingTime = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SigningTime', date('c'));
        $signedSigProps->appendChild($signingTime);

        // SigningCertificate
        $signingCertificate = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SigningCertificate');
        $certNode = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:Cert');
        
        $certDigestNode = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:CertDigest');
        $digestMethod = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $certDigestNode->appendChild($digestMethod);
        $certDigestNode->appendChild($dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:DigestValue', $certDigest));
        $certNode->appendChild($certDigestNode);

        $issuerSerialNode = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:IssuerSerial');
        $issuerSerialNode->appendChild($dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:X509IssuerName', $issuerName));
        $issuerSerialNode->appendChild($dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:X509SerialNumber', $serialNumber));
        $certNode->appendChild($issuerSerialNode);

        $signingCertificate->appendChild($certNode);
        $signedSigProps->appendChild($signingCertificate);

        // SignaturePolicyIdentifier
        $sigPolicyId = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SignaturePolicyIdentifier');
        $sigPolicyIdNode = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SignaturePolicyId');
        
        $sigPolicyIdDetails = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SigPolicyId');
        $sigPolicyIdDetails->appendChild($dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:Identifier', self::POLICY_URL));
        $sigPolicyIdDetails->appendChild($dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:Description', self::POLICY_DESC));
        $sigPolicyIdNode->appendChild($sigPolicyIdDetails);

        $sigPolicyHash = $dom->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:SigPolicyHash');
        $digestMethodPolicy = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:DigestMethod');
        $digestMethodPolicy->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $sigPolicyHash->appendChild($digestMethodPolicy);
        $sigPolicyHash->appendChild($dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:DigestValue', self::POLICY_HASH_SHA256));
        $sigPolicyIdNode->appendChild($sigPolicyHash);

        $sigPolicyId->appendChild($sigPolicyIdNode);
        $signedSigProps->appendChild($sigPolicyId);

        $signedProps->appendChild($signedSigProps);
        $qualProperties->appendChild($signedProps);
        $objectNode->appendChild($qualProperties);
        $sigNode->appendChild($objectNode);

        // 4. Inject key and public certificate
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $objKey->loadKey($privateKey, false);

        // Add reference to the SignedProperties element
        $objDSig->addReference(
            $sigNode, 
            XMLSecurityDSig::SHA256, 
            null, 
            [
                'type' => 'http://uri.etsi.org/01903#SignedProperties', 
                'id_name' => 'Id', 
                'overwrite' => false
            ]
        );

        // Calculate and build signature
        $objDSig->sign($objKey);
        $objDSig->add509Cert($publicKey, true, false, ['issuerSerial' => true, 'subjectName' => true]);

        // Fix KeyInfo ID
        $keyInfoNode = $sigNode->getElementsByTagName('KeyInfo')->item(0);
        if ($keyInfoNode) {
            $keyInfoNode->setAttribute('Id', $keyInfoId);
        }

        // Find the second UBLExtension's ExtensionContent to inject the signature
        $extNodes = $dom->getElementsByTagNameNS('urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2', 'ExtensionContent');
        if ($extNodes->length > 1) {
            // Index 1 is the signature placeholder
            $extContent = $extNodes->item(1);
            $extContent->appendChild($dom->importNode($sigNode, true));
        } else {
            // Fallback: Append to the first or create a new extension if not existing
            $dom->documentElement->appendChild($dom->importNode($sigNode, true));
        }

        return $dom->saveXML();
    }
}
