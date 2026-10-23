# Documento Soporte en Adquisiciones (DS)

## ¿Cuándo emitirlo?

Cuando su empresa **compra** bienes o servicios a un proveedor **no obligado a
facturar** (por ejemplo, personas naturales no responsables de IVA) o a un
proveedor del exterior. Su empresa genera y firma el documento para soportar
el costo y el IVA descontable (Resolución DIAN 000167 de 2021).

## Diferencias con la factura de venta

| | Factura de venta | Documento soporte |
|---|---|---|
| `cbc:CustomizationID` | `10` (tipo de operación) | `10` vendedor residente / `11` no residente |
| `cbc:InvoiceTypeCode` | `01` | `05` |
| `cbc:UUID schemeName` | `CUFE-SHA384` | `CUDS-SHA384` |
| Clave del hash | Clave técnica | PIN del software |
| `AccountingSupplierParty` | Su empresa | El vendedor no obligado (`setSupplier`) |
| `AccountingCustomerParty` | El cliente | Su empresa (`setCompany`), que firma |
| Líneas | — | `cac:InvoicePeriod` con la fecha de compra y la forma de transmisión |
| Resolución | De facturación | De documento soporte (prefijo y rango propios) |

## Uso

```php
use RoyaltyFusion\DianPhp\SupportDocument\SupportDocument;

$ds = (new SupportDocument())
    ->setPrefijo('DS')->setNumero('1')
    ->setFecha(new DateTimeImmutable())
    ->setCompany($miEmpresa)                 // adquiriente, emite y firma
    ->setSupplier($vendedor)                 // Client: persona no obligada
    ->setSoftware($software)
    ->setResolution($resolucionDs)
    ->addPayment((new Payment())->setMethodId('1')->setMeansId('10'))
    ->addItem((new Item())->setDescripcion('Servicio de transporte')->setCantidad(1)->setPrecio(500000));

$ds->setCustomizationId(SupportDocument::NO_RESIDENTE);                 // vendedor del exterior
$ds->setTransmissionCode(SupportDocument::TRANSMISION_ACUMULADO_SEMANAL); // compras acumuladas

$report = $dian->preflight($ds);              // mismas validaciones que la factura
$result = $dian->sendSupportDocument($ds);
```

## Nota de ajuste (tipo 95)

```php
use RoyaltyFusion\DianPhp\SupportDocument\SupportDocumentAdjustmentNote;

$nota = new SupportDocumentAdjustmentNote();
$nota->setPrefijo('NA')->setNumero('1')->setFecha(new DateTimeImmutable())
    ->setCompany($miEmpresa)->setSupplier($vendedor)->setSoftware($software)
    ->addItem($itemAjustado);
$nota->setBillingReference((new BillingReference())->setNumber('DS1')->setUuid($cudsOriginal)->setDate($fechaDs));
$nota->setDiscrepancyResponse((new DiscrepancyResponse())->setResponseCode('2')->setDescription('Anulación'));

$result = $dian->sendSupportDocument($nota);
```

## CUDS

```
CUDS = SHA-384(NumDS + FecDS + HorDS + ValDS + 01 + ValIva + ValTot
               + NumDocVendedor + NitAdquiriente + PinSoftware + TipoAmbiente)
```

`CudsGenerator::buildString()` devuelve la cadena previa al hash. El documento
y la nota de ajuste generados validan contra el XSD UBL 2.1 + DIAN; la fórmula
del CUDS debe confirmarse en el set de pruebas del documento soporte.
