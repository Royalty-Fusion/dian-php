<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

/**
 * UBL AdditionalDocumentReference — order, contract, despatch, receipt advice.
 *
 * Used to link an invoice with the source document (purchase order, contract).
 */
class AdditionalDocumentReference
{
    /** Use one of: OrderReference, ContractDocumentReference, DespatchDocumentReference, ReceiptDocumentReference */
    private string $type = 'OrderReference';
    private string $id = '';
    private string $documentType = '';
    private \DateTimeInterface $issueDate;

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setDocumentType(string $documentType): self
    {
        $this->documentType = $documentType;
        return $this;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function setIssueDate(\DateTimeInterface $issueDate): self
    {
        $this->issueDate = $issueDate;
        return $this;
    }

    public function getIssueDate(): ?\DateTimeInterface
    {
        return $this->issueDate ?? null;
    }
}
