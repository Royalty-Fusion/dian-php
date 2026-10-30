<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp;

use Psr\Log\LoggerInterface;
use RoyaltyFusion\DianPhp\AttachedDocument\AttachedDocument;
use RoyaltyFusion\DianPhp\AttachedDocument\AttachedDocumentBuilder;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;
use RoyaltyFusion\DianPhp\Model\EquivalentDocument;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\Result;
use RoyaltyFusion\DianPhp\Payroll\CuneGenerator;
use RoyaltyFusion\DianPhp\Payroll\PayrollAdjustment;
use RoyaltyFusion\DianPhp\Payroll\PayrollDocument;
use RoyaltyFusion\DianPhp\Payroll\PayrollXmlBuilder;
use RoyaltyFusion\DianPhp\Radian\ApplicationResponseBuilder;
use RoyaltyFusion\DianPhp\Radian\EventCudeGenerator;
use RoyaltyFusion\DianPhp\Radian\EventDocument;
use RoyaltyFusion\DianPhp\Signer\Certificate;
use RoyaltyFusion\DianPhp\Signer\SignatureVerifier;
use RoyaltyFusion\DianPhp\Signer\XadesSigner;
use RoyaltyFusion\DianPhp\SupportDocument\CudsGenerator;
use RoyaltyFusion\DianPhp\SupportDocument\SupportDocument;
use RoyaltyFusion\DianPhp\SupportDocument\SupportDocumentAdjustmentNote;
use RoyaltyFusion\DianPhp\SupportDocument\SupportDocumentBuilder;
use RoyaltyFusion\DianPhp\Validator\BusinessRuleValidator;
use RoyaltyFusion\DianPhp\Validator\CertificateChecker;
use RoyaltyFusion\DianPhp\Validator\PreflightReport;
use RoyaltyFusion\DianPhp\Validator\ValidationResult;
use RoyaltyFusion\DianPhp\Validator\XmlConsistencyChecker;
use RoyaltyFusion\DianPhp\Validator\XsdValidator;
use RoyaltyFusion\DianPhp\Ws\FileNameGenerator;
use RoyaltyFusion\DianPhp\Ws\SoapClient;
use RoyaltyFusion\DianPhp\Ws\StatusResult;
use RoyaltyFusion\DianPhp\Xml\CufeGenerator;
use RoyaltyFusion\DianPhp\Xml\QrGenerator;
use RoyaltyFusion\DianPhp\Xml\XmlBuilder;

/**
 * Facade of the SDK: one object per facturador (certificate + environment).
 *
 *   $dian   = new Dian('/ruta/cert.p12', 'clave', SoapClient::ENV_HABILITACION);
 *   $report = $dian->preflight($invoice);   // dry run, nothing is sent
 *   $result = $dian->send($invoice);        // validate → CUFE → XML → firma → DIAN
 *
 * Every send runs the pre-flight checks first (business rules, XSD, signature
 * and the same recomputations DIAN does); disable with validateBeforeSend(false).
 */
class Dian
{
    private ?string $certPath;
    private ?string $certPassword;
    private string $environment;
    private ?Certificate $certificate = null;

    private ?XmlBuilder $xmlBuilder = null;
    private ?CufeGenerator $cufeGenerator = null;
    private ?QrGenerator $qrGenerator = null;
    private ?XadesSigner $signer = null;
    private ?SoapClient $soapClient = null;
    private ?BusinessRuleValidator $validator = null;
    private ?LoggerInterface $logger = null;
    private bool $validateBeforeSend = true;
    private string $providerCode = '000';
    private ?string $signerRole = XadesSigner::ROLE_SUPPLIER;

    /**
     * @param string $environment SoapClient::ENV_HABILITACION | SoapClient::ENV_PRODUCCION
     */
    public function __construct(
        ?string $certPath,
        ?string $certPassword,
        string $environment = SoapClient::ENV_HABILITACION
    ) {
        if (!in_array($environment, [SoapClient::ENV_HABILITACION, SoapClient::ENV_PRODUCCION], true)) {
            throw new \InvalidArgumentException("Ambiente inválido '{$environment}': use 'habilitacion' o 'produccion'.");
        }
        $this->certPath     = $certPath;
        $this->certPassword = $certPassword;
        $this->environment  = $environment;
    }

