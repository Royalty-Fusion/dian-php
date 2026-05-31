<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\SupportDocument;

/**
 * Generator for the CUDS hash (Código Único Documento Soporte).
 *
 * Anexo Técnico Documento Soporte v1.2 §10:
 *
 *   CUDS = SHA-384(
 *     NumDS + FecDS + HoraDS + ValDS +
 *     CodImp1 + ValImp1 +
 *     CodImp2 + ValImp2 +
 *     CodImp3 + ValImp3 +
 *     ValTot +
 *     NitOFE + NumAdq + TipoAmb + PinSoftware
 *   )
 *
 * Note: the acquirente (your company) is the *issuer* of the DS.
 * "OFE" here means the obligado a facturar emitiendo el DS — i.e. you.
 * "Adq" is the seller (un no obligado a facturar).
 */
final class CudsGenerator
{
    public const ENV_PRODUCCION   = '1';
    public const ENV_HABILITACION = '2';

    public function generate(SupportDocument $doc, string $pinSoftware, string $environment = self::ENV_HABILITACION): string
    {
        $numDoc  = $doc->getPrefijo() . $doc->getNumero();
        $fecDoc  = $doc->getFecha() ? $doc->getFecha()->format('Y-m-d') : date('Y-m-d');
        $horaDoc = $doc->getFecha() ? $doc->getFecha()->format('H:i:sP') : date('H:i:sP');

        $valDoc = 0.0;
        foreach ($doc->getItems() as $item) {
            $valDoc += ($item->getCantidad() * $item->getPrecio());
        }
        $valDocStr = number_format($valDoc, 2, '.', '');

        // Same three tax slots as CUFE/CUDE — IVA, INC, ICA
        $valImp1 = 0.0; // 01 = IVA
        $valImp2 = 0.0; // 04 = INC
        $valImp3 = 0.0; // 03 = ICA
        foreach ($doc->getTaxes() as $tax) {
            if ($tax->getCode() === '01') {
                $valImp1 += $tax->getAmount();
            } elseif ($tax->getCode() === '04') {
                $valImp2 += $tax->getAmount();
            } elseif ($tax->getCode() === '03') {
                $valImp3 += $tax->getAmount();
            }
        }

        $valTotStr = number_format($doc->getTotal(), 2, '.', '');
        $nitOfe    = $doc->getCompany()  ? $doc->getCompany()->getNit()           : '';
        $numAdq    = $doc->getSupplier() ? $doc->getSupplier()->getNumeroDocumento() : '';

        $base = $numDoc . $fecDoc . $horaDoc . $valDocStr
            . '01' . number_format($valImp1, 2, '.', '')
            . '04' . number_format($valImp2, 2, '.', '')
            . '03' . number_format($valImp3, 2, '.', '')
            . $valTotStr . $nitOfe . $numAdq . $environment . $pinSoftware;

        return hash('sha384', $base);
    }
}
