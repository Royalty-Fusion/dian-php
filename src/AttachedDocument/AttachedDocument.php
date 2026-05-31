<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\AttachedDocument;

use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;

/**
 * AttachedDocument — "Contenedor de Factura Electrónica" per DIAN Anexo V1.9.
 *
 * It wraps:
 *   1. The signed Invoice / CreditNote / DebitNote XML (embedded via CDATA)
 *   2. The DIAN-signed ApplicationResponse with status (also via CDATA)
 *   3. An optional outer XAdES signature by the facturador tecnológico.
 *
 * This is the format issuers (Siigo, Alegra, Facture, etc.) send to the
 * receiver via email — it bundles the legal proof that DIAN accepted the
 * document.
 */
class AttachedDocument
{
    private string $id = '';                            // typically the CUFE
    private \DateTimeInterface $issueDate;
    private string $parentDocumentId = '';              // e.g. FV5
    private ?Company $sender = null;
    private ?Client $receiver = null;
    private string $signedInvoiceXml = '';
    private string $applicationResponseXml = '';
    private string $customizationId = 'Documentos adjuntos';
    private string $profileId = 'Factura Electrónica de Venta';
    private string $profileExecutionId = '1';

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
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

    public function setParentDocumentId(string $parentDocumentId): self
    {
        $this->parentDocumentId = $parentDocumentId;
        return $this;
    }

    public function getParentDocumentId(): string
    {
        return $this->parentDocumentId;
    }

    public function setSender(Company $sender): self
    {
        $this->sender = $sender;
        return $this;
    }

    public function getSender(): ?Company
    {
        return $this->sender;
    }

    public function setReceiver(Client $receiver): self
    {
        $this->receiver = $receiver;
        return $this;
    }

    public function getReceiver(): ?Client
    {
        return $this->receiver;
    }

    public function setSignedInvoiceXml(string $signedInvoiceXml): self
    {
        $this->signedInvoiceXml = $signedInvoiceXml;
        return $this;
    }

    public function getSignedInvoiceXml(): string
    {
        return $this->signedInvoiceXml;
    }

    public function setApplicationResponseXml(string $applicationResponseXml): self
    {
        $this->applicationResponseXml = $applicationResponseXml;
        return $this;
    }

    public function getApplicationResponseXml(): string
    {
        return $this->applicationResponseXml;
    }

    public function setCustomizationId(string $customizationId): self
    {
        $this->customizationId = $customizationId;
        return $this;
    }

    public function getCustomizationId(): string
    {
        return $this->customizationId;
    }

    public function setProfileId(string $profileId): self
    {
        $this->profileId = $profileId;
        return $this;
    }

    public function getProfileId(): string
    {
        return $this->profileId;
    }

    public function setProfileExecutionId(string $profileExecutionId): self
    {
        $this->profileExecutionId = $profileExecutionId;
        return $this;
    }

    public function getProfileExecutionId(): string
    {
        return $this->profileExecutionId;
    }
}
