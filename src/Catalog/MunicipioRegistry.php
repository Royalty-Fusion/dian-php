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

        // Hydrate from resources/catalogs/municipalities.csv if present.
        // Format: TSV (tab-separated), no header, columns:
        //   id  department_id  name  code
        // where code is the 5-digit DANE municipality code.
        $csvFile = __DIR__ . '/../../resources/catalogs/municipalities.csv';
        if (is_file($csvFile)) {
            $handle = fopen($csvFile, 'r');
            if ($handle !== false) {
                while (($row = fgetcsv($handle, 0, "\t")) !== false) {
                    if (count($row) >= 4) {
                        $code   = trim($row[3]);
                        $name   = trim($row[2]);
                        $deptId = trim($row[1]);
                        // Convert soenac department_id to DANE 2-digit code via known mapping
                        $deptCode = self::departmentIdToDaneCode($deptId);
                        self::$data[$code] = [$name, $deptCode];
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

    /**
     * Maps the soenac/api-dian department row ID to the DANE 2-digit code.
     * The CSV ships department_id as the row PK, not the DANE code.
     *
     * @var array<string,string>
     */
    private const DEPT_ID_TO_DANE = [
        '1'  => '91', '2'  => '05', '3'  => '81', '4'  => '08', '5'  => '11',
        '6'  => '13', '7'  => '15', '8'  => '17', '9'  => '18', '10' => '85',
        '11' => '19', '12' => '20', '13' => '27', '14' => '23', '15' => '25',
        '16' => '94', '17' => '95', '18' => '41', '19' => '44', '20' => '47',
        '21' => '50', '22' => '52', '23' => '54', '24' => '86', '25' => '63',
        '26' => '66', '27' => '88', '28' => '68', '29' => '70', '30' => '73',
        '31' => '76', '32' => '97', '33' => '99',
    ];

    private static function departmentIdToDaneCode(string $deptId): string
    {
        return self::DEPT_ID_TO_DANE[$deptId] ?? $deptId;
    }
}
