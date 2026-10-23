<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Signer;

/**
 * Verifies the XAdES-EPES enveloped signature of a DIAN document.
 *
 * Recomputes every ds:Reference digest (document, KeyInfo, SignedProperties)
 * and checks the ds:SignatureValue against the embedded X509 certificate —
 * the same checks DIAN runs on reception (rules ZB01/ZE02 family).
 *
 * Used by the pre-flight checker to catch broken signatures locally, and by
 * the test-suite against real DIAN-approved documents.
 */
final class SignatureVerifier
{
    public const NS_DS    = 'http://www.w3.org/2000/09/xmldsig#';
    public const NS_XADES = 'http://uri.etsi.org/01903/v1.3.2#';

    /**
     * @return list<string> human readable problems; empty list = valid signature
     */
    public function verify(string $xml): array
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;
        if (!@$dom->loadXML($xml)) {
            return ['El XML firmado no es XML bien formado.'];
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ds', self::NS_DS);
        $xpath->registerNamespace('xades', self::NS_XADES);

        $signatures = $xpath->query('//ds:Signature');
        if ($signatures === false || $signatures->length === 0) {
            return ['El documento no contiene ds:Signature.'];
        }
        if ($signatures->length > 1) {
            return ['El documento contiene más de una ds:Signature.'];
        }
        /** @var \DOMElement $signature */
        $signature = $signatures->item(0);

        $errors = [];
        $refs   = $xpath->query('ds:SignedInfo/ds:Reference', $signature);
        if ($refs === false || $refs->length !== 3) {
            $errors[] = 'Se esperaban 3 ds:Reference (documento, KeyInfo, SignedProperties).';
        }

        $hasSignedProps = false;
        foreach ($refs ?: [] as $ref) {
            if (!$ref instanceof \DOMElement) {
                continue;
            }
            $uri      = $ref->getAttribute('URI');
            $expected = trim((string) $xpath->evaluate('string(ds:DigestValue)', $ref));
            $algo     = (string) $xpath->evaluate('string(ds:DigestMethod/@Algorithm)', $ref);
            $hashAlgo = self::hashAlgo($algo);
            if ($hashAlgo === null) {
                $errors[] = "Algoritmo de digest no soportado: {$algo}";
                continue;
            }

            if ($ref->getAttribute('Type') === 'http://uri.etsi.org/01903#SignedProperties') {
                $hasSignedProps = true;
            }

            if ($uri === '') {
                $copy = new \DOMDocument();
                $copy->preserveWhiteSpace = true;
                $copy->loadXML($dom->saveXML() ?: '');
                $copyXpath = new \DOMXPath($copy);
                $copyXpath->registerNamespace('ds', self::NS_DS);
                foreach ($copyXpath->query('//ds:Signature') ?: [] as $sig) {
                    $sig->parentNode?->removeChild($sig);
                }
                $canonical = $copy->C14N(false, false);
            } else {
                $id     = ltrim($uri, '#');
                $target = $xpath->query("//*[@Id='{$id}']")?->item(0);
                if (!$target instanceof \DOMElement) {
                    $errors[] = "No se encontró el elemento referenciado {$uri}.";
                    continue;
                }
                $canonical = $target->C14N(false, false);
            }

            $digest = base64_encode(hash($hashAlgo, (string) $canonical, true));
            if (!hash_equals($expected, $digest)) {
                $errors[] = "Digest inválido para la referencia URI=\"{$uri}\".";
            }
        }
        if (!$hasSignedProps) {
            $errors[] = 'Falta la referencia a xades:SignedProperties.';
        }

        $certB64 = preg_replace('/\s+/', '', (string) $xpath->evaluate('string(ds:KeyInfo/ds:X509Data/ds:X509Certificate)', $signature));
        if ($certB64 === '' || $certB64 === null) {
            $errors[] = 'No hay ds:X509Certificate en ds:KeyInfo.';
            return $errors;
        }
        $pem = "-----BEGIN CERTIFICATE-----\n" . chunk_split($certB64, 64, "\n") . "-----END CERTIFICATE-----\n";

        $signedInfo = $xpath->query('ds:SignedInfo', $signature)?->item(0);
        $sigValue   = base64_decode(preg_replace('/\s+/', '', (string) $xpath->evaluate('string(ds:SignatureValue)', $signature)) ?? '', true);
        $method     = (string) $xpath->evaluate('string(ds:SignedInfo/ds:SignatureMethod/@Algorithm)', $signature);
        $opensslAlg = match ($method) {
            'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256' => OPENSSL_ALGO_SHA256,
            'http://www.w3.org/2001/04/xmldsig-more#rsa-sha384' => OPENSSL_ALGO_SHA384,
            'http://www.w3.org/2001/04/xmldsig-more#rsa-sha512' => OPENSSL_ALGO_SHA512,
            default => null,
        };
        if ($opensslAlg === null) {
            $errors[] = "SignatureMethod no soportado: {$method}";
        } elseif (!$signedInfo instanceof \DOMElement || $sigValue === false) {
            $errors[] = 'ds:SignedInfo o ds:SignatureValue ausentes o corruptos.';
        } elseif (openssl_verify((string) $signedInfo->C14N(false, false), $sigValue, $pem, $opensslAlg) !== 1) {
            $errors[] = 'ds:SignatureValue no corresponde al certificado (firma inválida).';
        }

        $policy = trim((string) $xpath->evaluate('string(//xades:SigPolicyHash/ds:DigestValue)', $signature));
        if ($policy !== XadesSigner::POLICY_HASH_SHA256) {
            $errors[] = 'El hash de la política de firma DIAN no es el oficial.';
        }

        return $errors;
    }

    private static function hashAlgo(string $uri): ?string
    {
        return match ($uri) {
            'http://www.w3.org/2001/04/xmlenc#sha256'  => 'sha256',
            'http://www.w3.org/2001/04/xmldsig-more#sha384' => 'sha384',
            'http://www.w3.org/2001/04/xmlenc#sha512'  => 'sha512',
            'http://www.w3.org/2000/09/xmldsig#sha1'   => 'sha1',
            default => null,
        };
    }
}
