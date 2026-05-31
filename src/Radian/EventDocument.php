<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Radian;

use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;

/**
 * RADIAN event payload. The *receiver* (party that signs) is modelled as the
 * Company (because for RADIAN they are the issuer of the response), and the
 * original invoice supplier is the Client.
 */
class EventDocument
{
    private string $id = '';
    private string $cude = '';                 // CUDE of THIS event response
    private \DateTimeInterface $issueDate;
    private ApplicationResponseEvent $event;
    private string $invoiceCufe = '';          // CUFE of the original invoice
    private string $invoiceId = '';            // FV-123 reference
    private \DateTimeInterface $invoiceDate;
    private ?Company $receiver = null;         // Party that signs the response
    private ?Client $supplier = null;          // Original issuer of the invoice

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setCude(string $cude): self
    {
        $this->cude = $cude;
        return $this;
    }

    public function getCude(): string
    {
        return $this->cude;
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

    public function setEvent(ApplicationResponseEvent $event): self
    {
        $this->event = $event;
        return $this;
    }

    public function getEvent(): ?ApplicationResponseEvent
    {
        return $this->event ?? null;
    }

    public function setInvoiceCufe(string $invoiceCufe): self
    {
        $this->invoiceCufe = $invoiceCufe;
        return $this;
    }

    public function getInvoiceCufe(): string
    {
        return $this->invoiceCufe;
    }

    public function setInvoiceId(string $invoiceId): self
    {
        $this->invoiceId = $invoiceId;
        return $this;
    }

    public function getInvoiceId(): string
    {
        return $this->invoiceId;
    }

    public function setInvoiceDate(\DateTimeInterface $invoiceDate): self
    {
        $this->invoiceDate = $invoiceDate;
        return $this;
    }

    public function getInvoiceDate(): ?\DateTimeInterface
    {
        return $this->invoiceDate ?? null;
    }

    public function setReceiver(Company $receiver): self
    {
        $this->receiver = $receiver;
        return $this;
    }

    public function getReceiver(): ?Company
    {
        return $this->receiver;
    }

    public function setSupplier(Client $supplier): self
    {
        $this->supplier = $supplier;
        return $this;
    }

    public function getSupplier(): ?Client
    {
        return $this->supplier;
    }
}
