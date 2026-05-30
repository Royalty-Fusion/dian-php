<?php

/**
 * Ejemplo ejecutable: Nota Débito por intereses de mora.
 *
 * $ php examples/debit-note.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use RoyaltyFusion\DianPhp\Catalog\TipoNotaDebito;
use RoyaltyFusion\DianPhp\Dian;
use RoyaltyFusion\DianPhp\Model\BillingReference;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\DebitNote;
use RoyaltyFusion\DianPhp\Model\DiscrepancyResponse;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Model\Tax;

$company  = (new Company())->setNit('901234567')->setRazonSocial('Royalty Fusion S.A.S')->setTipoDocumento('31');
$client   = (new Client())->setTipoDocumento('13')->setNumeroDocumento('1010101010')->setRazonSocial('Juan Pérez');
$software = (new Software())->setId('uuid-demo')->setPin('12345')->setProviderNit('901234567');

$debitNote = (new DebitNote())
    ->setPrefijo('ND')
    ->setNumero('990000001')
    ->setFecha(new DateTimeImmutable('now'))
    ->setCompany($company)
    ->setClient($client)
    ->setSoftware($software)
    ->setBillingReference(
        (new BillingReference())
            ->setNumber('SETT990000001')
            ->setUuid(str_repeat('a', 96))
            ->setDate(new DateTimeImmutable('-45 days'))
    )
    ->setDiscrepancyResponse(
        (new DiscrepancyResponse())
            ->setReferenceId('SETT990000001')
            ->setResponseCode(TipoNotaDebito::INTERESES->code())
            ->setDescription('Intereses por mora — 2.5% mensual')
    )
    ->setTestSetId('a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d');

$iva   = (new Tax())->setCode('01')->setName('IVA')->setPercent(19.0)->setBase(2500.0)->setAmount(475.0);
$item  = (new Item())->setDescripcion('Interés por mora — Mayo 2026')->setCantidad(1.0)->setPrecio(2500.0)->addTax($iva);

$debitNote
    ->setTotal(2975.0)
    ->addItem($item)
    ->addTax($iva);

$dian   = new Dian(__DIR__ . '/../resources/certs/test.p12', 'pwd', \RoyaltyFusion\DianPhp\Ws\SoapClient::ENV_HABILITACION);
$result = $dian->send($debitNote);

echo $result->isSuccess()
    ? "OK CUDE {$result->getCufe()}\n"
    : "ERROR {$result->getErrorMessage()}\n";
