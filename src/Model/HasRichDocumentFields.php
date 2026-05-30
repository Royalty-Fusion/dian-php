<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

/**
 * Trait shared by Invoice, CreditNote and DebitNote with the optional UBL
 * collaborators introduced in Phase 3: allowance/charge, prepayments,
 * additional document references, notes, multi-currency support.
 */
trait HasRichDocumentFields
{
    /** @var AllowanceCharge[] */
    private array $allowanceCharges = [];

    /** @var Prepayment[] */
    private array $prepayments = [];

    /** @var AdditionalDocumentReference[] */
    private array $additionalDocumentReferences = [];

    /** @var Note[] */
    private array $notes = [];

    private string $currencyCode = 'COP';
    private float $exchangeRate = 0.0;

    public function addAllowanceCharge(AllowanceCharge $allowanceCharge): self
    {
        $this->allowanceCharges[] = $allowanceCharge;
        return $this;
    }

    /** @return AllowanceCharge[] */
    public function getAllowanceCharges(): array
    {
        return $this->allowanceCharges;
    }

    public function addPrepayment(Prepayment $prepayment): self
    {
        $this->prepayments[] = $prepayment;
        return $this;
    }

    /** @return Prepayment[] */
    public function getPrepayments(): array
    {
        return $this->prepayments;
    }

    public function addAdditionalDocumentReference(AdditionalDocumentReference $reference): self
    {
        $this->additionalDocumentReferences[] = $reference;
        return $this;
    }

    /** @return AdditionalDocumentReference[] */
    public function getAdditionalDocumentReferences(): array
    {
        return $this->additionalDocumentReferences;
    }

    public function addNote(Note|string $note): self
    {
        $this->notes[] = $note instanceof Note ? $note : new Note($note);
        return $this;
    }

    /** @return Note[] */
    public function getNotes(): array
    {
        return $this->notes;
    }

    public function setCurrencyCode(string $currencyCode): self
    {
        $this->currencyCode = $currencyCode;
        return $this;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function setExchangeRate(float $exchangeRate): self
    {
        $this->exchangeRate = $exchangeRate;
        return $this;
    }

    public function getExchangeRate(): float
    {
        return $this->exchangeRate;
    }
}