    public static function withCertificate(Certificate $certificate, string $environment = SoapClient::ENV_HABILITACION): self
    {
        $dian = new self(null, null, $environment);
        $dian->certificate = $certificate;

        return $dian;
    }

    // ------------------------------------------------------------------
    // Configuration
    // ------------------------------------------------------------------

    public function setXmlBuilder(XmlBuilder $xmlBuilder): self
    {
        $this->xmlBuilder = $xmlBuilder;
        return $this;
    }

    public function setCufeGenerator(CufeGenerator $cufeGenerator): self
    {
        $this->cufeGenerator = $cufeGenerator;
        return $this;
    }

    public function setQrGenerator(QrGenerator $qrGenerator): self
    {
        $this->qrGenerator = $qrGenerator;
        return $this;
    }

    public function setSigner(XadesSigner $signer): self
    {
        $this->signer = $signer;
        return $this;
    }

    public function setSoapClient(SoapClient $soapClient): self
    {
        $this->soapClient = $soapClient;
        return $this;
    }

    public function setValidator(BusinessRuleValidator $validator): self
    {
        $this->validator = $validator;
        return $this;
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /** Runs the pre-flight checks before every send (default true). */
    public function validateBeforeSend(bool $enabled = true): self
    {
        $this->validateBeforeSend = $enabled;
        return $this;
    }

    /** Código del proveedor tecnológico (PPP) for the file names; "000" for software propio. */
    public function setProviderCode(string $providerCode): self
    {
        $this->providerCode = $providerCode;
        return $this;
    }

    /** XadesSigner::ROLE_SUPPLIER (default) or ROLE_THIRD_PARTY for proveedores tecnológicos. */
    public function setSignerRole(?string $signerRole): self
    {
        $this->signerRole = $signerRole;
        $this->signer     = null;
        return $this;
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /** '1' producción / '2' habilitación (TipoAmbiente). */
    public function getEnvironmentCode(): string
    {
        return $this->isProduction() ? CufeGenerator::ENV_PRODUCCION : CufeGenerator::ENV_HABILITACION;
    }

    public function isProduction(): bool
    {
        return $this->environment === SoapClient::ENV_PRODUCCION;
    }

    public function getCertificate(): Certificate
    {
        if ($this->certificate === null) {
            if ($this->certPath === null) {
                throw new \RuntimeException('No se configuró el certificado digital.');
            }
            $this->certificate = Certificate::fromPkcs12File($this->certPath, (string) $this->certPassword);
        }

        return $this->certificate;
    }

    public function getSoapClient(): SoapClient
    {
        if ($this->soapClient === null) {
            $this->soapClient = new SoapClient($this->environment, null, null, $this->logger);
        }
        $this->soapClient->setCertificate($this->getCertificate());
        if ($this->logger !== null) {
            $this->soapClient->setLogger($this->logger);
        }

        return $this->soapClient;
    }

    // ------------------------------------------------------------------
    // Factura, Nota Crédito, Nota Débito
    // ------------------------------------------------------------------

    /**
     * Validates, signs and sends a factura / nota.
     *
     * Habilitación with setTestSetId(): SendTestSetAsync → pending Result with
     * ZipKey (use waitForResult()). Otherwise SendBillSync → final Result.
     */
    public function send(Invoice|CreditNote|DebitNote $document): Result
    {
        try {
            if ($this->validateBeforeSend) {
                $report = $this->preflight($document);
                if (!$report->isReady()) {
                    return $this->blocked($report, $report->getUuid());
                }
                $signedXml = $report->getSignedXml();
                $uuid      = $report->getUuid();
            } else {
                [$uuid, $signedXml] = $this->buildSigned($document);
            }

            $type     = match (true) {
                $document instanceof EquivalentDocument => FileNameGenerator::forEquivalentDocument($document->getTipo()),
                $document instanceof CreditNote && $document->isAjusteDocumentoEquivalente() => FileNameGenerator::forEquivalentDocument('94'),
                $document instanceof DebitNote && $document->isAjusteDocumentoEquivalente()  => FileNameGenerator::forEquivalentDocument('93'),
                $document instanceof Invoice    => FileNameGenerator::FACTURA,
                $document instanceof CreditNote => FileNameGenerator::NOTA_CREDITO,
                default                         => FileNameGenerator::NOTA_DEBITO,
            };
            $fileName = FileNameGenerator::base($type, $document->getCompany()?->getNit() ?? '', $document->getNumero(), $document->getFecha(), $this->providerCode);
            $testSet  = $this->isProduction() ? '' : $document->getTestSetId();

            return $this->getSoapClient()->send($fileName, $signedXml, $testSet, $uuid);
        } catch (\Throwable $th) {
            return Result::failure($th->getMessage());
        }
    }

    /** Signed XML of a factura / nota without sending it. */
    public function getSignedXml(Invoice|CreditNote|DebitNote $document): string
    {
        return $this->buildSigned($document)[1];
    }

    /** CUFE / CUDE of a document (clave técnica for facturas, PIN for notas). */
    public function getUuid(Invoice|CreditNote|DebitNote $document): string
    {
        return $this->cufe()->generate($document, $this->keyOrPin($document), $this->getEnvironmentCode());
    }

    /**
     * Dry run: business rules, certificate, CUFE, XML, signature, XSD and
     * the recomputations DIAN performs on reception. Nothing is sent.
     */
    public function preflight(Invoice|CreditNote|DebitNote|SupportDocument $document): PreflightReport
    {
        $report = new PreflightReport();
        $report->add('reglas', ($this->validator ?? new BusinessRuleValidator())->validate($document, $this->getEnvironmentCode()));

        try {
            $certificate = $this->getCertificate();
            $nit         = $document->getCompany()?->getNit();
            $report->add('certificado', (new CertificateChecker())->check($certificate, $nit));
        } catch (\Throwable $e) {
            $report->add('certificado', (new ValidationResult())->add('CERT_000', $e->getMessage()));
            return $report;
        }

        try {
            if ($document instanceof SupportDocument) {
                [$uuid, $signedXml] = $this->buildSignedSupportDocument($document);
                $keyOrPin = $document->getSoftware()?->getPin() ?? '';
            } else {
                [$uuid, $signedXml] = $this->buildSigned($document);
                $keyOrPin = $this->keyOrPin($document);
            }
        } catch (\Throwable $e) {
            $report->add('xml', (new ValidationResult())->add('XML_000', 'No fue posible generar o firmar el XML: ' . $e->getMessage()));
            return $report;
        }
        $report->setSignedXml($signedXml)->setUuid($uuid);

        $report->add('xsd', (new XsdValidator())->validate($signedXml));

        $signature = new ValidationResult();
        foreach ((new SignatureVerifier())->verify($signedXml) as $problem) {
            $signature->add('SIG_001', $problem);
        }
        $report->add('firma', $signature);

        $report->add('consistencia', (new XmlConsistencyChecker())->check(
            $signedXml,
            $keyOrPin,
            $document->getSoftware()?->getPin(),
            $this->getEnvironmentCode()
        ));

        return $report;
    }

    // ------------------------------------------------------------------
    // Documento Soporte y nota de ajuste
    // ------------------------------------------------------------------

    public function sendSupportDocument(SupportDocument $document): Result
    {
        try {
            if ($this->validateBeforeSend) {
                $report = $this->preflight($document);
                if (!$report->isReady()) {
                    return $this->blocked($report, $report->getUuid());
                }
                [$cuds, $signedXml] = [$report->getUuid(), $report->getSignedXml()];
            } else {
                [$cuds, $signedXml] = $this->buildSignedSupportDocument($document);
            }

            $type     = $document instanceof SupportDocumentAdjustmentNote ? FileNameGenerator::NOTA_AJUSTE_DS : FileNameGenerator::SOPORTE;
            $fileName = FileNameGenerator::base($type, $document->getCompany()?->getNit() ?? '', $document->getNumero(), $document->getFecha(), $this->providerCode);

            return $this->getSoapClient()->send($fileName, $signedXml, $this->isProduction() ? '' : $document->getTestSetId(), $cuds);
        } catch (\Throwable $th) {
            return Result::failure($th->getMessage());
        }
    }

    public function getSignedSupportDocument(SupportDocument $document): string
    {
        return $this->buildSignedSupportDocument($document)[1];
    }

    // ------------------------------------------------------------------
    // Nómina electrónica
    // ------------------------------------------------------------------

    /**
     * Signs and sends a Nómina Individual. In habilitación pass the TestSetId
     * of the nómina set de pruebas to use SendTestSetAsync.
     */
    public function sendPayroll(PayrollDocument $document, string $testSetId = ''): Result
    {
        try {
            [$cune, $signed] = $this->buildSignedPayroll($document);

            $type     = $document instanceof PayrollAdjustment ? FileNameGenerator::NOMINA_AJUSTE : FileNameGenerator::NOMINA;
            $fileName = FileNameGenerator::base($type, $document->getEmpleador()?->getNit() ?? '', $document->getConsecutivo(), $document->getFechaGen(), $this->providerCode);
            if (!$this->isProduction() && $testSetId !== '') {
                return $this->getSoapClient()->sendTestSetAsync($fileName, $signed, $testSetId, $cune);
            }

            return $this->getSoapClient()->sendNominaSync($signed, $cune, $fileName);
        } catch (\Throwable $th) {
            return Result::failure($th->getMessage());
        }
    }

    /**
     * Nota de ajuste de nómina (reemplazar o eliminar una nómina ya validada).
     * Same flow as sendPayroll(); kept as its own method for readability.
     */
    public function sendPayrollAdjustment(PayrollAdjustment $adjustment, string $testSetId = ''): Result
    {
        return $this->sendPayroll($adjustment, $testSetId);
    }

    /** Signed nómina / nota de ajuste XML without sending it. */
    public function getSignedPayroll(PayrollDocument $document): string
    {
        return $this->buildSignedPayroll($document)[1];
    }

    /** @return array{string,string} [cune, signedXml] */
    private function buildSignedPayroll(PayrollDocument $document): array
    {
        $document->setAmbiente($this->getEnvironmentCode());
        $cune = (new CuneGenerator())->generate($document, $document->getSoftware()?->getPin() ?? '');
        $xml  = (new PayrollXmlBuilder())->build($document, $cune, QrGenerator::url($cune, $this->isProduction()));

        return [$cune, $this->signer()->sign($xml)];
    }

    // ------------------------------------------------------------------
    // RADIAN
    // ------------------------------------------------------------------

    /** Signs and sends a RADIAN event (acuse, recibo, aceptación, reclamo…). */
    public function sendEvent(EventDocument $event): Result
    {
        try {
            if ($event->getCude() === '') {
                $event->setCude((new EventCudeGenerator())->generate($event, $event->getSoftware()?->getPin() ?? ''));
            }
            $xml    = (new ApplicationResponseBuilder(null, $this->getEnvironmentCode()))->build($event);
            $signed = $this->signer()->sign($xml);

            $fileName = FileNameGenerator::base(FileNameGenerator::EVENTO, $event->getReceiver()?->getNit() ?? '', $event->getId(), $event->getIssueDate(), $this->providerCode);

            return $this->getSoapClient()->sendEventUpdateStatus($signed, $event->getCude(), $fileName);
        } catch (\Throwable $th) {
            return Result::failure($th->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Entrega al adquiriente
    // ------------------------------------------------------------------

    /**
     * Signed AttachedDocument (XML del documento + ApplicationResponse de la
     * DIAN) to deliver to the buyer together with the PDF.
     */
    public function buildAttachedDocument(Invoice|CreditNote|DebitNote $document, Result $result): string
    {
        if (!$result->isSuccess()) {
            throw new \LogicException('Solo se entrega al adquiriente un documento validado por la DIAN.');
        }
        $client = $document->getClient();
        $company = $document->getCompany();
        if ($client === null || $company === null) {
            throw new \LogicException('El documento no tiene emisor o adquiriente.');
        }

        $attached = (new AttachedDocument())
            ->setId($result->getCufe())
            ->setIssueDate(new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota')))
            ->setParentDocumentId($document->getPrefijo() . $document->getNumero())
            ->setSender($company)
            ->setReceiver($client)
            ->setSignedInvoiceXml($result->getSignedXml())
            ->setApplicationResponseXml($result->getApplicationResponseXml())
            ->setProfileExecutionId($this->getEnvironmentCode());

        return $this->signer()->sign((new AttachedDocumentBuilder())->build($attached));
    }

    /** Signs any UBL document (e.g. one built manually) with the configured certificate. */
    public function signXml(string $xml): string
    {
        return $this->signer()->sign($xml);
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    /** Status by CUFE/CUDE (sync) or ZipKey (async / set de pruebas). */
    public function getStatus(string $trackId): StatusResult
    {
        return $this->getSoapClient()->getStatusZip($trackId);
    }

    /**
     * Polls DIAN until an async upload (set de pruebas, SendBillAsync) finishes.
     */
    public function waitForResult(string $zipKey, int $attempts = 10, int $delaySeconds = 3): StatusResult
    {
        $status = $this->getStatus($zipKey);
        for ($i = 1; $i < $attempts && ($status->isProcessing() || $status->getStatusCode() === ''); $i++) {
            sleep($delaySeconds);
            $status = $this->getStatus($zipKey);
        }

        return $status;
    }

    /**
     * Authorized numbering ranges with their TechnicalKey (clave técnica).
     *
     * @return list<array<string,string>>
     */
    public function getNumberingRanges(string $nit, string $softwareId, ?string $providerNit = null): array
    {
        return $this->getSoapClient()->getNumberingRanges($nit, $providerNit ?? $nit, $softwareId);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /** @return array{string,string} [uuid, signedXml] */
    private function buildSigned(Invoice|CreditNote|DebitNote $document): array
    {
        $uuid    = $this->getUuid($document);
        $qr      = ($this->qrGenerator ?? new QrGenerator())->content($document, $uuid, $this->isProduction());
        $builder = $this->xmlBuilder ?? new XmlBuilder(null, $this->getEnvironmentCode());

        return [$uuid, $this->signer()->sign($builder->build($document, $uuid, $qr))];
    }

    /** @return array{string,string} [cuds, signedXml] */
    private function buildSignedSupportDocument(SupportDocument $document): array
    {
        $cuds = (new CudsGenerator())->generate($document, $document->getSoftware()?->getPin() ?? '', $this->getEnvironmentCode());
        $xml  = (new SupportDocumentBuilder(null, $this->getEnvironmentCode()))->build($document, $cuds);

        return [$cuds, $this->signer()->sign($xml)];
    }

    private function keyOrPin(Invoice|CreditNote|DebitNote $document): string
    {
        // Factura: clave técnica (CUFE). Notas y documentos equivalentes: PIN (CUDE).
        return $document instanceof Invoice && !$document instanceof EquivalentDocument
            ? $document->getTechnicalKey()
            : ($document->getSoftware()?->getPin() ?? '');
    }

    private function cufe(): CufeGenerator
    {
        return $this->cufeGenerator ??= new CufeGenerator();
    }

    private function signer(): XadesSigner
    {
        return $this->signer ??= XadesSigner::fromCertificate($this->getCertificate(), $this->signerRole);
    }

    private function blocked(PreflightReport $report, string $uuid): Result
    {
        return Result::failure(
            'Validación previa al envío: ' . implode(' | ', $report->messages()),
            $uuid,
            $report->getSignedXml()
        )->setErrors($report->messages());
    }
}
