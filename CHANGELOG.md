# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Hace que el flujo completo funcione: antes la firma fallaba siempre y la DIAN
habría rechazado los documentos por CUFE y por falta de WS-Security.

### Fixed

- **Firma XAdES**: `XadesSigner` lanzaba `ArgumentCountError` en cada firma y
  el hash de la política DIAN era inválido. Reescrito con las 3 referencias de
  los documentos aprobados (documento, KeyInfo, SignedProperties); verificado
  contra las firmas reales de `resources/fixtures` y con signxml.
- **Escape XML**: los textos con `&`, `<` o comillas rompían el XML (y permitían
  inyectar nodos). Todas las plantillas escapan ahora por defecto.
- **CUFE**: usaba `cantidad × precio` sin descuentos y el `setTotal()` del
  usuario; ahora usa los mismos totales del XML. Reproduce el ejemplo oficial
  del Anexo. CUDE sin `TipoDoc`. CUDS según el anexo del documento soporte.
- **Estructura UBL**: orden de elementos según el XSD en factura, notas y
  líneas (`PaymentExchangeRate`, `PostalZone`, `BrandName`, retenciones y
  anticipos en notas). Todos los documentos generados validan contra UBL 2.1.
- `InvoiceTypeCode`, `CustomizationID`, `ProfileExecutionID` y `UUID@schemeID`
  dinámicos (antes fijos: exportación, contingencia y RADIAN en producción eran
  imposibles); DV del adquiriente y del proveedor tecnológico; hora en -05:00.
- Documento soporte: roles de vendedor/adquiriente, residente/no residente,
  control de numeración y periodo por línea.
- RADIAN: `DianExtensions` y ambiente dinámico.
- Nómina: namespace `NominaIndividual` en todo el documento; orden de
  elementos, atributos y conceptos (horas extra, vacaciones, incapacidades,
  licencias…) según el esquema DIAN; CUNE con fecha y hora de Colombia.
- `TipoOperacion` tenía los códigos desplazados: AIU es 09, mandatos 11,
  transporte 12, cambiario 13, notarios 14, divisas 15/16
  (`TipoOperacion::MANDATARIOS` queda como alias de `MANDATOS`).
- Catálogo `Tributo` alineado con la tabla DIAN (25, 34 y 35 tenían otro significado).
- Bundle Symfony: el XML recibía `habilitacion` como ambiente en vez de `2`.
- PHPStan nivel 6 sin errores (el CI fallaba desde antes).

### Added

- **Documento equivalente electrónico** (`EquivalentDocument`, Res. 165/2023):
  tiquete POS y los demás tipos, CUDE con el PIN, extensiones de fabricante,
  beneficios del comprador y caja, notas de ajuste 93/94 y
  `Client::consumidorFinal()`.
- **Nómina de ajuste** (`PayrollAdjustment`, TipoXML 103): reemplazar o
  eliminar una nómina; `Dian::sendPayrollAdjustment()`.
- **Recepción** de documentos de proveedores: `Dian::receive()` (datos,
  firmas y validación DIAN), `Dian::acknowledge()` con eventos 030–033,
  `Dian::getDocument()` por CUFE y `Dian::getAcquirer()`.
- **Consecutivos**: `NumberingManager` con `PdoNumberingStore` o
  `InMemoryNumberingStore`, alertas de rango y vencimiento.
- Notas a varias facturas (`addBillingReference()`); referencias de remisión,
  recepción y contrato.
- Impuestos de valor fijo por unidad (`Tax::setPerUnit()`), mandatos
  (`Item::setMandante()`), `Item::addProperty()`, entrega e Incoterm
  (`Delivery`) y extensión del sector salud (`SectorSalud`).
- Eventos RADIAN con constructores nombrados y concepto de reclamo (031).
- Reglas `DEE_001..005`, `NOTE_006`, `INV_004..007`.
- **WS-Security + WS-Addressing** en todas las llamadas (`WsSecurity`).
- `SoapClient` reescrito: ZIP en todos los envíos, nombres de archivo del
  Anexo, `DianResponse` (rechazos, notificaciones, SOAP Fault, ZipKey,
  ApplicationResponse), reintentos con espera exponencial, PSR-3, `call()`.
- **Preflight**: `Dian::preflight()` y validación automática en `send()` con
  `BusinessRuleValidator` (~60 reglas), `CertificateChecker`, `XsdValidator`,
  `SignatureVerifier` y `XmlConsistencyChecker`.
- XSD de UBL 2.1 + DIAN + XAdES incluidos en `resources/xsd`.
- `vendor/bin/dian-doctor` y comando `dian:doctor`.
- Facade: `sendSupportDocument()`, `sendPayroll()`, `sendEvent()`,
  `waitForResult()`, `getNumberingRanges()`, `buildAttachedDocument()`.
