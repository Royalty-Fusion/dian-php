<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Radian;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Renderer of RADIAN ApplicationResponse events (DIAN Anexo RADIAN v1.0).
 *
 * Each event is sent as a UBL ApplicationResponse signed by the *receiver*
 * (not the supplier) and registered against the CUFE of the original
 * invoice. Codes are listed in {@see ApplicationResponseEvent}.
 */
class ApplicationResponseBuilder
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

    public function build(EventDocument $event): string
    {
        return $this->twig->render('event.xml.twig', ['doc' => $event]);
    }
}
