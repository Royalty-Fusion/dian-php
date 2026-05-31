<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Payroll;

use RoyaltyFusion\DianPhp\Model\Software;

/**
 * Nómina Individual Electrónica (NIE) — main aggregate.
 *
 * Anexo Técnico Nómina Electrónica v2.0. Targets the dedicated DIAN endpoint
 * (apifedi.dian.gov.co/Nomina) not the regular VPFE.
 *
 * Fields and structure adapted from the reference recipe in
 * lopezsoft/ubl21dian's SignPayroll (MIT, credited in CREDITS.md).
 */
class PayrollDocument
{
    // Numeración
    private string $prefijo = '';
    private string $numero = '';
    private string $consecutivo = '';
    private string $codigoTrabajador = '';
    private string $sucursal = '';

    // Fechas
    private \DateTimeInterface $fechaGen;          // FechaGen + HoraGen used in CUNE
    private \DateTimeInterface $fechaPago;

    // Ambiente / tipo
    private string $ambiente = '2';                // 1=Prod, 2=Hab
    private string $tipoXML = '102';               // 102=Individual, 103=Ajuste
    private string $tipoNota = '';                  // empty on Individual, set on Adjustment
    private string $periodoNomina = '5';           // 5=Mensual, 4=Quincenal, etc.

    // Moneda
    private string $tipoMoneda = 'COP';
    private float $trm = 0.0;

    // Lugar generación
    private string $lugarPais = 'CO';
    private string $lugarDepartamentoEstado = '';
    private string $lugarMunicipioCiudad = '';
    private string $lugarIdioma = 'es';

    // Software DIAN
    private ?Software $software = null;
    private string $softwareSC = '';                // SoftwareSecurityCode, computed from software+numero

    // Sub-modelos
    private ?Empleador $empleador = null;
    private ?Trabajador $trabajador = null;
    private ?Periodo $periodo = null;
    private ?Pago $pago = null;
    private Devengados $devengados;
    private Deducciones $deducciones;

    /** @var string[] */
    private array $notas = [];

    public function __construct()
    {
        $this->devengados  = new Devengados();
        $this->deducciones = new Deducciones();
    }

    public function setPrefijo(string $v): self { $this->prefijo = $v; return $this; }
    public function getPrefijo(): string { return $this->prefijo; }

    public function setNumero(string $v): self { $this->numero = $v; return $this; }
    public function getNumero(): string { return $this->numero; }

    public function setConsecutivo(string $v): self { $this->consecutivo = $v; return $this; }
    public function getConsecutivo(): string { return $this->consecutivo ?: $this->numero; }

    public function setCodigoTrabajador(string $v): self { $this->codigoTrabajador = $v; return $this; }
    public function getCodigoTrabajador(): string { return $this->codigoTrabajador; }

    public function setSucursal(string $v): self { $this->sucursal = $v; return $this; }
    public function getSucursal(): string { return $this->sucursal; }

    public function setFechaGen(\DateTimeInterface $v): self { $this->fechaGen = $v; return $this; }
    public function getFechaGen(): ?\DateTimeInterface { return $this->fechaGen ?? null; }

    public function setFechaPago(\DateTimeInterface $v): self { $this->fechaPago = $v; return $this; }
    public function getFechaPago(): ?\DateTimeInterface { return $this->fechaPago ?? null; }

    public function setAmbiente(string $v): self { $this->ambiente = $v; return $this; }
    public function getAmbiente(): string { return $this->ambiente; }

    public function setTipoXML(string $v): self { $this->tipoXML = $v; return $this; }
    public function getTipoXML(): string { return $this->tipoXML; }

    public function setTipoNota(string $v): self { $this->tipoNota = $v; return $this; }
    public function getTipoNota(): string { return $this->tipoNota; }

    public function setPeriodoNomina(string $v): self { $this->periodoNomina = $v; return $this; }
    public function getPeriodoNomina(): string { return $this->periodoNomina; }

    public function setTipoMoneda(string $v): self { $this->tipoMoneda = $v; return $this; }
    public function getTipoMoneda(): string { return $this->tipoMoneda; }
    public function setTrm(float $v): self { $this->trm = $v; return $this; }
    public function getTrm(): float { return $this->trm; }

    public function setLugarPais(string $v): self { $this->lugarPais = $v; return $this; }
    public function getLugarPais(): string { return $this->lugarPais; }
    public function setLugarDepartamentoEstado(string $v): self { $this->lugarDepartamentoEstado = $v; return $this; }
    public function getLugarDepartamentoEstado(): string { return $this->lugarDepartamentoEstado; }
    public function setLugarMunicipioCiudad(string $v): self { $this->lugarMunicipioCiudad = $v; return $this; }
    public function getLugarMunicipioCiudad(): string { return $this->lugarMunicipioCiudad; }
    public function setLugarIdioma(string $v): self { $this->lugarIdioma = $v; return $this; }
    public function getLugarIdioma(): string { return $this->lugarIdioma; }

    public function setSoftware(Software $software): self { $this->software = $software; return $this; }
    public function getSoftware(): ?Software { return $this->software; }
    public function setSoftwareSC(string $v): self { $this->softwareSC = $v; return $this; }
    public function getSoftwareSC(): string
    {
        if ($this->softwareSC !== '') {
            return $this->softwareSC;
        }
        if ($this->software === null) {
            return '';
        }
        return $this->software->getSecurityCode($this->prefijo . $this->numero);
    }

    public function setEmpleador(Empleador $e): self { $this->empleador = $e; return $this; }
    public function getEmpleador(): ?Empleador { return $this->empleador; }

    public function setTrabajador(Trabajador $t): self { $this->trabajador = $t; return $this; }
    public function getTrabajador(): ?Trabajador { return $this->trabajador; }

    public function setPeriodo(Periodo $p): self { $this->periodo = $p; return $this; }
    public function getPeriodo(): ?Periodo { return $this->periodo; }

    public function setPago(Pago $p): self { $this->pago = $p; return $this; }
    public function getPago(): ?Pago { return $this->pago; }

    public function getDevengados(): Devengados { return $this->devengados; }
    public function setDevengados(Devengados $d): self { $this->devengados = $d; return $this; }

    public function getDeducciones(): Deducciones { return $this->deducciones; }
    public function setDeducciones(Deducciones $d): self { $this->deducciones = $d; return $this; }

    public function addNota(string $nota): self { $this->notas[] = $nota; return $this; }
    /** @return string[] */
    public function getNotas(): array { return $this->notas; }

    /** Comprobante total = devengados.total - deducciones.total */
    public function getComprobanteTotal(): float
    {
        return floor(($this->devengados->total() - $this->deducciones->total()) * 100) / 100;
    }
}
