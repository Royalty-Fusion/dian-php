<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Service;

use RoyaltyFusion\DianPhp\Model\Result;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\HttpClient;

class SoapClient
{
    private string $environment;
    private HttpClientInterface $httpClient;

    const ENV_HABILITACION = 'habilitacion';
    const ENV_PRODUCCION = 'produccion';

    public function __construct(string $environment = self::ENV_HABILITACION, ?HttpClientInterface $httpClient = null)
    {
        $this->environment = $environment;
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    /**
     * Sends the signed XML to DIAN. If testSetId is provided, it uses SendTestSetAsync,
     * otherwise it uses SendBillAsync.
     * 
     * @param string $fileName
     * @param string $signedXml
     * @param string $testSetId (Optional test set ID for validation/habilitacion)
     * @return Result
     */
    public function send(string $fileName, string $signedXml, string $testSetId = ''): Result
    {
        $endpoint = $this->environment === self::ENV_PRODUCCION 
            ? 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc' 
            : 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc';

        $zipContent = $this->compress($fileName, $signedXml);
        $base64Zip = base64_encode($zipContent);

        // Determine SOAP Action and Body
        if (!empty($testSetId)) {
            $soapAction = 'http://wcf.dian.colombia/IWcfDianCustomerServices/SendTestSetAsync';
            $soapBody = $this->buildSendTestSetEnvelope($fileName, $base64Zip, $testSetId);
        } else {
            $soapAction = 'http://wcf.dian.colombia/IWcfDianCustomerServices/SendBillAsync';
            $soapBody = $this->buildSendBillEnvelope($fileName, $base64Zip);
        }

        $result = new Result();
        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Content-Type' => 'application/soap+xml;charset=UTF-8;action="' . $soapAction . '"',
                ],
                'body' => $soapBody
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);

            if ($statusCode >= 200 && $statusCode < 300) {
                $result->setSuccess(true);
                $result->setSignedXml($signedXml);
                
                // Parse standard DIAN responses
                $cufe = $this->extractCufe($signedXml);
                $result->setCufe($cufe);
                
                // Check if DIAN response shows failure
                if (stripos($content, '<b:IsValid>false</b:IsValid>') !== false || stripos($content, '<b:StatusCode>99</b:StatusCode>') !== false) {
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
        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $zip->addFromString($fileName . '.xml', $xml);
            $zip->close();
        }
        
        $content = file_get_contents($zipFile);
        unlink($zipFile);
        
        return $content;
    }

    private function extractCufe(string $xml): string
    {
        preg_match('/<cbc:UUID[^>]*>(.*?)<\/cbc:UUID>/', $xml, $matches);
        return $matches[1] ?? 'CUFE-NOT-FOUND-IN-XML';
    }
}
