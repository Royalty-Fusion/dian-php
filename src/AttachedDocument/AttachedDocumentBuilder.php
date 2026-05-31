<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\AttachedDocument;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Renders an AttachedDocument XML from a populated {@see AttachedDocument}.
 *
 * The output is ready to be signed (XAdES-EPES) by an outer XadesSigner call
 * if the facturador tecnológico wants to certify the bundle.
 */
class AttachedDocumentBuilder
{
    private Environment $twig;

    public function __construct(?Environment $twig = null)
    {
        if ($twig instanceof Environment) {
            $this->twig = $twig;
            return;
        }

        $loader = new FilesystemLoader(__DIR__ . '/templates');
        $this->twig = new Environment($loader, [
            'autoescape'       => false,
            'strict_variables' => false,
        ]);
    }

    public function build(AttachedDocument $doc): string
    {
        return $this->twig->render('attacheddocument.xml.twig', ['doc' => $doc]);
    }
}
