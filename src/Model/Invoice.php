<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

class Invoice
{
    use HasRichDocumentFields;

    private string $prefijo;
    private string $numero;
    private \DateTimeInterface $fecha;
    private float $total;
    private Company $company;
    private Client $client;
    private Software $software;
    private Resolution $resolution;
    
    /** @var Item[] */
    private array $items = [];

    /** @var Tax[] */
    private array $taxes = [];

    /** @var Payment[] */
    private array $payments = [];
    
    private string $technicalKey;
    private string $testSetId;

    public function setPrefijo(string $prefijo): self
    {
        $this->prefijo = $prefijo;
        return $this;
    }

    public function getPrefijo(): string
    {
        return $this->prefijo ?? '';
    }

    public function setNumero(string $numero): self
    {
        $this->numero = $numero;
        return $this;
    }

    public function getNumero(): string
    {
        return $this->numero ?? '';
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
        return $this->total ?? 0.0;
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

    public function setClient(Client $client): self
    {
        $this->client = $client;
        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client ?? null;
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

    public function setResolution(Resolution $resolution): self
    {
        $this->resolution = $resolution;
        return $this;
    }

    public function getResolution(): ?Resolution
    {
        return $this->resolution ?? null;
    }

    public function addItem(Item $item): self
    {
        $this->items[] = $item;
        return $this;
    }

    /**
     * @return Item[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function addTax(Tax $tax): self
    {
        $this->taxes[] = $tax;
        return $this;
    }

    /**
     * @return Tax[]
     */
    public function getTaxes(): array
    {
        return $this->taxes;
    }

    public function addPayment(Payment $payment): self
    {
        $this->payments[] = $payment;
        return $this;
    }

    /**
     * @return Payment[]
     */
    public function getPayments(): array
    {
        return $this->payments;
    }

    public function setTechnicalKey(string $technicalKey): self
    {
        $this->technicalKey = $technicalKey;
        return $this;
    }

    public function getTechnicalKey(): string
    {
        return $this->technicalKey ?? '';
    }

    public function setTestSetId(string $testSetId): self
    {
        $this->testSetId = $testSetId;
        return $this;
    }

    public function getTestSetId(): string
    {
        return $this->testSetId ?? '';
    }
}
