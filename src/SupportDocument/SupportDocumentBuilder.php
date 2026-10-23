<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\SupportDocument;

use RoyaltyFusion\DianPhp\Catalog\TipoAmbiente;
use RoyaltyFusion\DianPhp\Model\NitDvCalculator;
use RoyaltyFusion\DianPhp\Xml\DocumentCalculator;
use RoyaltyFusion\DianPhp\Xml\QrGenerator;
use RoyaltyFusion\DianPhp\Xml\TwigFactory;
use Twig\Environment;

/**
 * Renderer of the Documento Soporte (Invoice, InvoiceTypeCode 05) and of its
 * nota de ajuste (CreditNote, CreditNoteTypeCode 95).
 *
 * Shares partials, escaping and the totals calculator with the invoice stack.
 */
class SupportDocumentBuilder
{
    private Environment $twig;
    private string $environment;

    /** @param string $environment TipoAmbiente value, '2' = habilitación (enum ->value in defaults needs PHP 8.2) */
    public function __construct(?Environment $twig = null, string $environment = '2')
    {
        $this->twig        = $twig ?? TwigFactory::create(__DIR__ . '/templates', dirname(__DIR__) . '/Xml/templates');
        $this->environment = $environment;
    }

    /**
     * @param string $qrCode sts:QRCode content; defaults to the VPFE consultation URL.
     */
    public function build(SupportDocument $document, string $cuds, string $qrCode = ''): string
    {
        $totals      = DocumentCalculator::totals($document);
        $providerNit = $document->getSoftware()?->getProviderNit()
            ?: ($document->getCompany()?->getNit() ?? '');
        $purchase    = $document->getPurchaseDate();

        return $this->twig->render(
            $document instanceof SupportDocumentAdjustmentNote ? 'supportdocument-adjustment.xml.twig' : 'supportdocument.xml.twig',
            [
                'doc'           => $document,
                'uuid'          => $cuds,
                'cuds'          => $cuds,
                'qrCode'        => $qrCode !== '' ? $qrCode : QrGenerator::url($cuds, $this->environment === TipoAmbiente::PRODUCCION->value),
                'totals'        => $totals,
                'currency'      => $document->getCurrencyCode() ?: 'COP',
                'profileExecId' => $this->environment,
                'uuidSchemeId'  => $this->environment,
                'providerNit'   => $providerNit,
                'providerDv'    => NitDvCalculator::compute($providerNit),
                'linePeriod'    => [
                    'startDate'   => $purchase ? DocumentCalculator::issueDateTime($purchase)[0] : $totals['issueDate'],
                    'code'        => $document->getTransmissionCode(),
                    'description' => $document->getTransmissionDescription(),
                ],
            ]
        );
    }
}
