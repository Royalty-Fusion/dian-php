<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Service;

use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class XmlBuilder
{
    private Environment $twig;

    public function __construct()
    {
        // Setup Twig environment pointing to the templates directory
        $loader = new FilesystemLoader(__DIR__ . '/../Xml/templates');
        $this->twig = new Environment($loader, [
            'autoescape' => false, // Output raw XML without escaping HTML characters
        ]);
    }

    /**
     * Builds the complete UBL 2.1 XML for DIAN (Anexo V1.9) using Twig templates
     * 
     * @param Invoice|CreditNote|DebitNote $document
     * @param string $uuid (CUFE or CUDE)
     * @param string $qrCodeUrl
     * @return string
     */
    public function build(Invoice|CreditNote|DebitNote $document, string $uuid, string $qrCodeUrl): string
    {
        // 1. Group taxes for consolidation
        $taxGroups = [];
        foreach ($document->getTaxes() as $tax) {
            $code = $tax->getCode();
            if (!isset($taxGroups[$code])) {
                $taxGroups[$code] = [
                    'amount' => 0.0,
                    'subtotals' => []
                ];
            }
            $taxGroups[$code]['amount'] += $tax->getAmount();
            $taxGroups[$code]['subtotals'][] = [
                'base' => $tax->getBase(),
                'amount' => $tax->getAmount(),
                'percent' => $tax->getPercent(),
                'code' => $tax->getCode(),
                'name' => $tax->getName(),
            ];
        }

        // 2. Calculate totals
        $lineExtensionAmount = 0.0;
        foreach ($document->getItems() as $item) {
            $lineExtensionAmount += ($item->getCantidad() * $item->getPrecio());
        }

        $taxExclusiveAmount = $lineExtensionAmount;
        $totalTaxAmount = 0.0;
        foreach ($document->getTaxes() as $tax) {
            if (!$tax->isRetention()) {
                $totalTaxAmount += $tax->getAmount();
            }
        }
        
        $taxInclusiveAmount = $taxExclusiveAmount + $totalTaxAmount;
        $payableAmount = $taxInclusiveAmount;

        $totals = [
            'lineExtensionAmount' => $lineExtensionAmount,
            'taxExclusiveAmount' => $taxExclusiveAmount,
            'taxInclusiveAmount' => $taxInclusiveAmount,
            'payableAmount' => $payableAmount
        ];

        // 3. Determine template and parameters
        if ($document instanceof Invoice) {
            $template = 'invoice.xml.twig';
            $params = [
                'doc' => $document,
                'cufe' => $uuid,
                'qrCodeUrl' => $qrCodeUrl,
                'taxGroupsData' => $taxGroups,
                'totals' => $totals,
            ];
        } elseif ($document instanceof CreditNote) {
            $template = 'creditnote.xml.twig';
            $params = [
                'doc' => $document,
                'cude' => $uuid,
                'qrCodeUrl' => $qrCodeUrl,
                'taxGroupsData' => $taxGroups,
                'totals' => $totals,
            ];
        } else {
            $template = 'debitnote.xml.twig';
            $params = [
                'doc' => $document,
                'cude' => $uuid,
                'qrCodeUrl' => $qrCodeUrl,
                'taxGroupsData' => $taxGroups,
                'totals' => $totals,
            ];
        }

        // 4. Render the Twig XML template
        return $this->twig->render($template, $params);
    }
}
