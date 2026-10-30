<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Ws;

/**
 * File names required by the Anexo Técnico for the XML and the ZIP sent to DIAN:
 *
 *   <tipo><NIT 10 dígitos><PPP><AA><consecutivo 8 hex>.xml
 *   z<NIT 10 dígitos><PPP><AA><consecutivo 8 hex>.zip
 *
 * tipo: fv factura · nc nota crédito · nd nota débito · ds documento soporte
 *       nas nota de ajuste DS · nie nómina · niae nómina de ajuste
 *       ar application response (RADIAN) · ad attached document
 * PPP:  código del proveedor tecnológico ("000" when the company is its own provider)
 * AA:   last two digits of the year
 */
final class FileNameGenerator
{
    public const FACTURA        = 'fv';
    public const NOTA_CREDITO   = 'nc';
    public const NOTA_DEBITO    = 'nd';
    public const SOPORTE        = 'ds';
    public const NOTA_AJUSTE_DS = 'nas';
    public const NOMINA         = 'nie';
    public const NOMINA_AJUSTE  = 'niae';
    public const EVENTO         = 'ar';
    public const ADJUNTO        = 'ad';

    /** File prefix of each documento equivalente (and its notas de ajuste). */
    private const EQUIVALENT_PREFIXES = [
        '20' => 'pos', '25' => 'cin', '27' => 'esp', '30' => 'jue', '35' => 'ttr', '40' => 'pjs',
        '45' => 'ext', '50' => 'tae', '55' => 'bls', '60' => 'srv', '93' => 'ndq', '94' => 'ncq',
    ];

    public static function forEquivalentDocument(string $tipo): string
    {
        return self::EQUIVALENT_PREFIXES[$tipo] ?? 'dee';
    }

    /**
     * Base name without extension (e.g. "fv0900123456000260000002a").
     *
     * @param int|string $consecutive document consecutive; non-digits are ignored
     */
    public static function base(string $type, string $nit, int|string $consecutive, ?\DateTimeInterface $date = null, string $providerCode = '000'): string
    {
        $nit    = str_pad(substr(preg_replace('/\D/', '', $nit) ?? '', 0, 10), 10, '0', STR_PAD_LEFT);
        $number = (int) (preg_replace('/\D/', '', (string) $consecutive) ?: '0');
        $year   = ($date ?? new \DateTimeImmutable())->format('y');
        $ppp    = str_pad(substr(preg_replace('/\D/', '', $providerCode) ?? '', 0, 3), 3, '0', STR_PAD_LEFT);

        return $type . $nit . $ppp . $year . str_pad(dechex($number % 0x100000000), 8, '0', STR_PAD_LEFT);
    }

    public static function xml(string $type, string $nit, int|string $consecutive, ?\DateTimeInterface $date = null, string $providerCode = '000'): string
    {
        return self::base($type, $nit, $consecutive, $date, $providerCode) . '.xml';
    }

    public static function zip(string $type, string $nit, int|string $consecutive, ?\DateTimeInterface $date = null, string $providerCode = '000'): string
    {
        return 'z' . substr(self::base($type, $nit, $consecutive, $date, $providerCode), strlen($type)) . '.zip';
    }
}
