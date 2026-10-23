<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Payroll;

use Twig\Environment;
use RoyaltyFusion\DianPhp\Xml\TwigFactory;

/**
 * Renderer of the Nómina Individual Electrónica XML.
 */
class PayrollXmlBuilder
{
    private Environment $twig;

    public function __construct(?Environment $twig = null)
    {
        $this->twig = $twig ?? TwigFactory::create(__DIR__ . '/templates');
    }

    public function build(PayrollDocument $doc, string $cune, string $qrCodeUrl): string
    {
        return $this->twig->render('nomina-individual.xml.twig', [
            'doc'        => $doc,
            'cune'       => $cune,
            'qrCodeUrl'  => $qrCodeUrl,
            'devTotal'   => number_format(floor($doc->getDevengados()->total() * 100) / 100, 2, '.', ''),
            'dedTotal'   => number_format(floor($doc->getDeducciones()->total() * 100) / 100, 2, '.', ''),
            'compTotal'  => number_format(floor($doc->getComprobanteTotal() * 100) / 100, 2, '.', ''),
        ]);
    }
}
