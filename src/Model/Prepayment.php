<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

/**
 * Anticipo recibido — affects the LegalMonetaryTotal/PrepaidAmount and is
 * also itemized via <cac:PrepaidPayment>.
 */
class Prepayment
{
    private string $id = '';
    private float $paidAmount = 0.0;
    private \DateTimeInterface $paidDate;

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setPaidAmount(float $paidAmount): self
    {
        $this->paidAmount = $paidAmount;
        return $this;
    }

    public function getPaidAmount(): float
    {
        return $this->paidAmount;
    }

    public function setPaidDate(\DateTimeInterface $paidDate): self
    {
        $this->paidDate = $paidDate;
        return $this;
    }

    public function getPaidDate(): ?\DateTimeInterface
    {
        return $this->paidDate ?? null;
    }
}
