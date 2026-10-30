<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Payroll;

use RoyaltyFusion\DianPhp\Model\NitDvCalculator;
use RoyaltyFusion\DianPhp\Xml\DocumentCalculator;
use RoyaltyFusion\DianPhp\Xml\TwigFactory;
use Twig\Environment;

/**
 * Renderer of the Nómina Individual (TipoXML 102) and of its nota de ajuste
 * (NominaIndividualDeAjuste, TipoXML 103: reemplazar o eliminar).
 */
class PayrollXmlBuilder
{
    /** Attributes holding quantities (not money): rendered without trailing zeros. */
    private const QUANTITIES = ['Cantidad', 'DiasTrabajados'];

    private Environment $twig;

    public function __construct(?Environment $twig = null)
    {
        $this->twig = $twig ?? TwigFactory::create(__DIR__ . '/templates');
    }

    /**
     * @param string $qrCode CodigoQR content (the VPFE consultation URL with the CUNE)
     */
    public function build(PayrollDocument $doc, string $cune, string $qrCode): string
    {
        [$fechaGen, $horaGen] = DocumentCalculator::issueDateTime($doc->getFechaGen());
        $providerNit = $doc->getSoftware()?->getProviderNit() ?: ($doc->getEmpleador()?->getNit() ?? '');

        return $this->twig->render(
            $doc instanceof PayrollAdjustment ? 'nomina-ajuste.xml.twig' : 'nomina-individual.xml.twig',
            [
                'doc'         => $doc,
                'cune'        => $cune,
                'qrCode'      => $qrCode,
                'qrCodeUrl'   => $qrCode,
                'fechaGen'    => $fechaGen,
                'horaGen'     => $horaGen,
                'providerNit' => $providerNit,
                'providerDv'  => NitDvCalculator::compute($providerNit),
                'devengados'  => self::format($doc->getDevengados()->nodes()),
                'deducciones' => self::format($doc->getDeducciones()->nodes()),
                'totals'      => [
                    'devengados'  => self::money($doc->getDevengados()->total()),
                    'deducciones' => self::money($doc->getDeducciones()->total()),
                    'comprobante' => self::money($doc->getComprobanteTotal()),
                ],
            ]
        );
    }

    /**
     * Converts the numeric values of the node tree to the strings DIAN expects.
     *
     * @param list<array<string,mixed>> $nodes
     * @return list<array<string,mixed>>
     */
    private static function format(array $nodes): array
    {
        foreach ($nodes as &$node) {
            foreach ($node['attrs'] ?? [] as $name => $value) {
                if (is_string($value)) {
                    continue;
                }
                $node['attrs'][$name] = in_array($name, self::QUANTITIES, true)
                    ? rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.')
                    : self::money((float) $value);
            }
            if (isset($node['text'])) {
                $node['text'] = self::money((float) $node['text']);
            }
            if (isset($node['children'])) {
                $node['children'] = self::format($node['children']);
            }
        }

        return $nodes;
    }

    private static function money(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
