<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Ws;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RoyaltyFusion\DianPhp\Model\Result;
use RoyaltyFusion\DianPhp\Signer\Certificate;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client for the DIAN web service WcfDianCustomerServices (SOAP 1.2).
 *
 * - Every call carries WS-Addressing and WS-Security ({@see WsSecurity}),
 *   signed with the facturador's certificate. Without a certificate DIAN
 *   answers with a security fault.
 * - Documents travel as base64 ZIP files named per the Anexo Técnico.
 * - Answers are parsed into {@see DianResponse} (faults included).
 * - Transport errors and HTTP 5xx are retried with exponential backoff.
 *
 * One endpoint serves every document family (factura, notas, DS, nómina,
 * RADIAN); the operation (SOAP action) selects the flow.
 */
class SoapClient
{
    public const ENV_HABILITACION = 'habilitacion';
    public const ENV_PRODUCCION   = 'produccion';

    public const ENDPOINT_HAB  = 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc';
    public const ENDPOINT_PROD = 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc';

    private string $environment;
    private HttpClientInterface $httpClient;
    private ?Certificate $certificate;
    private LoggerInterface $logger;
    private int $maxRetries;
    private float $timeout;
    private ?string $endpoint = null;

    public function __construct(
        string $environment = self::ENV_HABILITACION,
        ?HttpClientInterface $httpClient = null,
        ?Certificate $certificate = null,
        ?LoggerInterface $logger = null,
        int $maxRetries = 2,
        float $timeout = 90.0
    ) {
        $this->environment = $environment;
        $this->httpClient  = $httpClient ?? HttpClient::create();
        $this->certificate = $certificate;
        $this->logger      = $logger ?? new NullLogger();
        $this->maxRetries  = max(0, $maxRetries);
        $this->timeout     = $timeout;
    }

