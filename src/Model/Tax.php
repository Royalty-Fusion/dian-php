<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

class Tax
{
    /** @var string DIAN Code for Tax (e.g. 01 for IVA, 04 for INC, 03 for ICA) */
    private string $code;
    
    /** @var string Name of the tax (e.g. IVA) */
    private string $name;

    /** @var float Tax percentage (e.g. 19.00) */
    private float $percent;

    /** @var float Taxable base amount */
    private float $base;

    /** @var float Calculated tax amount */
    private float $amount;
    
    /** @var bool True if it's a retention tax (ReteIVA, ReteRenta) */
    private bool $isRetention = false;

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getCode(): string
    {
        return $this->code ?? '';
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name ?? '';
    }

    public function setPercent(float $percent): self
    {
        $this->percent = $percent;
        return $this;
    }

    public function getPercent(): float
    {
        return $this->percent ?? 0.0;
    }

    public function setBase(float $base): self
    {
        $this->base = $base;
        return $this;
    }

    public function getBase(): float
    {
        return $this->base ?? 0.0;
    }

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getAmount(): float
    {
        return $this->amount ?? 0.0;
    }

    public function setIsRetention(bool $isRetention): self
    {
        $this->isRetention = $isRetention;
        return $this;
    }

    public function isRetention(): bool
    {
        return $this->isRetention;
    }

    private float $perUnitAmount = 0.0;
    private float $baseUnitMeasure = 0.0;
    private string $unitCode = '94';

    /**
     * Tributo de valor fijo por unidad (INC bolsas plásticas 22, impuesto al
     * consumo de licores, carbono…): valor por unidad × unidades. The tax has
     * no tarifa ni base gravable; its amount is calculated when not set.
     */
    public function setPerUnit(float $amountPerUnit, float $units, string $unitCode = '94'): self
    {
        $this->perUnitAmount   = $amountPerUnit;
        $this->baseUnitMeasure = $units;
        $this->unitCode        = $unitCode;
        return $this;
    }

    public function isPerUnit(): bool
    {
        return $this->baseUnitMeasure > 0;
    }

    public function getPerUnitAmount(): float
    {
        return $this->perUnitAmount;
    }

    public function getBaseUnitMeasure(): float
    {
        return $this->baseUnitMeasure;
    }

    public function getUnitCode(): string
    {
        return $this->unitCode;
    }
}
