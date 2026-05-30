# Roadmap

| Fase | Tema | Estado |
|---|---|---|
| 0 | Estructura base estilo Greenter (namespaces, PHPUnit, CI) | ✅ |
| 1 | Auditoría profunda + fixes contra Anexo V1.9 | ✅ |
| 2 | Catálogos DIAN como enums tipados | ✅ |
| 3 | Modelos enriquecidos (Address, Charge, Prepayment, ...) | ✅ |
| 4 | Template `invoice.xml.twig` 100% Anexo V1.9 | ✅ |
| 5 | Templates `creditnote.xml.twig` y `debitnote.xml.twig` | ✅ |
| 6 | Documento Soporte (DS) + Nota Ajuste DS | 🚧 scaffold |
| 7 | Nómina Electrónica (NIE) | 🚧 scaffold |
| 8 | RADIAN (eventos de título valor) | 🚧 scaffold |
| 9 | Validators (BusinessRule + XSD) | ✅ |
| 10 | Representación gráfica HTML (PDF-ready) | ✅ |
| 11 | WS extra (GetStatus, GetStatusZip) | ✅ |
| 12 | Bundle Symfony + comandos | ✅ |
| 13 | Tests con fixtures DIAN reales | 🚧 base lista |
| 14 | Documentación pública | ✅ esta serie |

## Próximos hitos

### Fase 6 (Documento Soporte) — completar
* `src/Xml/templates/supportdocument.xml.twig`
* `CudsGenerator` (hash específico)
* `SupportDocumentAdjustmentNote` (TipoDoc=95)
* Wiring en `Dian::send()` con override de tipo

### Fase 7 (Nómina) — completar
* Modelos `Devengado`, `Deducciones`, `EmpleadorTrabajador`
* `nomina-individual.xml.twig` + `nomina-ajuste.xml.twig`
* `CuneGenerator`
* `PayrollSoapClient` contra `apifedi.dian.gov.co/Nomina`

### Fase 8 (RADIAN) — completar
* `ApplicationResponse.xml.twig`
* Firmador separado (cert del receptor)
* `RadianSoapClient.sendEventUpdateStatus()`
* Mapeo de transiciones de estado en `Invoice`

### Otras mejoras pendientes
* `CustomizationID` dinámico por `TipoOperacion` en factura
* Validación XSD con el zip oficial vendor-eado
* Snapshot tests golden master con XMLs del set de pruebas DIAN
* Provider de eventos en el bundle (`PreSendEvent`, `PostSendEvent`, `ValidationFailedEvent`)
* `BinaryReceipt` WS-Security para `SendBillSync`
* Catálogo completo de municipios DANE en `resources/catalogs/municipios.csv`
