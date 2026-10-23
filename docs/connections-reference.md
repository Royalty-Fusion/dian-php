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

Todas las llamadas llevan **WS-Addressing** (`wsa:Action`, `wsa:To`) y
**WS-Security** (`wsse:BinarySecurityToken` con el certificado X.509,
`wsu:Timestamp` y firma RSA-SHA256 de `wsa:To` con C14N exclusivo), generados
por `RoyaltyFusion\DianPhp\Ws\WsSecurity`. Los documentos viajan en un ZIP
codificado en base64.

### Envío

| Operación | Método SDK | Respuesta | Uso típico |
|---|---|:-:|---|
| `SendBillSync` | `send()` sin TestSetId / `sendBillSync()` | Inmediata | Factura, NC, ND y DS en producción |
| `SendTestSetAsync` | `send()` con `setTestSetId()` en habilitación | `ZipKey` | Set de pruebas |
| `SendBillAsync` | `sendBillAsync()` | `ZipKey` | Envío por lotes |
| `SendBillAttachmentAsync` | `sendBillAttachmentAsync()` | `ZipKey` | XML con anexos |
| `SendNominaSync` | `sendNominaSync()` / `Dian::sendPayroll()` | Inmediata | Nómina electrónica |
| `SendEventUpdateStatus` | `sendEventUpdateStatus()` / `Dian::sendEvent()` | Inmediata | Eventos RADIAN |

Los envíos asíncronos devuelven un `Result` con `isPending() === true` y
`getZipKey()`; consulte el resultado final con `Dian::waitForResult($zipKey)`.

### Consulta

| Operación | Método SDK | Parámetros | Devuelve |
|---|---|---|---|
| `GetStatus` | `getStatus($trackId)` | CUFE/CUDE o ZipKey | `StatusResult` |
| `GetStatusZip` | `getStatusZip($trackId)` / `Dian::getStatus()` | CUFE/CUDE o ZipKey | `StatusResult` + ApplicationResponse descomprimido |
| `GetNumberingRange` | `getNumberingRanges($nit, $nitProveedor, $softwareId)` | 3 | Resoluciones con `TechnicalKey` |
| `GetXmlByDocumentKey` | `getXmlByDocumentKey($cufe)` | CUFE/CUDE | XML firmado |
| `GetStatusEvent` | `getStatusEvent($cufe)` | CUFE | Eventos RADIAN (XML) |
| `GetReferenceNotes` | `getReferenceNotes($cufe)` | CUFE | NC/ND que referencian la factura (XML) |
| `GetAcquirer` | `getAcquirer($tipoDoc, $numero)` | 2 | Datos del adquiriente (XML) |
| `GetExchangeEmails` | `getExchangeEmails()` | 0 | Correos de recepción (XML) |

Cualquier otra operación, o una variante de parámetros, se puede llamar con
`SoapClient::call('Operacion', ['parametro' => 'valor'])`, que devuelve un
`DianResponse` ya interpretado (incluidos los SOAP Fault).

## Constantes XAdES (firma digital)

```
POLICY_URL          = https://facturaelectronica.dian.gov.co/politicadefirma/v2/politicadefirmav2.pdf
POLICY_HASH_SHA256  = dMoMvtcG5aIzgYo0tIsSQeVJBDnUnfSOfBpxXrmor0Y=
POLICY_DESCRIPTION  = Política de firma para facturas electrónicas de la República de Colombia
SIGNATURE_ALGORITHM = RSA-SHA256
CANONICALIZATION    = http://www.w3.org/TR/2001/REC-xml-c14n-20010315  (C14N inclusive)
DIGEST_ALGORITHM    = SHA-256
HASH_ALGORITHM      = SHA-384  (para CUFE / CUDE / CUDS / CUNE)
```

> El hash de la política y la estructura de la firma (3 referencias: documento,
> KeyInfo y SignedProperties) se tomaron de documentos reales aprobados por la
> DIAN (`resources/fixtures`), y `SignatureVerifier` valida esas firmas en la
> suite de pruebas.

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

## Estado de cada pieza

| Pieza | Estado |
|---|---|
| WS-Security en todas las llamadas | ✅ Implementado y verificado de forma independiente; pendiente de confirmar contra la DIAN en vivo |
| Validación XSD | ✅ Esquemas UBL 2.1 + DIAN incluidos en `resources/xsd` (sin red) |
| Reintentos | ✅ 2 reintentos con espera exponencial ante errores de red y HTTP 5xx |
| Logging | ✅ PSR-3 opcional (`Dian::setLogger`, `SoapClient::setLogger`) |
| TLS | Bundle de CA del sistema (symfony/http-client) |

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
