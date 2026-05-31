<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Payroll;

/**
 * Devengados — all positive earnings paid to the worker.
 *
 * Flat aggregation of the most common Anexo Nómina v2.0 fields. Every value
 * is optional and defaults to 0.0 so consumers only need to set what applies.
 *
 * Fields not present in the basic shape (Teletrabajo, BonoEPCTV, Compensaciones,
 * Indemnizacion, Reintegro, etc.) can be appended via setOtrosConceptos().
 */
class Devengados
{
    // Basico
    private int $basicoDiasTrabajados = 30;
    private float $basicoSueldoTrabajado = 0.0;

    // Auxilios
    private float $auxilioTransporte = 0.0;
    private float $viaticoManuAlojS = 0.0;
    private float $viaticoManuAlojNS = 0.0;

    // Horas extras (suma agregada — para desglose detallado, extender)
    private float $horasExtraDiurnas = 0.0;
    private float $horasExtraNocturnas = 0.0;
    private float $horasRecargoDiurnoNocturno = 0.0;

    // Vacaciones
    private float $vacacionesComunes = 0.0;
    private float $vacacionesCompensadas = 0.0;

    // Primas
    private float $primas = 0.0;
    private float $primasNS = 0.0;

    // Cesantías
    private float $cesantias = 0.0;
    private float $interesesCesantias = 0.0;
    private float $porcentajeInteresesCesantias = 0.0;

    // Conceptos varios
    private float $incapacidades = 0.0;
    private float $licencias = 0.0;
    private float $bonificaciones = 0.0;
    private float $auxilios = 0.0;
    private float $huelgasLegales = 0.0;
    private float $otrosConceptos = 0.0;
    private float $comisiones = 0.0;
    private float $pagosTerceros = 0.0;
    private float $anticipos = 0.0;
    private float $dotacion = 0.0;
    private float $apoyoSost = 0.0;
    private float $teletrabajo = 0.0;
    private float $bonificacionRetiro = 0.0;
    private float $indemnizacion = 0.0;
    private float $reintegro = 0.0;

