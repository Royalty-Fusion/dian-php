<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Signer;

/**
 * Digital certificate (PKCS#12 or PEM) used to sign DIAN documents and to
 * authenticate SOAP calls with WS-Security.
 *
 * Shared by {@see XadesSigner}, {@see \RoyaltyFusion\DianPhp\Ws\WsSecurity}
 * and the pre-flight checker so the certificate is read once and every
 * component sees the same key material.
 */
final class Certificate
{
    private string $privateKeyPem;
    private string $certificatePem;
    /** @var array<string,mixed> */
    private array $parsed;

    private function __construct(string $privateKeyPem, string $certificatePem)
    {
        $parsed = openssl_x509_parse($certificatePem);
        if ($parsed === false) {
            throw new \RuntimeException('No fue posible interpretar el certificado X.509.');
        }
        if (!openssl_x509_check_private_key($certificatePem, $privateKeyPem)) {
            throw new \RuntimeException('La llave privada no corresponde al certificado.');
        }

        $this->privateKeyPem  = $privateKeyPem;
        $this->certificatePem = $certificatePem;
        $this->parsed         = $parsed;
    }

    public static function fromPkcs12File(string $path, string $password): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException('Certificado no encontrado: ' . $path);
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('No fue posible leer el certificado: ' . $path);
        }

        return self::fromPkcs12($content, $password);
    }

    public static function fromPkcs12(string $content, string $password): self
    {
        $certs = [];
        if (!openssl_pkcs12_read($content, $certs, $password)) {
            $reason = '';
            while (($msg = openssl_error_string()) !== false) {
                $reason = $msg;
            }
            $hint = str_contains($reason, 'unsupported')
                ? ' El archivo usa cifrado heredado (RC2/3DES) que OpenSSL 3 no carga por defecto.'
                  . ' Conviértalo con: openssl pkcs12 -legacy -in cert.p12 -nodes -out tmp.pem &&'
                  . ' openssl pkcs12 -export -in tmp.pem -out cert-nuevo.p12'
                : ' Verifique la contraseña y que el archivo sea PKCS#12 (.p12/.pfx).';
            throw new \RuntimeException('No fue posible abrir el certificado PKCS#12.' . $hint . ($reason !== '' ? " ({$reason})" : ''));
        }

        return new self((string) $certs['pkey'], (string) $certs['cert']);
    }

    public static function fromPem(string $certificatePem, string $privateKeyPem, ?string $passphrase = null): self
    {
        if ($passphrase !== null && $passphrase !== '') {
            $key = openssl_pkey_get_private($privateKeyPem, $passphrase);
            if ($key === false || !openssl_pkey_export($key, $privateKeyPem)) {
                throw new \RuntimeException('No fue posible descifrar la llave privada PEM.');
            }
        }

        return new self($privateKeyPem, $certificatePem);
    }

    public function getPrivateKeyPem(): string
    {
        return $this->privateKeyPem;
    }

    public function getCertificatePem(): string
    {
        return $this->certificatePem;
    }

    /** Base64 DER body of the certificate, without PEM armour or line breaks. */
    public function getCertificateBase64(): string
    {
        return (string) preg_replace(
            '/-----(BEGIN|END) CERTIFICATE-----|\s+/',
            '',
            $this->certificatePem
        );
    }

    public function getCertificateDer(): string
    {
        return (string) base64_decode($this->getCertificateBase64(), true);
    }

    /** Base64 digest of the DER certificate (xades:CertDigest). */
    public function getDigest(string $algo = 'sha256'): string
    {
        return base64_encode(hash($algo, $this->getCertificateDer(), true));
    }

    /**
     * Issuer DN in the "C=CO, L=..., O=..., CN=..." form used by DIAN-approved
     * documents (certificate order, e-mail emitted by OID).
     */
    public function getIssuerName(): string
    {
        $parts = [];
        foreach ((array) ($this->parsed['issuer'] ?? []) as $key => $value) {
            $key = $key === 'emailAddress' ? '1.2.840.113549.1.9.1' : (string) $key;
            foreach ((array) $value as $v) {
                $parts[] = $key . '=' . $v;
            }
        }

        return implode(', ', $parts);
    }

    /** Decimal serial number (ds:X509SerialNumber). */
    public function getSerialNumber(): string
    {
        $serial = (string) ($this->parsed['serialNumber'] ?? '');
        if (str_starts_with($serial, '0x') || ctype_xdigit($serial) && !ctype_digit($serial)) {
            $serial = self::hexToDec(ltrim(str_starts_with($serial, '0x') ? substr($serial, 2) : $serial, '0'));
        }

        return $serial;
    }

    /** @return array<string,mixed> */
    public function getSubject(): array
    {
        return (array) ($this->parsed['subject'] ?? []);
    }

    public function getSubjectName(): string
    {
        $subject = $this->getSubject();
        $cn      = $subject['CN'] ?? '';

        return is_array($cn) ? implode(' ', $cn) : (string) $cn;
    }

    public function getValidFrom(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@' . (int) $this->parsed['validFrom_time_t']))
            ->setTimezone(new \DateTimeZone('America/Bogota'));
    }

    public function getValidTo(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@' . (int) $this->parsed['validTo_time_t']))
            ->setTimezone(new \DateTimeZone('America/Bogota'));
    }

    public function isValidAt(\DateTimeInterface $moment): bool
    {
        return $moment >= $this->getValidFrom() && $moment <= $this->getValidTo();
    }

    public function daysUntilExpiration(?\DateTimeInterface $now = null): int
    {
        $now ??= new \DateTimeImmutable();
        $diff = $this->getValidTo()->getTimestamp() - $now->getTimestamp();

        return (int) floor($diff / 86400);
    }

    /**
     * Whether the NIT appears anywhere in the subject (Colombian CAs put it in
     * serialNumber, OU, CN or the x500UniqueIdentifier depending on the issuer).
     */
    public function subjectMentions(string $nit): bool
    {
        $nit = preg_replace('/\D/', '', $nit) ?? '';
        if ($nit === '') {
            return false;
        }
        $flat = json_encode($this->getSubject()) ?: '';

        return str_contains(preg_replace('/[^0-9]/', '', $flat) ?? '', $nit);
    }

    private static function hexToDec(string $hex): string
    {
        $dec = '0';
        foreach (str_split(strtolower($hex)) as $char) {
            $carry = (int) hexdec($char);
            $out   = '';
            for ($i = strlen($dec) - 1; $i >= 0; $i--) {
                $val   = ((int) $dec[$i]) * 16 + $carry;
                $out   = ($val % 10) . $out;
                $carry = intdiv($val, 10);
            }
            while ($carry > 0) {
                $out   = ($carry % 10) . $out;
                $carry = intdiv($carry, 10);
            }
            $dec = ltrim($out, '0') ?: '0';
        }

        return $dec;
    }
}
