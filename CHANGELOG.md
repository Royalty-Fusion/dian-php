# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Pending
- WS-Security `BinarySecurityToken` for `SendBillSync` (synchronous flow).
- Golden master tests against real Nómina / DS / RADIAN XMLs (waiting for fixtures).
- Symfony bundle event subscribers (`PreSendEvent`, `PostSendEvent`, `ValidationFailedEvent`).

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

#### Web services
- `SoapClient` over `symfony/http-client`:
  - `SendBillAsync` (production flow)
  - `SendTestSetAsync` (Habilitación)
  - `GetStatus`, `GetStatusZip` (status query, returns ApplicationResponse XML)
  - `GetNumberingRange` (authorised numbering ranges)
  - `GetXmlByDocumentKey` (download signed XML by CUFE)

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
- PHPUnit 11 — 71 tests / 305 assertions, all green.
- PHPStan level 6.
- GitHub Actions CI matrix: PHP 8.1 / 8.2 / 8.3 / 8.4 × Symfony 6.4 / 7.1.
- Public documentation set under `docs/` (15 markdown guides).
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
