<?php

/**
 * Ejemplo: Documento Soporte en Adquisiciones (DS).
 *
 * Se usa cuando le compras a un NO obligado a facturar (típico: persona natural
 * por debajo del umbral). Tu empresa (acquirente) emite el DS contra DIAN.
 *
 *   $ php examples/support-document.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Model\Tax;
use RoyaltyFusion\DianPhp\SupportDocument\CudsGenerator;
use RoyaltyFusion\DianPhp\SupportDocument\SupportDocument;
use RoyaltyFusion\DianPhp\SupportDocument\SupportDocumentBuilder;

// Acquirente (tu empresa) — emisor del DS
$company = (new Company())
    ->setNit('901234567')
    ->setRazonSocial('Royalty Fusion S.A.S')
    ->setTipoDocumento('31')
    ->setRegimen('48')
    ->setResponsabilidades('O-13')
    ->setTipoOrganizacion('1');

// Proveedor no obligado a facturar
$proveedor = (new Client())
    ->setTipoDocumento('13')
    ->setNumeroDocumento('1010101010')
    ->setRazonSocial('Carlos Pérez (No Obligado)')
    ->setResponsabilidades('R-99-PN')
    ->setTipoOrganizacion('2');

$software = (new Software())
    ->setId('uuid-software-ds')
    ->setPin('12345')
    ->setProviderNit('901234567');

$iva  = (new Tax())->setCode('01')->setName('IVA')->setPercent(0.0)->setBase(50000.0)->setAmount(0.0);
$item = (new Item())
    ->setDescripcion('Servicios de mantenimiento puntual')
    ->setCantidad(1.0)
    ->setPrecio(50000.0)
    ->setUnitCode('HUR')
    ->addTax($iva);

$ds = (new SupportDocument())
    ->setPrefijo('DS')->setNumero('1')
    ->setFecha(new DateTimeImmutable('now'))
    ->setCompany($company)
    ->setSupplier($proveedor)
    ->setSoftware($software)
    ->setTotal(50000.0)
    ->addItem($item)
    ->addTax($iva);

$cuds = (new CudsGenerator())->generate($ds, $software->getPin(), CudsGenerator::ENV_HABILITACION);
$xml  = (new SupportDocumentBuilder())->build($ds, $cuds, "https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey={$cuds}");

echo "CUDS: {$cuds}\n";
echo "XML generado: " . strlen($xml) . " bytes\n";

// Para firmarlo y enviarlo:
//   $signed = (new XadesSigner('/path/to/cert.p12', 'pwd'))->sign($xml);
//   $result = (new SoapClient(SoapClient::ENV_HABILITACION))->send("z{nit}{prefijo}{numero}", $signed, '', $cuds);
