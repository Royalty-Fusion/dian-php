# Nómina Electrónica (NIE)

Documento independiente de la factura electrónica, con su propio XSD y su
propio endpoint DIAN. Implementado en `src/Payroll/`.

## Estructura

```
PayrollDocument
├── Empleador      (NIT, razón social, ubicación)
├── Trabajador     (tipo, contrato, sueldo, ubicación)
├── Periodo        (fechas de ingreso, liquidación, retiro)
├── Pago           (forma, método, banco, cuenta)
├── Devengados     (todos los conceptos del esquema DIAN)
└── Deducciones    (todos los conceptos del esquema DIAN)
```

## Ejemplo mínimo

```php
use RoyaltyFusion\DianPhp\Payroll\{
    PayrollDocument, PayrollXmlBuilder, CuneGenerator,
    Empleador, Trabajador, Periodo, Pago
};
use RoyaltyFusion\DianPhp\Model\Software;

$nomina = (new PayrollDocument())
    ->setPrefijo('NE')->setNumero('1')->setConsecutivo('1')
    ->setFechaGen(new DateTimeImmutable('now'))
    ->setFechaPago(new DateTimeImmutable('now'))
    ->setLugarPais('CO')->setLugarDepartamentoEstado('11')->setLugarMunicipioCiudad('11001')
    ->setSoftware($software)
    ->setEmpleador($empleador)
    ->setTrabajador($trabajador)
    ->setPeriodo($periodo)
    ->setPago($pago);

$nomina->getDevengados()
    ->setBasicoSueldoTrabajado(3000000)
    ->setAuxilioTransporte(162000);

$nomina->getDeducciones()
    ->setSaludDeduccion(120000)
    ->setPensionDeduccion(120000);

$cune = (new CuneGenerator())->generate($nomina, $software->getPin());
$xml  = (new PayrollXmlBuilder())->build($nomina, $cune, $qrUrl);
```

## Devengados

Los elementos se generan en el orden del esquema DIAN; los totales
(`DevengadosTotal`, `DeduccionesTotal`, `ComprobanteTotal`) se calculan.

```php
$nomina->getDevengados()
    ->setBasicoDiasTrabajados(30)->setBasicoSueldoTrabajado(3000000)
    ->setAuxilioTransporte(162000)
    ->addHoraExtra('HED', 4, 62500)                 // HED, HEN, HRN, HEDDF, HRDDF, HENDF, HRNDF (porcentaje legal por defecto)
    ->addVacacionesComunes(5, 500000, new DateTimeImmutable('2026-05-04'), new DateTimeImmutable('2026-05-08'))
    ->setPrimas(30, 1500000)
    ->setCesantias(3000000, 12, 360000)
    ->addIncapacidad(2, '1', 133333)                // 1 común, 2 profesional, 3 laboral
    ->addLicencia('MP', 0, 0)                       // MP maternidad/paternidad, R remunerada, NR no remunerada
    ->addBonificacion(200000, 50000)                // salarial, no salarial
    ->addAuxilio(0, 100000)
    ->addOtroConcepto('Rodamiento', 0, 80000)
    ->addComision(150000)
    ->setDotacion(120000);
```

También: `addVacacionesCompensadas`, `addHuelgaLegal`, `addCompensacion`,
`addBonoEPCTV`, `addPagoTercero`, `addAnticipo`, `setViaticoManuAloj{S,NS}`,
`setApoyoSost`, `setTeletrabajo`, `setBonificacionRetiro`, `setIndemnizacion`,
`setReintegro`.

## Deducciones

```php
$nomina->getDeducciones()
    ->setSaludDeduccion(120000)->setPensionDeduccion(120000)   // 4 % por defecto
    ->setFondoSP(1, 30000)                                      // fondo de solidaridad pensional
    ->addSindicato(1, 30000)
    ->addLibranza('Crédito vivienda', 200000)
    ->setRetencionFuente(50000)
    ->setAfc(100000);
```

También: `addSancion`, `addPagoTercero`, `addAnticipo`, `addOtraDeduccion`,
`setPensionVoluntaria`, `setCooperativa`, `setEmbargoFiscal`,
`setPlanComplementarios`, `setEducacion`, `setReintegro`, `setDeuda`.

Varias fechas de pago: `addFechaPago()`. Razón social del proveedor
tecnológico: `setProveedorRazonSocial()` (por defecto la del empleador).

## CUNE

```
CUNE = SHA-384(
  Numero + FechaGen + HoraGen +
  DevengadosTotal + DeduccionesTotal + ComprobanteTotal +
  NIT_Empleador + NumDoc_Trabajador +
  TipoXML + PinSoftware + Ambiente
)
```

FechaGen y HoraGen en hora de Colombia (−05:00). En una nota de ajuste de
eliminación los totales son `0.00` y el documento del trabajador `0`.

## Envío al WS

Nómina usa el **mismo endpoint VPFE** que las facturas — el discriminador es
el SOAP action `SendNominaSync` (síncrono, recibes la validación en el call).

```php
$dian   = new Dian('/path/cert.p12', 'pwd', SoapClient::ENV_HABILITACION);
$result = $dian->sendPayroll($nomina, $testSetIdNomina);   // TestSetId solo en habilitación

echo $result->isSuccess() ? 'OK' : $result->getErrorMessage();
```

`sendPayroll()` fija el ambiente, calcula el CUNE con el PIN del software,
genera el XML en el namespace `dian:gov:co:facturaelectronica:NominaIndividual`,
lo firma y lo envía en ZIP.

Ver [docs/connections-reference.md](./connections-reference.md) para la
tabla completa de las 13 operaciones SOAP soportadas.

## Nota de ajuste (TipoXML 103)

Corrige o anula una nómina ya aceptada, referenciando su número, CUNE y fecha
de generación:

```php
use RoyaltyFusion\DianPhp\Payroll\PayrollAdjustment;

// Reemplazar: documento completo con los valores correctos.
$ajuste = PayrollAdjustment::reemplazar('NE1', $cuneOriginal, new DateTimeImmutable('2026-05-31'));
$ajuste->setPrefijo('NA')->setNumero('1')->setConsecutivo('1') /* … mismos datos que una nómina … */;
$dian->sendPayrollAdjustment($ajuste);

// Eliminar: sin trabajador ni valores.
$eliminar = PayrollAdjustment::eliminar('NE1', $cuneOriginal, new DateTimeImmutable('2026-05-31'))
    ->setPrefijo('NA')->setNumero('2')->setConsecutivo('2')
    ->setFechaGen(new DateTimeImmutable())->setSoftware($software)->setEmpleador($empleador)
    ->setLugarPais('CO')->setLugarDepartamentoEstado('11')->setLugarMunicipioCiudad('11001');
$dian->sendPayrollAdjustment($eliminar);
```

## Verificación

La estructura (orden de elementos, atributos y nombres) sigue el esquema de
nómina de la DIAN y se probó con nóminas completas, notas de reemplazo y de
eliminación. El SDK no incluye el XSD de nómina: confirme el resultado con el
set de pruebas de nómina en el portal.
