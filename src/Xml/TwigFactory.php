<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Xml;

use Twig\Environment;
use Twig\Extension\EscaperExtension;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;
use Twig\TwigFilter;

/**
 * Builds the Twig environment shared by every XML builder of the SDK.
 *
 * Every `{{ value }}` is XML-escaped by default ("&", "<", ">", quotes) and
 * characters forbidden by XML 1.0 (control chars) are removed, so free text
 * such as "Pérez & Cía" can never break the document or inject nodes.
 *
 * Extra filters:
 *   - `cdata`  wraps a string in a CDATA section, splitting any "]]>" inside.
 *   - `money`  formats an amount with 2 decimals and "." separator.
 */
final class TwigFactory
{
    public static function create(string ...$templateDirs): Environment
    {
        $loader = new FilesystemLoader($templateDirs);
        $loader->addPath(__DIR__ . '/templates/partials', 'dian');

        $twig = new Environment($loader, [
            'autoescape'       => 'xml',
            'strict_variables' => false,
        ]);

        $escaper = static fn (string $string): string => self::escape($string);
        if (class_exists(EscaperRuntime::class)) {
            $twig->getRuntime(EscaperRuntime::class)->setEscaper('xml', $escaper);
        } else {
            // Twig < 3.10
            $twig->getExtension(EscaperExtension::class)->setEscaper(
                'xml',
                static fn (Environment $env, mixed $string): string => self::escape((string) $string)
            );
        }

        $twig->addFilter(new TwigFilter('cdata', [self::class, 'cdata'], ['is_safe' => ['xml', 'html']]));
        $twig->addFilter(new TwigFilter('money', [self::class, 'money']));

        return $twig;
    }

    public static function escape(mixed $value): string
    {
        // ENT_SUBSTITUTE first turns invalid UTF-8 into U+FFFD, so the regex below is safe.
        return self::stripInvalidChars(
            htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8')
        );
    }

    public static function cdata(?string $value): string
    {
        $raw = (string) $value;
        if (preg_match('//u', $raw) !== 1) {
            throw new \InvalidArgumentException('El contenido CDATA no es UTF-8 válido.');
        }
        $clean = self::stripInvalidChars($raw);

        return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $clean) . ']]>';
    }

    public static function money(mixed $value, int $decimals = 2): string
    {
        return number_format(round((float) $value, $decimals), $decimals, '.', '');
    }

    private static function stripInvalidChars(string $value): string
    {
        return (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);
    }
}
