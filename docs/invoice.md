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

## Variantes (CustomizationID)

| Tipo | Constante | CustomizationID |
|---|---|---|
| Estándar | `TipoOperacion::ESTANDAR` | `10` |
| AIU | `TipoOperacion::AIU` | `11` |
| Mandatos | `TipoOperacion::MANDATARIOS` | `12` |
| Transporte | `TipoOperacion::TRANSPORTE` | `13` |
| Notarios | `TipoOperacion::NOTARIOS` | `15` |

> Hoy la plantilla hardcodea `10`. La selección dinámica por `tipoOperacion`
> está en el roadmap como `Phase 4 follow-up`.
