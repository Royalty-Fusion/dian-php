<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Payroll;

class Periodo
{
    private \DateTimeInterface $fechaIngreso;
    private ?\DateTimeInterface $fechaRetiro = null;
    private \DateTimeInterface $fechaLiquidacionInicio;
    private \DateTimeInterface $fechaLiquidacionFin;
    private int $tiempoLaborado = 30;

    public function setFechaIngreso(\DateTimeInterface $v): self { $this->fechaIngreso = $v; return $this; }
    public function getFechaIngreso(): ?\DateTimeInterface { return $this->fechaIngreso ?? null; }
    public function setFechaRetiro(\DateTimeInterface $v): self { $this->fechaRetiro = $v; return $this; }
    public function getFechaRetiro(): ?\DateTimeInterface { return $this->fechaRetiro; }
    public function setFechaLiquidacionInicio(\DateTimeInterface $v): self { $this->fechaLiquidacionInicio = $v; return $this; }
    public function getFechaLiquidacionInicio(): ?\DateTimeInterface { return $this->fechaLiquidacionInicio ?? null; }
    public function setFechaLiquidacionFin(\DateTimeInterface $v): self { $this->fechaLiquidacionFin = $v; return $this; }
    public function getFechaLiquidacionFin(): ?\DateTimeInterface { return $this->fechaLiquidacionFin ?? null; }
    public function setTiempoLaborado(int $v): self { $this->tiempoLaborado = $v; return $this; }
    public function getTiempoLaborado(): int { return $this->tiempoLaborado; }
}
