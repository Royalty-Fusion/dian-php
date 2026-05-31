# Documento Soporte en Adquisiciones (DS)

## ¿Cuándo emitirlo?

Cuando tu empresa **compra** bienes o servicios a un **proveedor que NO está
obligado a facturar electrónicamente** (típicamente personas naturales por
debajo del umbral de la UVT). Tu empresa toma el rol de emisor del DS frente
a DIAN para soportar el costo/gasto y el IVA descontable.

## Diferencias vs. Factura de Venta

| | Factura de Venta | Documento Soporte |
|---|---|---|
| `cbc:CustomizationID` | `10` | `11` |
| `cbc:InvoiceTypeCode` | `01` | `05` |
| `cbc:UUID schemeName` | `CUFE-SHA384` | `CUDS-SHA384` |
| Hash | CUFE | CUDS (algoritmo distinto) |
| Roles | Emisor=tu empresa, Cliente=adquiriente | Emisor=tu empresa, Cliente=proveedor no obligado |

## Uso mínimo

```php
use RoyaltyFusion\DianPhp\SupportDocument\{CudsGenerator, SupportDocument, SupportDocumentBuilder};
use RoyaltyFusion\DianPhp\Model\{Client, Company, Item, Software, Tax};

$ds = (new SupportDocument())
    ->setPrefijo('DS')->setNumero('1')
    ->setFecha(new DateTimeImmutable('now'))
    ->setCompany($miEmpresa)        // tu empresa = emisor
    ->setSupplier($proveedor)        // persona no obligada
    ->setSoftware($software)
    ->setTotal(59500.0)
    ->addItem($item)
    ->addTax($iva);

$cuds = (new CudsGenerator())->generate($ds, $software->getPin(), CudsGenerator::ENV_HABILITACION);
$xml  = (new SupportDocumentBuilder())->build($ds, $cuds, $qrUrl);
```

Ver `examples/support-document.php` para el ejemplo completo ejecutable.

## Algoritmo CUDS

```
CUDS = SHA-384(
  NumDS + FecDS + HoraDS + ValDS +
  CodImp1 + ValImp1 + CodImp2 + ValImp2 + CodImp3 + ValImp3 +
  ValTot + NitOFE + NumAdq + TipoAmb + PinSoftware
)
```

Mismo patrón que CUFE pero con el ambiente entre los datos del receptor y el PIN.

## Pendiente

* **Nota de Ajuste DS** (TipoDocumento=95) — modelo y template aún sin
  implementar. El emisor también es tu empresa; se referencia el CUDS del
  DS original.
