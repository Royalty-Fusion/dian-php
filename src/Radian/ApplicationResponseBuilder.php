<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Radian;

use RoyaltyFusion\DianPhp\Catalog\TipoAmbiente;
use RoyaltyFusion\DianPhp\Model\NitDvCalculator;
use RoyaltyFusion\DianPhp\Xml\DocumentCalculator;
use RoyaltyFusion\DianPhp\Xml\QrGenerator;
use RoyaltyFusion\DianPhp\Xml\TwigFactory;
use Twig\Environment;

/**
 * Renderer of RADIAN events (UBL ApplicationResponse).
 *
 * Structure follows the ApplicationResponse DIAN itself issues (see
 * resources/fixtures): DianExtensions in the first UBLExtension and an empty
 * second one for the XAdES signature of the party that generates the event.
 * Codes are listed in {@see ApplicationResponseEvent}; the CUDE is computed
 * with {@see EventCudeGenerator}.
 */
class ApplicationResponseBuilder
{
    private Environment $twig;
    private string $environment;

    /** @param string $environment TipoAmbiente value, '2' = habilitación (enum ->value in defaults needs PHP 8.2) */
    public function __construct(?Environment $twig = null, string $environment = '2')
    {
        $this->twig        = $twig ?? TwigFactory::create(__DIR__ . '/templates');
        $this->environment = $environment;
    }

    public function build(EventDocument $event): string
    {
        [$date, $time] = DocumentCalculator::issueDateTime($event->getIssueDate());
        $providerNit   = $event->getSoftware()?->getProviderNit() ?: ($event->getReceiver()?->getNit() ?? '');

        return $this->twig->render('event.xml.twig', [
            'doc'         => $event,
            'environment' => $this->environment,
            'issueDate'   => $date,
            'issueTime'   => $time,
            'providerNit' => $providerNit,
            'providerDv'  => NitDvCalculator::compute($providerNit),
            'qrCode'      => QrGenerator::url($event->getCude(), $this->environment === TipoAmbiente::PRODUCCION->value),
        ]);
    }
}
