<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Sector;

use RoyaltyFusion\DianPhp\Model\CustomExtension;

/**
 * Factura electrónica del sector salud: extensión CustomTagGeneral /
 * Interoperabilidad (Group schemeName="Sector Salud") with one Collection per
 * usuario atendido, plus the CustomizationID SS-* of the operation.
 *
 * The set of fields is defined by the Ministerio de Salud resolution in force
 * (Res. 506 de 2021, modified by Res. 2275 de 2023 and later ones): the SDK does
 * not hard-code it. Pass the fields with their DIAN/MinSalud names; a value
 * with a code from a MinSalud list is given as [code, name, list]:
 *
 *   $salud = (new SectorSalud('Resolución 2275:2023'))
 *       ->addUsuario([
 *           'CODIGO_PRESTADOR'          => '110010000001',
 *           'MODALIDAD_PAGO'            => ['04', 'Pago por evento', 'salud_modalidad_pago.gc'],
 *           'COBERTURA_PLAN_BENEFICIOS' => ['01', 'Plan de beneficios en salud financiado con UPC', 'salud_cobertura.gc'],
 *           'NUMERO_CONTRATO'           => 'CT-2026-01',
 *           'COPAGO'                    => '0',
 *       ]);
 *   $invoice->setCustomizationId(SectorSalud::SS_CUFE)->addExtension($salud->toExtension());
 */
final class SectorSalud
{
    /** Tipos de operación del sector salud (CustomizationID). */
    public const SS_CUFE       = 'SS-CUFE';
    public const SS_CUDE       = 'SS-CUDE';
    public const SS_POS        = 'SS-POS';
    public const SS_SNUM       = 'SS-SNum';
    public const SS_RECAUDO    = 'SS-Recaudo';
    public const SS_REPORTE    = 'SS-Reporte';
    public const SS_SIN_APORTE = 'SS-SinAporte';

    public const OPERACIONES = [
        self::SS_CUFE, self::SS_CUDE, self::SS_POS, self::SS_SNUM,
        self::SS_RECAUDO, self::SS_REPORTE, self::SS_SIN_APORTE,
    ];

    /** @var list<array<string,string|array{0:string,1:string,2?:string}>> */
    private array $usuarios = [];

    /**
     * @param string $actoAdministrativo "Tipo, identificador:año del acto administrativo", e.g. "Resolución 2275:2023"
     */
    public function __construct(
        private readonly string $actoAdministrativo,
        private readonly string $responsable = 'url www.minsalud.gov.co',
    ) {
    }

    /**
     * @param array<string,string|int|float|array{0:string,1:string,2?:string}> $campos
     *        Name => value, or Name => [código, nombre, lista (schemeName)]
     */
    public function addUsuario(array $campos): self
    {
        $usuario = [];
        foreach ($campos as $name => $value) {
            $usuario[$name] = is_array($value) ? $value : (string) $value;
        }
        $this->usuarios[] = $usuario;

        return $this;
    }

    /** @return list<array<string,string|array{0:string,1:string,2?:string}>> */
    public function getUsuarios(): array
    {
        return $this->usuarios;
    }

    public function toExtension(): CustomExtension
    {
        $collections = [];
        foreach ($this->usuarios as $usuario) {
            $info = [];
            foreach ($usuario as $name => $value) {
                $valueNode = is_array($value)
                    ? ['name' => 'Value', 'attrs' => array_filter(['schemeName' => $value[2] ?? '', 'schemeID' => $value[0]], static fn (string $v) => $v !== ''), 'text' => $value[1]]
                    : ['name' => 'Value', 'text' => $value];
                $info[] = ['name' => 'AdditionalInformation', 'children' => [['name' => 'Name', 'text' => (string) $name], $valueNode]];
            }
            $collections[] = ['name' => 'Collection', 'attrs' => ['schemeName' => 'Usuario'], 'children' => $info];
        }

        return new CustomExtension([[
            'name'     => 'CustomTagGeneral',
            'children' => [
                ['name' => 'Name', 'text' => 'Responsable'],
                ['name' => 'Value', 'text' => $this->responsable],
                ['name' => 'Name', 'text' => 'Tipo, identificador:año del acto administrativo'],
                ['name' => 'Value', 'text' => $this->actoAdministrativo],
                ['name' => 'Interoperabilidad', 'children' => [
                    ['name' => 'Group', 'attrs' => ['schemeName' => 'Sector Salud'], 'children' => $collections],
                ]],
            ],
        ]], true);
    }
}
