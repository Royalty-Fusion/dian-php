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
}
