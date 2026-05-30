# Royalty Fusion DIAN PHP — Documentación

SDK Open Source en PHP 8.1+ para emitir facturación electrónica DIAN (Colombia)
bajo el **Anexo Técnico V1.9** y el estándar **UBL 2.1**, con la misma filosofía
desacoplada de [Greenter](https://github.com/giansalex/greenter).

## ¿Por dónde empezar?

| Sección | Lo que vas a aprender |
|---|---|
| [Instalación](./installation.md) | Composer, requisitos del sistema, certificado de prueba. |
| [Quick start](./quick-start.md) | Tu primera factura firmada en menos de 5 minutos. |
| [Factura electrónica](./invoice.md) | Modelo `Invoice` completo, descuentos, anticipos, retenciones. |
| [Notas crédito / débito](./credit-debit-notes.md) | Cuándo usar `CreditNote` vs `DebitNote`, códigos de discrepancia. |
| [Validación](./validation.md) | `BusinessRuleValidator`, `XsdValidator`, reglas oficiales. |
| [Representación gráfica](./report.md) | `HtmlReport` + integración con dompdf/mpdf. |
| [Bundle Symfony](./symfony.md) | DI, configuración YAML, comando `dian:status`. |
| [Arquitectura](./architecture.md) | Cómo está organizado el SDK (estilo Greenter). |
| [Troubleshooting](./troubleshooting.md) | Errores DIAN comunes y cómo resolverlos. |

## Documentos soportados

| Documento | Estado |
|---|---|
| Factura Electrónica de Venta | ✅ Completo |
| Nota Crédito (con / sin referencia) | ✅ Completo |
| Nota Débito (con / sin referencia) | ✅ Completo |
| Documento Soporte (DS) | 🚧 Scaffold + TODO |
| Nómina Electrónica (NIE) | 🚧 Scaffold + TODO |
| RADIAN (eventos de título valor) | 🚧 Scaffold + TODO |

Ver el [roadmap](./roadmap.md) para detalle.

## Filosofía

* **Modelos planos PHP** — `Invoice`, `CreditNote`, `Company`, `Item`, etc.
* **Catálogos como enums** — `Tributo::IVA`, `FormaPago::CREDITO`, validados al instante.
* **Templates Twig visibles** — `src/Xml/templates/invoice.xml.twig` se lee como un XSD comentado.
* **Servicios desacoplados** — cada paso (`CufeGenerator`, `XmlBuilder`, `XadesSigner`, `SoapClient`) es inyectable y testeable.
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
