<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Tests\Unit\Payroll;

use PHPUnit\Framework\TestCase;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Payroll\CuneGenerator;
use RoyaltyFusion\DianPhp\Payroll\Empleador;
use RoyaltyFusion\DianPhp\Payroll\Pago;
use RoyaltyFusion\DianPhp\Payroll\PayrollAdjustment;
use RoyaltyFusion\DianPhp\Payroll\PayrollDocument;
use RoyaltyFusion\DianPhp\Signer\SignatureVerifier;
use RoyaltyFusion\DianPhp\Signer\XadesSigner;
use RoyaltyFusion\DianPhp\Tests\Support\TestCertificate;
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

    public function testFullPayrollFollowsTheDianStructure(): void
    {
        $doc = $this->makePayroll();
        $doc->addFechaPago(new \DateTimeImmutable('2026-05-15'))->addNota('Nómina de mayo & ajustes');
        $doc->getDevengados()
            ->addHoraExtra('HED', 4, 50000)
            ->addHoraExtra('HRN', 2, 10000, null, new \DateTimeImmutable('2026-05-10T21:00:00'), new \DateTimeImmutable('2026-05-10T23:00:00'))
            ->addVacacionesComunes(5, 500000, new \DateTimeImmutable('2026-05-02'), new \DateTimeImmutable('2026-05-06'))
            ->setPrimas(30, 250000)
            ->addIncapacidad(2, '1', 60000)
            ->addLicencia('NR', 1)
            ->addBonificacion(100000, 20000)
            ->addOtroConcepto('Auxilio de conectividad', 0, 30000)
            ->addComision(70000)
            ->setDotacion(90000);
        $doc->getDeducciones()
            ->setFondoSP(1.0, 30000)
            ->addLibranza('Crédito vivienda', 150000)
            ->setRetencionFuente(12000);

        // 2140606 + 50000 + 10000 + 500000 + 250000 + 60000 + 120000 + 30000 + 70000 + 90000
        $this->assertSame(3320606.0, $doc->getDevengados()->total());
        // 160000 + 30000 + 150000 + 12000
        $this->assertSame(352000.0, $doc->getDeducciones()->total());

        $xml = (new PayrollXmlBuilder())->build($doc, str_repeat('c', 96), 'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey=x');
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));

        $ns    = 'dian:gov:co:facturaelectronica:NominaIndividual';
        $order = [];
        foreach ($dom->documentElement->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $order[] = $child->localName;
                $this->assertSame($child->localName === 'UBLExtensions' ? 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2' : $ns, $child->namespaceURI);
            }
        }
        $this->assertSame([
            'UBLExtensions', 'Periodo', 'NumeroSecuenciaXML', 'LugarGeneracionXML', 'ProveedorXML', 'CodigoQR',
            'InformacionGeneral', 'Notas', 'Empleador', 'Trabajador', 'Pago', 'FechasPagos',
            'Devengados', 'Deducciones', 'DevengadosTotal', 'DeduccionesTotal', 'ComprobanteTotal',
        ], $order);

        $this->assertStringContainsString('<HEDs><HED Cantidad="4" Porcentaje="25.00" Pago="50000.00"/></HEDs>', $xml);
        $this->assertStringContainsString('<HRN HoraInicio="2026-05-10T21:00:00" HoraFin="2026-05-10T23:00:00" Cantidad="2" Porcentaje="35.00" Pago="10000.00"/>', $xml);
        $this->assertStringContainsString('<LicenciaNR Cantidad="1"/>', $xml);
        $this->assertStringContainsString('<Comisiones><Comision>70000.00</Comision></Comisiones>', $xml);
        $this->assertStringContainsString('<Dotacion>90000.00</Dotacion>', $xml);
        $this->assertStringContainsString('<Libranza Descripcion="Crédito vivienda" Deduccion="150000.00"/>', $xml);
        $this->assertStringContainsString('<FechaPago>2026-05-15</FechaPago>', $xml);
        $this->assertStringContainsString('<Notas>Nómina de mayo &amp; ajustes</Notas>', $xml);
        $this->assertStringContainsString('HoraGen="10:00:00-05:00"', $xml);
        $this->assertStringContainsString('DV="8"', $xml);   // DV of 900123456
        $this->assertStringContainsString('<ComprobanteTotal>2968606.00</ComprobanteTotal>', $xml);
    }

    public function testCuneFollowsTheAnexoOrder(): void
    {
        $this->assertSame(
            'NE12026-05-3110:00:00-05:002140606.00160000.001980606.009001234561010101010102123452',
            (new CuneGenerator())->buildString($this->makePayroll(), '12345')
        );
    }

    public function testReplacementAdjustmentNote(): void
    {
        $doc = PayrollAdjustment::reemplazar('NE1', str_repeat('a', 96), new \DateTimeImmutable('2026-05-31'));
        $this->copyInto($this->makePayroll(), $doc);
        $doc->setPrefijo('NA')->setNumero('1')->setConsecutivo('1');

        $cune = (new CuneGenerator())->generate($doc, '12345');
        $xml  = (new PayrollXmlBuilder())->build($doc, $cune, 'qr');
        $dom  = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));

        $this->assertSame('NominaIndividualDeAjuste', $dom->documentElement->localName);
        $this->assertSame('dian:gov:co:facturaelectronica:NominaIndividualDeAjuste', $dom->documentElement->namespaceURI);
        $this->assertStringContainsString('<TipoNota>1</TipoNota>', $xml);
        $this->assertStringContainsString('<ReemplazandoPredecesor NumeroPred="NE1" CUNEPred="' . str_repeat('a', 96) . '" FechaGenPred="2026-05-31"/>', $xml);
        $this->assertStringContainsString('TipoXML="103"', $xml);
        $this->assertStringContainsString('<Trabajador ', $xml);
        $this->assertStringContainsString('<ComprobanteTotal>1980606.00</ComprobanteTotal>', $xml);
    }

    public function testDeletionAdjustmentNoteHasNoWorkerNorAmounts(): void
    {
        $doc = PayrollAdjustment::eliminar('NE1', str_repeat('a', 96), new \DateTimeImmutable('2026-05-31'));
        $this->copyInto($this->makePayroll(), $doc);
        $doc->setPrefijo('NA')->setNumero('2')->setConsecutivo('2');

        $this->assertSame(
            'NA22026-05-3110:00:00-05:000.000.000.009001234560103123452',
            (new CuneGenerator())->buildString($doc, '12345')
        );

        $xml = (new PayrollXmlBuilder())->build($doc, 'x', 'qr');
        $this->assertStringContainsString('<Eliminar>', $xml);
        $this->assertStringContainsString('<EliminandoPredecesor NumeroPred="NE1"', $xml);
        $this->assertStringNotContainsString('<Trabajador', $xml);
        $this->assertStringNotContainsString('<Devengados', $xml);
        $this->assertStringNotContainsString('PeriodoNomina=', $xml);
    }

    public function testSignedPayrollKeepsAValidSignature(): void
    {
        $xml    = (new PayrollXmlBuilder())->build($this->makePayroll(), 'x', 'qr');
        $signed = (new XadesSigner(TestCertificate::path(), TestCertificate::PASSWORD))->sign($xml);

        $this->assertSame([], (new SignatureVerifier())->verify($signed));
    }

    private function copyInto(PayrollDocument $from, PayrollDocument $to): void
    {
        $to->setFechaGen($from->getFechaGen() ?? new \DateTimeImmutable())
            ->setFechaPago($from->getFechaPago() ?? new \DateTimeImmutable())
            ->setLugarPais('CO')->setLugarDepartamentoEstado('11')->setLugarMunicipioCiudad('11001')
            ->setSoftware($from->getSoftware() ?? new Software())
            ->setEmpleador($from->getEmpleador() ?? new Empleador())
            ->setTrabajador($from->getTrabajador() ?? new Trabajador())
            ->setPeriodo($from->getPeriodo() ?? new Periodo())
            ->setPago($from->getPago() ?? new Pago())
            ->setDevengados($from->getDevengados())
            ->setDeducciones($from->getDeducciones());
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
