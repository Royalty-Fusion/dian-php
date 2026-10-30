# Guía de integración: de cero a producción

Esta guía lleva un proyecto PHP (puro, Laravel o Symfony) desde la instalación
hasta la emisión en producción ante la DIAN. Sigue el orden real del proceso:

1. [Requisitos](#1-requisitos)
2. [Registro en el portal de la DIAN](#2-registro-en-el-portal-de-la-dian)
3. [Instalación y configuración](#3-instalación-y-configuración)
4. [Diagnóstico del entorno (`dian-doctor`)](#4-diagnóstico-del-entorno)
5. [Construir documentos](#5-construir-documentos)
6. [Validación previa al envío (preflight)](#6-validación-previa-al-envío-preflight)
7. [Set de pruebas de habilitación](#7-set-de-pruebas-de-habilitación)
8. [Paso a producción](#8-paso-a-producción)
9. [Entrega al adquiriente](#9-entrega-al-adquiriente)
10. [Integración por framework](#10-integración-por-framework)
11. [Operación en producción](#11-operación-en-producción)
12. [Errores y cómo resolverlos](#12-errores-y-cómo-resolverlos)
13. [Checklists](#13-checklists)
14. [Qué está verificado y qué no](#14-qué-está-verificado-y-qué-no)

---

## 1. Requisitos

| Requisito | Detalle |
|---|---|
| PHP | 8.1 – 8.4 con `dom`, `libxml`, `openssl`, `zip`, `json` |
| Certificado digital | `.p12`/`.pfx` de una entidad de certificación acreditada (Andes SCD, GSE, Certicámara…) a nombre de la empresa o de su representante |
| Registro DIAN | Facturador electrónico habilitado en el portal (sección 2) |
| Red | Salida HTTPS hacia `vpfe-hab.dian.gov.co` (habilitación) y `vpfe.dian.gov.co` (producción) |

> **OpenSSL 3 y certificados antiguos.** Muchos `.p12` colombianos usan cifrado
> RC2/3DES, que OpenSSL 3 no abre por defecto. El SDK lo detecta y muestra
> cómo convertirlo:
>
> ```bash
> openssl pkcs12 -legacy -in empresa.p12 -nodes -out tmp.pem
> openssl pkcs12 -export -in tmp.pem -out empresa-nuevo.p12   # pide nueva contraseña
> rm tmp.pem
> ```

## 2. Registro en el portal de la DIAN

En el portal de facturación electrónica de la DIAN (servicios en línea →
Factura electrónica → Habilitación):

1. **Registrar el software.** Elija *software propio* si su empresa genera el
   XML con este SDK. Defina un **nombre** y un **PIN** (normalmente 5 dígitos).
   La DIAN asigna un **SoftwareID** (UUID).
2. **Set de pruebas.** El portal muestra un **TestSetId** y cuántos
   documentos de cada tipo deben ser aceptados (facturas, notas crédito y
   notas débito). Guarde el TestSetId.
3. **Resolución de habilitación.** El set de pruebas usa el prefijo `SETP`,
   el rango `990000000 – 995000000` y la clave técnica pública
   `fc8eac422eba16e22ffd8c6f94b3f40a6e38162c`.
4. **Correo de recepción.** Registre el correo donde recibirá facturas de
   proveedores.

Con estos datos ya puede trabajar en habilitación:

| Dato | Dónde se usa |
|---|---|
| SoftwareID y PIN | `Software::setId()`, `Software::setPin()` |
| TestSetId | `Invoice::setTestSetId()` (solo habilitación) |
| Clave técnica | `Invoice::setTechnicalKey()` |
| NIT del proveedor del software | `Software::setProviderNit()` (su propio NIT si el software es propio) |

## 3. Instalación y configuración

```bash
composer require royaltyfusion/dian-php
```

Guarde los secretos fuera del código, por ejemplo en variables de entorno:

```dotenv
DIAN_ENVIRONMENT=habilitacion          # habilitacion | produccion
DIAN_CERT_PATH=/etc/dian/empresa.p12
DIAN_CERT_PASSWORD=********
DIAN_NIT=900123456                     # sin DV
DIAN_SOFTWARE_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
DIAN_SOFTWARE_PIN=12345
DIAN_TEST_SET_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
DIAN_TECHNICAL_KEY=fc8eac422eba16e22ffd8c6f94b3f40a6e38162c
```

El objeto principal es `Dian`: uno por facturador (certificado + ambiente).

```php
use RoyaltyFusion\DianPhp\Dian;

$dian = new Dian(getenv('DIAN_CERT_PATH'), getenv('DIAN_CERT_PASSWORD'), getenv('DIAN_ENVIRONMENT'));
```

Opciones útiles:

```php
$dian->setLogger($psr3Logger);                         // registra cada llamada a la DIAN
$dian->setSignerRole(XadesSigner::ROLE_THIRD_PARTY);    // si firma un proveedor tecnológico
$dian->setProviderCode('000');                          // código PPP en el nombre de los archivos
$dian->validateBeforeSend(true);                        // por defecto ya está activo
```

## 4. Diagnóstico del entorno

Antes de construir documentos, verifique entorno, certificado y conexión:

```bash
vendor/bin/dian-doctor --cert=/etc/dian/empresa.p12 --password='***' \
    --env=habilitacion --nit=900123456 --software-id=<SoftwareID>
```

(o `php bin/console dian:doctor` en Symfony). Revisa:

* **entorno:** versión de PHP y extensiones.
* **certificado:** que abra con la contraseña, vigencia (avisa 30 días antes
  del vencimiento) y que el NIT aparezca en el certificado.
* **esquemas:** XSD de UBL 2.1 + DIAN incluidos en el paquete.
* **dian:** una llamada real firmada a `GetNumberingRange`. Si responde, la
  DIAN aceptó su certificado (WS-Security) y el comando lista sus resoluciones
  con la **clave técnica** de cada una.

Termina con código de salida `0` cuando todo está bien, así que puede ir en
el pipeline de despliegue.

## 5. Construir documentos

### Datos del emisor, el adquiriente y el software

```php
use RoyaltyFusion\DianPhp\Model\{Address, Client, Company, Contact, Resolution, Software};

$company = (new Company())
    ->setNit('900123456')                 // sin DV: el DV se calcula
    ->setRazonSocial('Mi Empresa S.A.S.')
    ->setTipoDocumento('31')              // NIT
    ->setTipoOrganizacion('1')            // 1 jurídica, 2 natural
    ->setRegimen('48')
    ->setResponsabilidades('O-13;O-15')   // O-13, O-15, O-23, O-47 o R-99-PN
    ->setIndustryClassificationCode('6201')
    ->setAddress((new Address())
        ->setLine('Cra. 7 # 71-21')
        ->setCityCode('11001')->setCityName('Bogotá, D.C.')        // código DANE de 5 dígitos
        ->setDepartmentCode('11')->setDepartmentName('Bogotá'))
    ->setContact((new Contact())->setElectronicMail('facturacion@miempresa.com'));

$client = (new Client())
    ->setTipoDocumento('31')->setNumeroDocumento('800199436')     // el DV se calcula para NIT
    ->setRazonSocial('Cliente S.A.S.')
    ->setTipoOrganizacion('1')
    ->setResponsabilidades('O-13')
    ->setEmail('compras@cliente.com');

$software   = (new Software())->setId($softwareId)->setPin($pin)->setProviderNit('900123456');
$resolution = (new Resolution())
    ->setNumber('18760000001')->setPrefix('SETP')
    ->setFrom('990000000')->setTo('995000000')
    ->setDateFrom(new DateTimeImmutable('2019-01-19'))
    ->setDateTo(new DateTimeImmutable('2030-01-19'));
```

### Factura

Declare los impuestos **por línea**; el SDK calcula todos los totales,
agrupa los tributos del documento y redondea como la DIAN.

```php
use RoyaltyFusion\DianPhp\Catalog\{TipoFactura, TipoOperacion};
use RoyaltyFusion\DianPhp\Model\{AllowanceCharge, Invoice, Item, Payment, Tax};

$invoice = (new Invoice())
    ->setPrefijo('SETP')->setNumero('990000001')           // número solo dígitos
    ->setFecha(new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))
    ->setTipoFactura(TipoFactura::VENTA)                    // 02 exportación, 03/04 contingencia
    ->setTipoOperacion(TipoOperacion::ESTANDAR)             // 09 AIU, 11 mandatos, 12 transporte…
    ->setCompany($company)->setClient($client)
    ->setSoftware($software)->setResolution($resolution)
    ->setTechnicalKey($technicalKey)
    ->setTestSetId($testSetId)                              // solo en habilitación
    ->addPayment((new Payment())->setMethodId('2')->setMeansId('42')
        ->setDueDate(new DateTimeImmutable('+30 days')))    // 1 contado / 2 crédito
    ->addItem((new Item())
        ->setDescripcion('Licencia anual')
        ->setCantidad(2)->setPrecio(50000)->setUnitCode('94')
        ->addAllowanceCharge((new AllowanceCharge())->setChargeIndicator(false)
            ->setReasonCode('11')->setReason('Descuento')->setAmount(5000)->setBaseAmount(100000))
        ->addTax((new Tax())->setCode('01')->setPercent(19)->setBase(95000)));   // base = valor de la línea
```

Reglas que conviene recordar:

* La **base del impuesto** de una línea es su valor después de descuentos y
  cargos de línea (`cantidad × precio − descuentos + cargos`).
* El valor del tributo se calcula si no lo indica (`base × tarifa`).
* Las **retenciones** (`05` ReteIVA, `06` ReteRenta, `07` ReteICA) se marcan
  con `setIsRetention(true)`, no restan del valor a pagar y solo existen en
  facturas y documento soporte.
* Los **descuentos y cargos globales** (`Invoice::addAllowanceCharge`) y los
  **anticipos** (`addPrepayment`) afectan el valor a pagar, no la base gravable.
* `setTotal()` es opcional: si lo usa, el preflight avisa cuando no coincide
  con el total calculado.

Ver [factura](./invoice.md), [notas](./credit-debit-notes.md),
[documento soporte](./support-document.md), [nómina](./payroll.md) y
[RADIAN](./radian.md) para cada tipo.

### Notas crédito y débito

```php
$note = (new CreditNote())
    ->setPrefijo('NC')->setNumero('1')->setFecha(new DateTimeImmutable())
    ->setCompany($company)->setClient($client)->setSoftware($software)
    ->setBillingReference((new BillingReference())
        ->setNumber('SETP990000001')->setUuid($cufeFactura)->setDate($fechaFactura))
    ->setDiscrepancyResponse((new DiscrepancyResponse())
        ->setResponseCode('2')->setDescription('Anulación de factura'))
    ->addPayment((new Payment())->setMethodId('1')->setMeansId('10'))
    ->addItem(/* … */);
```

Sin factura de referencia (CustomizationID 22/32) indique
`setInvoicePeriod($desde, $hasta)`. Los CUDE usan el **PIN del software**.

## 6. Validación previa al envío (preflight)

`$dian->preflight($documento)` hace todo el proceso **sin enviar nada**:

| Sección | Qué revisa |
|---|---|
| reglas | ~60 reglas de negocio: emisor, adquiriente, municipio DANE, responsabilidades vigentes, unidades de medida, tributos y tarifas, medios de pago, resolución (prefijo, rango, vigencia), software y clave técnica, notas, ambiente |
| certificado | vigencia y NIT |
| xsd | el XML firmado contra los esquemas UBL 2.1 + DIAN incluidos |
| firma | recalcula los 3 digests XAdES y verifica la firma RSA |
| consistencia | recalcula **desde el XML** lo que recalcula la DIAN: CUFE/CUDE/CUDS, SoftwareSecurityCode, totales, tributos, número de líneas, ambiente, QR |

```php
$report = $dian->preflight($invoice);

if (!$report->isReady()) {
    foreach ($report->errors() as $error) {
        echo "{$error->code}: {$error->message}\n";       // p. ej. PARTY_007: Código de municipio DANE '99999' del emisor no existe.
    }
}
echo $report->toText();            // resumen legible
$json = $report->toArray();        // para su API o su log
```

`$dian->send()` ejecuta el mismo preflight automáticamente y **no envía** si
hay errores; los avisos no bloquean. Los códigos están documentados en
[validación](./validation.md).

## 7. Set de pruebas de habilitación

```php
$dian   = new Dian($cert, $password, SoapClient::ENV_HABILITACION);
$result = $dian->send($invoice->setTestSetId($testSetId));

if ($result->isPending()) {                       // el set de pruebas es asíncrono
    $status = $dian->waitForResult($result->getZipKey());
    echo $status->isValid() ? 'Aceptado' : implode("\n", $status->getErrorMessages());
}
```

1. Envíe la cantidad de facturas, notas crédito y notas débito que pide el
   portal, con números consecutivos dentro del rango `SETP990000000+`.
2. Las notas deben referenciar facturas del mismo set (use el CUFE que
   devolvió `$result->getCufe()`).
3. Cuando el portal muestre el set **aceptado**, la DIAN lo habilita para
   producción.

Sin `setTestSetId()` el documento se envía por `SendBillSync` y no cuenta
para el set (el preflight lo avisa con `ENV_002`).

## 8. Paso a producción

1. **Resolución real.** Solicite la numeración de facturación en el portal
   (formulario 1876) y asocie el **prefijo** al software.
2. **Clave técnica real.** Obténgala con:
   ```php
   $rangos = $dian->getNumberingRanges('900123456', $softwareId);
   // [['ResolutionNumber' => …, 'Prefix' => 'FE', 'FromNumber' => …, 'ToNumber' => …, 'TechnicalKey' => '…'], …]
   ```
   o con `dian-doctor --env=produccion`.
3. **Cambie el ambiente** a `produccion`. El SDK ajusta automáticamente:
   endpoint `vpfe.dian.gov.co`, `ProfileExecutionID=1`, ambiente del CUFE,
   URL del QR y envío por `SendBillSync` (respuesta inmediata).
4. Quite `setTestSetId()` (en producción se ignora).

El preflight bloquea los errores típicos del cambio de ambiente: clave
técnica de habilitación en producción (`ENV_001`) y prefijo `SETP` en
producción (`ENV_003`).

## 9. Entrega al adquiriente

La factura válida se entrega al cliente como **AttachedDocument** (XML
firmado + ApplicationResponse de la DIAN) junto con la representación
gráfica en PDF:

```php
$result = $dian->send($invoice);
if ($result->isSuccess()) {
    $attachedXml = $dian->buildAttachedDocument($invoice, $result);     // ya firmado
    $html        = (new HtmlReport())->render($invoice, $result->getCufe(), QrGenerator::url($result->getCufe(), true));
    // Convierta $html a PDF (dompdf/mpdf), comprima XML + PDF en un .zip
    // y envíelo al correo del adquiriente.
}
```

Ver [AttachedDocument](./attached-document.md) y [representación gráfica](./report.md).

### Otros documentos

* **POS y documentos equivalentes**: [documento equivalente](./equivalent-document.md).
* **Nómina y notas de ajuste de nómina**: [nómina](./payroll.md).
* **Compras a no obligados a facturar**: [documento soporte](./support-document.md).
* **Exportación, mandatos, AIU, sector salud, impuestos por unidad**: [factura](./invoice.md).

### Facturas de proveedores

Lea las facturas de compra que llegan por correo, verifique firma y
validación DIAN y respóndalas con los eventos RADIAN (acuse, recibo,
aceptación o reclamo): [recepción de documentos](./reception.md).

## 10. Integración por framework

### PHP puro

```php
require 'vendor/autoload.php';

$dian   = new Dian(getenv('DIAN_CERT_PATH'), getenv('DIAN_CERT_PASSWORD'), getenv('DIAN_ENVIRONMENT'));
$result = $dian->send($invoice);
```

Los ejemplos de `examples/` funcionan sin configuración (modo simulación con
un certificado temporal) y con `--send` envían usando las variables `DIAN_*`.

### Laravel

`config/dian.php`:

```php
return [
    'environment' => env('DIAN_ENVIRONMENT', 'habilitacion'),
    'cert_path'   => env('DIAN_CERT_PATH'),
    'cert_pass'   => env('DIAN_CERT_PASSWORD'),
];
```

`app/Providers/AppServiceProvider.php`:

```php
use RoyaltyFusion\DianPhp\Dian;

public function register(): void
{
    $this->app->singleton(Dian::class, function ($app) {
        return (new Dian(config('dian.cert_path'), config('dian.cert_pass'), config('dian.environment')))
            ->setLogger($app->make('log'));
    });
}
```

Envío en cola (recomendado para no bloquear la petición HTTP):

```php
final class EnviarFacturaDian implements ShouldQueue
{
    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(private int $facturaId) {}

    public function handle(Dian $dian, FacturaMapper $mapper): void
    {
        $factura = Factura::findOrFail($this->facturaId);
        if ($factura->estado_dian === 'aceptada') {
            return;                                   // idempotente
        }
        $result = $dian->send($mapper->toInvoice($factura));

        $factura->update([
            'cufe'           => $result->getCufe(),
            'xml_firmado'    => $result->getSignedXml(),
            'respuesta_dian' => $result->getApplicationResponseXml(),
            'zip_key'        => $result->getZipKey(),
            'estado_dian'    => $result->isSuccess() ? 'aceptada' : ($result->isPending() ? 'pendiente' : 'rechazada'),
            'errores_dian'   => $result->getErrors(),
        ]);
    }
}
```

Diagnóstico en el servidor: `vendor/bin/dian-doctor` con las mismas variables
de entorno (`DIAN_CERT_PATH`, `DIAN_CERT_PASSWORD`, `DIAN_NIT`,
`DIAN_SOFTWARE_ID`).

### Symfony

```yaml
# config/packages/dian.yaml
dian:
  environment: '%env(DIAN_ENVIRONMENT)%'
  nit: '%env(DIAN_NIT)%'
  certificate:
    path: '%env(DIAN_CERT_PATH)%'
    password: '%env(DIAN_CERT_PASSWORD)%'
  software:
    id: '%env(DIAN_SOFTWARE_ID)%'
    pin: '%env(DIAN_SOFTWARE_PIN)%'
    provider_nit: '%env(DIAN_NIT)%'
  validate_before_send: true
```

Inyecte `RoyaltyFusion\DianPhp\Dian` donde lo necesite. Comandos:
`dian:doctor` y `dian:status <CUFE|ZipKey> [--zip]`. Ver [Symfony](./symfony.md).

## 11. Operación en producción

* **Guarde siempre**: XML firmado, CUFE/CUDE, `ApplicationResponse`,
  `ZipKey`, nombre de archivo y fecha de envío. La DIAN exige conservarlos.
* **Numeración.** Use `NumberingManager` con `PdoNumberingStore`: asigna el
  número en una transacción y alerta antes de agotar el rango o de que venza
  la resolución. Nunca reutilice un número ya enviado, aunque haya sido
  rechazado. Ver [consecutivos](./numbering.md).
* **Idempotencia.** Si la llamada se cae por timeout, **consulte primero**
  `$dian->getStatus($cufe)` antes de reenviar. Un documento repetido se
  rechaza como procesado anteriormente.
* **Reintentos.** El cliente SOAP reintenta solo ante errores de red y HTTP
  5xx (2 reintentos con espera exponencial). Los rechazos de validación no se
  reintentan: hay que corregir el documento.
* **Logs.** Pase un logger PSR-3 (`setLogger`): registra operación, código
  HTTP, duración y SOAP Faults, sin el contenido del documento.
* **Vencimientos.** Programe `dian-doctor` a diario: avisa 30 días antes de que
  venza el certificado y cuando la resolución vence o supera el 90 % del rango.
* **Hora.** Las fechas se convierten a hora de Colombia (UTC−5) en el XML y
  el CUFE. Mantenga el reloj del servidor sincronizado (NTP): WS-Security
  firma un *timestamp*.

## 12. Errores y cómo resolverlos

### Errores del SDK (antes de enviar)

| Mensaje | Causa | Solución |
|---|---|---|
| `No fue posible abrir el certificado PKCS#12… cifrado heredado` | `.p12` con RC2/3DES y OpenSSL 3 | Convertir el certificado (sección 1) |
| `CERT_001` | Certificado vencido | Renovar con la entidad certificadora |
| `PARTY_007` | Código DANE inexistente o departamento que no corresponde | Use el código de 5 dígitos del municipio (`MunicipioRegistry`) |
| `PARTY_008` | Responsabilidad fiscal histórica (O-48, O-49…) | Use O-13, O-15, O-23, O-47 o R-99-PN según el RUT |
| `SOFT_003` | Falta la clave técnica | `getNumberingRanges()` o `dian-doctor` |
| `RES_001` / `RES_003` | Número fuera de rango o prefijo distinto | Revise la resolución asociada al software |
| `XML_010` | El CUFE del XML no coincide con el recalculado | Clave técnica/PIN o ambiente incorrectos |

### Respuestas de la DIAN

`$result->getErrors()` trae los rechazos y `$result->getNotifications()` los
avisos que no rechazan. Casos frecuentes:

| Respuesta DIAN | Causa probable | Qué hacer |
|---|---|---|
| SOAP Fault `InvalidSecurity` | Certificado no aceptado, vencido o reloj desfasado | `dian-doctor`; verifique certificado y NTP |
| `FAD06` CUFE mal calculado | Clave técnica de otra resolución o ambiente cruzado | Use la clave de `GetNumberingRange` para ese prefijo |
| Documento procesado anteriormente | Reenvío del mismo número | Consulte el estado con `getStatus($cufe)` |
| Notificación: NIT/nombre no coincide con el RUT | Datos del adquiriente distintos al RUT | Corrija la razón social o el documento del cliente |
| Rechazos de la resolución | Prefijo no asociado al software o resolución vencida | Asocie la numeración en el portal |
| `StatusCode 66` / en proceso | Envío asíncrono aún en validación | `waitForResult($zipKey)` |

## 13. Checklists

### Antes de enviar el set de pruebas

- [ ] `dian-doctor` termina en `LISTO` y lista la resolución de habilitación.
- [ ] Software registrado; SoftwareID y PIN configurados.
- [ ] `setTestSetId()` con el TestSetId del portal.
- [ ] Prefijo `SETP`, números consecutivos en `990000000–995000000`.
- [ ] Clave técnica `fc8eac422eba16e22ffd8c6f94b3f40a6e38162c`.
- [ ] `preflight()` de cada tipo de documento sin errores.
- [ ] Las notas referencian el CUFE de facturas aceptadas del set.

### Antes de producción

- [ ] Set de pruebas aceptado en el portal.
- [ ] Resolución de numeración real asociada al software.
- [ ] Clave técnica real (no la de habilitación).
- [ ] `DIAN_ENVIRONMENT=produccion` y `dian-doctor --env=produccion` en `LISTO`.
- [ ] Persistencia de XML firmado, CUFE y ApplicationResponse.
- [ ] Envío del AttachedDocument + PDF al correo del adquiriente.
- [ ] Monitoreo de vencimiento del certificado y de la resolución.

## 14. Qué está verificado y qué no

Verificado automáticamente en la suite de pruebas:

* **Firma XAdES-EPES**: el verificador del SDK acepta las firmas de
  documentos reales aprobados por la DIAN y las del propio SDK; las firmas del
  SDK también se validaron con una librería independiente (signxml).
* **CUFE**: reproduce el ejemplo oficial del Anexo Técnico.
* **XSD**: todos los documentos generados (factura, notas, documento soporte
  y su nota de ajuste, eventos RADIAN) validan contra UBL 2.1 + DIAN, igual
  que los documentos reales incluidos en `resources/fixtures`.
* **Consistencia**: el recálculo de totales no da falsos positivos sobre los
  documentos reales.
* **WS-Security**: la firma de la cabecera SOAP se verificó con una
  implementación independiente de C14N exclusivo.
* **Recepción**: la lectura y la verificación de firmas se prueban con
  AttachedDocuments reales aprobados por la DIAN (factura nacional, exportación,
  con retenciones y notas crédito).
* **POS, referencias, impuestos por unidad, mandatos, exportación y sector
  salud**: los documentos generados pasan el preflight completo (XSD, firma y
  recálculo del CUDE/CUFE).

Pendiente de confirmar contra la DIAN en vivo (no hay acceso de red a la DIAN
desde el entorno de desarrollo del SDK). Ejecute `dian-doctor` y el set de
pruebas, y reporte cualquier diferencia:

* Aceptación de la cabecera WS-Security por el servicio de la DIAN.
* Fórmulas de CUDS (documento soporte) y del CUDE de eventos RADIAN, y la
  estructura de los eventos.
* Nómina electrónica y su nota de ajuste: no hay XSD de nómina incluido ni
  documentos reales de referencia.
* Documento equivalente POS y los campos de la extensión del sector salud
  (dependen de la resolución vigente del Ministerio de Salud).
* Parámetros de `GetAcquirer` y `GetExchangeEmails`, y los nombres de los
  campos de la respuesta de `GetAcquirer` (`ReceiverName`, `ReceiverEmail`)
  que lee `Dian::getAcquirer()`; si su caso difiere,
  `SoapClient::call('Operacion', [...])` permite llamar cualquier operación.
