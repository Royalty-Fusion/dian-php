<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Xml;

use RoyaltyFusion\DianPhp\Catalog\Tributo;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Tax;
use RoyaltyFusion\DianPhp\SupportDocument\SupportDocument;

/**
 * Single source of truth for every amount written in the XML, the CUFE/CUDE
 * and the QR. DIAN recomputes the CUFE from the XML values, so all three must
 * come from here.
 *
 * Formulas (Anexo Técnico V1.9, cac:LegalMonetaryTotal):
 *   line LineExtensionAmount = cantidad × precio − descuentos de línea + cargos de línea
 *   LineExtensionAmount      = Σ line LineExtensionAmount
 *   TaxExclusiveAmount       = Σ base gravable de las líneas con tributos (no retenciones)
 *   TaxInclusiveAmount       = LineExtensionAmount + Σ tributos (sin retenciones)
 *   PayableAmount            = TaxInclusiveAmount − AllowanceTotal + ChargeTotal − PrepaidAmount
 *
 * Retentions (cac:WithholdingTaxTotal) are informative and never change the
 * payable amount. Every value is rounded half-up to 2 decimals.
 *
 * Document taxes: when the document has explicit taxes (addTax) they are used
 * as given; otherwise they are aggregated from the item taxes by code+percent.
 */
