<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Payroll;

/**
 * Deducciones — all amounts withheld from the worker.
 *
 * Same flat shape as {@see Devengados} — every field is optional, default 0.0.
 */
class Deducciones
{
    private float $saludPorcentaje = 4.0;
    private float $saludDeduccion = 0.0;
    private float $pensionPorcentaje = 4.0;
    private float $pensionDeduccion = 0.0;
    private float $fondoSPDeduccionSP = 0.0;
    private float $fondoSPDeduccionSub = 0.0;
    private float $sindicatos = 0.0;
    private float $sanciones = 0.0;
    private float $libranzas = 0.0;
    private float $pagosTerceros = 0.0;
    private float $anticipos = 0.0;
    private float $otrasDeducciones = 0.0;
    private float $pensionVoluntaria = 0.0;
    private float $retencionFuente = 0.0;
    private float $afc = 0.0;
    private float $cooperativa = 0.0;
    private float $embargoFiscal = 0.0;
    private float $planComplementarios = 0.0;
    private float $educacion = 0.0;
    private float $reintegro = 0.0;
    private float $deuda = 0.0;

    public function setSaludPorcentaje(float $v): self { $this->saludPorcentaje = $v; return $this; }
    public function getSaludPorcentaje(): float { return $this->saludPorcentaje; }
    public function setSaludDeduccion(float $v): self { $this->saludDeduccion = $v; return $this; }
    public function getSaludDeduccion(): float { return $this->saludDeduccion; }
    public function setPensionPorcentaje(float $v): self { $this->pensionPorcentaje = $v; return $this; }
    public function getPensionPorcentaje(): float { return $this->pensionPorcentaje; }
    public function setPensionDeduccion(float $v): self { $this->pensionDeduccion = $v; return $this; }
    public function getPensionDeduccion(): float { return $this->pensionDeduccion; }
    public function setFondoSPDeduccionSP(float $v): self { $this->fondoSPDeduccionSP = $v; return $this; }
    public function getFondoSPDeduccionSP(): float { return $this->fondoSPDeduccionSP; }
    public function setFondoSPDeduccionSub(float $v): self { $this->fondoSPDeduccionSub = $v; return $this; }
    public function getFondoSPDeduccionSub(): float { return $this->fondoSPDeduccionSub; }
    public function setSindicatos(float $v): self { $this->sindicatos = $v; return $this; }
    public function getSindicatos(): float { return $this->sindicatos; }
    public function setSanciones(float $v): self { $this->sanciones = $v; return $this; }
    public function getSanciones(): float { return $this->sanciones; }
    public function setLibranzas(float $v): self { $this->libranzas = $v; return $this; }
    public function getLibranzas(): float { return $this->libranzas; }
    public function setPagosTerceros(float $v): self { $this->pagosTerceros = $v; return $this; }
    public function getPagosTerceros(): float { return $this->pagosTerceros; }
    public function setAnticipos(float $v): self { $this->anticipos = $v; return $this; }
    public function getAnticipos(): float { return $this->anticipos; }
    public function setOtrasDeducciones(float $v): self { $this->otrasDeducciones = $v; return $this; }
    public function getOtrasDeducciones(): float { return $this->otrasDeducciones; }
    public function setPensionVoluntaria(float $v): self { $this->pensionVoluntaria = $v; return $this; }
    public function getPensionVoluntaria(): float { return $this->pensionVoluntaria; }
    public function setRetencionFuente(float $v): self { $this->retencionFuente = $v; return $this; }
    public function getRetencionFuente(): float { return $this->retencionFuente; }
    public function setAfc(float $v): self { $this->afc = $v; return $this; }
    public function getAfc(): float { return $this->afc; }
    public function setCooperativa(float $v): self { $this->cooperativa = $v; return $this; }
    public function getCooperativa(): float { return $this->cooperativa; }
    public function setEmbargoFiscal(float $v): self { $this->embargoFiscal = $v; return $this; }
    public function getEmbargoFiscal(): float { return $this->embargoFiscal; }
    public function setPlanComplementarios(float $v): self { $this->planComplementarios = $v; return $this; }
    public function getPlanComplementarios(): float { return $this->planComplementarios; }
    public function setEducacion(float $v): self { $this->educacion = $v; return $this; }
    public function getEducacion(): float { return $this->educacion; }
    public function setReintegro(float $v): self { $this->reintegro = $v; return $this; }
    public function getReintegro(): float { return $this->reintegro; }
    public function setDeuda(float $v): self { $this->deuda = $v; return $this; }
    public function getDeuda(): float { return $this->deuda; }

    public function total(): float
    {
        $sum = $this->saludDeduccion + $this->pensionDeduccion + $this->fondoSPDeduccionSP
            + $this->fondoSPDeduccionSub + $this->sindicatos + $this->sanciones + $this->libranzas
            + $this->pagosTerceros + $this->anticipos + $this->otrasDeducciones
            + $this->pensionVoluntaria + $this->retencionFuente + $this->afc + $this->cooperativa
            + $this->embargoFiscal + $this->planComplementarios + $this->educacion + $this->reintegro
            + $this->deuda;
        return floor($sum * 100) / 100;
    }
}
