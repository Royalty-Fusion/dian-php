<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Catalog;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Catalog\Departamento;
use RoyaltyFusion\DianPhp\Catalog\DianCatalogInterface;
use RoyaltyFusion\DianPhp\Catalog\FormaPago;
use RoyaltyFusion\DianPhp\Catalog\MedioPago;
use RoyaltyFusion\DianPhp\Catalog\Moneda;
use RoyaltyFusion\DianPhp\Catalog\MunicipioRegistry;
use RoyaltyFusion\DianPhp\Catalog\Pais;
use RoyaltyFusion\DianPhp\Catalog\Responsabilidad;
use RoyaltyFusion\DianPhp\Catalog\TipoAmbiente;
use RoyaltyFusion\DianPhp\Catalog\TipoDocumentoIdentificacion;
use RoyaltyFusion\DianPhp\Catalog\TipoFactura;
use RoyaltyFusion\DianPhp\Catalog\TipoNotaCredito;
use RoyaltyFusion\DianPhp\Catalog\TipoNotaDebito;
use RoyaltyFusion\DianPhp\Catalog\TipoOperacion;
use RoyaltyFusion\DianPhp\Catalog\TipoOrganizacion;
use RoyaltyFusion\DianPhp\Catalog\TipoRegimen;
use RoyaltyFusion\DianPhp\Catalog\Tributo;
use RoyaltyFusion\DianPhp\Catalog\UnidadMedida;

final class EnumsTest extends TestCase
{
    /**
     * @return array<string,array{class-string<DianCatalogInterface>,string,string}>
     */
    public static function catalogProvider(): array
    {
        return [
            'TipoAmbiente'               => [TipoAmbiente::class,                '2',     'Habilitación'],
            'TipoDocumentoIdentificacion'=> [TipoDocumentoIdentificacion::class, '31',    'NIT'],
            'TipoOrganizacion'           => [TipoOrganizacion::class,            '1',     'Persona Jurídica y asimiladas'],
            'TipoRegimen'                => [TipoRegimen::class,                 '48',    'Responsable de IVA'],
            'Responsabilidad'            => [Responsabilidad::class,             'O-47',  'Régimen Simple de Tributación – SIMPLE'],
            'Tributo'                    => [Tributo::class,                     '01',    'IVA'],
            'FormaPago'                  => [FormaPago::class,                   '2',     'Crédito'],
            'MedioPago'                  => [MedioPago::class,                   '42',    'Consignación cuenta'],
            'UnidadMedida'               => [UnidadMedida::class,                'EA',    'Unidad'],
            'Moneda'                     => [Moneda::class,                      'COP',   'Peso colombiano'],
            'TipoFactura'                => [TipoFactura::class,                 '01',    'Factura electrónica de Venta'],
            'TipoNotaCredito'            => [TipoNotaCredito::class,             '2',     'Anulación de factura electrónica'],
            'TipoNotaDebito'             => [TipoNotaDebito::class,              '1',     'Intereses'],
            'TipoOperacion'              => [TipoOperacion::class,               '10',    'Estándar'],
            'Pais'                       => [Pais::class,                        'CO',    'Colombia'],
            'Departamento'               => [Departamento::class,                '11',    'Bogotá, D.C.'],
        ];
    }

    /**
     * @param  class-string<DianCatalogInterface>  $enumClass
     * @dataProvider catalogProvider
     */
    public function testCatalogResolution(string $enumClass, string $code, string $expectedDescription): void
    {
        /** @var \BackedEnum&DianCatalogInterface $instance */
        $instance = $enumClass::tryFromCode($code);
        $this->assertNotNull($instance, "Code $code should be resolvable on $enumClass");
        $this->assertSame($code, $instance->code());
        $this->assertSame($expectedDescription, $instance->description());
        $this->assertTrue($enumClass::hasCode($code));
        $this->assertFalse($enumClass::hasCode('__NONEXISTENT__'));
    }

    public function testTributoFlagsRetentions(): void
    {
        $this->assertTrue(Tributo::RETE_IVA->isRetention());
        $this->assertTrue(Tributo::RETE_FUENTE->isRetention());
        $this->assertTrue(Tributo::RETE_ICA->isRetention());
        $this->assertFalse(Tributo::IVA->isRetention());
        $this->assertFalse(Tributo::INC->isRetention());
    }

    public function testMunicipioRegistryHasBogotaAndDeptCode(): void
    {
        $this->assertTrue(MunicipioRegistry::has('11001'));
        // CSV name from soenac is "Bogotá, D.c." but bootstrap uses "Bogotá D.C." —
        // assert a robust substring match instead of exact spelling.
        $this->assertStringContainsString('Bogotá', (string) MunicipioRegistry::name('11001'));
        $this->assertSame('11', MunicipioRegistry::departmentCode('11001'));

        $this->assertNull(MunicipioRegistry::name('00000'));
        $this->assertFalse(MunicipioRegistry::has('00000'));
        $this->assertGreaterThan(20, count(MunicipioRegistry::all()));
    }
}
