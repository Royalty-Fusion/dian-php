<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\SupportDocument;

use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\HasRichDocumentFields;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Payment;
use RoyaltyFusion\DianPhp\Model\Resolution;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Model\Tax;

/**
 * Documento Soporte en adquisiciones efectuadas a sujetos no obligados a
 * expedir factura (DS) — Resolución DIAN 000167 de 2021, Anexo Técnico DS.
 *
 * Roles in the XML:
 *   - cac:AccountingSupplierParty = vendedor no obligado ({@see setSupplier()})
 *   - cac:AccountingCustomerParty = adquiriente obligado, who issues and signs
 *     the DS ({@see setCompany()})
 *
 * CustomizationID: 10 = vendedor residente, 11 = vendedor no residente.
 * The hash is the CUDS ({@see CudsGenerator}) and needs a DS numbering
 * resolution ({@see setResolution()}).
 */
class SupportDocument
{
    use HasRichDocumentFields;

    private string $prefijo = '';
    private string $numero  = '';
    private \DateTimeInterface $fecha;
    private float $total = 0.0;
    private Company $company;             // acquirente (the issuer)
    private Client $supplier;             // proveedor no obligado
    private Software $software;

    /** @var Item[] */
    private array $items = [];

    /** @var Tax[] */
    private array $taxes = [];

    /** @var Payment[] */
    private array $payments = [];

    private string $testSetId = '';

    public function setPrefijo(string $prefijo): self
    {
        $this->prefijo = $prefijo;
        return $this;
    }

    public function getPrefijo(): string
    {
        return $this->prefijo;
    }

    public function setNumero(string $numero): self
    {
        $this->numero = $numero;
        return $this;
    }

    public function getNumero(): string
    {
        return $this->numero;
    }

    public function setFecha(\DateTimeInterface $fecha): self
    {
        $this->fecha = $fecha;
        return $this;
    }

    public function getFecha(): ?\DateTimeInterface
    {
        return $this->fecha ?? null;
    }

    public function setTotal(float $total): self
    {
        $this->total = $total;
        return $this;
    }

    public function getTotal(): float
    {
        return $this->total;
    }

    public function setCompany(Company $company): self
    {
        $this->company = $company;
        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company ?? null;
    }

    public function setSupplier(Client $supplier): self
    {
        $this->supplier = $supplier;
        return $this;
    }

    public function getSupplier(): ?Client
    {
        return $this->supplier ?? null;
    }

    public function setSoftware(Software $software): self
    {
        $this->software = $software;
        return $this;
    }

    public function getSoftware(): ?Software
    {
        return $this->software ?? null;
    }

    public function addItem(Item $item): self
    {
        $this->items[] = $item;
        return $this;
    }

    /** @return Item[] */
    public function getItems(): array
    {
        return $this->items;
    }

    public function addTax(Tax $tax): self
    {
        $this->taxes[] = $tax;
        return $this;
    }

    /** @return Tax[] */
    public function getTaxes(): array
    {
        return $this->taxes;
    }

    public function addPayment(Payment $payment): self
    {
        $this->payments[] = $payment;
        return $this;
    }

    /** @return Payment[] */
    public function getPayments(): array
    {
        return $this->payments;
    }

    public function setTestSetId(string $testSetId): self
    {
        $this->testSetId = $testSetId;
        return $this;
    }

    public function getTestSetId(): string
    {
        return $this->testSetId;
    }

    public const RESIDENTE    = '10';
    public const NO_RESIDENTE = '11';

    public const TRANSMISION_POR_OPERACION   = '1';
    public const TRANSMISION_ACUMULADO_SEMANAL = '2';

    private ?Resolution $resolution = null;
    private string $customizationId = self::RESIDENTE;
    private string $transmissionCode = self::TRANSMISION_POR_OPERACION;
    private ?\DateTimeInterface $purchaseDate = null;
    private ?\DateTimeInterface $dueDate = null;

    /** Resolución de numeración del Documento Soporte (prefijo y rango). */
    public function setResolution(Resolution $resolution): self
    {
        $this->resolution = $resolution;
        return $this;
    }

    public function getResolution(): ?Resolution
    {
        return $this->resolution;
    }

    /** 10 = vendedor residente (default), 11 = vendedor no residente en Colombia. */
    public function setCustomizationId(string $customizationId): self
    {
        $this->customizationId = $customizationId;
        return $this;
    }

    public function getCustomizationId(): string
    {
        return $this->customizationId;
    }

    /**
     * Forma de generación y transmisión (cac:InvoicePeriod/cbc:DescriptionCode of every line):
     * 1 = por operación (default), 2 = acumulado semanal.
     */
    public function setTransmissionCode(string $transmissionCode): self
    {
        $this->transmissionCode = $transmissionCode;
        return $this;
    }

    public function getTransmissionCode(): string
    {
        return $this->transmissionCode;
    }

    public function getTransmissionDescription(): string
    {
        return $this->transmissionCode === self::TRANSMISION_ACUMULADO_SEMANAL ? 'Acumulado semanal' : 'Por operación';
    }

    /** Fecha de la compra (defaults to the issue date). */
    public function setPurchaseDate(\DateTimeInterface $purchaseDate): self
    {
        $this->purchaseDate = $purchaseDate;
        return $this;
    }

    public function getPurchaseDate(): ?\DateTimeInterface
    {
        return $this->purchaseDate ?? $this->getFecha();
    }

    public function setDueDate(\DateTimeInterface $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    /** Alias so generic code (QR, reports) can treat the seller as the counterpart. */
    public function getClient(): ?Client
    {
        return $this->getSupplier();
    }
}
