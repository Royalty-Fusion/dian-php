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

$event = (new ApplicationResponseEvent())
    ->setCode(ApplicationResponseEvent::ACUSE_RECIBO)
    ->setDescription('Acuse de recibo de la factura electrónica');

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

## Diferencias con la factura

* El XML root es `<ApplicationResponse>`, no `<Invoice>`.
* **Solo 1 `<ext:UBLExtension>`** (la factura tiene 2). El `XadesSigner`
  detecta esto automáticamente.
* La firma es del **receptor del evento**, no del emisor de la factura
  original — usa un certificado distinto.
* No lleva `LegalMonetaryTotal` ni líneas.

## Envío al WS

RADIAN usa el **mismo endpoint VPFE** con el SOAP action
`SendEventUpdateStatus` (síncrono).

```php
use RoyaltyFusion\DianPhp\Ws\SoapClient;

$signed = (new XadesSigner('/path/cert.p12', 'pwd'))->sign($xmlEvento);
$result = (new SoapClient(SoapClient::ENV_HABILITACION))->sendEventUpdateStatus($signed, $cudeEvento);
```

También puedes consultar los eventos registrados sobre un CUFE:

```php
$eventos = (new SoapClient(SoapClient::ENV_PRODUCCION))->getStatusEvent($cufeFactura);
```

## Pendiente

* **CUDE específico para eventos** (algoritmo distinto al de NC/ND) —
  implementar `EventCudeGenerator`.
