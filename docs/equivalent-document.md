# Documento equivalente electrónico (POS y demás)

Resolución DIAN 165 de 2023: el tiquete de máquina registradora POS y los
demás documentos equivalentes se emiten electrónicamente. Se modelan con
`EquivalentDocument` (una `Invoice` con CUDE en lugar de CUFE).

| Tipo | Constante `TipoDocumentoEquivalente` | Código |
|---|---|---|
| Tiquete POS | `POS` | 20 |
| Boleta de cine | `CINE` | 25 |
| Espectáculos públicos | `ESPECTACULOS_PUBLICOS` | 27 |
| Juegos de suerte y azar | `JUEGOS` | 30 |
| Transporte terrestre de pasajeros | `TRANSPORTE_TERRESTRE` | 35 |
| Peajes | `PEAJES` | 40 |
| Extracto financiero | `EXTRACTO_FINANCIERO` | 45 |
| Transporte aéreo | `TRANSPORTE_AEREO` | 50 |
| Bolsa de valores | `BOLSA_VALORES` | 55 |
| Servicios públicos domiciliarios | `SERVICIOS_PUBLICOS` | 60 |

## Tiquete POS

```php
use RoyaltyFusion\DianPhp\Catalog\TipoDocumentoEquivalente;
use RoyaltyFusion\DianPhp\Model\{Client, EquivalentDocument};

$pos = (new EquivalentDocument(TipoDocumentoEquivalente::POS))
    ->setPrefijo('POS')->setNumero('1001')
    ->setFecha(new DateTimeImmutable())
    ->setCompany($company)
    ->setClient(Client::consumidorFinal())       // 222222222222, o el cliente identificado
    ->setSoftware($software)                      // el CUDE usa el PIN del software
    ->setResolution($resolucionPos)
    ->addPayment((new Payment())->setMethodId('1')->setMeansId('10'))
    ->addItem($item);

$pos->setFabricanteSoftware('Nombre Apellido', 'Fabricante S.A.S.', 'Mi POS')
    ->setPuntoVenta('CAJA-01', 'Bogotá - Local 1', 'Cajero', 'Mostrador', 'V-1001')
    ->setBeneficiosComprador('0', 'Consumidor final', 0);   // programa de puntos, si existe

$report = $dian->preflight($pos);   // reglas DEE_001..005 + XSD + firma
$result = $dian->send($pos);        // archivo "pos…", SendBillSync
```

El XML lleva `InvoiceTypeCode` 20, `ProfileID` "DIAN 2.1: Documento
Equivalente POS", `UUID schemeName="CUDE-SHA384"` y tres extensiones con los
datos del fabricante del software, los beneficios del comprador y la caja
(incluido el subtotal de la venta).

## Notas de ajuste

Una devolución o corrección del documento equivalente es una nota crédito
(94) o débito (93) de ajuste que referencia su **CUDE**:

```php
$nota = (new CreditNote())
    ->setAjusteDocumentoEquivalente(true)
    ->setBillingReference((new BillingReference())
        ->setNumber('POS1001')->setUuid($cudePos)->setDate($fechaPos)
        ->setUuidSchemeName('CUDE-SHA384'))
    /* … mismos datos que una nota crédito … */;
```

El preflight rechaza (`NOTE_006`) la nota de ajuste que referencia un CUFE.

## Otros tipos

Para los tipos distintos de POS (20), transporte terrestre (35) y servicios
públicos (60) el SDK usa el `ProfileID` genérico y no modela sus campos
particulares; agréguelos con `addExtension(new CustomExtension([...]))` si su
caso los exige, y confírmelos en la habilitación (`DEE_005`).
