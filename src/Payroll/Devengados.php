<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Payroll;

/**
 * Devengados (earnings) of a Nómina Individual, rendered in the order and
 * nesting the DIAN schema requires:
 *
 *   Basico, Transporte*, HEDs, HENs, HRNs, HEDDFs, HRDDFs, HENDFs, HRNDFs,
 *   Vacaciones, Primas, Cesantias, Incapacidades, Licencias, Bonificaciones,
 *   Auxilios, HuelgasLegales, OtrosConceptos, Compensaciones, BonoEPCTVs,
 *   Comisiones, PagosTerceros, Anticipos, Dotacion, ApoyoSost, Teletrabajo,
 *   BonifRetiro, Indemnizacion, Reintegro
 *
 * Every concept that DIAN models as a list (horas extra, incapacidades,
 * licencias, bonificaciones…) is added with its own add*() method.
 */
final class Devengados
{
    /** Tipos de hora extra / recargo and their legal percentage (tabla DIAN). */
    public const HORAS = [
        'HED'   => 25.0,   // hora extra diurna
        'HEN'   => 75.0,   // hora extra nocturna
        'HRN'   => 35.0,   // recargo nocturno
        'HEDDF' => 100.0,  // hora extra diurna dominical y festiva
        'HRDDF' => 75.0,   // recargo diurno dominical y festivo
        'HENDF' => 150.0,  // hora extra nocturna dominical y festiva
        'HRNDF' => 110.0,  // recargo nocturno dominical y festivo
    ];

    private int $diasTrabajados = 30;
    private float $sueldoTrabajado = 0.0;
    private float $auxilioTransporte = 0.0;
    private float $viaticoManuAlojS = 0.0;
    private float $viaticoManuAlojNS = 0.0;
    /** @var array<string,list<array<string,string|float>>> */
    private array $horas = [];
    /** @var list<array<string,string|float>> */
    private array $vacacionesComunes = [];
    /** @var list<array<string,string|float>> */
    private array $vacacionesCompensadas = [];
    /** @var array<string,float>|null */
    private ?array $primas = null;
    /** @var array<string,float>|null */
    private ?array $cesantias = null;
    /** @var list<array<string,string|float>> */
    private array $incapacidades = [];
    /** @var array<string,list<array<string,string|float>>> */
    private array $licencias = ['LicenciaMP' => [], 'LicenciaR' => [], 'LicenciaNR' => []];
    /** @var list<array<string,float>> */
    private array $bonificaciones = [];
    /** @var list<array<string,float>> */
    private array $auxilios = [];
    /** @var list<array<string,string|float>> */
    private array $huelgasLegales = [];
    /** @var list<array<string,string|float>> */
    private array $otrosConceptos = [];
    /** @var list<array<string,float>> */
    private array $compensaciones = [];
    /** @var list<array<string,float>> */
    private array $bonosEPCTV = [];
    /** @var list<float> */
    private array $comisiones = [];
    /** @var list<float> */
    private array $pagosTerceros = [];
    /** @var list<float> */
    private array $anticipos = [];
    private float $dotacion = 0.0;
    private float $apoyoSost = 0.0;
    private float $teletrabajo = 0.0;
    private float $bonifRetiro = 0.0;
    private float $indemnizacion = 0.0;
    private float $reintegro = 0.0;

    // --- Básico y transporte ------------------------------------------

    public function setBasicoDiasTrabajados(int $v): self { $this->diasTrabajados = $v; return $this; }
    public function getBasicoDiasTrabajados(): int { return $this->diasTrabajados; }
    public function setBasicoSueldoTrabajado(float $v): self { $this->sueldoTrabajado = $v; return $this; }
    public function getBasicoSueldoTrabajado(): float { return $this->sueldoTrabajado; }

    public function setAuxilioTransporte(float $v): self { $this->auxilioTransporte = $v; return $this; }
    public function getAuxilioTransporte(): float { return $this->auxilioTransporte; }
    public function setViaticoManuAlojS(float $v): self { $this->viaticoManuAlojS = $v; return $this; }
    public function getViaticoManuAlojS(): float { return $this->viaticoManuAlojS; }
    public function setViaticoManuAlojNS(float $v): self { $this->viaticoManuAlojNS = $v; return $this; }
    public function getViaticoManuAlojNS(): float { return $this->viaticoManuAlojNS; }

