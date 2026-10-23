# Validación previa al envío

La DIAN rechaza un documento por cientos de reglas. El SDK detecta localmente
las causas de rechazo más comunes **antes** de enviar, con un código estable y
un mensaje en español.

| Componente | Qué hace |
|---|---|
| `BusinessRuleValidator` | Reglas de negocio sobre los objetos PHP (datos faltantes, catálogos, cálculos, resolución, ambiente). |
| `CertificateChecker` | Vigencia del certificado y NIT del titular. |
| `XsdValidator` | XML contra los esquemas UBL 2.1 + extensiones DIAN incluidos en `resources/xsd` (sin red). |
| `SignatureVerifier` | Recalcula los digests XAdES y verifica la firma RSA. |
| `XmlConsistencyChecker` | Recalcula **desde el XML** lo mismo que la DIAN: CUFE/CUDE/CUDS, SoftwareSecurityCode, totales y tributos. |

`Dian::preflight()` ejecuta todos y devuelve un `PreflightReport`;
`Dian::send()` lo ejecuta automáticamente y no envía si hay errores
(desactivable con `validateBeforeSend(false)`).

```php
$report = $dian->preflight($invoice);

$report->isReady();     // true si no hay errores (los avisos no bloquean)
$report->errors();      // list<ValidationError> con code, message, severity, xpath
$report->warnings();
$report->toText();      // resumen por sección
$report->toArray();     // para APIs/logs
```

Cada componente también se puede usar por separado:

```php
$result = (new BusinessRuleValidator())->validate($invoice, '2');   // '1' producción, '2' habilitación
$result = (new XsdValidator())->validate($signedXml);               // elige el XSD según la raíz
$result = (new XmlConsistencyChecker())->check($signedXml, $claveTecnicaOPin, $pin, '2');
```

## Códigos

`error` bloquea el envío; `aviso` (warning) solo informa.

### Documento

| Código | Severidad | Regla |
|---|---|---|
| `DOC_001` | error | El documento no tiene líneas |
| `DOC_002` | error | Sin fecha de emisión, o fecha en el futuro |
| `DOC_003` | aviso | Fecha de emisión con más de 10 días |
| `DOC_004` | error | Número no numérico o prefijo de más de 4 caracteres |
| `DOC_005` | aviso | `setTotal()` distinto del valor a pagar calculado |

### Emisor y adquiriente

| Código | Severidad | Regla |
|---|---|---|
| `PARTY_001` | error | NIT del emisor vacío o no numérico |
| `PARTY_002` | error | DV configurado distinto del calculado |
| `PARTY_003` / `PARTY_004` | error | Tipo de documento fuera de la tabla DIAN |
| `PARTY_005` | error | Sin razón social |
| `PARTY_006` | error | Emisor sin dirección |
| `PARTY_007` | error | Municipio DANE inexistente o departamento que no corresponde |
| `PARTY_008` | error | Responsabilidades vacías o no vigentes (válidas: O-13, O-15, O-23, O-47, R-99-PN) |
| `PARTY_009` | error | Tipo de organización distinto de 1 o 2 |
| `PARTY_010` | aviso | Emisor sin correo |
| `PARTY_011` | error | Falta el adquiriente |
| `PARTY_012` | error | Documento del adquiriente vacío, o NIT con DV o guiones |
| `PARTY_013` | aviso / error | Adquiriente sin correo (aviso) o con correo inválido (error) |
| `PARTY_014` | aviso | Dirección sin línea |

### Líneas y tributos

| Código | Severidad | Regla |
|---|---|---|
| `ITEM_001` – `ITEM_003` | error | Descripción vacía, cantidad ≤ 0, precio negativo |
| `ITEM_004` | error | Unidad de medida fuera de UN/ECE Rec 20 |
| `ITEM_005` | error | Línea gratuita sin precio de referencia |
| `ITEM_006` | aviso | Base del tributo distinta del valor de la línea |
| `TAX_001` | error | Código de tributo desconocido, o retención con código que no es 05/06/07 |
| `TAX_002` | error | Valor ≠ base × tarifa |
| `TAX_003` | aviso | Tarifa de IVA distinta de 0, 5 o 19 % |
| `TAX_004` | error | Retenciones en notas (UBL no las admite) |
| `TAX_005` | aviso | Documento sin tributos |
| `TAX_006` | error | Tributos del documento ≠ suma de los de las líneas |
| `TOTAL_002` | error | Valor a pagar negativo |
| `CURRENCY_001` | error | Moneda fuera de ISO 4217 |
| `CURRENCY_002` | error | Moneda extranjera sin tasa de cambio |

