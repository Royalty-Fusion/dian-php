<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

class Item
{
    private string $descripcion;
    private float $cantidad;
    private float $precio;

    /** @var Tax[] */
    private array $taxes = [];

    /** @var AllowanceCharge[] */
    private array $allowanceCharges = [];

    private string $unitCode = '94';        // UN/ECE Rec 20 — default "carga unitaria"
    private string $code = '';              // product code (UNSPSC, GTIN, brand, etc.)
    private string $codeScheme = '999';     // 010=GTIN, 020=EAN, 040=UPC, 999=brand-specific
    private string $sellersItemIdentification = '';
    private string $brandName = '';
    private string $modelName = '';
    private string $note = '';

    public function setDescripcion(string $descripcion): self
    {
        $this->descripcion = $descripcion;
        return $this;
    }

    public function getDescripcion(): string
    {
        return $this->descripcion ?? '';
    }

    public function setCantidad(float $cantidad): self
    {
        $this->cantidad = $cantidad;
        return $this;
    }

    public function getCantidad(): float
    {
        return $this->cantidad ?? 0.0;
    }

    public function setPrecio(float $precio): self
    {
        $this->precio = $precio;
        return $this;
    }

    public function getPrecio(): float
    {
        return $this->precio ?? 0.0;
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

    public function setUnitCode(string $unitCode): self
    {
        $this->unitCode = $unitCode;
        return $this;
    }

    public function getUnitCode(): string
    {
        return $this->unitCode;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCodeScheme(string $codeScheme): self
    {
        $this->codeScheme = $codeScheme;
        return $this;
    }

    public function getCodeScheme(): string
    {
        return $this->codeScheme;
    }

    public function setSellersItemIdentification(string $sellersItemIdentification): self
    {
        $this->sellersItemIdentification = $sellersItemIdentification;
        return $this;
    }

    public function getSellersItemIdentification(): string
    {
        return $this->sellersItemIdentification;
    }

    public function setBrandName(string $brandName): self
    {
        $this->brandName = $brandName;
        return $this;
    }

    public function getBrandName(): string
    {
        return $this->brandName;
    }

    public function setModelName(string $modelName): self
    {
        $this->modelName = $modelName;
        return $this;
    }

    public function getModelName(): string
    {
        return $this->modelName;
    }

    public function setNote(string $note): self
    {
        $this->note = $note;
        return $this;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    private float $referencePrice = 0.0;

    /**
     * Precio de referencia for free-of-charge lines (precio = 0). DIAN requires
     * cac:PricingReference with the commercial value of the gift.
     */
    public function setReferencePrice(float $referencePrice): self
    {
        $this->referencePrice = $referencePrice;
        return $this;
    }

    public function getReferencePrice(): float
    {
        return $this->referencePrice;
    }

    private string $mandanteNit = '';
    private ?int $mandanteDv = null;
    /** @var list<array{name:string,value:string}> */
    private array $properties = [];

    /**
     * Mandato (CustomizationID 11): NIT del mandante por cuenta de quien se
     * factura esta línea (cac:InformationContentProviderParty).
     */
    public function setMandante(string $nit, ?int $dv = null): self
    {
        $this->mandanteNit = $nit;
        $this->mandanteDv  = $dv;
        return $this;
    }

    public function getMandanteNit(): string
    {
        return $this->mandanteNit;
    }

    public function getMandanteDv(): ?int
    {
        return $this->mandanteNit === '' ? null : ($this->mandanteDv ?? NitDvCalculator::compute($this->mandanteNit));
    }

    /**
     * cac:AdditionalItemProperty, e.g. transporte de carga (RNDC):
     * addProperty('01', $numeroRemesa), addProperty('02', $consecutivoInterno),
     * addProperty('03', $valorFlete).
     */
    public function addProperty(string $name, string|int|float $value): self
    {
        $this->properties[] = ['name' => $name, 'value' => (string) $value];
        return $this;
    }

    /** @return list<array{name:string,value:string}> */
    public function getProperties(): array
    {
        return $this->properties;
    }
}