    // --- Horas extra y recargos ------------------------------------------

    /**
     * @param string     $tipo       HED, HEN, HRN, HEDDF, HRDDF, HENDF o HRNDF
     * @param float      $cantidad   número de horas
     * @param float      $pago       valor pagado
     * @param float|null $porcentaje null = porcentaje legal del tipo
     */
    public function addHoraExtra(
        string $tipo,
        float $cantidad,
        float $pago,
        ?float $porcentaje = null,
        ?\DateTimeInterface $inicio = null,
        ?\DateTimeInterface $fin = null
    ): self {
        if (!isset(self::HORAS[$tipo])) {
            throw new \InvalidArgumentException("Tipo de hora extra inválido '{$tipo}' (" . implode(', ', array_keys(self::HORAS)) . ').');
        }
        $item = [];
        if ($inicio !== null) {
            $item['HoraInicio'] = $inicio->format('Y-m-d\TH:i:s');
        }
        if ($fin !== null) {
            $item['HoraFin'] = $fin->format('Y-m-d\TH:i:s');
        }
        $item['Cantidad']   = $cantidad;
        $item['Porcentaje'] = $porcentaje ?? self::HORAS[$tipo];
        $item['Pago']       = $pago;
        $this->horas[$tipo][] = $item;

        return $this;
    }

    // --- Vacaciones, primas, cesantías ----------------------------------

    public function addVacacionesComunes(int $dias, float $pago, ?\DateTimeInterface $inicio = null, ?\DateTimeInterface $fin = null): self
    {
        $this->vacacionesComunes[] = self::period($inicio, $fin) + ['Cantidad' => $dias, 'Pago' => $pago];
        return $this;
    }

    public function addVacacionesCompensadas(int $dias, float $pago): self
    {
        $this->vacacionesCompensadas[] = ['Cantidad' => $dias, 'Pago' => $pago];
        return $this;
    }

    /** Prima de servicios: días liquidados, pago salarial y no salarial. */
    public function setPrimas(int $dias, float $pago, float $pagoNS = 0.0): self
    {
        $this->primas = ['Cantidad' => $dias, 'Pago' => $pago] + ($pagoNS > 0 ? ['PagoNS' => $pagoNS] : []);
        return $this;
    }

    public function setCesantias(float $pago, float $porcentajeIntereses = 0.0, float $pagoIntereses = 0.0): self
    {
        $this->cesantias = ['Pago' => $pago, 'Porcentaje' => $porcentajeIntereses, 'PagoIntereses' => $pagoIntereses];
        return $this;
    }

    // --- Incapacidades y licencias ----------------------------------------

    /**
     * @param string $tipo 1 común, 2 profesional, 3 laboral
     */
    public function addIncapacidad(int $dias, string $tipo, float $pago, ?\DateTimeInterface $inicio = null, ?\DateTimeInterface $fin = null): self
    {
        $this->incapacidades[] = self::period($inicio, $fin) + ['Cantidad' => $dias, 'Tipo' => $tipo, 'Pago' => $pago];
        return $this;
    }

    /**
     * @param string $tipo MP maternidad/paternidad, R remunerada, NR no remunerada (sin pago)
     */
    public function addLicencia(string $tipo, int $dias, float $pago = 0.0, ?\DateTimeInterface $inicio = null, ?\DateTimeInterface $fin = null): self
    {
        $key = 'Licencia' . strtoupper($tipo);
        if (!isset($this->licencias[$key])) {
            throw new \InvalidArgumentException("Tipo de licencia inválido '{$tipo}' (MP, R, NR).");
        }
        $item = self::period($inicio, $fin) + ['Cantidad' => $dias];
        if ($key !== 'LicenciaNR') {
            $item['Pago'] = $pago;
        }
        $this->licencias[$key][] = $item;

        return $this;
    }

    // --- Pagos adicionales -------------------------------------------------

    public function addBonificacion(float $salarial, float $noSalarial = 0.0): self
    {
        $this->bonificaciones[] = self::nonZero(['BonificacionS' => $salarial, 'BonificacionNS' => $noSalarial]);
        return $this;
    }

    public function addAuxilio(float $salarial, float $noSalarial = 0.0): self
    {
        $this->auxilios[] = self::nonZero(['AuxilioS' => $salarial, 'AuxilioNS' => $noSalarial]);
        return $this;
    }

