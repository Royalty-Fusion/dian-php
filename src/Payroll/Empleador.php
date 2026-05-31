<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Payroll;

use RoyaltyFusion\DianPhp\Model\NitDvCalculator;

/**
 * Empleador (employer) — the party issuing the payroll receipt.
 */
class Empleador
{
    private string $nit = '';
    private ?int $dv = null;
    private string $nombreRazonSocial = '';
    private string $pais = 'CO';
    private string $departamentoEstado = '';   // DANE code (2 digits)
    private string $municipioCiudad = '';      // DANE code (5 digits)
    private string $direccion = '';

    public function setNit(string $nit): self { $this->nit = $nit; return $this; }
    public function getNit(): string { return $this->nit; }

    public function setDv(int $dv): self { $this->dv = $dv; return $this; }
    public function getDv(): int { return $this->dv ?? NitDvCalculator::compute($this->nit); }

    public function setNombreRazonSocial(string $nombre): self { $this->nombreRazonSocial = $nombre; return $this; }
    public function getNombreRazonSocial(): string { return $this->nombreRazonSocial; }

    public function setPais(string $pais): self { $this->pais = $pais; return $this; }
    public function getPais(): string { return $this->pais; }

    public function setDepartamentoEstado(string $depto): self { $this->departamentoEstado = $depto; return $this; }
    public function getDepartamentoEstado(): string { return $this->departamentoEstado; }

    public function setMunicipioCiudad(string $municipio): self { $this->municipioCiudad = $municipio; return $this; }
    public function getMunicipioCiudad(): string { return $this->municipioCiudad; }

    public function setDireccion(string $direccion): self { $this->direccion = $direccion; return $this; }
    public function getDireccion(): string { return $this->direccion; }
}
