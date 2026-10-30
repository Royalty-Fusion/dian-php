# Royalty Fusion DIAN PHP — Documentación

SDK Open Source en PHP 8.1+ para emitir facturación electrónica DIAN (Colombia)
bajo el **Anexo Técnico V1.9** y el estándar **UBL 2.1**, con la misma filosofía
desacoplada de [Greenter](https://github.com/giansalex/greenter).

## ¿Por dónde empezar?

| Sección | Lo que vas a aprender |
|---|---|
| [**Guía de integración**](./integration-guide.md) | **De cero a producción: registro DIAN, set de pruebas, producción, Laravel/Symfony, errores y checklists.** |
| [Instalación](./installation.md) | Composer, requisitos del sistema, certificado y `dian-doctor`. |
| [Quick start](./quick-start.md) | Tu primera factura firmada en menos de 5 minutos. |
| [Factura electrónica](./invoice.md) | Modelo `Invoice` completo, descuentos, anticipos, retenciones, exportación, mandatos, impuestos por unidad, sector salud. |
| [Notas crédito / débito](./credit-debit-notes.md) | Cuándo usar `CreditNote` vs `DebitNote`, códigos de discrepancia. |
| [Documento Soporte (DS)](./support-document.md) | Compras a no obligados a facturar — emisión de DS. |
| [Documento equivalente (POS)](./equivalent-document.md) | Tiquete POS y demás documentos equivalentes, CUDE, notas de ajuste 93/94. |
| [Nómina Electrónica](./payroll.md) | Nómina individual y nota de ajuste (reemplazar/eliminar) con CUNE. |
| [Recepción de documentos](./reception.md) | Leer y verificar facturas de proveedores, eventos 030–033, consultar documentos y adquirientes. |
| [Consecutivos](./numbering.md) | Numeración atómica por resolución y alertas de rango y vigencia. |
| [RADIAN (eventos)](./radian.md) | Acuse, Recibo, Aceptación, Endoso, Pago — 13 eventos. |
| [AttachedDocument](./attached-document.md) | Contenedor B2B — parser + builder. |
| [Validación previa](./validation.md) | `preflight()`: reglas con código, XSD, firma y recálculo del CUFE. |
| [Representación gráfica](./report.md) | `HtmlReport` + integración con dompdf/mpdf. |
| [Bundle Symfony](./symfony.md) | DI, configuración YAML, comandos `dian:doctor` y `dian:status`. |
| [REST API blueprint](./rest-api-blueprint.md) | Diseño recomendado de endpoints REST para tu ERP. |
| [Conexiones DIAN](./connections-reference.md) | URLs, SOAP actions, namespaces, constantes — referencia completa. |
| [Arquitectura](./architecture.md) | Cómo está organizado el SDK (estilo Greenter). |
| [Troubleshooting](./troubleshooting.md) | Errores DIAN comunes y cómo resolverlos. |

## Documentos soportados

| Documento | Estado |
|---|---|
| Factura Electrónica de Venta | ✅ Completo |
| Nota Crédito (con / sin referencia) | ✅ Completo |
| Nota Débito (con / sin referencia) | ✅ Completo |
| Documento Soporte (DS) + nota de ajuste | ✅ Completo (CUDS por confirmar en habilitación) |
| Documento equivalente electrónico (POS y demás) + notas de ajuste | ✅ Completo (por confirmar en habilitación) |
| Nómina Electrónica (NIE) | ✅ Individual y nota de ajuste (por confirmar en habilitación de nómina) |
| Recepción de documentos de proveedores | ✅ Lectura, verificación de firmas y eventos RADIAN |
| RADIAN (eventos de título valor) | ✅ Completo (por confirmar en habilitación RADIAN) |
| AttachedDocument (B2B) | ✅ Builder + Parser |

Ver el [roadmap](./roadmap.md) para detalle.

## Filosofía

* **Modelos planos PHP** — `Invoice`, `CreditNote`, `Company`, `Item`, etc.
* **Catálogos como enums** — `Tributo::IVA`, `FormaPago::CREDITO`, validados al instante.
* **Templates Twig visibles** — `src/Xml/templates/invoice.xml.twig` se lee como un XSD comentado.
* **Servicios desacoplados** — cada paso (`CufeGenerator`, `XmlBuilder`, `XadesSigner`, `SoapClient`) es inyectable y testeable.
* **Validación antes de enviar** — `preflight()` detecta localmente lo que la DIAN rechazaría.
* **Sin `ext-soap`** — usamos `symfony/http-client` + ZIP nativo.
* **Sin frameworks obligatorios** — el bundle de Symfony es opcional, los componentes core funcionan en cualquier app PHP.

```
+----------+    +------------+    +-------------+    +-----------+    +------------+
|  Models  | -> | XmlBuilder | -> | XadesSigner | -> | SoapClient| -> | DIAN VPFE  |
+----------+    +------------+    +-------------+    +-----------+    +------------+
                      ^
                      |
            +--------------------+
            | CufeGen / QrGen /  |
            | DocumentCalculator |
            +--------------------+
```
