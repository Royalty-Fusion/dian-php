<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

/**
 * UBL `<cac:PaymentExchangeRate>` — multi-currency exchange rate block.
 *
 * Required by DIAN for export invoices (currency != COP) so the document
 * carries both the local amount (COP) and the foreign currency presentation.
 *
 * Example (real Siigo invoice):
 *   <cac:PaymentExchangeRate>
 *     <cbc:SourceCurrencyCode>COP</cbc:SourceCurrencyCode>
 *     <cbc:SourceCurrencyBaseRate>3669.96</cbc:SourceCurrencyBaseRate>
 *     <cbc:TargetCurrencyCode>USD</cbc:TargetCurrencyCode>
 *     <cbc:TargetCurrencyBaseRate>1.00</cbc:TargetCurrencyBaseRate>
 *     <cbc:CalculationRate>3669.96</cbc:CalculationRate>
 *     <cbc:Date>2026-03-31</cbc:Date>
 *   </cac:PaymentExchangeRate>
 */
class ExchangeRate
{
    private string $sourceCurrencyCode = 'COP';
    private float $sourceCurrencyBaseRate = 1.0;
    private string $targetCurrencyCode = 'USD';
    private float $targetCurrencyBaseRate = 1.0;
    private float $calculationRate = 0.0;
    private \DateTimeInterface $date;

    public function setSourceCurrencyCode(string $sourceCurrencyCode): self
    {
        $this->sourceCurrencyCode = $sourceCurrencyCode;
        return $this;
    }

    public function getSourceCurrencyCode(): string
    {
        return $this->sourceCurrencyCode;
    }

    public function setSourceCurrencyBaseRate(float $sourceCurrencyBaseRate): self
    {
        $this->sourceCurrencyBaseRate = $sourceCurrencyBaseRate;
        return $this;
    }

    public function getSourceCurrencyBaseRate(): float
    {
        return $this->sourceCurrencyBaseRate;
    }

    public function setTargetCurrencyCode(string $targetCurrencyCode): self
    {
        $this->targetCurrencyCode = $targetCurrencyCode;
        return $this;
    }

    public function getTargetCurrencyCode(): string
    {
        return $this->targetCurrencyCode;
    }

    public function setTargetCurrencyBaseRate(float $targetCurrencyBaseRate): self
    {
        $this->targetCurrencyBaseRate = $targetCurrencyBaseRate;
        return $this;
    }

    public function getTargetCurrencyBaseRate(): float
    {
        return $this->targetCurrencyBaseRate;
    }

    public function setCalculationRate(float $calculationRate): self
    {
        $this->calculationRate = $calculationRate;
        return $this;
    }

    public function getCalculationRate(): float
    {
        return $this->calculationRate;
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
}
