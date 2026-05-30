<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

/**
 * UBL AllowanceCharge — represents either a discount or a surcharge,
 * at line level or header level.
 *
 * Discount  → chargeIndicator = false (uses Anexo "Códigos de descuento")
 * Recargo   → chargeIndicator = true  (uses Anexo "Códigos de cargo/recargo")
 */
class AllowanceCharge
{
    private int $id = 1;
    private bool $chargeIndicator = false;
    private string $reasonCode = '';
    private string $reason = '';
    private float $multiplierFactorNumeric = 0.0;
    private float $amount = 0.0;
    private float $baseAmount = 0.0;
    private string $currencyId = 'COP';

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setChargeIndicator(bool $chargeIndicator): self
    {
        $this->chargeIndicator = $chargeIndicator;
        return $this;
    }

    public function isCharge(): bool
    {
        return $this->chargeIndicator;
    }

    public function isDiscount(): bool
    {
        return !$this->chargeIndicator;
    }

    public function setReasonCode(string $reasonCode): self
    {
        $this->reasonCode = $reasonCode;
        return $this;
    }

    public function getReasonCode(): string
    {
        return $this->reasonCode;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;
        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setMultiplierFactorNumeric(float $multiplierFactorNumeric): self
    {
        $this->multiplierFactorNumeric = $multiplierFactorNumeric;
        return $this;
    }

    public function getMultiplierFactorNumeric(): float
    {
        return $this->multiplierFactorNumeric;
    }

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setBaseAmount(float $baseAmount): self
    {
        $this->baseAmount = $baseAmount;
        return $this;
    }

    public function getBaseAmount(): float
    {
        return $this->baseAmount;
    }

    public function setCurrencyId(string $currencyId): self
    {
        $this->currencyId = $currencyId;
        return $this;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }
}
