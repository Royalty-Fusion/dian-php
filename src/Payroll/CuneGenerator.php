<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Payroll;

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
 * Algorithmic recipe taken from lopezsoft/ubl21dian's SignPayroll::getCUNE()
 * (MIT, credited in CREDITS.md). Implementation is independent — we don't
 * import their code, just follow the same byte-for-byte order which is what
 * DIAN actually validates.
 */
final class CuneGenerator
{
    public function generate(PayrollDocument $doc, string $pinSoftware): string
    {
        $numero    = $doc->getPrefijo() . $doc->getNumero();
        $fechaGen  = $doc->getFechaGen() ? $doc->getFechaGen()->format('Y-m-d') : '';
        $horaGen   = $doc->getFechaGen() ? $doc->getFechaGen()->format('H:i:sP') : '';

        $devTotal  = self::truncate2($doc->getDevengados()->total());
        $dedTotal  = self::truncate2($doc->getDeducciones()->total());
        $compTotal = self::truncate2($doc->getComprobanteTotal());

        $nitEmpleador = $doc->getEmpleador() ? $doc->getEmpleador()->getNit() : '';
        $numTrabajador = $doc->getTrabajador() ? $doc->getTrabajador()->getNumeroDocumento() : '0';

        $base = $numero
            . $fechaGen
            . $horaGen
            . self::formatAmount($devTotal)
            . self::formatAmount($dedTotal)
            . self::formatAmount($compTotal)
            . $nitEmpleador
            . $numTrabajador
            . $doc->getTipoXML()
            . $pinSoftware
            . $doc->getAmbiente();

        return hash('sha384', $base);
    }

    private static function truncate2(float $value): float
    {
        return floor($value * 100) / 100;
    }

    private static function formatAmount(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
