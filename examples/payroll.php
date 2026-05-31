<?php

/**
 * Ejemplo: Nómina Individual Electrónica (NIE) — pago mensual.
 *
 * Salario base $3.000.000 + auxilio transporte, con deducciones de salud (4%)
 * y pensión (4%) sobre el sueldo trabajado.
 *
 *   $ php examples/payroll.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Payroll\CuneGenerator;
use RoyaltyFusion\DianPhp\Payroll\Empleador;
use RoyaltyFusion\DianPhp\Payroll\Pago;
use RoyaltyFusion\DianPhp\Payroll\PayrollDocument;
use RoyaltyFusion\DianPhp\Payroll\PayrollXmlBuilder;
use RoyaltyFusion\DianPhp\Payroll\Periodo;
use RoyaltyFusion\DianPhp\Payroll\Trabajador;

$empleador = (new Empleador())
    ->setNit('900123456')
    ->setNombreRazonSocial('Royalty Fusion S.A.S')
    ->setPais('CO')
    ->setDepartamentoEstado('11')
    ->setMunicipioCiudad('11001')
    ->setDireccion('Cra. 7 # 71-21 Of. 1101');

$trabajador = (new Trabajador())
    ->setTipoTrabajador('01')               // 01 = Dependiente
    ->setTipoDocumento('13')                // Cédula
    ->setNumeroDocumento('1010101010')
    ->setPrimerApellido('Pérez')
    ->setPrimerNombre('Juan')
    ->setSegundoApellido('Gómez')
    ->setOtrosNombres('Carlos')
    ->setLugarTrabajoPais('CO')
    ->setLugarTrabajoDepartamentoEstado('11')
    ->setLugarTrabajoMunicipioCiudad('11001')
    ->setLugarTrabajoDireccion('Cra. 7 # 71-21')
    ->setTipoContrato('1')                  // 1 = Indefinido
    ->setSueldo(3000000.0)
    ->setCodigoTrabajador('EMP-0001');

$periodo = (new Periodo())
    ->setFechaIngreso(new DateTimeImmutable('2024-01-15'))
    ->setFechaLiquidacionInicio(new DateTimeImmutable('2026-05-01'))
    ->setFechaLiquidacionFin(new DateTimeImmutable('2026-05-31'))
    ->setTiempoLaborado(30);

$pago = (new Pago())
    ->setForma('1')                          // 1 = Contado
    ->setMetodo('42')                        // 42 = Consignación
    ->setBanco('Bancolombia')
    ->setTipoCuenta('Ahorros')
    ->setNumeroCuenta('1234567890');

$software = (new Software())
    ->setId('uuid-software-nomina')
    ->setPin('12345')
    ->setProviderNit('900123456');

$nomina = (new PayrollDocument())
    ->setPrefijo('NE')->setNumero('1')->setConsecutivo('1')
    ->setFechaGen(new DateTimeImmutable('2026-05-31T10:00:00-05:00'))
    ->setFechaPago(new DateTimeImmutable('2026-05-31'))
    ->setLugarPais('CO')
    ->setLugarDepartamentoEstado('11')
    ->setLugarMunicipioCiudad('11001')
    ->setSoftware($software)
    ->setEmpleador($empleador)
    ->setTrabajador($trabajador)
    ->setPeriodo($periodo)
    ->setPago($pago);

// Devengados
$nomina->getDevengados()
    ->setBasicoDiasTrabajados(30)
    ->setBasicoSueldoTrabajado(3000000.0)
    ->setAuxilioTransporte(162000.0);

// Deducciones (4% salud + 4% pensión sobre el sueldo)
$nomina->getDeducciones()
    ->setSaludDeduccion(120000.0)
    ->setPensionDeduccion(120000.0);

$cune = (new CuneGenerator())->generate($nomina, $software->getPin());
$xml  = (new PayrollXmlBuilder())->build($nomina, $cune, "https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey={$cune}");

echo "CUNE: {$cune}\n";
echo "Devengados total: $" . number_format($nomina->getDevengados()->total(), 2, ',', '.') . "\n";
echo "Deducciones total: $" . number_format($nomina->getDeducciones()->total(), 2, ',', '.') . "\n";
echo "Comprobante total: $" . number_format($nomina->getComprobanteTotal(), 2, ',', '.') . "\n";
echo "XML generado: " . strlen($xml) . " bytes\n";
