<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Xml;

use RoyaltyFusion\DianPhp\Catalog\TipoAmbiente;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\NitDvCalculator;
use Twig\Environment;

/**
 * Renderer of DIAN UBL 2.1 XML documents (Factura, Nota Crédito, Nota Débito).
 *
 * Every amount comes from {@see DocumentCalculator}; templates live in
 * src/Xml/templates and share the partials under templates/partials.
 * Values are XML-escaped automatically (see {@see TwigFactory}).
 */
class XmlBuilder
{
    private Environment $twig;
    private string $environment;
    private string $uuidSchemeId;

    /**
     * @param  string  $environment   TipoAmbiente value: '1' producción, '2' habilitación.
     * @param  string  $uuidSchemeId  cbc:UUID@schemeID — DIAN expects the ambiente
     *                                code; null keeps it equal to $environment.
     */
    public function __construct(
        ?Environment $twig = null,
        string $environment = '2', // TipoAmbiente::HABILITACION; enum ->value in defaults needs PHP 8.2
        ?string $uuidSchemeId = null
    ) {
        $this->twig         = $twig ?? TwigFactory::create(__DIR__ . '/templates');
        $this->environment  = $environment;
        $this->uuidSchemeId = $uuidSchemeId ?? $environment;
    }

    /**
     * @param string $qrCode Full sts:QRCode text ({@see QrGenerator::content()}) or just the URL.
     */
    public function build(Invoice|CreditNote|DebitNote $document, string $uuid, string $qrCode): string
    {
        $template = match (true) {
            $document instanceof Invoice    => 'invoice.xml.twig',
            $document instanceof CreditNote => 'creditnote.xml.twig',
            default                         => 'debitnote.xml.twig',
        };

        $providerNit = $document->getSoftware()?->getProviderNit()
            ?: ($document->getCompany()?->getNit() ?? '');

        return $this->twig->render($template, [
            'doc'           => $document,
            'uuid'          => $uuid,
            'cufe'          => $uuid,
            'cude'          => $uuid,
            'qrCode'        => $qrCode,
            'qrCodeUrl'     => $qrCode,
            'totals'        => DocumentCalculator::totals($document),
            'currency'      => $document->getCurrencyCode() ?: 'COP',
            'environment'   => $this->environment,
            'profileExecId' => $this->environment,
            'uuidSchemeId'  => $this->uuidSchemeId,
            'providerNit'   => $providerNit,
            'providerDv'    => NitDvCalculator::compute($providerNit),
        ]);
    }
}
