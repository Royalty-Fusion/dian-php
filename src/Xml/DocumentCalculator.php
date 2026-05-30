<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Xml;

use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;
use RoyaltyFusion\DianPhp\Model\Invoice;

/**
 * Pure-function totals calculator used by the XmlBuilder and by validators.
 *
 * Returns the four monetary totals required by DIAN's LegalMonetaryTotal,
 * plus the consolidated tax groups (split between retentions and non-retentions
 * since DIAN expects them in two separate <cac:TaxTotal>/<cac:WithholdingTaxTotal>
 * blocks).
 *
 * All numbers are kept as floats here; the templates apply the canonical
 * `number_format(_, 2, '.', '')` only on rendering.
 */
final class DocumentCalculator
{
    /**
     * @return array{
     *     lineExtensionAmount: float,
     *     taxExclusiveAmount: float,
     *     allowanceTotalAmount: float,
     *     chargeTotalAmount: float,
     *     prepaidAmount: float,
     *     taxInclusiveAmount: float,
     *     payableAmount: float,
     *     taxGroups: array<string,array{amount:float,subtotals:list<array{base:float,amount:float,percent:float,code:string,name:string}>}>,
     *     withholdingTaxGroups: array<string,array{amount:float,subtotals:list<array{base:float,amount:float,percent:float,code:string,name:string}>}>
     * }
     */
    public static function totals(Invoice|CreditNote|DebitNote $doc): array
    {
        $lineExtensionAmount = 0.0;
        foreach ($doc->getItems() as $item) {
            $itemBase = $item->getCantidad() * $item->getPrecio();
            // Subtract item-level discounts, add item-level charges
            foreach ($item->getAllowanceCharges() as $ac) {
                $itemBase += $ac->isCharge() ? $ac->getAmount() : -$ac->getAmount();
            }
            $lineExtensionAmount += $itemBase;
        }

        $allowanceTotalAmount = 0.0;
        $chargeTotalAmount    = 0.0;
        foreach ($doc->getAllowanceCharges() as $ac) {
            if ($ac->isCharge()) {
                $chargeTotalAmount += $ac->getAmount();
            } else {
                $allowanceTotalAmount += $ac->getAmount();
            }
        }

        $taxExclusiveAmount = $lineExtensionAmount - $allowanceTotalAmount + $chargeTotalAmount;

        $taxGroups            = [];
        $withholdingTaxGroups = [];
        $totalNonRetention    = 0.0;

        foreach ($doc->getTaxes() as $tax) {
            $row = [
                'base'    => $tax->getBase(),
                'amount'  => $tax->getAmount(),
                'percent' => $tax->getPercent(),
                'code'    => $tax->getCode(),
                'name'    => $tax->getName(),
            ];

            if ($tax->isRetention()) {
                $bucket = &$withholdingTaxGroups;
            } else {
                $bucket = &$taxGroups;
                $totalNonRetention += $tax->getAmount();
            }

            if (!isset($bucket[$tax->getCode()])) {
                $bucket[$tax->getCode()] = ['amount' => 0.0, 'subtotals' => []];
            }
            $bucket[$tax->getCode()]['amount']     += $tax->getAmount();
            $bucket[$tax->getCode()]['subtotals'][] = $row;
            unset($bucket);
        }

        $prepaidAmount = 0.0;
        foreach ($doc->getPrepayments() as $prepayment) {
            $prepaidAmount += $prepayment->getPaidAmount();
        }

        $taxInclusiveAmount = $taxExclusiveAmount + $totalNonRetention;
        $payableAmount      = $taxInclusiveAmount - $prepaidAmount;

        return [
            'lineExtensionAmount'  => $lineExtensionAmount,
            'taxExclusiveAmount'   => $taxExclusiveAmount,
            'allowanceTotalAmount' => $allowanceTotalAmount,
            'chargeTotalAmount'    => $chargeTotalAmount,
            'prepaidAmount'        => $prepaidAmount,
            'taxInclusiveAmount'   => $taxInclusiveAmount,
            'payableAmount'        => $payableAmount,
            'taxGroups'            => $taxGroups,
            'withholdingTaxGroups' => $withholdingTaxGroups,
        ];
    }
}
