<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Xml;

use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;

/**
 * Builds the textual content of the QR code embedded in every DIAN
 * electronic document.
 *
 * According to Anexo Técnico V1.9 the QR string is the consultation URL
 * of the catalog VPFE with the CUFE/CUDE appended as documentkey.
 */
class QrGenerator
{
    public const BASE_URL_PROD = 'https://catalogo-vpfe.dian.gov.co/document/searchqr';
    public const BASE_URL_HAB  = 'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr';

    public function generate(
        Invoice|CreditNote|DebitNote $doc,
        string $uuid,
        bool $production = false
    ): string {
        unset($doc); // reserved for future enrichments (verifiable QR per Anexo 1.9)
        $base = $production ? self::BASE_URL_PROD : self::BASE_URL_HAB;
        return $base . '?documentkey=' . $uuid;
    }
}