- Nota de ajuste al documento soporte; `EventCudeGenerator`; QR completo.
- `Certificate` (PKCS#12/PEM, aviso de cifrado heredado con OpenSSL 3).
- Guía de integración (`docs/integration-guide.md`).

### Changed

- `validateBeforeSend` está activo por defecto.
- `send()` sin TestSetId usa `SendBillSync` (antes `SendBillAsync`).
- Los impuestos del documento se agregan desde las líneas cuando no se indican;
  el valor del tributo se calcula si se omite.
- Descuentos/cargos globales y anticipos afectan el valor a pagar, no la base.
- `getAcquirer(tipo, número)` y `getExchangeEmails()` cambian de parámetros.
- `robrichards/xmlseclibs` deja de ser dependencia; se agrega `psr/log`.

## [0.1.1] - 2026-05-31

### Added

- 5 new real-world golden master fixtures from Siigo Nube production traffic
  (Factura nacional, Factura exportación USD/COP, Factura con retenciones
  ReteRenta+ReteICA, NC nacional, NC exportación, NC nacional sobre factura
  con retenciones). All come from BLINDACCES SAS (NIT 901944237-8) and are
  DIAN-approved (`<cbc:ResponseCode>02</cbc:ResponseCode>`).

- `SiigoGoldenMastersTest` — 10 assertions per fixture covering CUFE/CUDE
  format, ParentDocumentID, CustomizationID, schemeID, currency, PayableAmount,
  DIAN approval status, BillingReference, DiscrepancyResponse and supplier
  identity. The SDK's `AttachedDocumentParser` now has a regression net
  against 6 distinct production patterns.

### Validated by these new fixtures

- ✅ `schemeID="1"` default matches every real Siigo invoice.
- ✅ `PaymentExchangeRate` block structure matches exports to Guatemala USD/COP.
- ✅ `WithholdingTaxTotal` structure matches dual-retention scenario
  (ReteRenta code 06 + ReteICA code 07).
- ✅ Note `CustomizationID=20` with `BillingReference` matches all 3 NCs.
- ✅ `<cbc:ResponseCode>02</cbc:ResponseCode>` detection via `isAccepted()`
  works on real DIAN-approved documents.

### Still not validated against production

- ❌ Documento Soporte (DS) — no real DS XML available yet
- ❌ Nota Débito (ND) — no real ND XML available yet
- ❌ Nómina Electrónica (NIE) — no real Nómina XML available yet
- ❌ RADIAN events — no real event XML available yet

Tests: **89 / 429 assertions** green (was 79/321 in v0.1.0).

## [0.1.0] - 2026-05-31

First public release.

### Added

#### Core document support
- Factura Electrónica de Venta (Invoice) with full Anexo Técnico V1.9 compliance.
- Nota Crédito (CreditNote) and Nota Débito (DebitNote) with all discrepancy codes.
- Documento Soporte (SupportDocument) with `CudsGenerator` and dedicated template.
- Nómina Individual Electrónica (Payroll) — full NIE with `CuneGenerator`, 28 devengados + 21 deducciones fields.
- RADIAN events (ApplicationResponse) — 13 event codes (Acuse, Recibo, Aceptación, Endoso, Pago, ...).
- AttachedDocument — builder + parser for B2B envelopes (real golden master from Siigo).

#### Signing
- `XadesSigner` (XAdES-EPES) with official DIAN signature policy v2.
- Handles documents with 1 or 2 `UBLExtensions` automatically.
- Optional `SignerRole` (`supplier` / `third party` / `customer`).

#### Web services (13 SOAP operations)
- `SoapClient` over `symfony/http-client` — every public DIAN operation:
  - **Envío**: `SendBillAsync`, `SendBillSync`, `SendBillAttachmentAsync`,
    `SendTestSetAsync`, `SendNominaSync`, `SendEventUpdateStatus`
  - **Consulta**: `GetStatus`, `GetStatusZip`, `GetStatusEvent`,
    `GetNumberingRange`, `GetXmlByDocumentKey`, `GetAcquirer`,
    `GetExchangeEmails`, `GetReferenceNotes`
- Single VPFE endpoint (`vpfe.dian.gov.co` Prod, `vpfe-hab.dian.gov.co` Hab)
  serves every document family — Factura, NC/ND, DS, Nómina, RADIAN.

#### Catalogs
- 16 typed PHP enums for canonical DIAN codes (TipoDocumento, Responsabilidad, Tributo, FormaPago, MedioPago, UnidadMedida, Moneda, TipoFactura, TipoOperacion, etc.).
- Long-tail registries hydrated from public DIAN/DANE/UN-CEFACT/ISO CSVs:
  - 1,122 DANE municipalities
  - 1,092 UN/ECE Rec 20 units
  - 247 ISO 3166-1 countries
  - 178 ISO 4217 currencies
  - 110 DIAN responsibilities (historical)
  - 74 UN/CEFACT 4461 payment methods
  - 369 ISO 639 languages

#### Validation
- `BusinessRuleValidator` with 14 numbered error codes (DOC_001, TAX_002, TOTAL_002, RES_001, etc.).
- `XsdValidator` (libxml schemaValidate) that gracefully skips when XSDs are not vendored.
- `validateBeforeSend(true)` opt-in on the `Dian` facade.

#### Representación gráfica
- `HtmlReport` with configurable logo + accent color, PDF-engine agnostic.

#### Framework integration
- Symfony bundle (`src/Bridge/Symfony/`) with autowiring, YAML configuration, `dian:status` console command.

#### Tooling and quality
- PHPUnit 11 — 79 tests / 321 assertions, all green.
- PHPStan level 6.
- GitHub Actions CI matrix: PHP 8.1 / 8.2 / 8.3 / 8.4 × Symfony 6.4 / 7.1.
- Public documentation set under `docs/` (17 markdown guides) including the
  full `connections-reference.md` audit of every URL, SOAP action URI,
  namespace and XAdES constant the SDK emits.
- 7 runnable examples in `examples/`.

### Credits

Architecture inspired by [Greenter](https://github.com/giansalex/greenter)
(Peru). Algorithmic recipes (CUFE / CUDS / CUNE concatenation order, XAdES
reference order) cross-checked against [lopezsoft/ubl21dian](https://github.com/lopezsoft/ubl21dian)
(MIT). Public DIAN/DANE catalog data vendored from [soenac/api-dian](https://github.com/soenac/api-dian)
(LGPL-3.0 — only public-domain data, no code copied). Real-world golden
master courtesy of a Siigo-generated invoice for BLINDACCES SAS.

[Unreleased]: https://github.com/Royalty-Fusion/dian-php/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/Royalty-Fusion/dian-php/releases/tag/v0.1.0