    public function setCertificate(?Certificate $certificate): self
    {
        $this->certificate = $certificate;
        return $this;
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /** Overrides the endpoint URL (e.g. a proxy or a mock server). */
    public function setEndpoint(string $endpoint): self
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint
            ?? ($this->environment === self::ENV_PRODUCCION ? self::ENDPOINT_PROD : self::ENDPOINT_HAB);
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    // ------------------------------------------------------------------
    // Envío de documentos
    // ------------------------------------------------------------------

    /**
     * Sends a signed factura / nota. With $testSetId (habilitación) it uses
     * SendTestSetAsync and returns a pending Result with the ZipKey;
     * otherwise SendBillSync, which returns DIAN's final validation.
     *
     * @param string $fileName base name without extension (see FileNameGenerator)
     */
    public function send(string $fileName, string $signedXml, string $testSetId = '', string $cufe = ''): Result
    {
        return $testSetId !== ''
            ? $this->sendTestSetAsync($fileName, $signedXml, $testSetId, $cufe)
            : $this->sendBillSync($fileName, $signedXml, $cufe);
    }

    /** Synchronous validation — the standard flow in producción. */
    public function sendBillSync(string $fileName, string $signedXml, string $cufe = ''): Result
    {
        return $this->sendZip('SendBillSync', $fileName, $signedXml, $cufe);
    }

    /** Asynchronous upload (lotes). Returns a pending Result with the ZipKey. */
    public function sendBillAsync(string $fileName, string $signedXml, string $cufe = ''): Result
    {
        return $this->sendZip('SendBillAsync', $fileName, $signedXml, $cufe);
    }

    /** Set de pruebas de habilitación. Returns a pending Result with the ZipKey. */
    public function sendTestSetAsync(string $fileName, string $signedXml, string $testSetId, string $cufe = ''): Result
    {
        return $this->sendZip('SendTestSetAsync', $fileName, $signedXml, $cufe, ['testSetId' => $testSetId]);
    }

    /** Envío del AttachedDocument / anexos. */
    public function sendBillAttachmentAsync(string $fileName, string $signedXml, string $cufe = ''): Result
    {
        return $this->sendZip('SendBillAttachmentAsync', $fileName, $signedXml, $cufe);
    }

    /** Nómina Individual / Nómina de Ajuste (zip, sync). */
    public function sendNominaSync(string $signedXml, string $cune = '', string $fileName = ''): Result
    {
        return $this->sendZip('SendNominaSync', $fileName !== '' ? $fileName : 'nie' . substr(hash('sha1', $signedXml), 0, 18), $signedXml, $cune, [], false);
    }

    /** RADIAN event (ApplicationResponse signed by whoever generates the event). */
    public function sendEventUpdateStatus(string $signedXml, string $cude = '', string $fileName = ''): Result
    {
        return $this->sendZip('SendEventUpdateStatus', $fileName !== '' ? $fileName : 'ar' . substr(hash('sha1', $signedXml), 0, 18), $signedXml, $cude, [], false);
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    /** Status of a document by CUFE/CUDE (sync flow) or ZipKey (async flow). */
    public function getStatus(string $trackId): StatusResult
    {
        return StatusResult::fromResponse($this->call('GetStatus', ['trackId' => $trackId]));
    }

    /** Like getStatus() plus the ApplicationResponse XML signed by DIAN. */
    public function getStatusZip(string $trackId): StatusResult
    {
        return StatusResult::fromResponse($this->call('GetStatusZip', ['trackId' => $trackId]));
    }

    /**
     * Numbering ranges (resoluciones) authorized for a software, including
     * the TechnicalKey (clave técnica) needed for the CUFE. Raw XML.
     *
     * @param string $accountCode  NIT del facturador (sin DV)
     * @param string $accountCodeT NIT del proveedor tecnológico (igual al facturador si es software propio)
     * @param string $softwareCode SoftwareID asignado por la DIAN
     */
    public function getNumberingRange(string $accountCode, string $accountCodeT, string $softwareCode): string
    {
        return $this->call('GetNumberingRange', [
            'accountCode'  => $accountCode,
            'accountCodeT' => $accountCodeT,
            'softwareCode' => $softwareCode,
        ])->getRaw();
    }

    /**
     * Parsed variant of getNumberingRange(): one row per resolution with keys
     * such as ResolutionNumber, Prefix, FromNumber, ToNumber, ValidDateFrom,
     * ValidDateTo and TechnicalKey.
     *
     * @return list<array<string,string>>
     */
    public function getNumberingRanges(string $accountCode, string $accountCodeT, string $softwareCode): array
    {
        $response = $this->call('GetNumberingRange', [
            'accountCode'  => $accountCode,
            'accountCodeT' => $accountCodeT,
            'softwareCode' => $softwareCode,
        ]);
        if ($response->getFault() !== null) {
            throw new \RuntimeException('GetNumberingRange: ' . $response->getFault());
        }

        return $response->records('NumberRangeResponse');
    }

    /** Signed XML of a document by CUFE/CUDE ('' when not found). */
    public function getXmlByDocumentKey(string $trackId): string
    {
        $response = $this->call('GetXmlByDocumentKey', ['trackId' => $trackId]);
        $xml      = $response->getApplicationResponseXml();

        return $xml !== '' ? $xml : '';
    }

    /** Datos del adquiriente registrados en DIAN (raw XML). */
    public function getAcquirer(string $identificationType, string $identificationNumber): string
    {
        return $this->call('GetAcquirer', [
            'identificationType'   => $identificationType,
            'identificationNumber' => $identificationNumber,
        ])->getRaw();
    }

    /** Correos de recepción registrados por los facturadores (raw XML). */
    public function getExchangeEmails(): string
    {
        return $this->call('GetExchangeEmails', [])->getRaw();
    }

    /** NC/ND emitidas que referencian un documento (raw XML). */
    public function getReferenceNotes(string $documentKey): string
    {
        return $this->call('GetReferenceNotes', ['trackId' => $documentKey])->getRaw();
    }

    /** Eventos RADIAN registrados para un CUFE (raw XML). */
    public function getStatusEvent(string $trackId): string
    {
        return $this->call('GetStatusEvent', ['trackId' => $trackId])->getRaw();
    }

    /**
     * Low-level access to any operation: builds the signed envelope, posts it
     * with retries and parses the answer.
     *
     * @param array<string,string> $params wcf:<name> => value (escaped here)
     */
    public function call(string $operation, array $params): DianResponse
    {
        $body = '';
        foreach ($params as $name => $value) {
            $body .= sprintf('<wcf:%1$s>%2$s</wcf:%1$s>', $name, htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        }

        $envelope = (new WsSecurity($this->certificate))->envelope($operation, $body, $this->getEndpoint());

        return $this->post($operation, $envelope);
    }

    /**
     * @param array<string,string> $extra
     */
    private function sendZip(string $operation, string $fileName, string $signedXml, string $documentKey, array $extra = [], bool $withFileName = true): Result
    {
        try {
            $fileName = preg_replace('/\.(xml|zip)$/i', '', $fileName) ?? $fileName;
            $zip      = self::zip($fileName, $signedXml);
            $params   = $withFileName ? ['fileName' => $fileName . '.zip'] : [];
            $params  += ['contentFile' => base64_encode($zip)] + $extra;

            $response = $this->call($operation, $params);
        } catch (\Throwable $e) {
            return Result::failure($e->getMessage(), $documentKey, $signedXml);
        }

        return Result::fromResponse($response, $documentKey, $signedXml)->setFileName($fileName);
    }

    private function post(string $operation, string $envelope): DianResponse
    {
        $action  = 'http://wcf.dian.colombia/IWcfDianCustomerServices/' . $operation;
        $attempt = 0;

        while (true) {
            $attempt++;
            $start = microtime(true);
            try {
                $response = $this->httpClient->request('POST', $this->getEndpoint(), [
                    'headers' => [
                        'Content-Type' => 'application/soap+xml;charset=UTF-8;action="' . $action . '"',
                        'Accept'       => 'application/soap+xml, text/xml',
                    ],
                    'body'    => $envelope,
                    'timeout' => $this->timeout,
                ]);
                $status  = $response->getStatusCode();
                $content = $response->getContent(false);
            } catch (TransportExceptionInterface $e) {
                $this->logger->warning('DIAN {op}: error de transporte (intento {n}): {msg}', ['op' => $operation, 'n' => $attempt, 'msg' => $e->getMessage()]);
                if ($attempt <= $this->maxRetries) {
                    $this->backoff($attempt);
                    continue;
                }
                return DianResponse::transportError('Error de conexión con la DIAN: ' . $e->getMessage());
            }

            $this->logger->info('DIAN {op}: HTTP {status} en {ms} ms', [
                'op' => $operation, 'status' => $status, 'ms' => (int) ((microtime(true) - $start) * 1000),
            ]);

            if ($status >= 500 && !str_contains($content, 'Fault') && $attempt <= $this->maxRetries) {
                $this->backoff($attempt);
                continue;
            }

            $parsed = new DianResponse($status, $content);
            if ($parsed->getFault() !== null) {
                $this->logger->error('DIAN {op}: {fault}', ['op' => $operation, 'fault' => $parsed->getFault()]);
            }

            return $parsed;
        }
    }

    private function backoff(int $attempt): void
    {
        usleep((int) (min(8, 2 ** ($attempt - 1)) * 1_000_000));
    }

    /** In-memory-equivalent ZIP with "<fileName>.xml" inside. */
    public static function zip(string $fileName, string $xml): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'dian_zip_');
        if ($tmp === false) {
            throw new \RuntimeException('No fue posible crear un archivo temporal para el ZIP.');
        }
        try {
            $zip = new \ZipArchive();
            if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('No fue posible crear el ZIP.');
            }
            $zip->addFromString($fileName . '.xml', $xml);
            $zip->close();

            $content = file_get_contents($tmp);
            if ($content === false) {
                throw new \RuntimeException('No fue posible leer el ZIP generado.');
            }

            return $content;
        } finally {
            @unlink($tmp);
        }
    }
}
