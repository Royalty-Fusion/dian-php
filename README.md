# Royalty Fusion DIAN PHP

![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)
![UBL](https://img.shields.io/badge/UBL-2.1-orange.svg)

**Royalty Fusion DIAN PHP** es un SDK Open Source diseñado para interactuar con los Web Services de Facturación Electrónica de la DIAN (Colombia) de acuerdo con el **Anexo Técnico V1.9** y el estándar **UBL 2.1**.

Adoptando la filosofía de **Greenter**, el SDK está construido bajo un enfoque desacoplado y orientado a objetos en PHP 8.2+, empleando **plantillas Twig** para que la generación y el formato del XML sean completamente transparentes, declarativos y modulares.

---

## 🚀 Características Principales

- **Filosofía Greenter**: Plantillas Twig modulares (`.xml.twig`) para una visualización exacta del XML a compilar.
- **Flujo 100% Homologado**: Cumple con el Anexo Técnico V1.9 de la DIAN para Facturas, Notas de Crédito y Notas de Débito.
- **Fluent Interfaces**: Permite definir documentos de manera estructurada y fluida.
- **Firma Digital Avanzada XAdES-EPES**: Firmador seguro RSA-SHA256 con soporte oficial para la política de firma de la DIAN v2.
- **Cálculo Automático de CUFE/CUDE**: Genera los hashes reglamentarios (SHA-384) de forma transparente.
- **Soporte Completo de Impuestos y Pagos**: Impuestos consolidados y detallados (IVA, INC, ICA) y métodos/medios de pago.
- **Comunicación SOAP nativa**: Utiliza `symfony/http-client` y compresión ZIP nativa sin lidiar con los problemas de `ext-soap`.

---

## 📦 Instalación

Instala el SDK en tu proyecto vía [Composer](https://getcomposer.org/):

```bash
composer require royaltyfusion/dian-php
```

**Requisitos del Sistema:**
- PHP 8.1 o superior (recomendado 8.2+).
- Extensiones PHP: `libxml`, `dom`, `zip`, y `openssl`.

---

## 💻 Ejemplos de Uso Completo (con 5 ítems cada uno)

A continuación se detallan los ejemplos de código para los tres tipos de documentos principales de la DIAN:

---

### 1. Factura Electrónica de Venta (Invoice)
Ejemplo detallado para generar una factura comercial estándar con 5 ítems y desgloses de impuestos.

```php
<?php

require 'vendor/autoload.php';

use RoyaltyFusion\DianPhp\Dian;
use RoyaltyFusion\DianPhp\Service\SoapClient;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Model\Resolution;
use RoyaltyFusion\DianPhp\Model\Tax;
use RoyaltyFusion\DianPhp\Model\Payment;

// Emisor
$company = (new Company())
    ->setNit('901234567')
    ->setRazonSocial('Royalty Fusion S.A.S')
    ->setTipoDocumento('31')
    ->setRegimen('48')
    ->setResponsabilidades('O-47;R-99-PN')
    ->setTipoOrganizacion('1');

// Adquiriente
$client = (new Client())
    ->setTipoDocumento('13')
    ->setNumeroDocumento('1010101010')
    ->setRazonSocial('Juan Pérez')
    ->setEmail('juan.perez@example.com')
    ->setRegimen('49')
    ->setResponsabilidades('R-99-PN')
    ->setTipoOrganizacion('2');

// Software y Resolución
$software = (new Software())
    ->setId('d35e1234-abcd-1234-abcd-0123456789ab')
    ->setPin('12345')
    ->setProviderNit('901234567');

$resolution = (new Resolution())
    ->setNumber('18760000001')
    ->setPrefix('SETT')
    ->setFrom('990000000')
    ->setTo('995000000')
    ->setDateFrom(new \DateTime('2026-01-01'))
    ->setDateTo(new \DateTime('2026-12-31'));

$invoice = (new Invoice())
    ->setPrefijo('SETT')
    ->setNumero('990000001')
    ->setFecha(new \DateTime('now'))
    ->setCompany($company)
    ->setClient($client)
    ->setSoftware($software)
    ->setResolution($resolution)
    ->setTechnicalKey('fc84fa2d9d0e2d147814b74bb20942d45a990000')
    ->setTestSetId('a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d');

// Agregar 5 Ítems
$subtotal = 0.0;
$totalIva = 0.0;

for ($i = 1; $i <= 5; $i++) {
    $precio = 10000.00 * $i;
    $cantidad = 2.0;
    $base = $precio * $cantidad;
    $ivaAmount = $base * 0.19;
    
    $subtotal += $base;
    $totalIva += $ivaAmount;

    $itemTax = (new Tax())
        ->setCode('01')
        ->setName('IVA')
        ->setPercent(19.00)
        ->setBase($base)
        ->setAmount($ivaAmount);

    $item = (new Item())
        ->setDescripcion("Servicio TI Nivel $i")
        ->setCantidad($cantidad)
        ->setPrecio($precio)
        ->addTax($itemTax);

    $invoice->addItem($item);
}

// Impuestos globales a nivel de Factura
$globalTax = (new Tax())
    ->setCode('01')
    ->setName('IVA')
    ->setPercent(19.00)
    ->setBase($subtotal)
    ->setAmount($totalIva);

$invoice->addTax($globalTax)
    ->setTotal($subtotal + $totalIva);

// Forma de Pago
$payment = (new Payment())
    ->setMethodId('2') // Crédito
    ->setMeansId('42') // Consignación
    ->setDueDate(new \DateTime('+30 days'));
$invoice->addPayment($payment);

// Enviar a la DIAN
$dian = new Dian('/ruta/certificado.p12', 'MiClaveSecreta123');
$result = $dian->send($invoice);

if ($result->isSuccess()) {
    echo "✔ Factura aceptada. CUFE: " . $result->getCufe();
} else {
    echo "❌ Error: " . $result->getErrorMessage();
}
```

---

### 2. Nota de Crédito (Credit Note)
Ejemplo de nota de crédito para realizar ajustes o anulación total de una factura con 5 ítems de devolución.

```php
<?php

require 'vendor/autoload.php';

use RoyaltyFusion\DianPhp\Dian;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\BillingReference;
use RoyaltyFusion\DianPhp\Model\DiscrepancyResponse;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Model\Tax;
use RoyaltyFusion\DianPhp\Model\Payment;

// Emisor, Cliente y Software (mismos que en la factura)
$company = (new Company())->setNit('901234567')->setRazonSocial('Royalty Fusion S.A.S')->setTipoDocumento('31');
$client = (new Client())->setTipoDocumento('13')->setNumeroDocumento('1010101010')->setRazonSocial('Juan Pérez');
$software = (new Software())->setId('d35e1234-abcd-1234-abcd-0123456789ab')->setPin('12345')->setProviderNit('901234567');

// Referencia de la Factura Original afectada
$billingRef = (new BillingReference())
    ->setNumber('SETT990000001')
    ->setUuid('34fc3127c42c29541765c675056829c244c5480fdb48246a014467e226b805e1169c46821788cb7233ebde74ef92699b') // CUFE de factura original
    ->setDate(new \DateTime('2026-05-28'));

// Concepto de Discrepancia
$discrepancy = (new DiscrepancyResponse())
    ->setReferenceId('SETT990000001')
    ->setResponseCode('2') // 2: Anulación de factura electrónica
    ->setDescription('Devolución completa de la mercadería');

$creditNote = (new CreditNote())
    ->setPrefijo('NC')
    ->setNumero('990000001')
    ->setFecha(new \DateTime('now'))
    ->setCompany($company)
    ->setClient($client)
    ->setSoftware($software)
    ->setBillingReference($billingRef)
    ->setDiscrepancyResponse($discrepancy)
    ->setTestSetId('a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d');

// Cargar los 5 ítems a descontar/anular
$subtotal = 0.0;
$totalIva = 0.0;

for ($i = 1; $i <= 5; $i++) {
    $precio = 10000.00 * $i;
    $cantidad = 2.0;
    $base = $precio * $cantidad;
    $ivaAmount = $base * 0.19;
    
    $subtotal += $base;
    $totalIva += $ivaAmount;

    $itemTax = (new Tax())->setCode('01')->setName('IVA')->setPercent(19.00)->setBase($base)->setAmount($ivaAmount);
    $item = (new Item())->setDescripcion("Devolucion Producto $i")->setCantidad($cantidad)->setPrecio($precio)->addTax($itemTax);
    $creditNote->addItem($item);
}

$globalTax = (new Tax())->setCode('01')->setName('IVA')->setPercent(19.00)->setBase($subtotal)->setAmount($totalIva);
$creditNote->addTax($globalTax)->setTotal($subtotal + $totalIva);

// Enviar Nota de Crédito
$dian = new Dian('/ruta/certificado.p12', 'MiClaveSecreta123');
$result = $dian->send($creditNote);

if ($result->isSuccess()) {
    echo "✔ Nota de Crédito enviada. CUDE: " . $result->getCufe();
} else {
    echo "❌ Error: " . $result->getErrorMessage();
}
```

---

### 3. Nota de Débito (Debit Note)
Ejemplo de nota de débito para realizar un cobro adicional o intereses sobre una factura previamente generada, con 5 ítems de recargo.

```php
<?php

require 'vendor/autoload.php';

use RoyaltyFusion\DianPhp\Dian;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\DebitNote;
use RoyaltyFusion\DianPhp\Model\BillingReference;
use RoyaltyFusion\DianPhp\Model\DiscrepancyResponse;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Model\Tax;

// Emisor, Cliente y Software
$company = (new Company())->setNit('901234567')->setRazonSocial('Royalty Fusion S.A.S')->setTipoDocumento('31');
$client = (new Client())->setTipoDocumento('13')->setNumeroDocumento('1010101010')->setRazonSocial('Juan Pérez');
$software = (new Software())->setId('d35e1234-abcd-1234-abcd-0123456789ab')->setPin('12345')->setProviderNit('901234567');

// Referencia de la Factura Original afectada
$billingRef = (new BillingReference())
    ->setNumber('SETT990000001')
    ->setUuid('34fc3127c42c29541765c675056829c244c5480fdb48246a014467e226b805e1169c46821788cb7233ebde74ef92699b')
    ->setDate(new \DateTime('2026-05-28'));

// Concepto de Recargo
$discrepancy = (new DiscrepancyResponse())
    ->setReferenceId('SETT990000001')
    ->setResponseCode('3') // 3: Valor adicional
    ->setDescription('Intereses y mora en el pago');

$debitNote = (new DebitNote())
    ->setPrefijo('ND')
    ->setNumero('990000001')
    ->setFecha(new \DateTime('now'))
    ->setCompany($company)
    ->setClient($client)
    ->setSoftware($software)
    ->setBillingReference($billingRef)
    ->setDiscrepancyResponse($discrepancy)
    ->setTestSetId('a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d');

// Cargar los 5 ítems de cobro adicional
$subtotal = 0.0;
$totalIva = 0.0;

for ($i = 1; $i <= 5; $i++) {
    $precio = 2000.00 * $i;
    $cantidad = 1.0;
    $base = $precio * $cantidad;
    $ivaAmount = $base * 0.19;
    
    $subtotal += $base;
    $totalIva += $ivaAmount;

    $itemTax = (new Tax())->setCode('01')->setName('IVA')->setPercent(19.00)->setBase($base)->setAmount($ivaAmount);
    $item = (new Item())->setDescripcion("Recargo por servicio Nivel $i")->setCantidad($cantidad)->setPrecio($precio)->addTax($itemTax);
    $debitNote->addItem($item);
}

$globalTax = (new Tax())->setCode('01')->setName('IVA')->setPercent(19.00)->setBase($subtotal)->setAmount($totalIva);
$debitNote->addTax($globalTax)->setTotal($subtotal + $totalIva);

// Enviar Nota de Débito
$dian = new Dian('/ruta/certificado.p12', 'MiClaveSecreta123');
$result = $dian->send($debitNote);

if ($result->isSuccess()) {
    echo "✔ Nota de Débito enviada. CUDE: " . $result->getCufe();
} else {
    echo "❌ Error: " . $result->getErrorMessage();
}
```

---

## 🏗 Arquitectura

El SDK separa responsabilidades de acuerdo con la convención de Greenter:

- `src/Model/`: Modelos y DTOs estructurados de datos.
- `src/Xml/templates/`: Contiene las plantillas XML compiladas en Twig (`invoice.xml.twig`, `creditnote.xml.twig`, `debitnote.xml.twig`).
- `src/Service/XmlBuilder.php`: Mapea datos y renderiza las plantillas Twig.
- `src/Service/XadesSigner.php`: Ejecuta la firma digital XAdES-EPES.
- `src/Service/SoapClient.php`: Comprime en ZIP y envía al Web Service oficial de la DIAN.

---

## 👨‍💻 Acerca del Autor

**Daniel Muñoz**  
Arquitecto de Software & Experto en Facturación Electrónica DIAN.
- ✉️ Email: dmunoz@royaltyfusion.com
- 🌐 Sitio Web: [https://royaltifusion.com](https://royaltifusion.com)
