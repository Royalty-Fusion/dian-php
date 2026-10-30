<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

class BillingReference
{
    private string $number;
    private string $uuid;
    private \DateTimeInterface $date;

    public function setNumber(string $number): self
    {
        $this->number = $number;
        return $this;
    }

    public function getNumber(): string
    {
        return $this->number ?? '';
    }

    public function setUuid(string $uuid): self
    {
        $this->uuid = $uuid;
        return $this;
    }

    public function getUuid(): string
    {
        return $this->uuid ?? '';
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date ?? null;
    }

    private string $uuidSchemeName = 'CUFE-SHA384';

    /** CUFE-SHA384 (factura, default) or CUDE-SHA384 (documento equivalente). */
    public function setUuidSchemeName(string $schemeName): self
    {
        $this->uuidSchemeName = $schemeName;
        return $this;
    }

    public function getUuidSchemeName(): string
    {
        return $this->uuidSchemeName;
    }
}
