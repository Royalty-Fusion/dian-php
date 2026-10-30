<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Payroll;

/**
 * Nota de ajuste del documento soporte de pago de nómina electrónica
 * (NominaIndividualDeAjuste, TipoXML 103).
 *
 *   TipoNota 1 — Reemplazar: corrige una nómina ya validada; lleva la nómina
 *                completa corregida (periodo, trabajador, devengados, deducciones).
 *   TipoNota 2 — Eliminar: anula una nómina; solo lleva numeración, empleador
 *                y la referencia a la nómina eliminada.
 *
 * The predecessor (número, CUNE y fecha de generación) is mandatory in both.
 */
class PayrollAdjustment extends PayrollDocument
{
    public const REEMPLAZAR = '1';
    public const ELIMINAR   = '2';

    private string $numeroPred = '';
    private string $cunePred = '';
    private ?\DateTimeInterface $fechaGenPred = null;

    public function __construct(string $tipoNota = self::REEMPLAZAR)
    {
        parent::__construct();
        $this->setTipoXML('103');
        $this->setTipoNota($tipoNota);
    }

    public static function reemplazar(string $numeroPred, string $cunePred, \DateTimeInterface $fechaGenPred): self
    {
        return (new self(self::REEMPLAZAR))->setPredecesor($numeroPred, $cunePred, $fechaGenPred);
    }

    public static function eliminar(string $numeroPred, string $cunePred, \DateTimeInterface $fechaGenPred): self
    {
        return (new self(self::ELIMINAR))->setPredecesor($numeroPred, $cunePred, $fechaGenPred);
    }

    public function setPredecesor(string $numero, string $cune, \DateTimeInterface $fechaGen): self
    {
        $this->numeroPred   = $numero;
        $this->cunePred     = $cune;
        $this->fechaGenPred = $fechaGen;
        return $this;
    }

    public function getNumeroPred(): string { return $this->numeroPred; }
    public function getCunePred(): string { return $this->cunePred; }
    public function getFechaGenPred(): ?\DateTimeInterface { return $this->fechaGenPred; }

    public function isEliminacion(): bool
    {
        return $this->getTipoNota() === self::ELIMINAR;
    }
}
