# Royalty Fusion DIAN PHP

![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)
![UBL](https://img.shields.io/badge/UBL-2.1-orange.svg)
![Anexo](https://img.shields.io/badge/DIAN-Anexo%20V1.9-red.svg)

SDK Open Source en PHP 8.1+ para facturación electrónica DIAN (Colombia) bajo
UBL 2.1 y el Anexo Técnico V1.9, con la **misma filosofía desacoplada** de
[Greenter](https://github.com/giansalex/greenter).

> Modelos planos · Plantillas Twig visibles · Catálogos como enums · Validador
> previo · XAdES-EPES con política DIAN v2 · Bundle Symfony incluido.

## Instalación

```bash
composer require royaltyfusion/dian-php
```

## Quick start

```php
use RoyaltyFusion\DianPhp\Dian;
use RoyaltyFusion\DianPhp\Model\{Company, Client, Invoice, Item, Tax, Software, Resolution};
use RoyaltyFusion\DianPhp\Ws\SoapClient;

// 1) Construir el documento
$invoice = (new Invoice())
    ->setPrefijo('SETT')->setNumero('990000001')
    ->setFecha(new DateTimeImmutable('now'))
    ->setCompany($company)        // emisor con NIT, DV, dirección, contacto...
    ->setClient($client)
    ->setSoftware($software)
    ->setResolution($resolution)
    ->setTechnicalKey('...')
    ->setTotal(119000.0)
    ->addItem($item)
    ->addTax($iva);

// 2) Firmar + enviar
$dian   = (new Dian('/path/cert.p12', 'pwd', SoapClient::ENV_HABILITACION))
            ->validateBeforeSend(true);
$result = $dian->send($invoice);

echo $result->isSuccess() ? $result->getCufe() : $result->getErrorMessage();
```

→ **[Guía completa de 5 minutos](./docs/quick-start.md)** · **[Toda la documentación](./docs/index.md)**

## Características

- **Factura electrónica de venta** ✅
- **Notas crédito y débito** (con y sin referencia, todos los códigos de discrepancia) ✅
- **Descuentos, recargos, anticipos, retenciones** (AllowanceCharge, Prepayment, WithholdingTaxTotal) ✅
- **Catálogos oficiales DIAN** como enums tipados (`Tributo`, `FormaPago`, `Responsabilidad`, ...) ✅
- **Validador previo al envío** con códigos de error legibles ✅
- **Firma XAdES-EPES** con política oficial DIAN v2 (RSA-SHA256, SHA-384) ✅
- **Representación gráfica HTML** lista para mpdf / dompdf ✅
- **Bundle de Symfony** con DI, configuración YAML y comandos de consola ✅
- **Multi-moneda** con TRM ✅
- **Documento Soporte / Nómina / RADIAN** 🚧 *scaffold listo, completándose en próximas releases*

## Arquitectura

```
src/
├── Dian.php                  Facade
├── Model/                    DTOs (Invoice, Company, AllowanceCharge, ...)
├── Catalog/                  Enums DIAN
├── Xml/                      XmlBuilder + DocumentCalculator + CUFE + QR + Twig
├── Signer/                   XadesSigner
├── Ws/                       SoapClient (SendBill, GetStatus, GetStatusZip)
├── Validator/                BusinessRule + Xsd
├── Report/                   HtmlReport (PDF-ready)
├── SupportDocument/          (scaffold)
├── Payroll/                  (scaffold)
├── Radian/                   (scaffold)
└── Bridge/Symfony/           Bundle
```

Ver [docs/architecture.md](./docs/architecture.md) para el diagrama de flujo.

## Compatibilidad

* PHP 8.1, 8.2, 8.3, 8.4
* Symfony 6.4 LTS y 7.x
* DIAN Anexo Técnico V1.9 — UBL 2.1

## Licencia

MIT © [Daniel Muñoz](https://royaltifusion.com).

## Créditos

Diseño inspirado en [Greenter](https://github.com/giansalex/greenter) (autor:
[Giancarlos Salas](https://github.com/giansalex)) — el SDK de facturación
electrónica más usado de Perú.
