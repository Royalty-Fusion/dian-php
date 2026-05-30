# Quick start — tu primera factura

Este ejemplo cubre el caso típico: una factura con un ítem y un IVA del 19%.

```php
<?php
require 'vendor/autoload.php';

use RoyaltyFusion\DianPhp\Dian;
use RoyaltyFusion\DianPhp\Model\{Address, Client, Company, Contact, Invoice, Item, Payment, Resolution, Software, Tax};
use RoyaltyFusion\DianPhp\Ws\SoapClient;

// 1. Emisor
$company = (new Company())
    ->setNit('901234567')
    ->setRazonSocial('Royalty Fusion S.A.S')
    ->setCommercialName('Royalty Fusion')
    ->setTipoDocumento('31')              // 31 = NIT
    ->setRegimen('48')                    // 48 = Responsable IVA
    ->setResponsabilidades('O-13;O-15')   // gran contribuyente + autorretenedor
    ->setTipoOrganizacion('1')            // 1 = Persona Jurídica
    ->setIndustryClassificationCode('6201')
    ->setMunicipalityCode('11001')
    ->setAddress((new Address())
        ->setLine('Cra. 7 # 71-21 Of. 1101')
        ->setCityCode('11001')->setCityName('Bogotá D.C.')
        ->setDepartmentCode('11')->setDepartmentName('Bogotá')
        ->setCountryCode('CO')->setCountryName('Colombia'))
    ->setContact((new Contact())
        ->setName('Daniel Muñoz')
        ->setTelephone('+57 300 0000000')
        ->setElectronicMail('facturacion@royaltyfusion.com'));

// 2. Adquiriente
$client = (new Client())
    ->setTipoDocumento('13')               // 13 = Cédula
    ->setNumeroDocumento('1010101010')
    ->setRazonSocial('Juan Pérez')
    ->setEmail('juan.perez@example.com')
    ->setRegimen('49')                     // 49 = No responsable IVA
    ->setResponsabilidades('R-99-PN')
    ->setTipoOrganizacion('2');

// 3. Software DIAN (lo entrega la DIAN al habilitar)
$software = (new Software())
    ->setId('d35e1234-abcd-1234-abcd-0123456789ab')
    ->setPin('12345')
    ->setProviderNit('901234567');

// 4. Resolución de facturación electrónica
$resolution = (new Resolution())
    ->setNumber('18760000001')
    ->setPrefix('SETT')
    ->setFrom('990000000')->setTo('995000000')
    ->setDateFrom(new DateTimeImmutable('2026-01-01'))
    ->setDateTo(new DateTimeImmutable('2026-12-31'));

// 5. Línea + impuesto
$iva = (new Tax())->setCode('01')->setName('IVA')->setPercent(19.0)
    ->setBase(100000.0)->setAmount(19000.0);

$item = (new Item())
    ->setDescripcion('Consultoría TI - Mayo 2026')
    ->setCantidad(1.0)
    ->setPrecio(100000.0)
    ->setUnitCode('HUR')                   // horas
    ->setCode('CONS-TI-01')
    ->addTax($iva);

// 6. Documento + pago
$invoice = (new Invoice())
    ->setPrefijo('SETT')->setNumero('990000001')
    ->setFecha(new DateTimeImmutable('now'))
    ->setCompany($company)
    ->setClient($client)
    ->setSoftware($software)
    ->setResolution($resolution)
    ->setTechnicalKey('fc84fa2d9d0e2d147814b74bb20942d45a990000')
    ->setTestSetId('a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d')  // omitir en prod
    ->setTotal(119000.0)
    ->addItem($item)
    ->addTax($iva)
    ->addPayment((new Payment())
        ->setMethodId('2')                 // 2 = Crédito
        ->setMeansId('42')                 // 42 = Consignación
        ->setDueDate(new DateTimeImmutable('+30 days')));

// 7. Enviar
$dian = (new Dian(
    getenv('DIAN_CERT_PATH'),
    getenv('DIAN_CERT_PASSWORD'),
    SoapClient::ENV_HABILITACION
))->validateBeforeSend(true);

$result = $dian->send($invoice);

if ($result->isSuccess()) {
    echo "✔ CUFE: {$result->getCufe()}\n";
} else {
    echo "✗ {$result->getErrorMessage()}\n";
}
```

## Variantes

* **Solo generar XML sin enviar:** `$dian->getSignedXml($invoice);`
* **Consultar estado luego:** `bin/console dian:status <cufe>` (bundle Symfony)
* **Renderizar la representación gráfica:** ver [report.md](./report.md).
