<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Payroll;

/**
 * Deducciones of a Nómina Individual, in the order of the DIAN schema:
 *
 *   Salud, FondoPension, FondoSP, Sindicatos, Sanciones, Libranzas,
 *   PagosTerceros, Anticipos, OtrasDeducciones, PensionVoluntaria,
 *   RetencionFuente, AFC, Cooperativa, EmbargoFiscal, PlanComplementarios,
 *   Educacion, Reintegro, Deuda
 */
final class Deducciones
{
    private float $saludPorcentaje = 4.0;
    private float $saludDeduccion = 0.0;
    private float $pensionPorcentaje = 4.0;
    private float $pensionDeduccion = 0.0;
    /** @var array<string,float> */
    private array $fondoSP = [];
    /** @var list<array<string,float>> */
    private array $sindicatos = [];
    /** @var list<array<string,float>> */
    private array $sanciones = [];
    /** @var list<array<string,string|float>> */
    private array $libranzas = [];
    /** @var list<float> */
    private array $pagosTerceros = [];
    /** @var list<float> */
    private array $anticipos = [];
    /** @var list<float> */
    private array $otrasDeducciones = [];
    /** @var array<string,float> */
    private array $simple = [
        'PensionVoluntaria'   => 0.0,
        'RetencionFuente'     => 0.0,
        'AFC'                 => 0.0,
        'Cooperativa'         => 0.0,
        'EmbargoFiscal'       => 0.0,
        'PlanComplementarios' => 0.0,
        'Educacion'           => 0.0,
        'Reintegro'           => 0.0,
        'Deuda'               => 0.0,
    ];

    public function setSaludPorcentaje(float $v): self { $this->saludPorcentaje = $v; return $this; }
    public function getSaludPorcentaje(): float { return $this->saludPorcentaje; }
    public function setSaludDeduccion(float $v): self { $this->saludDeduccion = $v; return $this; }
    public function getSaludDeduccion(): float { return $this->saludDeduccion; }
    public function setPensionPorcentaje(float $v): self { $this->pensionPorcentaje = $v; return $this; }
    public function getPensionPorcentaje(): float { return $this->pensionPorcentaje; }
    public function setPensionDeduccion(float $v): self { $this->pensionDeduccion = $v; return $this; }
    public function getPensionDeduccion(): float { return $this->pensionDeduccion; }

    /** Fondo de solidaridad pensional y/o de subsistencia. */
    public function setFondoSP(float $porcentaje, float $deduccion, float $porcentajeSub = 0.0, float $deduccionSub = 0.0): self
    {
        $this->fondoSP = ['Porcentaje' => $porcentaje, 'DeduccionSP' => $deduccion];
        if ($deduccionSub > 0) {
            $this->fondoSP += ['PorcentajeSub' => $porcentajeSub, 'DeduccionSub' => $deduccionSub];
        }
        return $this;
    }

    public function addSindicato(float $porcentaje, float $deduccion): self
    {
        $this->sindicatos[] = ['Porcentaje' => $porcentaje, 'Deduccion' => $deduccion];
        return $this;
    }

    public function addSancion(float $publica, float $privada = 0.0): self
    {
        $this->sanciones[] = ['SancionPublic' => $publica, 'SancionPriv' => $privada];
        return $this;
    }

    public function addLibranza(string $descripcion, float $deduccion): self
    {
        $this->libranzas[] = ['Descripcion' => $descripcion, 'Deduccion' => $deduccion];
        return $this;
    }

    public function addPagoTercero(float $v): self { $this->pagosTerceros[] = $v; return $this; }
    public function addAnticipo(float $v): self { $this->anticipos[] = $v; return $this; }
    public function addOtraDeduccion(float $v): self { $this->otrasDeducciones[] = $v; return $this; }

    /** Single-value shortcuts kept for backwards compatibility. */
    public function setPagosTerceros(float $v): self { $this->pagosTerceros = $v > 0 ? [$v] : []; return $this; }
    public function setAnticipos(float $v): self { $this->anticipos = $v > 0 ? [$v] : []; return $this; }
    public function setOtrasDeducciones(float $v): self { $this->otrasDeducciones = $v > 0 ? [$v] : []; return $this; }

    public function setPensionVoluntaria(float $v): self { $this->simple['PensionVoluntaria'] = $v; return $this; }
    public function setRetencionFuente(float $v): self { $this->simple['RetencionFuente'] = $v; return $this; }
    public function setAfc(float $v): self { $this->simple['AFC'] = $v; return $this; }
    public function setCooperativa(float $v): self { $this->simple['Cooperativa'] = $v; return $this; }
    public function setEmbargoFiscal(float $v): self { $this->simple['EmbargoFiscal'] = $v; return $this; }
    public function setPlanComplementarios(float $v): self { $this->simple['PlanComplementarios'] = $v; return $this; }
    public function setEducacion(float $v): self { $this->simple['Educacion'] = $v; return $this; }
    public function setReintegro(float $v): self { $this->simple['Reintegro'] = $v; return $this; }
    public function setDeuda(float $v): self { $this->simple['Deuda'] = $v; return $this; }

    /** Sum of every deduction (DeduccionesTotal). */
    public function total(): float
    {
        $sum = $this->saludDeduccion + $this->pensionDeduccion
            + ($this->fondoSP['DeduccionSP'] ?? 0.0) + ($this->fondoSP['DeduccionSub'] ?? 0.0)
            + array_sum($this->pagosTerceros) + array_sum($this->anticipos) + array_sum($this->otrasDeducciones)
            + array_sum($this->simple);
        foreach ($this->sindicatos as $item) {
            $sum += $item['Deduccion'];
        }
        foreach ($this->sanciones as $item) {
            $sum += $item['SancionPublic'] + $item['SancionPriv'];
        }
        foreach ($this->libranzas as $item) {
            $sum += (float) $item['Deduccion'];
        }

        return round($sum, 2);
    }

    /**
     * @return list<array{name:string,attrs?:array<string,string|float>,text?:float,children?:list<array<string,mixed>>}>
     */
    public function nodes(): array
    {
        $nodes = [
            ['name' => 'Salud', 'attrs' => ['Porcentaje' => $this->saludPorcentaje, 'Deduccion' => $this->saludDeduccion]],
            ['name' => 'FondoPension', 'attrs' => ['Porcentaje' => $this->pensionPorcentaje, 'Deduccion' => $this->pensionDeduccion]],
        ];
        if ($this->fondoSP !== []) {
            $nodes[] = ['name' => 'FondoSP', 'attrs' => $this->fondoSP];
        }
        foreach ([
            ['Sindicatos', 'Sindicato', $this->sindicatos],
            ['Sanciones', 'Sancion', $this->sanciones],
            ['Libranzas', 'Libranza', $this->libranzas],
        ] as [$group, $element, $items]) {
            if ($items !== []) {
                $nodes[] = ['name' => $group, 'children' => array_map(static fn (array $a) => ['name' => $element, 'attrs' => $a], $items)];
            }
        }
        foreach ([
            ['PagosTerceros', 'PagoTercero', $this->pagosTerceros],
            ['Anticipos', 'Anticipo', $this->anticipos],
            ['OtrasDeducciones', 'OtraDeduccion', $this->otrasDeducciones],
        ] as [$group, $element, $values]) {
            if ($values !== []) {
                $nodes[] = ['name' => $group, 'children' => array_map(static fn (float $v) => ['name' => $element, 'text' => $v], $values)];
            }
        }
        foreach ($this->simple as $name => $value) {
            if ($value > 0) {
                $nodes[] = ['name' => $name, 'text' => $value];
            }
        }

        return $nodes;
    }
}
