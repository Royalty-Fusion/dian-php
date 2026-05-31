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
├── Devengados     (28 campos opcionales)
└── Deducciones    (21 campos opcionales)
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

## Campos de Devengados

`setBasico*`, `setAuxilioTransporte`, `setViaticoManuAloj{S|NS}`, horas extras
(`setHorasExtra{Diurnas|Nocturnas}`, `setHorasRecargoDiurnoNocturno`),
`setVacaciones{Comunes|Compensadas}`, `setPrimas{|NS}`, cesantías
(`setCesantias`, `setInteresesCesantias`, `setPorcentajeInteresesCesantias`),
`setIncapacidades`, `setLicencias`, `setBonificaciones`, `setAuxilios`,
`setHuelgasLegales`, `setOtrosConceptos`, `setComisiones`, `setPagosTerceros`,
`setAnticipos`, `setDotacion`, `setApoyoSost`, `setTeletrabajo`,
`setBonificacionRetiro`, `setIndemnizacion`, `setReintegro`.

## Campos de Deducciones

`setSalud{Porcentaje|Deduccion}`, `setPension{Porcentaje|Deduccion}`,
`setFondoSPDeduccion{SP|Sub}`, `setSindicatos`, `setSanciones`, `setLibranzas`,
`setPagosTerceros`, `setAnticipos`, `setOtrasDeducciones`, `setPensionVoluntaria`,
`setRetencionFuente`, `setAfc`, `setCooperativa`, `setEmbargoFiscal`,
`setPlanComplementarios`, `setEducacion`, `setReintegro`, `setDeuda`.

## CUNE

```
CUNE = SHA-384(
  Numero + FechaGen + HoraGen +
  DevengadosTotal + DeduccionesTotal + ComprobanteTotal +
  NIT_Empleador + NumDoc_Trabajador +
  TipoXML + PinSoftware + Ambiente
)
```

Los totales se **truncan** a 2 decimales (no se redondean) según
especificación DIAN. El SDK ya aplica el truncado.

## Envío al WS

Nómina usa el **mismo endpoint VPFE** que las facturas — el discriminador es
el SOAP action `SendNominaSync` (síncrono, recibes la validación en el call).

```php
use RoyaltyFusion\DianPhp\Ws\SoapClient;
use RoyaltyFusion\DianPhp\Signer\XadesSigner;

$signed = (new XadesSigner('/path/cert.p12', 'pwd'))->sign($xml);
$result = (new SoapClient(SoapClient::ENV_HABILITACION))->sendNominaSync($signed, $cune);

echo $result->isSuccess() ? 'OK' : $result->getErrorMessage();
```

Ver [docs/connections-reference.md](./connections-reference.md) para la
tabla completa de las 13 operaciones SOAP soportadas.

## Pendiente

* **Nómina de Ajuste** (`TipoXML=103`) — reemplazo o eliminación de una nómina
  previa por CUNE.
* **Golden master** contra un XML real de Nómina aprobado.