    public function addHuelgaLegal(int $dias, ?\DateTimeInterface $inicio = null, ?\DateTimeInterface $fin = null): self
    {
        $this->huelgasLegales[] = self::period($inicio, $fin) + ['Cantidad' => $dias];
        return $this;
    }

    public function addOtroConcepto(string $descripcion, float $salarial, float $noSalarial = 0.0): self
    {
        $this->otrosConceptos[] = ['DescripcionConcepto' => $descripcion] + self::nonZero(['ConceptoS' => $salarial, 'ConceptoNS' => $noSalarial]);
        return $this;
    }

    public function addCompensacion(float $ordinaria, float $extraordinaria = 0.0): self
    {
        $this->compensaciones[] = ['CompensacionO' => $ordinaria, 'CompensacionE' => $extraordinaria];
        return $this;
    }

    /** Bonos electrónicos o de papel de servicio, cheques, tarjetas, vales. */
    public function addBonoEPCTV(float $pagoS, float $pagoNS = 0.0, float $alimentacionS = 0.0, float $alimentacionNS = 0.0): self
    {
        $this->bonosEPCTV[] = self::nonZero([
            'PagoS'              => $pagoS,
            'PagoNS'             => $pagoNS,
            'PagoAlimentacionS'  => $alimentacionS,
            'PagoAlimentacionNS' => $alimentacionNS,
        ]);
        return $this;
    }

    public function addComision(float $v): self { $this->comisiones[] = $v; return $this; }
    public function addPagoTercero(float $v): self { $this->pagosTerceros[] = $v; return $this; }
    public function addAnticipo(float $v): self { $this->anticipos[] = $v; return $this; }

    /** Single-value shortcuts kept for backwards compatibility. */
    public function setComisiones(float $v): self { $this->comisiones = $v > 0 ? [$v] : []; return $this; }
    public function setPagosTerceros(float $v): self { $this->pagosTerceros = $v > 0 ? [$v] : []; return $this; }
    public function setAnticipos(float $v): self { $this->anticipos = $v > 0 ? [$v] : []; return $this; }

    public function setDotacion(float $v): self { $this->dotacion = $v; return $this; }
    public function setApoyoSost(float $v): self { $this->apoyoSost = $v; return $this; }
    public function setTeletrabajo(float $v): self { $this->teletrabajo = $v; return $this; }
    public function setBonificacionRetiro(float $v): self { $this->bonifRetiro = $v; return $this; }
    public function setIndemnizacion(float $v): self { $this->indemnizacion = $v; return $this; }
    public function setReintegro(float $v): self { $this->reintegro = $v; return $this; }

    /** Sum of every paid value (DevengadosTotal). */
    public function total(): float
    {
        $sum = 0.0;
        foreach ($this->nodes() as $node) {
            $sum += self::paid($node);
        }

        return round($sum, 2);
    }