    public function setBasicoDiasTrabajados(int $v): self { $this->basicoDiasTrabajados = $v; return $this; }
    public function getBasicoDiasTrabajados(): int { return $this->basicoDiasTrabajados; }
    public function setBasicoSueldoTrabajado(float $v): self { $this->basicoSueldoTrabajado = $v; return $this; }
    public function getBasicoSueldoTrabajado(): float { return $this->basicoSueldoTrabajado; }
    public function setAuxilioTransporte(float $v): self { $this->auxilioTransporte = $v; return $this; }
    public function getAuxilioTransporte(): float { return $this->auxilioTransporte; }
    public function setViaticoManuAlojS(float $v): self { $this->viaticoManuAlojS = $v; return $this; }
    public function getViaticoManuAlojS(): float { return $this->viaticoManuAlojS; }
    public function setViaticoManuAlojNS(float $v): self { $this->viaticoManuAlojNS = $v; return $this; }
    public function getViaticoManuAlojNS(): float { return $this->viaticoManuAlojNS; }
    public function setHorasExtraDiurnas(float $v): self { $this->horasExtraDiurnas = $v; return $this; }
    public function getHorasExtraDiurnas(): float { return $this->horasExtraDiurnas; }
    public function setHorasExtraNocturnas(float $v): self { $this->horasExtraNocturnas = $v; return $this; }
    public function getHorasExtraNocturnas(): float { return $this->horasExtraNocturnas; }
    public function setHorasRecargoDiurnoNocturno(float $v): self { $this->horasRecargoDiurnoNocturno = $v; return $this; }
    public function getHorasRecargoDiurnoNocturno(): float { return $this->horasRecargoDiurnoNocturno; }
    public function setVacacionesComunes(float $v): self { $this->vacacionesComunes = $v; return $this; }
    public function getVacacionesComunes(): float { return $this->vacacionesComunes; }
    public function setVacacionesCompensadas(float $v): self { $this->vacacionesCompensadas = $v; return $this; }
    public function getVacacionesCompensadas(): float { return $this->vacacionesCompensadas; }
    public function setPrimas(float $v): self { $this->primas = $v; return $this; }
    public function getPrimas(): float { return $this->primas; }
    public function setPrimasNS(float $v): self { $this->primasNS = $v; return $this; }
    public function getPrimasNS(): float { return $this->primasNS; }
    public function setCesantias(float $v): self { $this->cesantias = $v; return $this; }
    public function getCesantias(): float { return $this->cesantias; }
    public function setInteresesCesantias(float $v): self { $this->interesesCesantias = $v; return $this; }
    public function getInteresesCesantias(): float { return $this->interesesCesantias; }
    public function setPorcentajeInteresesCesantias(float $v): self { $this->porcentajeInteresesCesantias = $v; return $this; }
    public function getPorcentajeInteresesCesantias(): float { return $this->porcentajeInteresesCesantias; }
    public function setIncapacidades(float $v): self { $this->incapacidades = $v; return $this; }
    public function getIncapacidades(): float { return $this->incapacidades; }
    public function setLicencias(float $v): self { $this->licencias = $v; return $this; }
    public function getLicencias(): float { return $this->licencias; }
    public function setBonificaciones(float $v): self { $this->bonificaciones = $v; return $this; }
    public function getBonificaciones(): float { return $this->bonificaciones; }
    public function setAuxilios(float $v): self { $this->auxilios = $v; return $this; }
    public function getAuxilios(): float { return $this->auxilios; }
    public function setHuelgasLegales(float $v): self { $this->huelgasLegales = $v; return $this; }
    public function getHuelgasLegales(): float { return $this->huelgasLegales; }
    public function setOtrosConceptos(float $v): self { $this->otrosConceptos = $v; return $this; }
    public function getOtrosConceptos(): float { return $this->otrosConceptos; }
    public function setComisiones(float $v): self { $this->comisiones = $v; return $this; }
    public function getComisiones(): float { return $this->comisiones; }
    public function setPagosTerceros(float $v): self { $this->pagosTerceros = $v; return $this; }
    public function getPagosTerceros(): float { return $this->pagosTerceros; }
    public function setAnticipos(float $v): self { $this->anticipos = $v; return $this; }
    public function getAnticipos(): float { return $this->anticipos; }
    public function setDotacion(float $v): self { $this->dotacion = $v; return $this; }
    public function getDotacion(): float { return $this->dotacion; }
    public function setApoyoSost(float $v): self { $this->apoyoSost = $v; return $this; }
    public function getApoyoSost(): float { return $this->apoyoSost; }
    public function setTeletrabajo(float $v): self { $this->teletrabajo = $v; return $this; }
    public function getTeletrabajo(): float { return $this->teletrabajo; }
    public function setBonificacionRetiro(float $v): self { $this->bonificacionRetiro = $v; return $this; }
    public function getBonificacionRetiro(): float { return $this->bonificacionRetiro; }
    public function setIndemnizacion(float $v): self { $this->indemnizacion = $v; return $this; }
    public function getIndemnizacion(): float { return $this->indemnizacion; }
    public function setReintegro(float $v): self { $this->reintegro = $v; return $this; }
    public function getReintegro(): float { return $this->reintegro; }

    /** Truncate-to-2-decimals sum of every devengado (DIAN-spec compliant). */
    public function total(): float
    {
        $sum = $this->basicoSueldoTrabajado + $this->auxilioTransporte + $this->viaticoManuAlojS
            + $this->viaticoManuAlojNS + $this->horasExtraDiurnas + $this->horasExtraNocturnas
            + $this->horasRecargoDiurnoNocturno + $this->vacacionesComunes + $this->vacacionesCompensadas
            + $this->primas + $this->primasNS + $this->cesantias + $this->interesesCesantias
            + $this->incapacidades + $this->licencias + $this->bonificaciones + $this->auxilios
            + $this->huelgasLegales + $this->otrosConceptos + $this->comisiones + $this->pagosTerceros
            + $this->anticipos + $this->dotacion + $this->apoyoSost + $this->teletrabajo
            + $this->bonificacionRetiro + $this->indemnizacion + $this->reintegro;
        return floor($sum * 100) / 100;
    }
}
