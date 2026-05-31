<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Catalog;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Catalog\CountryRegistry;
use RoyaltyFusion\DianPhp\Catalog\CurrencyRegistry;
use RoyaltyFusion\DianPhp\Catalog\MunicipioRegistry;
use RoyaltyFusion\DianPhp\Catalog\PaymentMethodRegistry;
use RoyaltyFusion\DianPhp\Catalog\ResponsabilidadRegistry;
use RoyaltyFusion\DianPhp\Catalog\UnitMeasureRegistry;

/**
 * Smoke tests that confirm every registry is hydrated from the bundled
 * resources/catalogs/*.csv and exposes the expected canonical codes.
 */
final class RegistriesTest extends TestCase
{
    public function testMunicipioRegistryHasFullDaneList(): void
    {
        // soenac bundles ~1,100 municipalities — we should see at least 1,000.
        $this->assertGreaterThan(1000, count(MunicipioRegistry::all()));
        $this->assertTrue(MunicipioRegistry::has('11001'));
        $this->assertTrue(MunicipioRegistry::has('05001'));
        $this->assertTrue(MunicipioRegistry::has('76001'));
        $this->assertStringContainsString('Medellín', (string) MunicipioRegistry::name('05001'));
        $this->assertSame('11', MunicipioRegistry::departmentCode('11001'));
        $this->assertSame('05', MunicipioRegistry::departmentCode('05001'));
    }

    public function testCountryRegistryHasMajorIsoCodes(): void
    {
        $this->assertGreaterThan(200, count(CountryRegistry::all()));
        $this->assertSame('Colombia',   CountryRegistry::name('CO'));
        $this->assertSame('Alemania',   CountryRegistry::name('DE'));
        $this->assertTrue(CountryRegistry::has('US'));
        $this->assertFalse(CountryRegistry::has('ZZ'));
    }

    public function testUnitMeasureRegistryHasManyCodes(): void
    {
        // UN/ECE Rec 20 ships ~1,000 codes.
        $this->assertGreaterThan(900, count(UnitMeasureRegistry::all()));
        $this->assertTrue(UnitMeasureRegistry::has('KGM'));   // Kilogram
        $this->assertTrue(UnitMeasureRegistry::has('LTR'));   // Litre
        $this->assertTrue(UnitMeasureRegistry::has('HUR'));   // Hour
    }

    public function testResponsabilidadRegistryHasFullDianList(): void
    {
        // soenac ships ~110 historical responsabilidades codes.
        $this->assertGreaterThan(50, count(ResponsabilidadRegistry::all()));
        // O-13 (Gran contribuyente) is definitely in the bundled CSV.
        $this->assertTrue(ResponsabilidadRegistry::has('O-13'));
        $this->assertStringContainsString('Gran contribuyente', (string) ResponsabilidadRegistry::name('O-13'));
        // Newer codes like O-47 / R-99-PN remain in the Responsabilidad enum
        // because they post-date this CSV snapshot. The enum is the canonical
        // source for modern codes; the registry is the long-tail of history.
    }

    public function testPaymentMethodRegistryHasCommonUnCefactCodes(): void
    {
        $this->assertGreaterThan(50, count(PaymentMethodRegistry::all()));
        $this->assertTrue(PaymentMethodRegistry::has('10'));   // Efectivo
        $this->assertTrue(PaymentMethodRegistry::has('42'));   // Consignación
        $this->assertTrue(PaymentMethodRegistry::has('48'));   // Tarjeta crédito
    }

    public function testCurrencyRegistryHasFullIso4217(): void
    {
        $this->assertGreaterThan(150, count(CurrencyRegistry::all()));
        $this->assertTrue(CurrencyRegistry::has('COP'));
        $this->assertTrue(CurrencyRegistry::has('USD'));
        $this->assertTrue(CurrencyRegistry::has('EUR'));
    }
}