final class DocumentCalculator
{
    /**
     * @return array{
     *     issueDate: string,
     *     issueTime: string,
     *     lines: list<array{lineExtensionAmount: float, taxes: list<array{base:float,amount:float,percent:float,code:string,name:string}>, withholdings: list<array{base:float,amount:float,percent:float,code:string,name:string}>}>,
     *     lineExtensionAmount: float,
     *     taxExclusiveAmount: float,
     *     allowanceTotalAmount: float,
     *     chargeTotalAmount: float,
     *     prepaidAmount: float,
     *     taxInclusiveAmount: float,
     *     payableAmount: float,
     *     taxTotalsByCode: array<string,float>,
     *     taxGroups: array<string,array{amount:float,subtotals:list<array{base:float,amount:float,percent:float,code:string,name:string}>}>,
     *     withholdingTaxGroups: array<string,array{amount:float,subtotals:list<array{base:float,amount:float,percent:float,code:string,name:string}>}>
     * }
     */
    public static function totals(Invoice|CreditNote|DebitNote|SupportDocument $doc): array
    {
        $lines               = [];
        $lineExtensionAmount = 0.0;
        $taxableFromLines    = 0.0;
        $itemTaxRows         = [];

        foreach ($doc->getItems() as $item) {
            $lineAmount = self::lineExtensionAmount($item);
            $lineExtensionAmount += $lineAmount;

            $taxes = [];
            $withholdings = [];
            $lineTaxable  = 0.0;
            foreach ($item->getTaxes() as $tax) {
                $row = self::taxRow($tax, $lineAmount);
                if ($tax->isRetention()) {
                    $withholdings[] = $row;
                } else {
                    $taxes[]     = $row;
                    $lineTaxable = max($lineTaxable, $row['base']);
                }
                $itemTaxRows[] = [$row, $tax->isRetention()];
            }
            $taxableFromLines += $lineTaxable;

            $lines[] = [
                'lineExtensionAmount' => $lineAmount,
                'taxes'               => $taxes,
                'withholdings'        => $withholdings,
            ];
        }
        $lineExtensionAmount = self::round($lineExtensionAmount);

        // Document level taxes: explicit ones win, otherwise aggregate the lines.
        $docRows = [];
        if ($doc->getTaxes() !== []) {
            foreach ($doc->getTaxes() as $tax) {
                $docRows[] = [self::taxRow($tax, 0.0), $tax->isRetention()];
            }
        } else {
            $merged = [];
            foreach ($itemTaxRows as [$row, $isRetention]) {
                $key = ($isRetention ? 'R' : 'T') . $row['code'] . '|' . number_format($row['percent'], 4, '.', '');
                if (!isset($merged[$key])) {
                    $merged[$key] = [$row, $isRetention];
                    continue;
                }
                $merged[$key][0]['base']   = self::round($merged[$key][0]['base'] + $row['base']);
                $merged[$key][0]['amount'] = self::round($merged[$key][0]['amount'] + $row['amount']);
            }
            $docRows = array_values($merged);
        }

        $taxGroups            = [];
        $withholdingTaxGroups = [];
        $taxTotalsByCode      = [];
        $totalTaxes           = 0.0;
        $taxableFromDoc       = 0.0;

        foreach ($docRows as [$row, $isRetention]) {
            $code = $row['code'];
            if ($isRetention) {
                $withholdingTaxGroups[$code] ??= ['amount' => 0.0, 'subtotals' => []];
                $withholdingTaxGroups[$code]['amount']      = self::round($withholdingTaxGroups[$code]['amount'] + $row['amount']);
                $withholdingTaxGroups[$code]['subtotals'][] = $row;
                continue;
            }
            $totalTaxes     += $row['amount'];
            $taxableFromDoc += $row['base'];
            $taxTotalsByCode[$code] = self::round(($taxTotalsByCode[$code] ?? 0.0) + $row['amount']);
            $taxGroups[$code] ??= ['amount' => 0.0, 'subtotals' => []];
            $taxGroups[$code]['amount']      = self::round($taxGroups[$code]['amount'] + $row['amount']);
            $taxGroups[$code]['subtotals'][] = $row;
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

        $prepaidAmount = 0.0;
        foreach ($doc->getPrepayments() as $prepayment) {
            $prepaidAmount += $prepayment->getPaidAmount();
        }

        $hasItemTaxes       = $itemTaxRows !== [];
        $taxExclusiveAmount = self::round($hasItemTaxes ? $taxableFromLines : $taxableFromDoc);
        $taxInclusiveAmount = self::round($lineExtensionAmount + $totalTaxes);
        $payableAmount      = self::round(
            $taxInclusiveAmount - $allowanceTotalAmount + $chargeTotalAmount - $prepaidAmount
        );

        [$issueDate, $issueTime] = self::issueDateTime($doc->getFecha());

        return [
            'issueDate'            => $issueDate,
            'issueTime'            => $issueTime,
            'lines'                => $lines,
            'lineExtensionAmount'  => $lineExtensionAmount,
            'taxExclusiveAmount'   => $taxExclusiveAmount,
            'allowanceTotalAmount' => self::round($allowanceTotalAmount),
            'chargeTotalAmount'    => self::round($chargeTotalAmount),
            'prepaidAmount'        => self::round($prepaidAmount),
            'taxInclusiveAmount'   => $taxInclusiveAmount,
            'payableAmount'        => $payableAmount,
            'taxTotalsByCode'      => $taxTotalsByCode,
            'taxGroups'            => $taxGroups,
            'withholdingTaxGroups' => $withholdingTaxGroups,
        ];
    }

    public static function lineExtensionAmount(Item $item): float
    {
        $amount = $item->getCantidad() * $item->getPrecio();
        foreach ($item->getAllowanceCharges() as $ac) {
            $amount += $ac->isCharge() ? $ac->getAmount() : -$ac->getAmount();
        }

        return self::round($amount);
    }

    /**
     * Normalises the issue date to Colombian time (UTC-5), as DIAN expects
     * IssueTime with the -05:00 offset.
     *
     * @return array{string,string}
     */
    public static function issueDateTime(?\DateTimeInterface $date): array
    {
        $date = $date === null
            ? new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota'))
            : \DateTimeImmutable::createFromInterface($date)->setTimezone(new \DateTimeZone('America/Bogota'));

        return [$date->format('Y-m-d'), $date->format('H:i:sP')];
    }

    public static function round(float $value): float
    {
        return round($value, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * @return array{base:float,amount:float,percent:float,code:string,name:string}
     */
    private static function taxRow(Tax $tax, float $defaultBase): array
    {
        $base   = $tax->getBase() > 0 ? $tax->getBase() : $defaultBase;
        $amount = $tax->getAmount();
        if ($amount == 0.0 && $tax->getPercent() > 0 && $base > 0) {
            $amount = $base * $tax->getPercent() / 100;
        }

        return [
            'base'    => self::round($base),
            'amount'  => self::round($amount),
            'percent' => $tax->getPercent(),
            'code'    => $tax->getCode(),
            'name'    => $tax->getName() !== '' ? $tax->getName() : self::taxName($tax->getCode()),
        ];
    }

    private static function taxName(string $code): string
    {
        return Tributo::tryFrom($code)?->schemeName() ?? $code;
    }
}
