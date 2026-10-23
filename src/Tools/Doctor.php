<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tools;

use RoyaltyFusion\DianPhp\Signer\Certificate;
use RoyaltyFusion\DianPhp\Validator\BusinessRuleValidator;
use RoyaltyFusion\DianPhp\Validator\CertificateChecker;
use RoyaltyFusion\DianPhp\Validator\PreflightReport;
use RoyaltyFusion\DianPhp\Validator\ValidationResult;
use RoyaltyFusion\DianPhp\Ws\SoapClient;

/**
 * Environment diagnosis to run before habilitación and on every deploy:
 *
 *   entorno       PHP version and extensions (dom, openssl, zip, libxml)
 *   certificado   opens the .p12, validity, NIT in the subject
 *   esquemas      bundled XSDs present
 *   dian          signed GetNumberingRange call: proves DIAN accepts the
 *                 certificate (WS-Security) and lists the resolutions with
 *                 their clave técnica
 */
final class Doctor
{
    /** @var list<array<string,string>> */
    private array $ranges = [];

    public function __construct(private readonly ?SoapClient $client = null)
    {
    }

    public function run(
        string $certPath,
        string $certPassword,
        string $environment = SoapClient::ENV_HABILITACION,
        ?string $nit = null,
        ?string $softwareId = null,
        ?string $providerNit = null
    ): PreflightReport {
        $report = new PreflightReport();
        $report->add('entorno', $this->checkRuntime());

        $certificate = null;
        try {
            $certificate = Certificate::fromPkcs12File($certPath, $certPassword);
            $report->add('certificado', (new CertificateChecker())->check($certificate, $nit));
        } catch (\Throwable $e) {
            $report->add('certificado', (new ValidationResult())->add('CERT_000', $e->getMessage()));
        }

        $schemas = new ValidationResult();
        $xsdDir  = dirname(__DIR__, 2) . '/resources/xsd/maindoc';
        foreach (['Invoice', 'CreditNote', 'DebitNote', 'ApplicationResponse', 'AttachedDocument'] as $doc) {
            if (!is_file("{$xsdDir}/UBL-{$doc}-2.1.xsd")) {
                $schemas->add('XSD_000', "Falta el esquema UBL-{$doc}-2.1.xsd en resources/xsd.");
            }
        }
        $report->add('esquemas', $schemas);

        if ($certificate !== null && $nit !== null && $softwareId !== null && $softwareId !== '') {
            $report->add('dian', $this->checkDian($certificate, $environment, $nit, $softwareId, $providerNit ?? $nit));
        } else {
            $report->add('dian', (new ValidationResult())->add(
                'DIAN_000',
                'Conexión con la DIAN no probada: indique NIT y SoftwareID para consultar GetNumberingRange.',
                '',
                'warning'
            ));
        }

        return $report;
    }

    /** @return list<array<string,string>> ranges returned by the last run() */
    public function getRanges(): array
    {
        return $this->ranges;
    }

    private function checkRuntime(): ValidationResult
    {
        $result = new ValidationResult();
        if (PHP_VERSION_ID < 80100) {
            $result->add('ENV_PHP', 'Se requiere PHP 8.1 o superior (actual ' . PHP_VERSION . ').');
        }
        foreach (['dom', 'libxml', 'openssl', 'zip'] as $ext) {
            if (!extension_loaded($ext)) {
                $result->add('ENV_EXT', "Falta la extensión PHP '{$ext}'.");
            }
        }
        if (defined('OPENSSL_VERSION_NUMBER') && OPENSSL_VERSION_NUMBER >= 0x30000000) {
            $result->add('ENV_SSL', 'OpenSSL 3: si el .p12 usa cifrado heredado (RC2/3DES), conviértalo (ver docs/troubleshooting.md).', '', 'warning');
        }
        if (!in_array('sha384', hash_algos(), true)) {
            $result->add('ENV_HASH', 'El algoritmo sha384 no está disponible.');
        }

        return $result;
    }

    private function checkDian(Certificate $certificate, string $environment, string $nit, string $softwareId, string $providerNit): ValidationResult
    {
        $result = new ValidationResult();
        $client = $this->client ?? new SoapClient($environment);
        $client->setCertificate($certificate);

        try {
            $this->ranges = $client->getNumberingRanges($nit, $providerNit, $softwareId);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $hint    = str_contains($message, 'InvalidSecurity') || str_contains($message, 'security')
                ? ' La DIAN no aceptó la firma WS-Security: verifique que el certificado esté vigente y asociado al NIT en el portal.'
                : '';
            $result->add('DIAN_001', 'Falló la consulta a la DIAN: ' . $message . $hint);
            return $result;
        }

        if ($this->ranges === []) {
            $result->add('DIAN_002', 'La DIAN respondió pero no hay rangos de numeración para ese NIT/SoftwareID.', '', 'warning');
        }
        foreach ($this->ranges as $range) {
            $key = $range['TechnicalKey'] ?? '';
            if ($environment === SoapClient::ENV_PRODUCCION && strtolower($key) === BusinessRuleValidator::HABILITACION_TECHNICAL_KEY) {
                $result->add('DIAN_003', 'La resolución de producción devolvió la clave técnica de habilitación.', '', 'warning');
            }
        }

        return $result;
    }
}
