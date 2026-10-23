<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Xml;

use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;
use RoyaltyFusion\DianPhp\Model\Invoice;

/**
 * CUFE (Factura) and CUDE (Nota Crédito / Nota Débito) — Anexo Técnico V1.9.
 *
 * CUFE = SHA-384(NumFac + FecFac + HorFac + ValFac + 01 + ValImp1 + 04 + ValImp2
 *                + 03 + ValImp3 + ValTot + NitOFE + NumAdq + ClTec + TipoAmb)
 * CUDE = SHA-384(NumDoc + FecDoc + HorDoc + ValDoc + 01 + ValImp1 + 04 + ValImp2
 *                + 03 + ValImp3 + ValTot + NitOFE + NumAdq + PinSoftware + TipoAmb)
 *
 * ValFac = LineExtensionAmount, ValImpN = TaxTotal of IVA/INC/ICA,
 * ValTot = PayableAmount — taken from {@see DocumentCalculator}, the same
 * numbers rendered in the XML, because DIAN recomputes the hash from the XML.
 *
 * Verified against the worked example of the Anexo Técnico (see CufeGeneratorTest).
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
        return hash('sha384', $this->buildString($doc, $keyOrPin, $environment));
    }

    /**
     * The exact pre-image of the hash. Exposed so integrators can compare it
     * with the "cadena" DIAN shows when it rejects a document for CUFE mismatch.
     */
    public function buildString(
        Invoice|CreditNote|DebitNote $doc,
        string $keyOrPin,
        string $environment = self::ENV_HABILITACION
    ): string {
        $totals = DocumentCalculator::totals($doc);

        return self::compose(
            $doc->getPrefijo() . $doc->getNumero(),
            $totals['issueDate'],
            $totals['issueTime'],
            $totals['lineExtensionAmount'],
            $totals['taxTotalsByCode']['01'] ?? 0.0,
            $totals['taxTotalsByCode']['04'] ?? 0.0,
            $totals['taxTotalsByCode']['03'] ?? 0.0,
            $totals['payableAmount'],
            $doc->getCompany()?->getNit() ?? '',
            $doc->getClient()?->getNumeroDocumento() ?? '',
            $keyOrPin,
            $environment
        );
    }

    /**
     * Raw formula, shared with the pre-flight checker that recomputes the
     * CUFE from an already rendered XML.
     */
    public static function compose(
        string $number,
        string $issueDate,
        string $issueTime,
        float $lineExtensionAmount,
        float $iva,
        float $inc,
        float $ica,
        float $payableAmount,
        string $supplierNit,
        string $customerId,
        string $keyOrPin,
        string $environment
    ): string {
        $f = static fn (float $v): string => number_format(round($v, 2), 2, '.', '');

        return $number . $issueDate . $issueTime . $f($lineExtensionAmount)
            . '01' . $f($iva)
            . '04' . $f($inc)
            . '03' . $f($ica)
            . $f($payableAmount)
            . preg_replace('/\D/', '', $supplierNit)
            . $customerId
            . $keyOrPin
            . $environment;
    }
}
