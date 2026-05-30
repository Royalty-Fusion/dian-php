# Notas crédito y débito

## ¿Cuándo cada una?

| Caso | Documento |
|---|---|
| Devolución parcial de productos | Nota Crédito (cod. 1) |
| Anulación total de una factura | Nota Crédito (cod. 2) |
| Rebaja / descuento posterior | Nota Crédito (cod. 3) |
| Ajuste de precio a la baja | Nota Crédito (cod. 4) |
| Intereses por mora | Nota Débito (cod. 1) |
| Gastos por cobrar | Nota Débito (cod. 2) |
| Ajuste de precio al alza | Nota Débito (cod. 3) |

## Nota Crédito

```php
use RoyaltyFusion\DianPhp\Model\{BillingReference, CreditNote, DiscrepancyResponse};
use RoyaltyFusion\DianPhp\Catalog\TipoNotaCredito;

$creditNote = (new CreditNote())
    ->setPrefijo('NC')->setNumero('990000001')
    ->setFecha(new DateTimeImmutable('now'))
    ->setCompany($company)->setClient($client)->setSoftware($software)
    ->setBillingReference(
        (new BillingReference())
            ->setNumber('SETT990000123')
            ->setUuid($cufeOriginal)        // CUFE de la factura referenciada
            ->setDate(new DateTimeImmutable('2026-05-10'))
    )
    ->setDiscrepancyResponse(
        (new DiscrepancyResponse())
            ->setReferenceId('SETT990000123')
            ->setResponseCode(TipoNotaCredito::DEVOLUCION_PARCIAL->code())
            ->setDescription('Devolución de 2 unidades por defecto de fabricación')
    )
    ->setTotal(23800.0)
    ->addItem($itemDevuelto)
    ->addTax($ivaDevuelto);

$dian->send($creditNote);
```

### Nota Crédito sin referencia

Si **no conoces el CUFE original** (por ejemplo factura impresa antigua),
omite `setBillingReference()`. El SDK detecta el caso y cambia
`CustomizationID` de `20` a `22` automáticamente.

## Nota Débito

```php
use RoyaltyFusion\DianPhp\Model\DebitNote;
use RoyaltyFusion\DianPhp\Catalog\TipoNotaDebito;

$debitNote = (new DebitNote())
    ->setPrefijo('ND')->setNumero('990000001')
    ->setFecha(new DateTimeImmutable('now'))
    ->setCompany($company)->setClient($client)->setSoftware($software)
    ->setBillingReference($billingRef)
    ->setDiscrepancyResponse(
        (new DiscrepancyResponse())
            ->setReferenceId('SETT990000123')
            ->setResponseCode(TipoNotaDebito::INTERESES->code())
            ->setDescription('Intereses de mora del 2.5% mensual')
    )
    ->setTotal(2975.0)
    ->addItem($itemRecargo)
    ->addTax($ivaRecargo);

$dian->send($debitNote);
```

## Diferencias clave en el XML

| | Factura | Nota Crédito | Nota Débito |
|---|---|---|---|
| Hash | CUFE | CUDE | CUDE |
| Línea | `InvoiceLine` | `CreditNoteLine` | `DebitNoteLine` |
| Cantidad | `InvoicedQuantity` | `CreditedQuantity` | `DebitedQuantity` |
| Total monetario | `LegalMonetaryTotal` | `LegalMonetaryTotal` | `RequestedMonetaryTotal` |
| `CustomizationID` | 10 | 20 / 22 | 30 / 32 |
