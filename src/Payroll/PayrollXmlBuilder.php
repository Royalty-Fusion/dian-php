<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Payroll;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Renderer of the Nómina Individual Electrónica XML.
 */
class PayrollXmlBuilder
{
    private Environment $twig;

    public function __construct(?Environment $twig = null)
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
