<?php

/**
 * Ejemplo ejecutable: Factura Electrónica de Venta (5 ítems).
 *
 * Requiere un certificado de prueba en resources/certs/test.p12 y un Twig
 * template ya renderizado. Si solo quieres ver el XML firmado sin enviar,
 * cambia $dian->send() por $dian->getSignedXml().
 *
 *   $ php examples/invoice.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use RoyaltyFusion\DianPhp\Dian;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Payment;
use RoyaltyFusion\DianPhp\Model\Resolution;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Model\Tax;
use RoyaltyFusion\DianPhp\Ws\SoapClient;

$company = (new Company())
    ->setNit('901234567')
    ->setRazonSocial('Royalty Fusion S.A.S')
    ->setTipoDocumento('31')
    ->setRegimen('48')
    ->setResponsabilidades('O-47;R-99-PN')
    ->setTipoOrganizacion('1');

$client = (new Client())
    ->setTipoDocumento('13')
    ->setNumeroDocumento('1010101010')
    ->setRazonSocial('Juan Pérez')
    ->setEmail('juan.perez@example.com')
    ->setRegimen('49')
    ->setResponsabilidades('R-99-PN')
    ->setTipoOrganizacion('2');

$software = (new Software())
    ->setId('d35e1234-abcd-1234-abcd-0123456789ab')
    ->setPin('12345')
    ->setProviderNit('901234567');

$resolution = (new Resolution())
    ->setNumber('18760000001')
    ->setPrefix('SETT')
    ->setFrom('990000000')
    ->setTo('995000000')
    ->setDateFrom(new DateTimeImmutable('2026-01-01'))
    ->setDateTo(new DateTimeImmutable('2026-12-31'));

$invoice = (new Invoice())
    ->setPrefijo('SETT')
    ->setNumero('990000001')
    ->setFecha(new DateTimeImmutable('now'))
    ->setCompany($company)
    ->setClient($client)
    ->setSoftware($software)
    ->setResolution($resolution)
    ->setTechnicalKey('fc84fa2d9d0e2d147814b74bb20942d45a990000')
    ->setTestSetId('a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d');

$subtotal = 0.0;
$totalIva = 0.0;

for ($i = 1; $i <= 5; $i++) {
    $precio   = 10000.00 * $i;
    $cantidad = 2.0;
    $base     = $precio * $cantidad;
    $iva      = $base * 0.19;

    $subtotal += $base;
    $totalIva += $iva;

    $itemTax = (new Tax())
        ->setCode('01')->setName('IVA')->setPercent(19.0)
        ->setBase($base)->setAmount($iva);

    $invoice->addItem(
        (new Item())
            ->setDescripcion("Servicio TI Nivel $i")
            ->setCantidad($cantidad)
            ->setPrecio($precio)
            ->addTax($itemTax)
    );
}

$invoice
    ->addTax(
        (new Tax())->setCode('01')->setName('IVA')->setPercent(19.0)
            ->setBase($subtotal)->setAmount($totalIva)
    )
    ->setTotal($subtotal + $totalIva)
    ->addPayment(
        (new Payment())
            ->setMethodId('2')
            ->setMeansId('42')
            ->setDueDate(new DateTimeImmutable('+30 days'))
    );

$dian = new Dian(
    __DIR__ . '/../resources/certs/test.p12',
    'MiClaveSecreta123',
    SoapClient::ENV_HABILITACION
);

$result = $dian->send($invoice);

if ($result->isSuccess()) {
    echo "OK - Factura aceptada. CUFE: {$result->getCufe()}\n";
} else {
    echo "ERROR: {$result->getErrorMessage()}\n";
}
