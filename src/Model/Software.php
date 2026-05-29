<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

class Software
{
    private string $id;
    private string $pin;
    private string $providerNit;

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): string
    {
        return $this->id ?? '';
    }

    public function setPin(string $pin): self
    {
        $this->pin = $pin;
        return $this;
    }

    public function getPin(): string
    {
        return $this->pin ?? '';
    }

    public function setProviderNit(string $providerNit): self
    {
        $this->providerNit = $providerNit;
        return $this;
    }

    public function getProviderNit(): string
    {
        return $this->providerNit ?? '';
    }

    /**
     * Calculates the SoftwareSecurityCode hash (SHA-384)
     * as required by DIAN: hash(IdSoftware + Pin + NumeroFactura)
     */
    public function getSecurityCode(string $invoiceNumber): string
    {
        if (empty($this->id) || empty($this->pin) || empty($invoiceNumber)) {
            return '';
        }
        return hash('sha384', $this->id . $this->pin . $invoiceNumber);
    }
}
