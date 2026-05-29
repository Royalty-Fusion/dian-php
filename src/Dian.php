<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp;

use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;
use RoyaltyFusion\DianPhp\Model\Result;
use RoyaltyFusion\DianPhp\Service\XmlBuilder;
use RoyaltyFusion\DianPhp\Service\XadesSigner;
use RoyaltyFusion\DianPhp\Service\SoapClient;
use RoyaltyFusion\DianPhp\Service\CufeGenerator;
use RoyaltyFusion\DianPhp\Service\QrGenerator;

class Dian
{
    private string $certPath;
    private string $certPassword;
    private string $environment;

    public function __construct(string $certPath, string $certPassword, string $environment = SoapClient::ENV_HABILITACION)
    {
        $this->certPath = $certPath;
        $this->certPassword = $certPassword;
        $this->environment = $environment;
    }

    /**
     * Processes, signs, and sends an Invoice, CreditNote or DebitNote document to DIAN
     *
     * @param Invoice|CreditNote|DebitNote $document
     * @return Result
     */
    public function send(Invoice|CreditNote|DebitNote $document): Result
    {
        try {
            // 1. Generate CUFE / CUDE
            $cufeGen = new CufeGenerator();
            $keyOrPin = ($document instanceof Invoice) 
                ? $document->getTechnicalKey() 
                : ($document->getSoftware() ? $document->getSoftware()->getPin() : '');

            $uuid = $cufeGen->generate($document, $keyOrPin);

            // 2. Generate QR Code Url
            $qrGen = new QrGenerator();
            $qrCodeUrl = $qrGen->generate($document, $uuid);

            // 3. Build XML
            $builder = new XmlBuilder();
            $xml = $builder->build($document, $uuid, $qrCodeUrl);

            // 4. Sign XML using XAdES-EPES
            $signer = new XadesSigner($this->certPath, $this->certPassword);
            $signedXml = $signer->sign($xml);

            // 5. Send via SOAP Client
            $soapClient = new SoapClient($this->environment);
            
            $nit = $document->getCompany() ? $document->getCompany()->getNit() : '000000000';
            $fileName = "z{$nit}{$document->getPrefijo()}{$document->getNumero()}";
            
            return $soapClient->send($fileName, $signedXml, $document->getTestSetId());

        } catch (\Throwable $th) {
            $result = new Result();
            $result->setSuccess(false);
            $result->setErrorMessage($th->getMessage());
            return $result;
        }
    }
}
