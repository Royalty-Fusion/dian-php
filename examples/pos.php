<?php

/**
 * Documento equivalente electrónico POS (tiquete de caja) a consumidor final,
 * con consecutivo asignado por NumberingManager.
 *
 *   php examples/pos.php           # dry run: valida, firma y verifica localmente
 *   php examples/pos.php --send    # además envía a la DIAN (requiere variables DIAN_*)
 */

declare(strict_types=1);

require __DIR__ . '/_config.php';

use RoyaltyFusion\DianPhp\Catalog\TipoDocumentoEquivalente;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\EquivalentDocument;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Payment;
use RoyaltyFusion\DianPhp\Model\Resolution;
use RoyaltyFusion\DianPhp\Model\Tax;
use RoyaltyFusion\DianPhp\Numbering\NumberingManager;
use RoyaltyFusion\DianPhp\Numbering\PdoNumberingStore;

// Resolución de numeración del POS (en habilitación, la del set de pruebas).
$resolution = (new Resolution())
    ->setNumber('18760000001')->setPrefix('SETP')->setFrom('990000000')->setTo('995000000')
    ->setDateFrom(new DateTimeImmutable('2019-01-19'))->setDateTo(new DateTimeImmutable('2030-01-19'));

// Consecutivos: en producción use la base de datos de su aplicación.
$store = new PdoNumberingStore(new PDO('sqlite::memory:'));
$store->createTable();
$numbering = new NumberingManager($store);

$fecha = new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
$pos   = (new EquivalentDocument(TipoDocumentoEquivalente::POS))
    ->setPrefijo($resolution->getPrefix())
    ->setNumero($numbering->next($resolution, $fecha))
    ->setFecha($fecha)
    ->setCompany(company())
    ->setClient(Client::consumidorFinal())
    ->setSoftware(software())
    ->setResolution($resolution)
    ->setTestSetId(env('DIAN_TEST_SET_ID'))
    ->addPayment((new Payment())->setMethodId('1')->setMeansId('10'))   // contado, efectivo
    ->addItem((new Item())->setDescripcion('Café americano')->setCantidad(2)->setPrecio(4200)
        ->addTax((new Tax())->setCode('04')->setPercent(8)->setBase(8400)))      // INC restaurantes
    ->addItem((new Item())->setDescripcion('Bolsa plástica')->setCantidad(1)->setPrecio(0)->setReferencePrice(66)
        ->addTax((new Tax())->setCode('22')->setPerUnit(66, 1)));               // INC bolsas: valor por unidad

$pos->setFabricanteSoftware('Daniel Muñoz', 'Royalty Fusion S.A.S.', 'RF POS')
    ->setPuntoVenta('CAJA-01', 'Bogotá - Local 1', 'Ana Pérez', 'Mostrador', 'V-' . $pos->getNumero())
    ->setBeneficiosComprador('0', 'Consumidor final', 0);

$dian   = dian();
$report = $dian->preflight($pos);

echo $report->toText(), "\n";
echo "CUDE: {$report->getUuid()}\n";
foreach ($numbering->alerts($resolution) as $alert) {
    echo "Alerta: {$alert}\n";
}

if (shouldSend() && $report->isReady()) {
    printResult($dian->send($pos));
}
