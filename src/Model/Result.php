<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

class Result
{
    private bool $success;
    private string $cufe;
    private string $signedXml;
    private string $errorMessage;

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): self
    {
        $this->success = $success;
        return $this;
    }

    public function getCufe(): string
    {
        return $this->cufe ?? '';
    }

    public function setCufe(string $cufe): self
    {
        $this->cufe = $cufe;
        return $this;
    }

    public function getSignedXml(): string
    {
        return $this->signedXml ?? '';
    }

    public function setSignedXml(string $signedXml): self
    {
        $this->signedXml = $signedXml;
        return $this;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage ?? '';
    }

    public function setErrorMessage(string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }
}
