# Factura Electrónica de Venta

## Mínimo necesario

```php
(new Invoice())
    ->setPrefijo('SETT')
    ->setNumero('990000001')
    ->setFecha(new DateTimeImmutable('now'))
    ->setCompany($company)
    ->setClient($client)
    ->setSoftware($software)
    ->setResolution($resolution)
    ->setTechnicalKey('...')
    ->setTotal(119000.0)
    ->addItem($item)
    ->addTax($iva);
```

## Descuentos a nivel línea

```php
use RoyaltyFusion\DianPhp\Model\AllowanceCharge;

$item->addAllowanceCharge(
    (new AllowanceCharge())
        ->setId(1)
        ->setChargeIndicator(false)   // false = descuento
        ->setReasonCode('00')          // ver tabla 5189
        ->setReason('Descuento por volumen')
        ->setAmount(5000.0)
        ->setBaseAmount(100000.0)
);
```

## Recargos a nivel cabecera

```php
$invoice->addAllowanceCharge(
    (new AllowanceCharge())
        ->setId(1)
        ->setChargeIndicator(true)    // true = recargo
        ->setReasonCode('01')
        ->setReason('Cargo logístico')
        ->setAmount(2500.0)
        ->setBaseAmount(119000.0)
);
```

## Anticipos

```php
use RoyaltyFusion\DianPhp\Model\Prepayment;

$invoice->addPrepayment(
    (new Prepayment())
        ->setId('ANT-001')
        ->setPaidAmount(50000.0)
        ->setPaidDate(new DateTimeImmutable('2026-05-15'))
);
```

El `PayableAmount` final se ajusta automáticamente:
`taxInclusive - prepaidAmount`.

## Retenciones (ReteIVA / ReteFuente / ReteICA)

```php
use RoyaltyFusion\DianPhp\Catalog\Tributo;

$reteIva = (new Tax())
    ->setCode(Tributo::RETE_IVA->code())
    ->setName('ReteIVA')
    ->setPercent(15.0)
    ->setBase(19000.0)
    ->setAmount(2850.0)
    ->setIsRetention(true);

$invoice->addTax($reteIva);
```

Las retenciones se renderizan en un bloque `<cac:WithholdingTaxTotal>`
separado del `<cac:TaxTotal>` normal, y no se suman al `PayableAmount`.

## Multi-moneda + TRM

```php
$invoice->setCurrencyCode('USD')
        ->setExchangeRate(3900.50);
```

## Notas y referencias

```php
use RoyaltyFusion\DianPhp\Model\AdditionalDocumentReference;

$invoice
    ->addNote('Factura emitida bajo el régimen SIMPLE.')
    ->addAdditionalDocumentReference(
        (new AdditionalDocumentReference())
            ->setType('OrderReference')
            ->setId('OC-12345')
            ->setIssueDate(new DateTimeImmutable('2026-05-15'))
    );
```

Tipos de referencia (se emiten en el orden del esquema UBL):

| Constante | Elemento | Uso |
|---|---|---|
| `AdditionalDocumentReference::ORDER` | `cac:OrderReference` | Orden de compra |
| `AdditionalDocumentReference::DESPATCH` | `cac:DespatchDocumentReference` | Remisión / guía de despacho |
| `AdditionalDocumentReference::RECEIPT` | `cac:ReceiptDocumentReference` | Recepción de mercancía |
| `AdditionalDocumentReference::CONTRACT` | `cac:ContractDocumentReference` | Contrato |
| `AdditionalDocumentReference::ADDITIONAL` | `cac:AdditionalDocumentReference` | Otro documento (`setDocumentTypeCode()`, `setDocumentType()`) |

## Impuestos de valor fijo por unidad

INC a las bolsas plásticas (22), impuesto al consumo de licores o al carbono:
no tienen base ni tarifa sino un valor por unidad.

```php
$item->addTax((new Tax())->setCode('22')->setPerUnit(66, 3));   // $66 × 3 bolsas
```

El XML lleva `BaseUnitMeasure` y `PerUnitAmount` en lugar de `TaxableAmount`
y `Percent`, y el valor no suma a la base gravable (`TaxExclusiveAmount`).

## Mandatos

Con `TipoOperacion::MANDATOS` (11), cada línea facturada por cuenta de un
tercero identifica al mandante:

```php
$invoice->setTipoOperacion(TipoOperacion::MANDATOS);
$item->setMandante('900123456');          // DV calculado si se omite
```

`Item::addProperty($nombre, $valor)` agrega `AdditionalItemProperty` (por
ejemplo los datos del RNDC en transporte de carga).

## Exportación (Incoterm y entrega)

```php
use RoyaltyFusion\DianPhp\Model\Delivery;

$invoice->setTipoFactura(TipoFactura::EXPORTACION)
    ->setCurrencyCode('USD')->setExchangeRate(3900.50)
    ->setDelivery((new Delivery())
        ->setIncoterm('FOB')                                  // descripción oficial automática
        ->setDate(new DateTimeImmutable('2026-06-01'))
        ->setAddress($direccionDeEntrega));
```

El preflight avisa (`INV_004`) si una exportación no tiene Incoterm y rechaza
Incoterms inexistentes (`INV_005`).

## Sector salud

La factura de servicios de salud lleva un tipo de operación `SS-*` y la
extensión de interoperabilidad del Ministerio de Salud. Los campos de cada
usuario los define la resolución vigente del Ministerio (Res. 506 de 2021 y sus
modificaciones, como la Res. 2275 de 2023): el SDK no los fija, se pasan con
su nombre oficial.

```php
use RoyaltyFusion\DianPhp\Sector\SectorSalud;

$salud = (new SectorSalud('Resolución 2275:2023'))
    ->addUsuario([
        'CODIGO_PRESTADOR' => '110010000001',
        'MODALIDAD_PAGO'   => ['04', 'Pago por evento', 'salud_modalidad_pago.gc'],   // [código, nombre, lista]
        'NUMERO_CONTRATO'  => 'CT-2026-01',
        'COPAGO'           => 0,
    ]);

$invoice->setTipoOperacion(SectorSalud::SS_CUFE)->addExtension($salud->toExtension());
```

## Variantes (CustomizationID)

| Tipo | Constante | CustomizationID |
|---|---|---|
| AIU | `TipoOperacion::AIU` | `09` |
| Estándar | `TipoOperacion::ESTANDAR` | `10` |
| Mandatos | `TipoOperacion::MANDATOS` | `11` |
| Transporte | `TipoOperacion::TRANSPORTE` | `12` |
| Cambiario | `TipoOperacion::CAMBIARIO` | `13` |
| Notarios | `TipoOperacion::NOTARIOS` | `14` |
| Compra / venta de divisas | `TipoOperacion::COMPRA_DIVISAS` / `VENTA_DIVISAS` | `15` / `16` |

Se selecciona con `$invoice->setTipoOperacion(TipoOperacion::AIU)`; el tipo de
factura (01 venta, 02 exportación, 03/04 contingencia) con
`$invoice->setTipoFactura(TipoFactura::EXPORTACION)`.
