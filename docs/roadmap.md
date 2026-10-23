# Roadmap

| Fase | Tema | Estado |
|---|---|---|
| 0 | Estructura base estilo Greenter (namespaces, PHPUnit, CI) | ✅ |
| 1 | Auditoría profunda + fixes contra Anexo V1.9 | ✅ |
| 2 | Catálogos DIAN como enums tipados | ✅ |
| 3 | Modelos enriquecidos (Address, Charge, Prepayment, ...) | ✅ |
| 4 | Template `invoice.xml.twig` 100% Anexo V1.9 | ✅ |
| 5 | Templates `creditnote.xml.twig` y `debitnote.xml.twig` | ✅ |
| 6 | Documento Soporte (DS) + Nota Ajuste DS | ✅ Completo |
| 7 | Nómina Electrónica (NIE) | ✅ Completo |
| 8 | RADIAN (eventos de título valor) | ✅ Completo |
| 9 | Validators (BusinessRule + XSD) | ✅ |
| 10 | Representación gráfica HTML (PDF-ready) | ✅ |
| 11 | WS extra (GetStatus, GetStatusZip) | ✅ |
| 12 | Bundle Symfony + comandos | ✅ |
| 13 | Tests con fixtures DIAN reales | ✅ Siigo golden master + 71 tests |
| 14 | Documentación pública | ✅ esta serie |
| 15 | Compliance fixes del XML real Siigo | ✅ Completo |
| 16 | DS + RADIAN + Nómina + extras (lopezsoft) | ✅ Completo |
| 17 | Catálogos DANE/DIAN completos + REST blueprint | ✅ Completo |
| 18 | Pulido: ejemplos + docs + CHANGELOG | ✅ Completo |
| 21 | Firma XAdES verificada, escape XML, cálculos y CUFE unificados, XSD incluidos | ✅ |
| 22 | WS-Security, cliente SOAP robusto, preflight, `dian-doctor`, guía de integración | ✅ |

## Próximos hitos

* Confirmar en habilitación real: WS-Security, CUDS, CUDE de eventos RADIAN y nómina.
* Nómina de ajuste (`TipoXML=103`) y XSD de nómina para validación local.
* Golden masters de documento soporte, nota débito, nómina y eventos RADIAN reales.
* Facturas del sector salud y otros anexos sectoriales.
* Eventos del bundle Symfony (`PreSendEvent`, `PostSendEvent`, `ValidationFailedEvent`).
* Paquete de integración nativo para Laravel (hoy: ejemplo en la guía).
