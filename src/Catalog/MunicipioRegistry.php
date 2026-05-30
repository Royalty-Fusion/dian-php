<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Catalog;

/**
 * Registry of DANE municipality codes (5 digits = depto 2 + municipio 3).
 *
 * The full DANE division político-administrativa list contains ~1100 entries,
 * which would bloat a PHP enum. Instead the registry loads a CSV from
 * resources/catalogs/municipios.csv on first access (lazy + cached).
 *
 * Until the CSV is populated (Phase 13 fixtures), the registry exposes a small
 * handful of capital cities so the SDK can be exercised end-to-end.
 *
 * Emitted under <cac:Address><cbc:ID> and <cbc:CityName>.
 */
final class MunicipioRegistry
{
    /** @var array<string,array{0:string,1:string}>|null  code => [name, departmentCode] */
    private static ?array $data = null;

    /** @return array<string,array{0:string,1:string}> */
    private static function loadData(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        // Bootstrap: capital cities. Replace with CSV load in Phase 13.
        self::$data = [
            '05001' => ['Medellín', '05'],
            '08001' => ['Barranquilla', '08'],
            '11001' => ['Bogotá D.C.', '11'],
            '13001' => ['Cartagena de Indias', '13'],
            '15001' => ['Tunja', '15'],
            '17001' => ['Manizales', '17'],
            '18001' => ['Florencia', '18'],
            '19001' => ['Popayán', '19'],
            '20001' => ['Valledupar', '20'],
            '23001' => ['Montería', '23'],
            '25001' => ['Agua de Dios', '25'],
            '27001' => ['Quibdó', '27'],
            '41001' => ['Neiva', '41'],
            '44001' => ['Riohacha', '44'],
            '47001' => ['Santa Marta', '47'],
            '50001' => ['Villavicencio', '50'],
            '52001' => ['Pasto', '52'],
            '54001' => ['Cúcuta', '54'],
            '63001' => ['Armenia', '63'],
            '66001' => ['Pereira', '66'],
            '68001' => ['Bucaramanga', '68'],
            '70001' => ['Sincelejo', '70'],
            '73001' => ['Ibagué', '73'],
            '76001' => ['Cali', '76'],
            '81001' => ['Arauca', '81'],
            '85001' => ['Yopal', '85'],
            '86001' => ['Mocoa', '86'],
            '88001' => ['San Andrés', '88'],
            '91001' => ['Leticia', '91'],
            '94001' => ['Inírida', '94'],
            '95001' => ['San José del Guaviare', '95'],
            '97001' => ['Mitú', '97'],
            '99001' => ['Puerto Carreño', '99'],
        ];

        // If a CSV file is present, hydrate from it (overrides the bootstrap rows).
        $csvFile = __DIR__ . '/../../resources/catalogs/municipios.csv';
        if (is_file($csvFile)) {
            $handle = fopen($csvFile, 'r');
            if ($handle !== false) {
                // Expected columns: codigo,nombre,depto
                fgetcsv($handle); // skip header
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) >= 3) {
                        self::$data[$row[0]] = [$row[1], $row[2]];
                    }
                }
                fclose($handle);
            }
        }

        return self::$data;
    }

    public static function name(string $code): ?string
    {
        return self::loadData()[$code][0] ?? null;
    }

    public static function departmentCode(string $code): ?string
    {
        return self::loadData()[$code][1] ?? null;
    }

    public static function has(string $code): bool
    {
        return isset(self::loadData()[$code]);
    }

    /** @return array<string,string>  code => name */
    public static function all(): array
    {
        $out = [];
        foreach (self::loadData() as $code => $row) {
            $out[$code] = $row[0];
        }
        return $out;
    }
}
