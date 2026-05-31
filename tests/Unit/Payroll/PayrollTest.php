<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Payroll;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Payroll\CuneGenerator;
use RoyaltyFusion\DianPhp\Payroll\Empleador;
use RoyaltyFusion\DianPhp\Payroll\Pago;
use RoyaltyFusion\DianPhp\Payroll\PayrollDocument;
use RoyaltyFusion\DianPhp\Payroll\PayrollXmlBuilder;
use RoyaltyFusion\DianPhp\Payroll\Periodo;
use RoyaltyFusion\DianPhp\Payroll\Trabajador;

final class PayrollTest extends TestCase
{
    public function testTotalsAreComputedAndTruncated(): void
    {
        $doc = $this->makePayroll();
        $this->assertSame(2140606.0, $doc->getDevengados()->total()); // 2000000 + 140606
        $this->assertSame(160000.0, $doc->getDeducciones()->total());  // 80000 + 80000
        $this->assertSame(1980606.0, $doc->getComprobanteTotal());
    }

    public function testCuneIsDeterministicSha384(): void
    {
        $doc = $this->makePayroll();
        $gen = new CuneGenerator();

        $a = $gen->generate($doc, 'pin-test');
        $b = $gen->generate($doc, 'pin-test');

        $this->assertSame($a, $b);
        $this->assertSame(96, strlen($a));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{96}$/', $a);
    }

    public function testCuneVariesWithEnvironment(): void
    {
        $doc = $this->makePayroll();
        $gen = new CuneGenerator();

        $hab = $gen->generate($doc, 'pin');
        $doc->setAmbiente('1');
        $prod = $gen->generate($doc, 'pin');

        $this->assertNotSame($hab, $prod);
    }

    public function testBuilderRendersWellFormedNominaXml(): void
    {
        $doc  = $this->makePayroll();
        $cune = (new CuneGenerator())->generate($doc, 'pin');
        $xml  = (new PayrollXmlBuilder())->build($doc, $cune, 'https://example.test/qr');

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        $this->assertSame('NominaIndividual', $dom->documentElement->localName);

        $this->assertStringContainsString('TipoXML="102"', $xml);
        $this->assertStringContainsString('PeriodoNomina="5"', $xml);
        $this->assertStringContainsString('CUNE="' . $cune . '"', $xml);
        $this->assertStringContainsString('EncripCUNE="CUNE-SHA384"', $xml);
        $this->assertStringContainsString('<Devengados>', $xml);
        $this->assertStringContainsString('<Deducciones>', $xml);
        $this->assertStringContainsString('Sueldo="3000000.00"', $xml);
        $this->assertStringContainsString('SueldoTrabajado="2000000.00"', $xml);
        $this->assertStringContainsString('AuxilioTransporte="140606.00"', $xml);
        $this->assertStringContainsString('DevengadosTotal>2140606.00<', $xml);
        $this->assertStringContainsString('DeduccionesTotal>160000.00<', $xml);
        $this->assertStringContainsString('ComprobanteTotal>1980606.00<', $xml);
    }

    private function makePayroll(): PayrollDocument
    {
        $emp = (new Empleador())
            ->setNit('900123456')
            ->setNombreRazonSocial('Royalty Fusion S.A.S')
            ->setPais('CO')->setDepartamentoEstado('11')->setMunicipioCiudad('11001')
            ->setDireccion('Cra. 7 # 71-21');

        $tra = (new Trabajador())
            ->setTipoDocumento('13')->setNumeroDocumento('1010101010')
            ->setPrimerApellido('Pérez')->setPrimerNombre('Juan')
            ->setLugarTrabajoDepartamentoEstado('11')->setLugarTrabajoMunicipioCiudad('11001')
            ->setLugarTrabajoDireccion('Cra. 7 # 71-21')
            ->setSueldo(3000000.0)
            ->setCodigoTrabajador('EMP-0001');

        $per = (new Periodo())
            ->setFechaIngreso(new \DateTimeImmutable('2024-01-15'))
            ->setFechaLiquidacionInicio(new \DateTimeImmutable('2026-05-01'))
            ->setFechaLiquidacionFin(new \DateTimeImmutable('2026-05-31'))
            ->setTiempoLaborado(30);

        $pago = (new Pago())->setForma('1')->setMetodo('42')
            ->setBanco('Bancolombia')->setTipoCuenta('Ahorros')->setNumeroCuenta('1234567890');

        $software = (new Software())
            ->setId('uuid-payroll')->setPin('12345')->setProviderNit('900123456');

        $doc = (new PayrollDocument())
            ->setPrefijo('NE')->setNumero('1')
            ->setConsecutivo('1')
            ->setFechaGen(new \DateTimeImmutable('2026-05-31T10:00:00-05:00'))
            ->setFechaPago(new \DateTimeImmutable('2026-05-31'))
            ->setLugarPais('CO')->setLugarDepartamentoEstado('11')->setLugarMunicipioCiudad('11001')
            ->setSoftware($software)
            ->setEmpleador($emp)
            ->setTrabajador($tra)
            ->setPeriodo($per)
            ->setPago($pago);

        $doc->getDevengados()
            ->setBasicoDiasTrabajados(30)
            ->setBasicoSueldoTrabajado(2000000.0)
            ->setAuxilioTransporte(140606.0);

        $doc->getDeducciones()
            ->setSaludDeduccion(80000.0)
            ->setPensionDeduccion(80000.0);

        return $doc;
    }
}
