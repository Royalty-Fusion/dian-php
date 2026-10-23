# Royalty Fusion DIAN PHP

![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)
![UBL](https://img.shields.io/badge/UBL-2.1-orange.svg)
![Anexo](https://img.shields.io/badge/DIAN-Anexo%20V1.9-red.svg)

SDK en PHP 8.1+ para facturación electrónica DIAN (Colombia) bajo UBL 2.1 y el
Anexo Técnico V1.9: genera, valida, firma y envía los documentos, y detecta
localmente los errores que la DIAN rechazaría **antes** de enviar.

## Instalación

```bash
composer require royaltyfusion/dian-php
vendor/bin/dian-doctor --cert=empresa.p12 --password='***'   # diagnóstico del entorno
```

## Uso

```php
use RoyaltyFusion\DianPhp\Dian;
use RoyaltyFusion\DianPhp\Ws\SoapClient;

$dian = new Dian('/ruta/empresa.p12', getenv('DIAN_CERT_PASSWORD'), SoapClient::ENV_HABILITACION);

// 1) Validación previa sin enviar: reglas DIAN, XSD, firma, CUFE recalculado
$report = $dian->preflight($invoice);
echo $report->toText();

// 2) Envío (repite la validación y no envía si hay errores)
$result = $dian->send($invoice);

if ($result->isSuccess()) {
    echo $result->getCufe();
} elseif ($result->isPending()) {                     // set de pruebas (asíncrono)
    $status = $dian->waitForResult($result->getZipKey());
} else {
    print_r($result->getErrors());                    // "Regla: FAD06, Rechazo: …"
}
```

→ **[Guía de integración: de cero a producción](./docs/integration-guide.md)** ·
[Quick start](./docs/quick-start.md) · [Toda la documentación](./docs/index.md)

## Características

| | |
|---|---|
| Documentos | Factura de venta (nacional, exportación, contingencia, AIU, mandatos…), nota crédito y débito (con y sin referencia), documento soporte y su nota de ajuste, nómina individual, eventos RADIAN, AttachedDocument |
| Firma | XAdES-EPES con la política DIAN v2, verificada contra firmas de documentos reales aprobados por la DIAN |
| Web service | Las 14 operaciones de la DIAN con WS-Security y WS-Addressing, ZIP, reintentos, logs PSR-3 y lectura de rechazos, notificaciones y SOAP Faults |
| Cálculos | Totales, tributos y redondeo según el Anexo; CUFE validado con el ejemplo oficial; CUDE, CUDS, CUNE y CUDE de eventos |
| Validación previa | ~60 reglas de negocio con códigos, XSD UBL 2.1 + DIAN incluidos, verificación de firma y recálculo del CUFE desde el XML |
| Herramientas | `dian-doctor` (CLI), comandos Symfony `dian:doctor` y `dian:status`, representación gráfica HTML para PDF |
| Catálogos | Tablas DIAN como enums; municipios DANE, unidades, monedas, países y medios de pago |
| Frameworks | PHP puro, Laravel (ver guía) y bundle Symfony 6.4/7 |

## Estructura

```
src/
├── Dian.php            Facade: preflight, send, consultas, AttachedDocument
├── Model/              Invoice, CreditNote, DebitNote, Company, Client, Item, Tax…
├── Catalog/            Enums y registros DIAN/DANE
├── Xml/                DocumentCalculator, CufeGenerator, QrGenerator, XmlBuilder, plantillas Twig
├── Signer/             Certificate, XadesSigner, SignatureVerifier
├── Ws/                 SoapClient, WsSecurity, DianResponse, FileNameGenerator
├── Validator/          BusinessRuleValidator, XsdValidator, XmlConsistencyChecker, Preflight
├── SupportDocument/    Documento soporte + nota de ajuste
├── Payroll/            Nómina electrónica
├── Radian/             Eventos RADIAN
├── AttachedDocument/   Contenedor para el adquiriente (builder + parser)
├── Report/             Representación gráfica HTML
├── Tools/              Doctor (diagnóstico)
└── Bridge/Symfony/     Bundle
resources/xsd/          Esquemas UBL 2.1 + DIAN (validación sin red)
resources/fixtures/     Documentos reales aprobados por la DIAN (pruebas de regresión)
```

## Calidad

* PHPUnit: firma, XSD, CUFE y consistencia probados contra documentos reales
  aprobados por la DIAN y contra el ejemplo del Anexo Técnico.
* PHPStan nivel 6 sin errores.
* CI: PHP 8.1 – 8.4 × Symfony 6.4 / 7.1.

Qué falta confirmar contra la DIAN en vivo:
[guía, sección 14](./docs/integration-guide.md#14-qué-está-verificado-y-qué-no).

## Compatibilidad

* PHP 8.1, 8.2, 8.3, 8.4 (`dom`, `libxml`, `openssl`, `zip`)
* Symfony 6.4 LTS y 7.x (opcional)
* DIAN Anexo Técnico V1.9 — UBL 2.1

## Licencia

MIT © [Daniel Muñoz](https://royaltifusion.com).

## Créditos

Diseño inspirado en [Greenter](https://github.com/giansalex/greenter). Ver
[CREDITS.md](./CREDITS.md).
