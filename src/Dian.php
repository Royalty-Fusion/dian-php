<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp;

use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\Result;
use RoyaltyFusion\DianPhp\Signer\XadesSigner;
use RoyaltyFusion\DianPhp\Validator\BusinessRuleValidator;
use RoyaltyFusion\DianPhp\Ws\SoapClient;
use RoyaltyFusion\DianPhp\Xml\CufeGenerator;
use RoyaltyFusion\DianPhp\Xml\QrGenerator;
use RoyaltyFusion\DianPhp\Xml\XmlBuilder;

/**
 * High-level facade — the "Greenter::see" of this SDK.
 *
 * Orchestrates: CUFE/CUDE generation → QR URL → XML render → XAdES sign → SOAP send.
 *
 * The orchestration deliberately keeps the surface small so consumers
 * (e.g. the Symfony bundle in Phase 12) can swap pieces independently.
 */
class Dian
{
    private string $certPath;
    private string $certPassword;
    private string $environment;

    private ?XmlBuilder             $xmlBuilder    = null;
    private ?CufeGenerator          $cufeGenerator = null;
    private ?QrGenerator            $qrGenerator   = null;
    private ?XadesSigner            $signer        = null;
    private ?SoapClient             $soapClient    = null;
    private ?BusinessRuleValidator  $validator     = null;
    private bool                    $validateBeforeSend = false;

    public function __construct(
        string $certPath,
        string $certPassword,
        string $environment = SoapClient::ENV_HABILITACION
    ) {
        $this->certPath     = $certPath;
        $this->certPassword = $certPassword;
        $this->environment  = $environment;
    }

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

    public function validateBeforeSend(bool $enabled = true): self
    {
        $this->validateBeforeSend = $enabled;
        return $this;
    }

    /**
     * Sign + send an Invoice, CreditNote or DebitNote to DIAN.
     */
    public function send(Invoice|CreditNote|DebitNote $document): Result
    {
        try {
            if ($this->validateBeforeSend) {
                $validator = $this->validator ?? new BusinessRuleValidator();
                $validation = $validator->validate($document);
                if (!$validation->isValid()) {
                    $result = new Result();
                    $result->setSuccess(false);
                    $result->setErrorMessage(implode(' | ', $validation->messages()));
                    return $result;
                }
            }

            $cufeGen = $this->cufeGenerator ?? new CufeGenerator();
            $envFlag = $this->environment === SoapClient::ENV_PRODUCCION
                ? CufeGenerator::ENV_PRODUCCION
                : CufeGenerator::ENV_HABILITACION;

            $keyOrPin = ($document instanceof Invoice)
                ? $document->getTechnicalKey()
                : ($document->getSoftware() ? $document->getSoftware()->getPin() : '');

            $uuid = $cufeGen->generate($document, $keyOrPin, $envFlag);

            $qrGen     = $this->qrGenerator ?? new QrGenerator();
            $qrCodeUrl = $qrGen->generate($document, $uuid, $this->environment === SoapClient::ENV_PRODUCCION);

            $builder = $this->xmlBuilder ?? new XmlBuilder(null, $envFlag);
            $xml     = $builder->build($document, $uuid, $qrCodeUrl);

            $signer    = $this->signer ?? new XadesSigner($this->certPath, $this->certPassword);
            $signedXml = $signer->sign($xml);

            $soap = $this->soapClient ?? new SoapClient($this->environment);

            $nit      = $document->getCompany() ? $document->getCompany()->getNit() : '000000000';
            $fileName = "z{$nit}{$document->getPrefijo()}{$document->getNumero()}";

            return $soap->send($fileName, $signedXml, $document->getTestSetId(), $uuid);
        } catch (\Throwable $th) {
            $result = new Result();
            $result->setSuccess(false);
            $result->setErrorMessage($th->getMessage());
            return $result;
        }
    }

    /**
     * Returns the signed XML without sending it. Useful for previews/tests.
     */
    public function getSignedXml(Invoice|CreditNote|DebitNote $document): string
    {
        $cufeGen = $this->cufeGenerator ?? new CufeGenerator();
        $envFlag = $this->environment === SoapClient::ENV_PRODUCCION
            ? CufeGenerator::ENV_PRODUCCION
            : CufeGenerator::ENV_HABILITACION;

        $keyOrPin = ($document instanceof Invoice)
            ? $document->getTechnicalKey()
            : ($document->getSoftware() ? $document->getSoftware()->getPin() : '');

        $uuid      = $cufeGen->generate($document, $keyOrPin, $envFlag);
        $qrCodeUrl = ($this->qrGenerator ?? new QrGenerator())
            ->generate($document, $uuid, $this->environment === SoapClient::ENV_PRODUCCION);
        $xml       = ($this->xmlBuilder ?? new XmlBuilder(null, $envFlag))->build($document, $uuid, $qrCodeUrl);

        return ($this->signer ?? new XadesSigner($this->certPath, $this->certPassword))->sign($xml);
    }
}
