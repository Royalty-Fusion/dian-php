<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Validator;

use RoyaltyFusion\DianPhp\Signer\Certificate;

/**
 * Checks the signing certificate before it reaches DIAN.
 *
 *   CERT_000  the certificate cannot be opened (path, password, legacy cipher)
 *   CERT_001  expired or not yet valid               (error)
 *   CERT_002  expires in less than 30 days           (warning)
 *   CERT_003  the issuer NIT does not appear in it   (warning)
 */
final class CertificateChecker
{
    public function check(Certificate $certificate, ?string $nit = null, ?\DateTimeInterface $now = null): ValidationResult
    {
        $result = new ValidationResult();
        $now  ??= new \DateTimeImmutable();

        if (!$certificate->isValidAt($now)) {
            $result->add('CERT_001', sprintf(
                'El certificado no está vigente (válido del %s al %s).',
                $certificate->getValidFrom()->format('Y-m-d'),
                $certificate->getValidTo()->format('Y-m-d')
            ));
        } elseif (($days = $certificate->daysUntilExpiration($now)) < 30) {
            $result->add('CERT_002', "El certificado vence en {$days} días ({$certificate->getValidTo()->format('Y-m-d')}).", '', 'warning');
        }

        if ($nit !== null && $nit !== '' && !$certificate->subjectMentions($nit)) {
            $result->add('CERT_003', "El NIT {$nit} no aparece en el sujeto del certificado ({$certificate->getSubjectName()}). Solo es válido si firma un proveedor tecnológico.", '', 'warning');
        }

        return $result;
    }

    public function checkFile(string $path, string $password, ?string $nit = null): ValidationResult
    {
        try {
            return $this->check(Certificate::fromPkcs12File($path, $password), $nit);
        } catch (\Throwable $e) {
            return (new ValidationResult())->add('CERT_000', $e->getMessage());
        }
    }
}
