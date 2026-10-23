<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\SupportDocument;

use RoyaltyFusion\DianPhp\Xml\DocumentCalculator;

/**
 * CUDS — Código Único del Documento Soporte (and of its nota de ajuste).
 *
 * Anexo Técnico Documento Soporte (Resolución 000167 de 2021):
 *
 *   CUDS = SHA-384(NumDS + FecDS + HorDS + ValDS + CodImp + ValImp + ValTol
 *                  + NumSNO + NITABS + SoftwarePIN + TipoAmbiente)
 *
 *   ValDS  = LineExtensionAmount
 *   CodImp = 01 (IVA), ValImp = total IVA
 *   ValTol = PayableAmount
 *   NumSNO = documento del vendedor no obligado (AccountingSupplierParty)
 *   NITABS = NIT del adquiriente que emite el DS (AccountingCustomerParty)
 *
 * Amounts come from {@see DocumentCalculator}, the same values written in the XML.
 */
final class CudsGenerator
{
    public const ENV_PRODUCCION   = '1';
    public const ENV_HABILITACION = '2';

    public function generate(SupportDocument $doc, string $pinSoftware, string $environment = self::ENV_HABILITACION): string
    {
        return hash('sha384', $this->buildString($doc, $pinSoftware, $environment));
    }

    public function buildString(SupportDocument $doc, string $pinSoftware, string $environment = self::ENV_HABILITACION): string
    {
        $totals = DocumentCalculator::totals($doc);
        $f      = static fn (float $v): string => number_format($v, 2, '.', '');

        return $doc->getPrefijo() . $doc->getNumero()
            . $totals['issueDate']
            . $totals['issueTime']
            . $f($totals['lineExtensionAmount'])
            . '01' . $f($totals['taxTotalsByCode']['01'] ?? 0.0)
            . $f($totals['payableAmount'])
            . ($doc->getSupplier()?->getNumeroDocumento() ?? '')
            . ($doc->getCompany()?->getNit() ?? '')
            . $pinSoftware
            . $environment;
    }
}
