# Recepción de documentos de proveedores

Las facturas de compra llegan por correo como **AttachedDocument** (el XML
firmado del proveedor + la respuesta de validación de la DIAN). El SDK las lee,
verifica y permite responderlas con los eventos RADIAN.

## Leer y verificar

```php
$doc = $dian->receive(file_get_contents('factura-proveedor.xml'));

if (!$doc->isAddressedTo($miNit)) { /* no es para nosotros */ }
if (!$doc->isTrustworthy())       { /* firma alterada o sin validación DIAN: $doc->signatureProblems */ }

$doc->type;                 // Invoice, CreditNote o DebitNote
$doc->typeCode;             // 01 venta, 02 exportación, 20 POS, 91 nota crédito…
$doc->number;               // FV7
$doc->uuid;                 // CUFE / CUDE
$doc->issueDate; $doc->dueDate; $doc->currency;
$doc->supplier;             // ['nit','dv','tipoDocumento','name','email','responsabilidades',…]
$doc->totals['payable'];    // también lineExtension, taxExclusive, taxInclusive, allowances, charges, prepaid
$doc->taxes;                // ['01' => 3254700.0, …]   por código de tributo
$doc->withholdings;         // ['06' => 428250.0, '07' => 189115.2]
$doc->lines;                // description, quantity, unitCode, price, lineExtension, code
$doc->billingReferences;    // notas: facturas corregidas
$doc->signedXml;            // guárdelo: es el soporte de la compra
```

`receive()` acepta también el XML firmado sin contenedor (en ese caso
`dianApproved` es `false`; consulte el estado con `$dian->getStatus($doc->uuid)`).

## Responder con eventos RADIAN

Orden que exige la DIAN para que la factura sea título valor:

1. **030** Acuse de recibo.
2. **032** Recibo del bien o prestación del servicio (indica quién recibió).
3. **033** Aceptación expresa — o **031** reclamo dentro de los 3 días
   hábiles siguientes al 032. Sin respuesta, el emisor puede registrar la
   aceptación tácita (034).

```php
use RoyaltyFusion\DianPhp\Radian\ApplicationResponseEvent;

$dian->acknowledge($doc, ApplicationResponseEvent::acuseRecibo(), 'EV-1', $miEmpresa, $software);
$dian->acknowledge($doc, ApplicationResponseEvent::reciboBienServicio(), 'EV-2', $miEmpresa, $software,
    ['1010101010', 'Ana', 'Pérez', 'Almacenista', 'Bodega']);
$dian->acknowledge($doc, ApplicationResponseEvent::aceptacionExpresa(), 'EV-3', $miEmpresa, $software);
// o: ApplicationResponseEvent::reclamo('02')   // mercancía no entregada totalmente
```

`acknowledge()` solo envía eventos sobre documentos dirigidos al NIT de
`$miEmpresa`, firma con el certificado configurado en `Dian` y calcula el
CUDE del evento con el PIN del software. Para armar el evento sin enviarlo:
`$doc->toEvent($evento, $id, $miEmpresa, $software)`.

## Consultas

```php
$doc       = $dian->getDocument($cufe);                 // descarga y lee un documento por CUFE/CUDE
$adquiente = $dian->getAcquirer('31', '900123456');     // nombre y correo de recepción registrados en la DIAN
$eventos   = $dian->getSoapClient()->getStatusEvent($cufe);    // eventos registrados (XML)
$notas     = $dian->getSoapClient()->getReferenceNotes($cufe); // notas que referencian la factura (XML)
```
