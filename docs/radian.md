# RADIAN — Eventos de Factura como Título Valor

RADIAN es el registro de la DIAN para que una factura electrónica pueda
circular como título valor. Cada movimiento se registra como un
`ApplicationResponse` firmado por la parte que ejecuta el evento.

## Eventos soportados

| Constante | Código | Descripción |
|---|---|---|
| `ACUSE_RECIBO` | `030` | El adquiriente acusa recibo de la factura |
| `RECLAMO` | `031` | El adquiriente reclama/rechaza la factura |
| `RECIBO_BIEN_SERVICIO` | `032` | El adquiriente confirma recepción del bien/servicio |
| `ACEPTACION_EXPRESA` | `033` | Aceptación expresa de la factura |
| `ACEPTACION_TACITA` | `034` | Aceptación tácita por vencimiento del plazo |
| `MANDATO` | `035` | Cobrador autorizado mediante mandato |
| `ENDOSO_PROPIEDAD` | `036` | Endoso en propiedad |
| `ENDOSO_GARANTIA` | `037` | Endoso en garantía |
| `ENDOSO_PROCURACION` | `038` | Endoso en procuración |
| `LIMITACION_CIRCULACION` | `039` | Limitación de circulación |
| `NOTIFICACION_PAGO` | `040` | Notificación de pago |
| `PAGO` | `042` | Pago de la factura |
| `AVALES` | `045` | Avales |

## Ejemplo: Acuse de Recibo

```php
use RoyaltyFusion\DianPhp\Radian\{ApplicationResponseBuilder, ApplicationResponseEvent, EventDocument};

$event = ApplicationResponseEvent::acuseRecibo();
// también: reciboBienServicio(), aceptacionExpresa(), aceptacionTacita(),
// reclamo('01'|'02'|'03'|'04')  →  01 inconsistencias, 02 mercancía no entregada
// totalmente, 03 parcialmente, 04 servicio no prestado

$evento = (new EventDocument())
    ->setId('EVT-001')
    ->setCude($cudeGenerado)            // CUDE de este evento
    ->setIssueDate(new DateTimeImmutable('now'))
    ->setEvent($event)
    ->setInvoiceCufe($cufeOriginal)     // CUFE de la factura referenciada
    ->setInvoiceId('FV-12345')
    ->setInvoiceDate($fechaFacturaOriginal)
    ->setReceiver($miEmpresa)            // quien firma el evento
    ->setSupplier($proveedorOriginal);   // emisor de la factura

$xml = (new ApplicationResponseBuilder())->build($evento);
```

Para facturas recibidas de proveedores es más simple partir del documento
recibido: `$dian->acknowledge($recibido, $evento, $id, $miEmpresa, $software)`.
Ver [recepción de documentos](./reception.md).

## Diferencias con la factura

* El XML root es `<ApplicationResponse>`, no `<Invoice>`.
* Dos `<ext:UBLExtension>`, como el ApplicationResponse que emite la propia
  DIAN: `sts:DianExtensions` (software, código de seguridad, QR) y el espacio
  para la firma, que `XadesSigner` ubica automáticamente.
* La firma es del **receptor del evento**, no del emisor de la factura
  original — usa un certificado distinto.
* No lleva `LegalMonetaryTotal` ni líneas.

## Envío

El facade calcula el CUDE (si no lo indicó), construye, firma y envía con
`SendEventUpdateStatus`:

```php
$evento->setSoftware($software);              // SoftwareID y PIN de quien genera el evento
$evento->setPerson('1010101010', 'Ana', 'Pérez', 'Almacenista', 'Bodega');   // requerido en 032

$dian   = new Dian($certReceptor, $clave, SoapClient::ENV_HABILITACION);
$result = $dian->sendEvent($evento);
```

Consultar los eventos registrados sobre una factura:

```php
$xml = $dian->getSoapClient()->getStatusEvent($cufeFactura);
```

## CUDE del evento

```
CUDE = SHA-384(NumEvento + FecEmi + HorEmi + NitEmisorEvento + DocReceptor
               + ResponseCode + NumFactura + DocumentTypeCode + PinSoftware)
```

Implementado en `EventCudeGenerator` (`buildString()` devuelve la cadena previa).
La estructura del evento valida contra el XSD; la fórmula y los campos
exigidos por cada código de evento deben confirmarse en habilitación RADIAN.
