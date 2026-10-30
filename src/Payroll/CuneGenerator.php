<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Payroll;

use RoyaltyFusion\DianPhp\Xml\DocumentCalculator;

/**
 * Generator for the CUNE (Código Único de Nómina Electrónica).
 *
 * Anexo Técnico Nómina v2.0:
 *
 *   CUNE = SHA-384(
 *     NumeroSecuenciaXML.Numero +
 *     FechaGen + HoraGen +
 *     DevengadosTotal (truncated to 2 decimals) +
 *     DeduccionesTotal (truncated to 2 decimals) +
 *     ComprobanteTotal (truncated to 2 decimals) +
 *     Empleador.NIT +
 *     Trabajador.NumeroDocumento +
 *     TipoXML +
 *     PinSoftware +
 *     Ambiente
 *   )
 *
 * FechaGen/HoraGen are expressed in Colombian time (-05:00). For a nota de
 * ajuste de eliminación the totals are 0.00 and the worker document is "0".
 */
final class CuneGenerator
{
    public function generate(PayrollDocument $doc, string $pinSoftware): string
    {
        return hash('sha384', $this->buildString($doc, $pinSoftware));
    }

    /** Pre-image of the hash, useful to compare with what DIAN reports. */
    public function buildString(PayrollDocument $doc, string $pinSoftware): string
    {
        [$fecha, $hora] = DocumentCalculator::issueDateTime($doc->getFechaGen());
        $eliminacion    = $doc instanceof PayrollAdjustment && $doc->isEliminacion();

        $f = static fn (float $v): string => number_format(floor(round($v * 100, 4)) / 100, 2, '.', '');

        return $doc->getPrefijo() . $doc->getNumero()
            . $fecha
            . $hora
            . $f($eliminacion ? 0.0 : $doc->getDevengados()->total())
            . $f($eliminacion ? 0.0 : $doc->getDeducciones()->total())
            . $f($eliminacion ? 0.0 : $doc->getComprobanteTotal())
            . ($doc->getEmpleador()?->getNit() ?? '')
            . ($eliminacion ? '0' : ($doc->getTrabajador()?->getNumeroDocumento() ?: '0'))
            . $doc->getTipoXML()
            . $pinSoftware
            . $doc->getAmbiente();
    }
}