### Pagos, resolución y software

| Código | Severidad | Regla |
|---|---|---|
| `PAY_001` | error | Sin medio de pago |
| `PAY_002` | error | Crédito sin fecha de vencimiento |
| `PAY_003` | error | Forma de pago distinta de 1 o 2 |
| `PAY_004` | aviso | Medio de pago fuera del catálogo |
| `RES_000` | error | Sin resolución |
| `RES_001` | error | Número fuera del rango autorizado |
| `RES_002` | error | Fecha fuera de la vigencia |
| `RES_003` | error | Prefijo distinto al de la resolución |
| `RES_004` / `RES_005` | aviso | Más del 90 % del rango usado / vence en menos de 30 días |
| `SOFT_001` | error | Sin SoftwareID o PIN |
| `SOFT_002` / `SOFT_004` | aviso | SoftwareID sin formato UUID / PIN que no es de 5 dígitos |
| `SOFT_003` | error | Sin clave técnica (o con formato inválido) |

### Notas, tipos de documento y ambiente

| Código | Severidad | Regla |
|---|---|---|
| `NOTE_001` | error | Sin concepto de corrección |
| `NOTE_002` | error | Concepto fuera de la tabla (NC 1–6, ND 1–4) o sin descripción |
| `NOTE_003` | error | Referencia sin número, fecha o con CUFE inválido |
| `NOTE_004` | error | Nota sin referencia y sin periodo facturado |
| `NOTE_005` | error | CustomizationID que no corresponde (20/22, 30/32) |
| `INV_001` | error | Tipo de factura fuera de 01–04 |
| `INV_002` | aviso | Exportación en COP o con adquiriente en Colombia |
| `INV_003` | error | Tipo de operación que no aplica a facturas |
| `DS_001` – `DS_003` | error / aviso | Documento soporte: residencia, forma de transmisión, documento del vendedor |
| `ENV_001` | error | Clave técnica de habilitación en producción |
| `ENV_002` | aviso | Habilitación sin TestSetId |
| `ENV_003` | error | Prefijo del set de pruebas en producción |

### Certificado, XML y firma

| Código | Severidad | Regla |
|---|---|---|
| `CERT_000` | error | No se puede abrir el certificado |
| `CERT_001` | error | Certificado vencido o aún no vigente |
| `CERT_002` | aviso | Vence en menos de 30 días |
| `CERT_003` | aviso | El NIT no aparece en el certificado |
| `XML_000` / `XML_001` | error | No se pudo generar/firmar el XML, o está mal formado |
| `XSD_xxxx` | error | Violación del esquema (mensaje de libxml con prefijos `cbc:`/`cac:`) |
| `SIG_001` | error | Firma XAdES inválida |
| `XML_010` | error | El CUFE/CUDE/CUDS del XML no coincide con el recalculado |
| `XML_011` | error | SoftwareSecurityCode inválido |
| `XML_020` – `XML_025` | error | Totales, tributos o líneas inconsistentes |
| `XML_030` – `XML_034` | error / aviso | LineCountNumeric, ambiente, hora sin -05:00, QR, DV del emisor |

## Esquemas incluidos

`resources/xsd` contiene UBL 2.1 (OASIS), `DIAN_UBL_Structures.xsd`, XMLDSig y
XAdES 1.3.2/1.4.1, con las rutas de importación locales. Ver
[`resources/xsd/README.md`](../resources/xsd/README.md) para el origen y los
cambios aplicados. La suite de pruebas valida con ellos los documentos reales
aprobados por la DIAN y todos los documentos que genera el SDK.
