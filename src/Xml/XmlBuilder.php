<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Xml;

use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Renderer of DIAN UBL 2.1 XML documents from Twig templates.
 *
 * Greenter-style: this class only orchestrates the template render — totals,
 * tax consolidation and other business calculations live in dedicated helpers
 * that will be introduced in later phases (Calculator, TaxAggregator, etc.).
 */
class XmlBuilder
{
    private Environment $twig;

    public function __construct(?Environment $twig = null)
    {
        if ($twig instanceof Environment) {
            $this->twig = $twig;
            return;
        }

        $loader = new FilesystemLoader(__DIR__ . '/templates');
        $this->twig = new Environment($loader, [
            'autoescape' => false,
            'strict_variables' => false,
        ]);
    }

    /**
     * Builds the complete UBL 2.1 XML for DIAN (Anexo V1.9) using Twig templates.
     *
     * @param Invoice|CreditNote|DebitNote $document
     * @param string                       $uuid       CUFE or CUDE already calculated
     * @param string                       $qrCodeUrl  Pre-rendered QR URL
     * @return string Unsigned XML ready for the signer
     */
    public function build(Invoice|CreditNote|DebitNote $document, string $uuid, string $qrCodeUrl): string
    {
        // 1. Consolidate taxes by code (e.g. 01 = IVA, 03 = ICA, 04 = INC)
        $taxGroups = [];
        foreach ($document->getTaxes() as $tax) {
            $code = $tax->getCode();
            if (!isset($taxGroups[$code])) {
                $taxGroups[$code] = [
                    'amount'    => 0.0,
                    'subtotals' => [],
                ];
            }
            $taxGroups[$code]['amount'] += $tax->getAmount();
            $taxGroups[$code]['subtotals'][] = [
                'base'    => $tax->getBase(),
                'amount'  => $tax->getAmount(),
                'percent' => $tax->getPercent(),
                'code'    => $tax->getCode(),
                'name'    => $tax->getName(),
            ];
        }

        // 2. Totals (will be enriched with Allowance/Charge/Prepaid in Phase 3-4)
        $lineExtensionAmount = 0.0;
        foreach ($document->getItems() as $item) {
            $lineExtensionAmount += ($item->getCantidad() * $item->getPrecio());
        }

        $taxExclusiveAmount = $lineExtensionAmount;
        $totalTaxAmount     = 0.0;
        foreach ($document->getTaxes() as $tax) {
            if (!$tax->isRetention()) {
                $totalTaxAmount += $tax->getAmount();
            }
        }

        $taxInclusiveAmount = $taxExclusiveAmount + $totalTaxAmount;
        $payableAmount      = $taxInclusiveAmount;

        $totals = [
            'lineExtensionAmount' => $lineExtensionAmount,
            'taxExclusiveAmount'  => $taxExclusiveAmount,
            'taxInclusiveAmount'  => $taxInclusiveAmount,
            'payableAmount'       => $payableAmount,
        ];

        // 3. Select template
        if ($document instanceof Invoice) {
            $template = 'invoice.xml.twig';
            $idKey    = 'cufe';
        } elseif ($document instanceof CreditNote) {
            $template = 'creditnote.xml.twig';
            $idKey    = 'cude';
        } else {
            $template = 'debitnote.xml.twig';
            $idKey    = 'cude';
        }

        return $this->twig->render($template, [
            'doc'           => $document,
            $idKey          => $uuid,
            'qrCodeUrl'     => $qrCodeUrl,
            'taxGroupsData' => $taxGroups,
            'totals'        => $totals,
        ]);
    }
}
