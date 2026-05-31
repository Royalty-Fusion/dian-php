<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Xml;

use RoyaltyFusion\DianPhp\Catalog\TipoAmbiente;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DebitNote;
use RoyaltyFusion\DianPhp\Model\Invoice;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Renderer of DIAN UBL 2.1 XML documents from Twig templates.
 *
 * Greenter-style: this class only orchestrates the template render —
 * business arithmetic is delegated to {@see DocumentCalculator}.
 */
class XmlBuilder
{
    private Environment $twig;
    private string $environment;
    private string $uuidSchemeId;

    /**
     * @param  string  $uuidSchemeId  '1' (matches Siigo and most production
     *                                facturadores) or '2' (post-Anexo V1.9
     *                                hashing scheme). Default '1' since that
     *                                is what real-world DIAN traffic uses.
     */
    public function __construct(
        ?Environment $twig = null,
        string $environment = TipoAmbiente::HABILITACION->value,
        string $uuidSchemeId = '1'
    ) {
        if ($twig instanceof Environment) {
            $this->twig = $twig;
        } else {
            $loader = new FilesystemLoader(__DIR__ . '/templates');
            $this->twig = new Environment($loader, [
                'autoescape'       => false,
                'strict_variables' => false,
            ]);
        }
        $this->environment  = $environment;
        $this->uuidSchemeId = $uuidSchemeId;
    }

    /**
     * Builds the complete UBL 2.1 XML for DIAN (Anexo V1.9) using Twig templates.
     */
    public function build(Invoice|CreditNote|DebitNote $document, string $uuid, string $qrCodeUrl): string
    {
        $totals = DocumentCalculator::totals($document);

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
            'totals'        => $totals,
            'environment'   => $this->environment,
            'profileExecId' => $this->environment,
            'uuidSchemeId'  => $this->uuidSchemeId,
        ]);
    }
}
