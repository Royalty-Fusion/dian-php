<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Ws;

use RoyaltyFusion\DianPhp\Model\Result;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin SOAP 1.2 client over symfony/http-client to talk to the
 * DIAN web services (Habilitación / Producción).
 *
 * Greenter-style: this class does not assemble UBL XML, it just packages
 * the already-signed XML inside a ZIP and pushes the SOAP envelope.
 *
 * Phase 11 will add: SendBillSync, GetStatus, GetStatusZip, GetNumberingRange,
 * GetXmlByDocumentKey and the BinaryReceipt parser.
 */
class SoapClient
{
    public const ENV_HABILITACION = 'habilitacion';
    public const ENV_PRODUCCION   = 'produccion';

    public const ENDPOINT_HAB  = 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc';
    public const ENDPOINT_PROD = 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc';

    private string $environment;
    private HttpClientInterface $httpClient;

    public function __construct(string $environment = self::ENV_HABILITACION, ?HttpClientInterface $httpClient = null)
    {
        $this->environment = $environment;
        $this->httpClient  = $httpClient ?? HttpClient::create();
    }

    /**
     * Sends the signed XML to DIAN.
     *
     * Uses SendTestSetAsync when $testSetId is provided (Habilitación),
     * SendBillAsync otherwise.
     */
    public function send(string $fileName, string $signedXml, string $testSetId = '', string $cufe = ''): Result
    {
        $endpoint = $this->environment === self::ENV_PRODUCCION
            ? self::ENDPOINT_PROD
            : self::ENDPOINT_HAB;

        $zipContent = $this->compress($fileName, $signedXml);
        $base64Zip  = base64_encode($zipContent);

        if ($testSetId !== '') {
            $soapAction = 'http://wcf.dian.colombia/IWcfDianCustomerServices/SendTestSetAsync';
            $soapBody   = $this->buildSendTestSetEnvelope($fileName, $base64Zip, $testSetId);
        } else {
            $soapAction = 'http://wcf.dian.colombia/IWcfDianCustomerServices/SendBillAsync';
            $soapBody   = $this->buildSendBillEnvelope($fileName, $base64Zip);
        }

        $result = new Result();
        // Prefer the CUFE passed explicitly by the orchestrator over regex extraction
        $result->setCufe($cufe !== '' ? $cufe : $this->extractCufe($signedXml));
        $result->setSignedXml($signedXml);

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Content-Type' => 'application/soap+xml;charset=UTF-8;action="' . $soapAction . '"',
                ],
                'body' => $soapBody,
            ]);

            $statusCode = $response->getStatusCode();
            $content    = $response->getContent(false);

            if ($statusCode >= 200 && $statusCode < 300) {
                $result->setSuccess(true);

                if (
                    stripos($content, '<b:IsValid>false</b:IsValid>') !== false
                    || stripos($content, '<b:StatusCode>99</b:StatusCode>') !== false
                ) {
                    $result->setSuccess(false);
                    preg_match('/<b:StatusDescription[^>]*>(.*?)<\/b:StatusDescription>/', $content, $msgMatches);
                    $errMsg = $msgMatches[1] ?? 'Documento rechazado por la DIAN (Validation Error).';
                    $result->setErrorMessage($errMsg);
                }
            } else {
                $result->setSuccess(false);
                $result->setErrorMessage("HTTP Error: $statusCode. SOAP Response: $content");
            }
        } catch (\Throwable $th) {
            $result->setSuccess(false);
            $result->setErrorMessage($th->getMessage());
        }

        return $result;
    }

    private function buildSendTestSetEnvelope(string $fileName, string $base64Zip, string $testSetId): string
    {
        return <<<XML
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:wcf="http://wcf.dian.colombia">
   <soap:Header/>
   <soap:Body>
      <wcf:SendTestSetAsync>
         <wcf:fileName>{$fileName}.zip</wcf:fileName>
         <wcf:contentFile>{$base64Zip}</wcf:contentFile>
         <wcf:testSetId>{$testSetId}</wcf:testSetId>
      </wcf:SendTestSetAsync>
   </soap:Body>
</soap:Envelope>
XML;
    }

    private function buildSendBillEnvelope(string $fileName, string $base64Zip): string
    {
        return <<<XML
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:wcf="http://wcf.dian.colombia">
   <soap:Header/>
   <soap:Body>
      <wcf:SendBillAsync>
         <wcf:fileName>{$fileName}.zip</wcf:fileName>
         <wcf:contentFile>{$base64Zip}</wcf:contentFile>
      </wcf:SendBillAsync>
   </soap:Body>
</soap:Envelope>
XML;
    }

    private function compress(string $fileName, string $xml): string
    {
        $zipFile = sys_get_temp_dir() . '/' . $fileName . '.zip';
        $zip     = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create ZIP archive at ' . $zipFile);
        }
        $zip->addFromString($fileName . '.xml', $xml);
        $zip->close();

        $content = file_get_contents($zipFile);
        @unlink($zipFile);

        if ($content === false) {
            throw new \RuntimeException('Unable to read generated ZIP archive.');
        }
        return $content;
    }

    /** Last-resort CUFE extractor used when the orchestrator doesn't pass it explicitly. */
    private function extractCufe(string $xml): string
    {
        if (preg_match('/<cbc:UUID[^>]*>(.*?)<\/cbc:UUID>/', $xml, $matches)) {
            return $matches[1];
        }
        return '';
    }

    /**
     * Queries the DIAN web service for the current status of a previously
     * sent document, by its trackId (the CUFE/CUDE).
     */
    public function getStatus(string $trackId): StatusResult
    {
        return $this->callStatus(
            'http://wcf.dian.colombia/IWcfDianCustomerServices/GetStatus',
            <<<XML
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:wcf="http://wcf.dian.colombia">
   <soap:Header/>
   <soap:Body>
      <wcf:GetStatus>
         <wcf:trackId>{$trackId}</wcf:trackId>
      </wcf:GetStatus>
   </soap:Body>
</soap:Envelope>
XML
        );
    }

    /**
     * Like getStatus() but also retrieves the b64-encoded ApplicationResponse
     * zip and unpacks the embedded XML for the caller.
     */
    public function getStatusZip(string $trackId): StatusResult
    {
        return $this->callStatus(
            'http://wcf.dian.colombia/IWcfDianCustomerServices/GetStatusZip',
            <<<XML
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:wcf="http://wcf.dian.colombia">
   <soap:Header/>
   <soap:Body>
      <wcf:GetStatusZip>
         <wcf:trackId>{$trackId}</wcf:trackId>
      </wcf:GetStatusZip>
   </soap:Body>
</soap:Envelope>
XML
            ,
            true
        );
    }

    private function callStatus(string $soapAction, string $envelope, bool $expectZip = false): StatusResult
    {
        $endpoint = $this->environment === self::ENV_PRODUCCION
            ? self::ENDPOINT_PROD
            : self::ENDPOINT_HAB;

        $result = new StatusResult();
        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Content-Type' => 'application/soap+xml;charset=UTF-8;action="' . $soapAction . '"',
                ],
                'body' => $envelope,
            ]);
            $content = $response->getContent(false);

            if (preg_match('/<b:IsValid[^>]*>(.*?)<\/b:IsValid>/', $content, $m)) {
                $result->setIsValid(strtolower(trim($m[1])) === 'true');
            }
            if (preg_match('/<b:StatusCode[^>]*>(.*?)<\/b:StatusCode>/', $content, $m)) {
                $result->setStatusCode(trim($m[1]));
            }
            if (preg_match('/<b:StatusDescription[^>]*>(.*?)<\/b:StatusDescription>/', $content, $m)) {
                $result->setStatusDescription(trim($m[1]));
            }
            if (preg_match('/<b:StatusMessage[^>]*>(.*?)<\/b:StatusMessage>/', $content, $m)) {
                $result->setStatusMessage(trim($m[1]));
            }

            // Collect error messages
            if (preg_match_all('/<b:string[^>]*>(.*?)<\/b:string>/', $content, $errs)) {
                $result->setErrorMessages(array_values(array_filter(array_map('trim', $errs[1]))));
            }

            if ($expectZip && preg_match('/<b:XmlBase64Bytes[^>]*>(.*?)<\/b:XmlBase64Bytes>/', $content, $m)) {
                $zipContent = base64_decode($m[1]);
                $zipFile    = tempnam(sys_get_temp_dir(), 'dian_ar_');
                if ($zipFile !== false) {
                    file_put_contents($zipFile, $zipContent);
                    $zip = new \ZipArchive();
                    if ($zip->open($zipFile) === true) {
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $name = $zip->getNameIndex($i);
                            if ($name !== false && str_ends_with(strtolower($name), '.xml')) {
                                $result->setApplicationResponseXml((string) $zip->getFromIndex($i));
                                break;
                            }
                        }
                        $zip->close();
                    }
                    @unlink($zipFile);
                }
            }
        } catch (\Throwable $th) {
            $result->setStatusDescription('Transport error: ' . $th->getMessage());
        }

        return $result;
    }

    /**
     * Consulta los rangos de numeración autorizados para una empresa.
     *
     * @param string $accountCode   NIT de la empresa (sin DV)
     * @param string $accountCodeT  NIT del proveedor tecnológico
     * @param string $softwareCode  Identificador del software DIAN (UUID)
     */
    public function getNumberingRange(string $accountCode, string $accountCodeT, string $softwareCode): string
    {
        $endpoint = $this->environment === self::ENV_PRODUCCION
            ? self::ENDPOINT_PROD
            : self::ENDPOINT_HAB;

        $soapAction = 'http://wcf.dian.colombia/IWcfDianCustomerServices/GetNumberingRange';
        $envelope = <<<XML
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:wcf="http://wcf.dian.colombia">
   <soap:Header/>
   <soap:Body>
      <wcf:GetNumberingRange>
         <wcf:accountCode>{$accountCode}</wcf:accountCode>
         <wcf:accountCodeT>{$accountCodeT}</wcf:accountCodeT>
         <wcf:softwareCode>{$softwareCode}</wcf:softwareCode>
      </wcf:GetNumberingRange>
   </soap:Body>
</soap:Envelope>
XML;

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Content-Type' => 'application/soap+xml;charset=UTF-8;action="' . $soapAction . '"',
                ],
                'body' => $envelope,
            ]);
            return (string) $response->getContent(false);
        } catch (\Throwable $th) {
            return '<error>' . htmlspecialchars($th->getMessage(), ENT_XML1) . '</error>';
        }
    }

    /**
     * Descarga el XML de un documento por su CUFE/CUDE.
     *
     * Returns the raw XML string of the signed document if found, else empty.
     */
    public function getXmlByDocumentKey(string $trackId): string
    {
        $endpoint = $this->environment === self::ENV_PRODUCCION
            ? self::ENDPOINT_PROD
            : self::ENDPOINT_HAB;

        $soapAction = 'http://wcf.dian.colombia/IWcfDianCustomerServices/GetXmlByDocumentKey';
        $envelope = <<<XML
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:wcf="http://wcf.dian.colombia">
   <soap:Header/>
   <soap:Body>
      <wcf:GetXmlByDocumentKey>
         <wcf:trackId>{$trackId}</wcf:trackId>
      </wcf:GetXmlByDocumentKey>
   </soap:Body>
</soap:Envelope>
XML;

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Content-Type' => 'application/soap+xml;charset=UTF-8;action="' . $soapAction . '"',
                ],
                'body' => $envelope,
            ]);
            $content = $response->getContent(false);
            if (preg_match('/<b:XmlBase64Bytes[^>]*>(.*?)<\/b:XmlBase64Bytes>/', $content, $m)) {
                return (string) base64_decode($m[1]);
            }
            return '';
        } catch (\Throwable) {
            return '';
        }
    }
}
