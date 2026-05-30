<?php

/**
 * Ejemplo ejecutable: Nota Crédito por devolución parcial.
 *
 * $ php examples/credit-note.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use RoyaltyFusion\DianPhp\Catalog\TipoNotaCredito;
use RoyaltyFusion\DianPhp\Dian;
use RoyaltyFusion\DianPhp\Model\BillingReference;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DiscrepancyResponse;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Model\Tax;

$company  = (new Company())->setNit('901234567')->setRazonSocial('Royalty Fusion S.A.S')->setTipoDocumento('31');
$client   = (new Client())->setTipoDocumento('13')->setNumeroDocumento('1010101010')->setRazonSocial('Juan Pérez');
$software = (new Software())->setId('uuid-demo')->setPin('12345')->setProviderNit('901234567');

$creditNote = (new CreditNote())
    ->setPrefijo('NC')
    ->setNumero('990000001')
    ->setFecha(new DateTimeImmutable('now'))
    ->setCompany($company)
    ->setClient($client)
    ->setSoftware($software)
    ->setBillingReference(
        (new BillingReference())
            ->setNumber('SETT990000001')
            ->setUuid(str_repeat('a', 96))
            ->setDate(new DateTimeImmutable('-15 days'))
    )
    ->setDiscrepancyResponse(
        (new DiscrepancyResponse())
            ->setReferenceId('SETT990000001')
            ->setResponseCode(TipoNotaCredito::DEVOLUCION_PARCIAL->code())
            ->setDescription('Devolución de 2 unidades por defecto')
    )
    ->setTestSetId('a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d');

$iva   = (new Tax())->setCode('01')->setName('IVA')->setPercent(19.0)->setBase(20000.0)->setAmount(3800.0);
$item  = (new Item())->setDescripcion('Producto X')->setCantidad(2.0)->setPrecio(10000.0)->addTax($iva);

$creditNote
    ->setTotal(23800.0)
    ->addItem($item)
    ->addTax($iva);

$dian   = new Dian(__DIR__ . '/../resources/certs/test.p12', 'pwd', \RoyaltyFusion\DianPhp\Ws\SoapClient::ENV_HABILITACION);
$result = $dian->send($creditNote);

echo $result->isSuccess()
    ? "OK CUDE {$result->getCufe()}\n"
    : "ERROR {$result->getErrorMessage()}\n";
