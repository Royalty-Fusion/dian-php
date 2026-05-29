<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

class DiscrepancyResponse
{
    private string $referenceId;
    private string $responseCode;
    private string $description;

    public function setReferenceId(string $referenceId): self
    {
        $this->referenceId = $referenceId;
        return $this;
    }

    public function getReferenceId(): string
    {
        return $this->referenceId ?? '';
    }

    public function setResponseCode(string $responseCode): self
    {
        $this->responseCode = $responseCode;
        return $this;
    }

    public function getResponseCode(): string
    {
        return $this->responseCode ?? '';
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description ?? '';
    }
}
