<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Xml;

use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;
use RoyaltyFusion\DianPhp\Model\Invoice;

/**
 * Content of sts:QRCode and of the QR printed on the representación gráfica.
 *
 * Anexo Técnico V1.9 (and every DIAN-approved document in resources/fixtures)
 * uses a multi-line text with the key fields followed by the VPFE search URL:
 *
 *   NumFac:SETP990000001
 *   FecFac:2026-01-15
 *   HorFac:10:14:00-05:00
 *   NitFac:900123456
 *   DocAdq:1010101010
 *   ValFac:100000.00
 *   ValIva:19000.00
 *   ValOtroIm:0.00
 *   ValTolFac:119000.00
 *   CUFE:<hash>
 *   https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey=<hash>
 */
class QrGenerator
{
    public const BASE_URL_PROD = 'https://catalogo-vpfe.dian.gov.co/document/searchqr';
    public const BASE_URL_HAB  = 'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr';

    /** Consultation URL only. */
    public function generate(
        Invoice|CreditNote|DebitNote $doc,
        string $uuid,
        bool $production = false
    ): string {
        return self::url($uuid, $production);
    }

    /** Full sts:QRCode text (fields + URL). */
    public function content(
        Invoice|CreditNote|DebitNote $doc,
        string $uuid,
        bool $production = false
    ): string {
        $totals = DocumentCalculator::totals($doc);
        $f      = static fn (float $v): string => number_format($v, 2, '.', '');
        $iva    = $totals['taxTotalsByCode']['01'] ?? 0.0;
        $others = array_sum($totals['taxTotalsByCode']) - $iva;
        $label  = $doc instanceof Invoice ? 'CUFE' : 'CUDE';

        return implode("\n", [
            'NumFac:' . $doc->getPrefijo() . $doc->getNumero(),
            'FecFac:' . $totals['issueDate'],
            'HorFac:' . $totals['issueTime'],
            'NitFac:' . ($doc->getCompany()?->getNit() ?? ''),
            'DocAdq:' . ($doc->getClient()?->getNumeroDocumento() ?? ''),
            'ValFac:' . $f($totals['lineExtensionAmount']),
            'ValIva:' . $f($iva),
            'ValOtroIm:' . $f($others),
            'ValTolFac:' . $f($totals['payableAmount']),
            $label . ':' . $uuid,
            self::url($uuid, $production),
        ]);
    }

    public static function url(string $uuid, bool $production = false): string
    {
        return ($production ? self::BASE_URL_PROD : self::BASE_URL_HAB) . '?documentkey=' . $uuid;
    }
}
