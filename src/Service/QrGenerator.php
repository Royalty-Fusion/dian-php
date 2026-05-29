<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Service;

use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;

class QrGenerator
{
    /**
     * Generates the QR Code URL string for DIAN documents
     *
     * @param Invoice|CreditNote|DebitNote $doc
     * @param string $uuid (CUFE or CUDE)
     * @return string
     */
    public function generate(Invoice|CreditNote|DebitNote $doc, string $uuid): string
    {
        // According to DIAN Technical Annex V1.9, the text of the QRCode
        // is typically the URL containing the CUFE/CUDE as documentkey.
        // https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey={CUFE/CUDE}
        
        $baseUrl = "https://catalogo-vpfe.dian.gov.co/document/searchqr";
        return $baseUrl . '?documentkey=' . $uuid;
    }
}
