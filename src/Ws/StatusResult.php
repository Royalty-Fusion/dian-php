<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Ws;

/**
 * Result of a GetStatus / GetStatusZip query against the DIAN web service.
 */
final class StatusResult
{
    private bool $isValid = false;
    private string $statusCode = '';
    private string $statusDescription = '';
    private string $statusMessage = '';
    private string $applicationResponseXml = '';  // populated by getStatusZip
    /** @var string[] */
    private array $errorMessages = [];

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function setIsValid(bool $isValid): self
    {
        $this->isValid = $isValid;
        return $this;
    }

    public function getStatusCode(): string
    {
        return $this->statusCode;
    }

    public function setStatusCode(string $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function getStatusDescription(): string
    {
        return $this->statusDescription;
    }

    public function setStatusDescription(string $statusDescription): self
    {
        $this->statusDescription = $statusDescription;
        return $this;
    }

    public function getStatusMessage(): string
    {
        return $this->statusMessage;
    }

    public function setStatusMessage(string $statusMessage): self
    {
        $this->statusMessage = $statusMessage;
        return $this;
    }

    public function getApplicationResponseXml(): string
    {
        return $this->applicationResponseXml;
    }

    public function setApplicationResponseXml(string $applicationResponseXml): self
    {
        $this->applicationResponseXml = $applicationResponseXml;
        return $this;
    }

    /** @return string[] */
    public function getErrorMessages(): array
    {
        return $this->errorMessages;
    }

    /** @param string[] $errorMessages */
    public function setErrorMessages(array $errorMessages): self
    {
        $this->errorMessages = $errorMessages;
        return $this;
    }
}
