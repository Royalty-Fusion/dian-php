# Conexiones DIAN — Referencia completa

Auditoría exhaustiva de todas las URLs, action URIs, namespaces y constantes
que el SDK usa para comunicarse con DIAN. Validado contra:
* [Anexo Técnico V1.9](https://www.dian.gov.co/impuestos/factura-electronica/factura-electronica/Documents/Anexo_Tecnico_Factura_Electronica_Vr_1_9.pdf)
* [lopezsoft/ubl21dian](https://github.com/lopezsoft/ubl21dian) (MIT)
* [Stenfrank/ubl21dian](https://github.com/Stenfrank/ubl21dian)
* Fixture real de Siigo (`resources/fixtures/siigo-blindacces-FV5.xml`)

## Endpoints HTTP

**Todo (Facturas, NC/ND, DS, Nómina, RADIAN) usa el mismo VPFE.** Solo
cambia el ambiente (Producción / Habilitación) y el SOAP action.

| Ambiente | URL VPFE | Constante |
|---|---|---|
| Producción | `https://vpfe.dian.gov.co/WcfDianCustomerServices.svc` | `SoapClient::ENDPOINT_PROD` |
| Habilitación | `https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc` | `SoapClient::ENDPOINT_HAB` |

> **Mito común desmentido**: la Nómina Electrónica NO tiene endpoint propio
> (`apifedi.dian.gov.co` u otro). Comparte el VPFE — el `SOAPAction` la diferencia.

## Operaciones SOAP — Tabla completa

Todas tienen base `http://wcf.dian.colombia/IWcfDianCustomerServices/<Operation>`.

### Envío (producción)

| Operación | Método SDK | Síncrono | Uso típico |
|---|---|:-:|---|
| `SendBillAsync` | `send()` | ❌ | Factura / NC / ND — flujo estándar |
| `SendBillSync` | `sendBillSync()` | ✅ | Mismo doc pero recibes validación inmediata |
| `SendBillAttachmentAsync` | `sendBillAttachmentAsync()` | ❌ | Cuando envías el XML con anexos binarios |
| `SendNominaSync` | `sendNominaSync()` | ✅ | Nómina Electrónica (NIE) |
| `SendEventUpdateStatus` | `sendEventUpdateStatus()` | ✅ | Eventos RADIAN |

### Envío (Habilitación / set de pruebas)

| Operación | Método SDK | Discriminador |
|---|---|---|
| `SendTestSetAsync` | `send()` auto-detecta con `setTestSetId()` | El testSetId asignado por DIAN al solicitar habilitación |

### Consulta

| Operación | Método SDK | Pasa | Devuelve |
|---|---|---|---|
| `GetStatus` | `getStatus(trackId)` | CUFE/CUDE | `StatusResult` (b:IsValid, b:StatusCode, b:StatusDescription) |
| `GetStatusZip` | `getStatusZip(trackId)` | CUFE/CUDE | `StatusResult` + ApplicationResponse XML descomprimido |
| `GetStatusEvent` | `getStatusEvent(trackId)` | CUFE | Eventos RADIAN registrados |
| `GetNumberingRange` | `getNumberingRange(NIT, NIT-proveedor, softwareId)` | 3 strings | Rangos autorizados (resoluciones) |
| `GetXmlByDocumentKey` | `getXmlByDocumentKey(trackId)` | CUFE/CUDE | XML firmado original (b64-decoded) |
| `GetAcquirer` | `getAcquirer(NIT, NIT-proveedor, softwareId)` | 3 strings | Datos del adquiriente registrado |
| `GetExchangeEmails` | `getExchangeEmails(NIT, NIT-proveedor, softwareId)` | 3 strings | Emails de notificación registrados |
| `GetReferenceNotes` | `getReferenceNotes(CUFE)` | CUFE | NC/ND que referencian la factura |

**Total: 13 operaciones SOAP soportadas** (las 8 más usadas + 5 de configuración).

## Constantes XAdES (firma digital)

```
POLICY_URL          = https://facturaelectronica.dian.gov.co/politicadefirma/v2/politicadefirmav2.pdf
POLICY_HASH_SHA256  = dMoQAOR5HscatV9QLJ864wS6u2bM=
POLICY_DESCRIPTION  = Política de firma para facturas electrónicas de la República de Colombia
SIGNATURE_ALGORITHM = RSA-SHA256
CANONICALIZATION    = http://www.w3.org/TR/2001/REC-xml-c14n-20010315  (C14N inclusive)
DIGEST_ALGORITHM    = SHA-256
HASH_ALGORITHM      = SHA-384  (para CUFE / CUDE / CUDS / CUNE)
```

> Cross-checked con `lopezsoft/ubl21dian` y con la fixture real de Siigo —
> los 4 hashes coinciden byte a byte.

## Namespaces UBL emitidos

Por documento:

| Namespace | Prefijo | Usado en |
|---|---|---|
| `urn:oasis:names:specification:ubl:schema:xsd:Invoice-2` | (default) | Factura, DS |
| `urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2` | (default) | Nota Crédito |
| `urn:oasis:names:specification:ubl:schema:xsd:DebitNote-2` | (default) | Nota Débito |
| `urn:oasis:names:specification:ubl:schema:xsd:AttachedDocument-2` | (default) | AttachedDocument |
| `urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2` | (default) | RADIAN |
| `dian:gov:co:facturaelectronica:NominaIndividual` | `fe` | Nómina |
| `urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2` | `cac` | Todos |
| `urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2` | `cbc` | Todos |
| `urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2` | `ext` | Todos |
| `dian:gov:co:facturaelectronica:Structures-2-1` | `sts` | Documentos con extensión DIAN |
| `http://www.w3.org/2000/09/xmldsig#` | `ds` | Firma XAdES |
| `http://uri.etsi.org/01903/v1.3.2#` | `xades` | XAdES v1.3.2 |
| `http://uri.etsi.org/01903/v1.4.1#` | `xades141` | XAdES v1.4.1 |

## URLs del catálogo VPFE (QR + verificación)

| Ambiente | Base URL |
|---|---|
| Producción | `https://catalogo-vpfe.dian.gov.co/document/searchqr` |
| Habilitación | `https://catalogo-vpfe-hab.dian.gov.co/document/searchqr` |

El QR es solo `<base>?documentkey=<CUFE/CUDE/CUDS/CUNE>`.

## Proveedor de Autorización DIAN (hardcoded en todos los XMLs)

```
NIT:  800197268
DV:   4
Name: U.A.E. DIRECCION DE IMPUESTOS Y ADUANAS NACIONALES
```

Aparece como `<sts:AuthorizationProviderID>` en cada documento — DIAN es la
autoridad que autoriza.

## Atributos `schemeID` por tipo de hash

Auditados contra Siigo:

| Documento | Tag | `schemeID` | `schemeName` |
|---|---|---|---|
| Factura | `<cbc:UUID>` | `1` (default, Siigo lo usa) o `2` (post-Anexo V1.9) | `CUFE-SHA384` |
| Nota Crédito | `<cbc:UUID>` | `1` o `2` | `CUDE-SHA384` |
| Nota Débito | `<cbc:UUID>` | `1` o `2` | `CUDE-SHA384` |
| Documento Soporte | `<cbc:UUID>` | `1` | `CUDS-SHA384` |
| Nómina | `<InformacionGeneral CUNE=... EncripCUNE>` | n/a | `CUNE-SHA384` |
| RADIAN | `<cbc:UUID>` | `1` | `CUDE-SHA384` |

Configurable vía `new XmlBuilder(twig: null, environment: '2', uuidSchemeId: '1')`.

## Encoding y MIME types

| Componente | Valor |
|---|---|
| Encoding XML | UTF-8 |
| Content-Type SOAP | `application/soap+xml;charset=UTF-8;action="..."` |
| Encoding `<cbc:UUID>` | hex SHA-384 (96 chars lowercase) |
| Compresión envío | ZIP DEFLATE (estándar `ZipArchive`) |
| Inner XML del AttachedDocument | embebido en `<![CDATA[...]]>` |

## ¿Qué seguimos sin tener?

Honestidad total:

| Pieza | Estado | Cuándo se necesita |
|---|---|---|
| **WS-Security `BinarySecurityToken`** (`wsse:Security` header en SOAP) | ❌ No implementado | Requerido para `SendBillSync` con producción real. `SendBillAsync` no lo necesita y es lo más común. |
| **Cliente SSL con bundle de certs** | ✅ Usa el bundle del sistema (curl/symfony-http-client default) | OK para 99% de casos. Si DIAN cambia su CA, hay que actualizar. |
| **Validación XSD contra el zip oficial** | ⚠️ Solo si descargas el zip a `resources/xsd/` | Opcional. La mayoría usa el `BusinessRuleValidator`. |
| **Reintentos exponenciales** | ❌ No hay middleware de retry | Implementar si tu ERP envía alto volumen — VPFE a veces tira 500. |
| **Logging estructurado** | ❌ No PSR-3 todavía | Wire de un `LoggerInterface` opcional al `SoapClient` queda pendiente. |

## Verificar conectividad manualmente

```bash
# Habilitación
curl -v https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?wsdl | head -5

# Producción
curl -v https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?wsdl | head -5

# Catálogo (QR)
curl -v "https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey=a7516114373f44f2160cca5d079afbb8bc865db2c249e4699b19c2de2d5ef9890d9955584f6e86cb49807bbbfd78a61e"
```

Si DIAN cambia algún endpoint o action, las constantes a tocar son las que
se documentan en este archivo.
