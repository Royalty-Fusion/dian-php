<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

use RoyaltyFusion\DianPhp\Ws\DianResponse;

/**
 * Outcome of sending a document to DIAN.
 *
 *   isSuccess()  DIAN validated the document (sync) — it is legally issued.
 *   isPending()  async upload accepted (test set / SendBillAsync); poll
 *                Dian::getStatus() with getZipKey() for the final answer.
 *
 * getErrors() lists every DIAN rejection ("Regla: FAD06, Rechazo: …") and
 * getNotifications() the informative ones that do not reject the document.
 */
class Result
{
    private bool $success = false;
    private bool $pending = false;
    private string $cufe = '';
    private string $signedXml = '';
    private string $errorMessage = '';
    private string $zipKey = '';
    private string $statusCode = '';
    private string $statusDescription = '';
    /** @var list<string> */
    private array $errors = [];
    /** @var list<string> */
    private array $notifications = [];
    private string $applicationResponseXml = '';
    private string $fileName = '';
    private ?DianResponse $response = null;

    public static function fromResponse(DianResponse $response, string $cufe = '', string $signedXml = ''): self
    {
        $result = (new self())
            ->setCufe($cufe !== '' ? $cufe : $response->getDocumentKey())
            ->setSignedXml($signedXml)
            ->setResponse($response);

        $result->zipKey                 = $response->getZipKey();
        $result->statusCode             = $response->getStatusCode();
        $result->statusDescription      = $response->getStatusDescription();
        $result->errors                 = $response->rejections();
        $result->notifications          = $response->notifications();
        $result->applicationResponseXml = $response->getApplicationResponseXml();

        if ($response->isValid()) {
            $result->setSuccess(true);
        } elseif ($response->isAccepted() || $response->isProcessing()) {
            $result->pending = true;
            $result->setErrorMessage(
                $response->isProcessing()
                    ? 'El documento sigue en procesamiento en la DIAN.'
                    : 'Documento recibido por la DIAN (ZipKey ' . $response->getZipKey() . '); consulte el estado.'
            );
        } else {
            $result->setErrorMessage($response->summary());
        }

        return $result;
    }

    public static function failure(string $message, string $cufe = '', string $signedXml = ''): self
    {
        return (new self())->setSuccess(false)->setErrorMessage($message)->setCufe($cufe)->setSignedXml($signedXml);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): self
    {
        $this->success = $success;
        return $this;
    }

    public function isPending(): bool
    {
        return $this->pending;
    }

    public function getCufe(): string
    {
        return $this->cufe;
    }

    public function setCufe(string $cufe): self
    {
        $this->cufe = $cufe;
        return $this;
    }

    public function getSignedXml(): string
    {
        return $this->signedXml;
    }

    public function setSignedXml(string $signedXml): self
    {
        $this->signedXml = $signedXml;
        return $this;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    /** ZipKey (trackId) of an async upload — use it with Dian::getStatus(). */
    public function getZipKey(): string
    {
        return $this->zipKey;
    }

    public function getStatusCode(): string
    {
        return $this->statusCode;
    }

    public function getStatusDescription(): string
    {
        return $this->statusDescription;
    }

    /** @return list<string> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @param list<string> $errors */
    public function setErrors(array $errors): self
    {
        $this->errors = $errors;
        return $this;
    }

    /** @return list<string> */
    public function getNotifications(): array
    {
        return $this->notifications;
    }

    /** ApplicationResponse signed by DIAN — attach it to the AttachedDocument sent to the buyer. */
    public function getApplicationResponseXml(): string
    {
        return $this->applicationResponseXml;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): self
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function getResponse(): ?DianResponse
    {
        return $this->response;
    }

    public function setResponse(?DianResponse $response): self
    {
        $this->response = $response;
        return $this;
    }
}
