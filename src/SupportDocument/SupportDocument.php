<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\SupportDocument;

use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\HasRichDocumentFields;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Payment;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Model\Tax;

/**
 * Documento Soporte en Adquisiciones (DS).
 *
 * Used when the supplier is NOT obligated to issue an electronic invoice
 * (e.g. natural persons under the income threshold). The acquirente becomes
 * the issuer and a CUDS hash replaces the CUFE.
 *
 * TODO (Phase 6 follow-up):
 *   - supportdocument.xml.twig template (CustomizationID = 11)
 *   - CudsGenerator (variant of CufeGenerator)
 *   - Adjustment Note (TipoDocumento=95) sister model + template
 *   - Wiring inside Dian facade with separate send() overload
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
}
