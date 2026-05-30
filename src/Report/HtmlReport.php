<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Report;

use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Xml\DocumentCalculator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Renders the Anexo-required "representación gráfica" of a DIAN document.
 *
 * Returns plain HTML — drop the result into mpdf, dompdf, browsershot or any
 * other PDF engine. Logo and accent color are configurable via constructor.
 */
class HtmlReport
{
    private Environment $twig;
    private string $logoUrl;
    private string $accentColor;

    public function __construct(?Environment $twig = null, string $logoUrl = '', string $accentColor = '#1e3a8a')
    {
        if ($twig instanceof Environment) {
            $this->twig = $twig;
        } else {
            $loader = new FilesystemLoader(__DIR__ . '/templates');
            $this->twig = new Environment($loader, [
                'autoescape'       => 'html',
                'strict_variables' => false,
            ]);
        }
        $this->logoUrl     = $logoUrl;
        $this->accentColor = $accentColor;
    }

    public function render(
        Invoice|CreditNote|DebitNote $document,
        string $uuid,
        string $qrCodeUrl = ''
    ): string {
        $totals = DocumentCalculator::totals($document);

        if ($document instanceof Invoice) {
            $heading = 'Factura Electrónica de Venta';
            $idLabel = 'CUFE';
        } elseif ($document instanceof CreditNote) {
            $heading = 'Nota Crédito Electrónica';
            $idLabel = 'CUDE';
        } else {
            $heading = 'Nota Débito Electrónica';
            $idLabel = 'CUDE';
        }

        return $this->twig->render('document.html.twig', [
            'doc'         => $document,
            'uuid'        => $uuid,
            'qrCodeUrl'   => $qrCodeUrl,
            'heading'     => $heading,
            'idLabel'     => $idLabel,
            'totals'      => $totals,
            'logoUrl'     => $this->logoUrl,
            'accentColor' => $this->accentColor,
        ]);
    }
}
