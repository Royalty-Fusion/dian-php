<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Payroll;

/**
 * Trabajador (worker / employee).
 *
 * Tipo trabajador (Anexo Nómina): 01=Dependiente, 02=Servicio temporal, 03=Aprendiz Sena.
 * Tipo contrato: 1=Indefinido, 2=Fijo, 3=Obra/Labor, 4=Aprendizaje, 5=Pasantía.
 */
class Trabajador
{
    private string $tipoTrabajador = '01';
    private string $subTipoTrabajador = '00';
    private bool $altoRiesgoPension = false;
    private string $tipoDocumento = '13';      // 13=CC, 22=CE, 31=NIT, etc.
    private string $numeroDocumento = '';
    private string $primerApellido = '';
    private string $segundoApellido = '';
    private string $primerNombre = '';
    private string $otrosNombres = '';
    private string $lugarTrabajoPais = 'CO';
    private string $lugarTrabajoDepartamentoEstado = '';
    private string $lugarTrabajoMunicipioCiudad = '';
    private string $lugarTrabajoDireccion = '';
    private bool $salarioIntegral = false;
    private string $tipoContrato = '1';
    private float $sueldo = 0.0;
    private string $codigoTrabajador = '';

    public function setTipoTrabajador(string $v): self { $this->tipoTrabajador = $v; return $this; }
    public function getTipoTrabajador(): string { return $this->tipoTrabajador; }
    public function setSubTipoTrabajador(string $v): self { $this->subTipoTrabajador = $v; return $this; }
    public function getSubTipoTrabajador(): string { return $this->subTipoTrabajador; }
    public function setAltoRiesgoPension(bool $v): self { $this->altoRiesgoPension = $v; return $this; }
    public function getAltoRiesgoPension(): bool { return $this->altoRiesgoPension; }
    public function setTipoDocumento(string $v): self { $this->tipoDocumento = $v; return $this; }
    public function getTipoDocumento(): string { return $this->tipoDocumento; }
    public function setNumeroDocumento(string $v): self { $this->numeroDocumento = $v; return $this; }
    public function getNumeroDocumento(): string { return $this->numeroDocumento; }
    public function setPrimerApellido(string $v): self { $this->primerApellido = $v; return $this; }
    public function getPrimerApellido(): string { return $this->primerApellido; }
    public function setSegundoApellido(string $v): self { $this->segundoApellido = $v; return $this; }
    public function getSegundoApellido(): string { return $this->segundoApellido; }
    public function setPrimerNombre(string $v): self { $this->primerNombre = $v; return $this; }
    public function getPrimerNombre(): string { return $this->primerNombre; }
    public function setOtrosNombres(string $v): self { $this->otrosNombres = $v; return $this; }
    public function getOtrosNombres(): string { return $this->otrosNombres; }
    public function setLugarTrabajoPais(string $v): self { $this->lugarTrabajoPais = $v; return $this; }
    public function getLugarTrabajoPais(): string { return $this->lugarTrabajoPais; }
    public function setLugarTrabajoDepartamentoEstado(string $v): self { $this->lugarTrabajoDepartamentoEstado = $v; return $this; }
    public function getLugarTrabajoDepartamentoEstado(): string { return $this->lugarTrabajoDepartamentoEstado; }
    public function setLugarTrabajoMunicipioCiudad(string $v): self { $this->lugarTrabajoMunicipioCiudad = $v; return $this; }
    public function getLugarTrabajoMunicipioCiudad(): string { return $this->lugarTrabajoMunicipioCiudad; }
    public function setLugarTrabajoDireccion(string $v): self { $this->lugarTrabajoDireccion = $v; return $this; }
    public function getLugarTrabajoDireccion(): string { return $this->lugarTrabajoDireccion; }
    public function setSalarioIntegral(bool $v): self { $this->salarioIntegral = $v; return $this; }
    public function getSalarioIntegral(): bool { return $this->salarioIntegral; }
    public function setTipoContrato(string $v): self { $this->tipoContrato = $v; return $this; }
    public function getTipoContrato(): string { return $this->tipoContrato; }
    public function setSueldo(float $v): self { $this->sueldo = $v; return $this; }
    public function getSueldo(): float { return $this->sueldo; }
    public function setCodigoTrabajador(string $v): self { $this->codigoTrabajador = $v; return $this; }
    public function getCodigoTrabajador(): string { return $this->codigoTrabajador; }
}
