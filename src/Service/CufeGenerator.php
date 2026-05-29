<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Service;

use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;

class CufeGenerator
{
    /**
     * Calculates the CUFE or CUDE string and its SHA-384 hash.
     *
     * @param Invoice|CreditNote|DebitNote $doc
     * @param string $keyOrPin (Clave técnica for Invoice, Software PIN for Notes)
     * @return string The SHA-384 hash
     */
    public function generate(Invoice|CreditNote|DebitNote $doc, string $keyOrPin): string
    {
        $numDoc = $doc->getPrefijo() . $doc->getNumero();
        $fecDoc = $doc->getFecha() ? $doc->getFecha()->format('Y-m-d') : date('Y-m-d');
        $horaDoc = $doc->getFecha() ? $doc->getFecha()->format('H:i:sP') : date('H:i:sP');
        
        $valDoc = 0.0;
        foreach ($doc->getItems() as $item) {
            $valDoc += ($item->getCantidad() * $item->getPrecio());
        }
        $valDocStr = number_format($valDoc, 2, '.', '');

        // Taxes extraction
        $codImp1 = '01'; // IVA
        $valImp1 = 0.0;
        $codImp2 = '04'; // INC
        $valImp2 = 0.0;
        $codImp3 = '03'; // ICA
        $valImp3 = 0.0;

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
        $nitOfe = $doc->getCompany() ? $doc->getCompany()->getNit() : '';
        $numAdq = $doc->getClient() ? $doc->getClient()->getNumeroDocumento() : '';

        if ($doc instanceof Invoice) {
            // CUFE: NumFac + FecFac + HoraFac + ValFac + CodImp1 + ValImp1 + CodImp2 + ValImp2 + CodImp3 + ValImp3 + ValTot + NitOfe + NumAdq + ClaveTecnica
            $baseString = $numDoc . $fecDoc . $horaDoc . $valDocStr 
                        . $codImp1 . $valImp1Str 
                        . $codImp2 . $valImp2Str 
                        . $codImp3 . $valImp3Str 
                        . $valTotStr . $nitOfe . $numAdq . $keyOrPin;
        } else {
            // CUDE: NumDoc + FecDoc + HoraDoc + ValDoc + CodImp1 + ValImp1 + CodImp2 + ValImp2 + CodImp3 + ValImp3 + ValTot + NitOfe + NumAdq + TipoDoc + PinSoftware
            $tipoDoc = ($doc instanceof CreditNote) ? '91' : '92';
            $baseString = $numDoc . $fecDoc . $horaDoc . $valDocStr 
                        . $codImp1 . $valImp1Str 
                        . $codImp2 . $valImp2Str 
                        . $codImp3 . $valImp3Str 
                        . $valTotStr . $nitOfe . $numAdq . $tipoDoc . $keyOrPin;
        }

        return hash('sha384', $baseString);
    }
}
