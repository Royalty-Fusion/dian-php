<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\SupportDocument;

use RoyaltyFusion\DianPhp\Catalog\TipoAmbiente;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Renderer of the Documento Soporte UBL XML (DIAN Anexo DS v1.2).
 *
 * Mirror of {@see \RoyaltyFusion\DianPhp\Xml\XmlBuilder} but for the DS
 * family. Kept in its own module so its evolution stays decoupled from the
 * Invoice/CN/DN stack.
 */
class SupportDocumentBuilder
{
    private Environment $twig;
    private string $environment;

    public function __construct(?Environment $twig = null, string $environment = TipoAmbiente::HABILITACION->value)
    {
        if ($twig instanceof Environment) {
            $this->twig = $twig;
        } else {
            $loader = new FilesystemLoader(__DIR__ . '/templates');
            $this->twig = new Environment($loader, [
                'autoescape'       => false,
                'strict_variables' => false,
            ]);
        }
        $this->environment = $environment;
    }

    public function build(SupportDocument $document, string $cuds, string $qrCodeUrl): string
    {
        // Re-use DocumentCalculator pattern for totals.
        $lineExtensionAmount = 0.0;
        foreach ($document->getItems() as $item) {
            $lineExtensionAmount += $item->getCantidad() * $item->getPrecio();
        }
        $taxAmount = 0.0;
        $taxGroups = [];
        foreach ($document->getTaxes() as $tax) {
            $taxAmount += $tax->isRetention() ? 0 : $tax->getAmount();
            $code = $tax->getCode();
            if (!isset($taxGroups[$code])) {
                $taxGroups[$code] = ['amount' => 0.0, 'subtotals' => []];
            }
            $taxGroups[$code]['amount']     += $tax->getAmount();
            $taxGroups[$code]['subtotals'][] = [
                'base'    => $tax->getBase(),
                'amount'  => $tax->getAmount(),
                'percent' => $tax->getPercent(),
                'code'    => $tax->getCode(),
                'name'    => $tax->getName(),
            ];
        }

        return $this->twig->render('supportdocument.xml.twig', [
            'doc'           => $document,
            'cuds'          => $cuds,
            'qrCodeUrl'     => $qrCodeUrl,
            'profileExecId' => $this->environment,
            'totals'        => [
                'lineExtensionAmount' => $lineExtensionAmount,
                'taxExclusiveAmount'  => $lineExtensionAmount,
                'taxInclusiveAmount'  => $lineExtensionAmount + $taxAmount,
                'payableAmount'       => $lineExtensionAmount + $taxAmount,
            ],
            'taxGroups'     => $taxGroups,
        ]);
    }
}
