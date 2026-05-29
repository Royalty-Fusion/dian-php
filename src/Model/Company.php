<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

class Company
{
    private string $nit;
    private string $razonSocial;
    private string $tipoDocumento;
    private string $regimen;
    private string $responsabilidades;
    private string $tipoOrganizacion;

    public function setNit(string $nit): self
    {
        $this->nit = $nit;
        return $this;
    }

    public function getNit(): string
    {
        return $this->nit ?? '';
    }

    public function setRazonSocial(string $razonSocial): self
    {
        $this->razonSocial = $razonSocial;
        return $this;
    }

    public function getRazonSocial(): string
    {
        return $this->razonSocial ?? '';
    }

    public function setTipoDocumento(string $tipoDocumento): self
    {
        $this->tipoDocumento = $tipoDocumento;
        return $this;
    }

    public function getTipoDocumento(): string
    {
        return $this->tipoDocumento ?? '';
    }

    public function setRegimen(string $regimen): self
    {
        $this->regimen = $regimen;
        return $this;
    }

    public function getRegimen(): string
    {
        return $this->regimen ?? '';
    }

    public function setResponsabilidades(string $responsabilidades): self
    {
        $this->responsabilidades = $responsabilidades;
        return $this;
    }

    public function getResponsabilidades(): string
    {
        return $this->responsabilidades ?? '';
    }

    public function setTipoOrganizacion(string $tipoOrganizacion): self
    {
        $this->tipoOrganizacion = $tipoOrganizacion;
        return $this;
    }

    public function getTipoOrganizacion(): string
    {
        return $this->tipoOrganizacion ?? '';
    }
}
