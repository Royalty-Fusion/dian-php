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
    public const ORDER       = 'OrderReference';
    public const DESPATCH    = 'DespatchDocumentReference';
    public const RECEIPT     = 'ReceiptDocumentReference';
    public const CONTRACT    = 'ContractDocumentReference';
    public const ADDITIONAL  = 'AdditionalDocumentReference';

    /**
     * OrderReference (orden de compra), DespatchDocumentReference (remisión),
     * ReceiptDocumentReference (recepción), ContractDocumentReference (contrato)
     * or AdditionalDocumentReference (otro documento).
     */
    private string $type = 'OrderReference';
    private string $documentTypeCode = '';
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

    /** cbc:DocumentTypeCode (AdditionalDocumentReference only). */
    public function setDocumentTypeCode(string $documentTypeCode): self
    {
        $this->documentTypeCode = $documentTypeCode;
        return $this;
    }

    public function getDocumentTypeCode(): string
    {
        return $this->documentTypeCode;
    }
}
