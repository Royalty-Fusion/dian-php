<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Xml;

use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;

/**
 * Generates the CUFE (Factura) or CUDE (Nota Crédito/Débito) hash
 * as defined in Anexo Técnico DIAN V1.9 §11.3.
 *
 * CUFE = SHA-384(NumFac + FecFac + HoraFac + ValFac + CodImp1 + ValImp1 +
 *                CodImp2 + ValImp2 + CodImp3 + ValImp3 + ValTot + NitOFE +
 *                NumAdq + ClaveTecnica + TipoAmbiente)
 *
 * CUDE = SHA-384(NumDoc + FecDoc + HoraDoc + ValDoc + CodImp1 + ValImp1 +
 *                CodImp2 + ValImp2 + CodImp3 + ValImp3 + ValTot + NitOFE +
 *                NumAdq + TipoDoc + PinSoftware + TipoAmbiente)
 *
 * Note: this implementation will be hardened in Phase 1 (audit) to ensure
 * the exact byte-level concatenation order and signed-time format match
 * the latest DIAN spec.
 */
class CufeGenerator
{
    public const ENV_PRODUCCION   = '1';
    public const ENV_HABILITACION = '2';

    public function generate(
        Invoice|CreditNote|DebitNote $doc,
        string $keyOrPin,
        string $environment = self::ENV_HABILITACION
    ): string {
        $numDoc  = $doc->getPrefijo() . $doc->getNumero();
        $fecDoc  = $doc->getFecha() ? $doc->getFecha()->format('Y-m-d') : date('Y-m-d');
        $horaDoc = $doc->getFecha() ? $doc->getFecha()->format('H:i:sP') : date('H:i:sP');

        $valDoc = 0.0;
        foreach ($doc->getItems() as $item) {
            $valDoc += ($item->getCantidad() * $item->getPrecio());
        }
        $valDocStr = number_format($valDoc, 2, '.', '');

        $valImp1 = 0.0; // IVA  (01)
        $valImp2 = 0.0; // INC  (04)
        $valImp3 = 0.0; // ICA  (03)

        foreach ($doc->getTaxes() as $tax) {
            if ($tax->getCode() === '01') {
                $valImp1 += $tax->getAmount();
            } elseif ($tax->getCode() === '04') {
                $valImp2 += $tax->getAmount();
            } elseif ($tax->getCode() === '03') {
                $valImp3 += $tax->getAmount();
            }
        }

        $valImp1Str = number_format($valImp1, 2, '.', '');
        $valImp2Str = number_format($valImp2, 2, '.', '');
        $valImp3Str = number_format($valImp3, 2, '.', '');

        $valTotStr = number_format($doc->getTotal(), 2, '.', '');
        $nitOfe    = $doc->getCompany() ? $doc->getCompany()->getNit() : '';
        $numAdq    = $doc->getClient() ? $doc->getClient()->getNumeroDocumento() : '';

        if ($doc instanceof Invoice) {
            $baseString = $numDoc . $fecDoc . $horaDoc . $valDocStr
                . '01' . $valImp1Str
                . '04' . $valImp2Str
                . '03' . $valImp3Str
                . $valTotStr . $nitOfe . $numAdq . $keyOrPin . $environment;
        } else {
            $tipoDoc = ($doc instanceof CreditNote) ? '91' : '92';
            $baseString = $numDoc . $fecDoc . $horaDoc . $valDocStr
                . '01' . $valImp1Str
                . '04' . $valImp2Str
                . '03' . $valImp3Str
                . $valTotStr . $nitOfe . $numAdq . $tipoDoc . $keyOrPin . $environment;
        }

        return hash('sha384', $baseString);
    }
}
