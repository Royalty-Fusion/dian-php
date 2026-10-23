<?php

/**
 * Shared configuration for the examples.
 *
 * Real data comes from environment variables. Without them the examples use
 * a throw-away self-signed certificate and run in "dry run" mode: they build,
 * sign and validate everything locally (preflight) but do not call DIAN.
 *
 *   DIAN_CERT_PATH, DIAN_CERT_PASSWORD   certificado .p12 del facturador
 *   DIAN_ENVIRONMENT                     habilitacion | produccion
 *   DIAN_NIT                             NIT del facturador (sin DV)
 *   DIAN_SOFTWARE_ID, DIAN_SOFTWARE_PIN  datos del software registrado
 *   DIAN_TECHNICAL_KEY                   clave técnica de la resolución
 *   DIAN_TEST_SET_ID                     TestSetId del set de pruebas
 *
 * Pass --send to actually send to DIAN.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use RoyaltyFusion\DianPhp\Dian;
use RoyaltyFusion\DianPhp\Model\Address;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\Contact;
use RoyaltyFusion\DianPhp\Model\Resolution;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Signer\Certificate;
use RoyaltyFusion\DianPhp\Ws\SoapClient;

function env(string $name, string $default = ''): string
{
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
}

function shouldSend(): bool
{
    return in_array('--send', $_SERVER['argv'] ?? [], true);
}

function dian(): Dian
{
    $environment = env('DIAN_ENVIRONMENT', SoapClient::ENV_HABILITACION);
    if (env('DIAN_CERT_PATH') !== '') {
        return new Dian(env('DIAN_CERT_PATH'), env('DIAN_CERT_PASSWORD'), $environment);
    }

    // Demo only: self-signed certificate generated on the fly (DIAN rejects it).
    $key  = openssl_pkey_new(['private_key_bits' => 2048]);
    $csr  = openssl_csr_new(['countryName' => 'CO', 'commonName' => 'DEMO ' . env('DIAN_NIT', '900123456')], $key);
    $cert = openssl_csr_sign($csr, null, $key, 365);
    openssl_x509_export($cert, $certPem);
    openssl_pkey_export($key, $keyPem);

    return Dian::withCertificate(Certificate::fromPem($certPem, $keyPem), $environment);
}

function company(): Company
{
    return (new Company())
        ->setNit(env('DIAN_NIT', '900123456'))
        ->setRazonSocial('Royalty Fusion S.A.S.')
        ->setCommercialName('Royalty Fusion')
        ->setTipoDocumento('31')                 // NIT
        ->setTipoOrganizacion('1')               // persona jurídica
        ->setRegimen('48')                       // responsable de IVA
        ->setResponsabilidades('O-13;O-15')
        ->setIndustryClassificationCode('6201')  // CIIU
        ->setMatriculaMercantil('123456')
        ->setAddress(
            (new Address())
                ->setLine('Cra. 7 # 71-21')
                ->setCityCode('11001')->setCityName('Bogotá, D.C.')
                ->setDepartmentCode('11')->setDepartmentName('Bogotá')
                ->setPostalZone('110231')
                ->setCountryCode('CO')->setCountryName('Colombia')
        )
        ->setContact((new Contact())->setName('Facturación')->setTelephone('6015550000')->setElectronicMail('facturacion@example.com'));
}

function client(): Client
{
    return (new Client())
        ->setTipoDocumento('13')                 // cédula de ciudadanía
        ->setNumeroDocumento('1010101010')
        ->setRazonSocial('Juan Pérez')
        ->setEmail('juan.perez@example.com')
        ->setTipoOrganizacion('2')               // persona natural
        ->setResponsabilidades('R-99-PN')
        ->setAddress(
            (new Address())
                ->setLine('Calle 10 # 5-20')
                ->setCityCode('05001')->setCityName('Medellín')
                ->setDepartmentCode('05')->setDepartmentName('Antioquia')
                ->setCountryCode('CO')->setCountryName('Colombia')
        );
}

function software(): Software
{
    return (new Software())
        ->setId(env('DIAN_SOFTWARE_ID', 'd35e1234-abcd-4234-abcd-0123456789ab'))
        ->setPin(env('DIAN_SOFTWARE_PIN', '12345'))
        ->setProviderNit(env('DIAN_NIT', '900123456'));
}

function resolution(): Resolution
{
    // Habilitación: resolución del set de pruebas (prefijo SETP, rango 990000000-995000000).
    return (new Resolution())
        ->setNumber('18760000001')
        ->setPrefix('SETP')
        ->setFrom('990000000')
        ->setTo('995000000')
        ->setDateFrom(new DateTimeImmutable('2019-01-19'))
        ->setDateTo(new DateTimeImmutable('2030-01-19'));
}

function technicalKey(): string
{
    // Clave técnica pública del set de pruebas de habilitación.
    return env('DIAN_TECHNICAL_KEY', 'fc8eac422eba16e22ffd8c6f94b3f40a6e38162c');
}

function printResult(\RoyaltyFusion\DianPhp\Model\Result $result): void
{
    if ($result->isSuccess()) {
        echo "ACEPTADO por la DIAN. CUFE/CUDE: {$result->getCufe()}\n";
    } elseif ($result->isPending()) {
        echo "Recibido (asíncrono). ZipKey: {$result->getZipKey()} — consulte con \$dian->waitForResult().\n";
    } else {
        echo "RECHAZADO: {$result->getErrorMessage()}\n";
        foreach ($result->getErrors() as $error) {
            echo "  - {$error}\n";
        }
    }
    foreach ($result->getNotifications() as $notification) {
        echo "  (notificación) {$notification}\n";
    }
}