    /**
     * XML tree consumed by the payroll templates.
     *
     * @return list<array{name:string,attrs?:array<string,string|float|int>,text?:float,children?:list<array<string,mixed>>}>
     */
    public function nodes(): array
    {
        $nodes   = [];
        $nodes[] = ['name' => 'Basico', 'attrs' => ['DiasTrabajados' => $this->diasTrabajados, 'SueldoTrabajado' => $this->sueldoTrabajado]];

        $transporte = self::nonZero([
            'AuxilioTransporte' => $this->auxilioTransporte,
            'ViaticoManuAlojS'  => $this->viaticoManuAlojS,
            'ViaticoManuAlojNS' => $this->viaticoManuAlojNS,
        ]);
        if ($transporte !== []) {
            $nodes[] = ['name' => 'Transporte', 'attrs' => $transporte];
        }

        foreach (array_keys(self::HORAS) as $tipo) {
            if (!empty($this->horas[$tipo])) {
                $nodes[] = self::group($tipo . 's', $tipo, $this->horas[$tipo]);
            }
        }

        if ($this->vacacionesComunes !== [] || $this->vacacionesCompensadas !== []) {
            $children = [];
            foreach ($this->vacacionesComunes as $item) {
                $children[] = ['name' => 'VacacionesComunes', 'attrs' => $item];
            }
            foreach ($this->vacacionesCompensadas as $item) {
                $children[] = ['name' => 'VacacionesCompensadas', 'attrs' => $item];
            }
            $nodes[] = ['name' => 'Vacaciones', 'children' => $children];
        }
        if ($this->primas !== null) {
            $nodes[] = ['name' => 'Primas', 'attrs' => $this->primas];
        }
        if ($this->cesantias !== null) {
            $nodes[] = ['name' => 'Cesantias', 'attrs' => $this->cesantias];
        }
        if ($this->incapacidades !== []) {
            $nodes[] = self::group('Incapacidades', 'Incapacidad', $this->incapacidades);
        }
        $licencias = [];
        foreach ($this->licencias as $name => $items) {
            foreach ($items as $item) {
                $licencias[] = ['name' => $name, 'attrs' => $item];
            }
        }
        if ($licencias !== []) {
            $nodes[] = ['name' => 'Licencias', 'children' => $licencias];
        }
        foreach ([
            ['Bonificaciones', 'Bonificacion', $this->bonificaciones],
            ['Auxilios', 'Auxilio', $this->auxilios],
            ['HuelgasLegales', 'HuelgaLegal', $this->huelgasLegales],
            ['OtrosConceptos', 'OtroConcepto', $this->otrosConceptos],
            ['Compensaciones', 'Compensacion', $this->compensaciones],
            ['BonoEPCTVs', 'BonoEPCTV', $this->bonosEPCTV],
        ] as [$group, $element, $items]) {
            if ($items !== []) {
                $nodes[] = self::group($group, $element, $items);
            }
        }
        foreach ([
            ['Comisiones', 'Comision', $this->comisiones],
            ['PagosTerceros', 'PagoTercero', $this->pagosTerceros],
            ['Anticipos', 'Anticipo', $this->anticipos],
        ] as [$group, $element, $values]) {
            if ($values !== []) {
                $nodes[] = ['name' => $group, 'children' => array_map(static fn (float $v) => ['name' => $element, 'text' => $v], $values)];
            }
        }
        foreach ([
            'Dotacion'      => $this->dotacion,
            'ApoyoSost'     => $this->apoyoSost,
            'Teletrabajo'   => $this->teletrabajo,
            'BonifRetiro'   => $this->bonifRetiro,
            'Indemnizacion' => $this->indemnizacion,
            'Reintegro'     => $this->reintegro,
        ] as $name => $value) {
            if ($value > 0) {
                $nodes[] = ['name' => $name, 'text' => $value];
            }
        }

        return $nodes;
    }

    /** Attributes whose value is money and is added to the total. */
    private const PAID = [
        'SueldoTrabajado', 'AuxilioTransporte', 'ViaticoManuAlojS', 'ViaticoManuAlojNS', 'Pago', 'PagoNS', 'PagoIntereses', 'BonificacionS', 'BonificacionNS',
        'AuxilioS', 'AuxilioNS', 'ConceptoS', 'ConceptoNS', 'CompensacionO', 'CompensacionE',
        'PagoS', 'PagoAlimentacionS', 'PagoAlimentacionNS',
    ];

    /** @param array<string,mixed> $node */
    private static function paid(array $node): float
    {
        $sum = 0.0;
        foreach ($node['attrs'] ?? [] as $attr => $value) {
            if (in_array($attr, self::PAID, true)) {
                $sum += (float) $value;
            }
        }
        if (isset($node['text'])) {
            $sum += (float) $node['text'];
        }
        foreach ($node['children'] ?? [] as $child) {
            $sum += self::paid($child);
        }

        return $sum;
    }

    /**
     * @param list<array<string,string|float|int>> $items
     * @return array{name:string,children:list<array{name:string,attrs:array<string,string|float|int>}>}
     */
    private static function group(string $group, string $element, array $items): array
    {
        return ['name' => $group, 'children' => array_map(static fn (array $a) => ['name' => $element, 'attrs' => $a], $items)];
    }

    /** @return array<string,string> */
    private static function period(?\DateTimeInterface $inicio, ?\DateTimeInterface $fin): array
    {
        $out = [];
        if ($inicio !== null) {
            $out['FechaInicio'] = $inicio->format('Y-m-d');
        }
        if ($fin !== null) {
            $out['FechaFin'] = $fin->format('Y-m-d');
        }

        return $out;
    }

    /**
     * @param array<string,float> $values
     * @return array<string,float>
     */
    private static function nonZero(array $values): array
    {
        return array_filter($values, static fn (float $v): bool => $v != 0.0);
    }
}
