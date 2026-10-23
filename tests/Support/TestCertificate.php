<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Support;

/**
 * Generates a throw-away self-signed PKCS#12 certificate for tests.
 */
final class TestCertificate
{
    public const PASSWORD = 'test-pass';

    private static ?string $path = null;

    public static function path(): string
    {
        if (self::$path !== null && is_file(self::$path)) {
            return self::$path;
        }

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($key === false) {
            throw new \RuntimeException('Cannot generate RSA key');
        }
        $dn = [
            'countryName'      => 'CO',
            'localityName'     => 'Bogota D.C.',
            'organizationName' => 'Royalty Fusion Test CA',
            'commonName'       => 'ROYALTY FUSION SAS',
            'serialNumber'     => '900123456',
        ];
        $csr  = openssl_csr_new($dn, $key, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, 2, ['digest_alg' => 'sha256'], random_int(1000, PHP_INT_MAX));
        if ($cert === false) {
            throw new \RuntimeException('Cannot self-sign certificate');
        }

        $p12 = '';
        openssl_pkcs12_export($cert, $p12, $key, self::PASSWORD);
        $path = tempnam(sys_get_temp_dir(), 'dian_test_cert_') . '.p12';
        file_put_contents($path, $p12);
        register_shutdown_function(static fn () => @unlink($path));

        return self::$path = $path;
    }
}
