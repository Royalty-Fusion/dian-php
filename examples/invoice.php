<?php

/**
 * Factura Electrónica de Venta: IVA 19 %, descuento de línea, crédito a 30 días.
 *
 *   php examples/invoice.php           # dry run: valida, firma y verifica localmente
 *   php examples/invoice.php --send    # además envía a la DIAN (requiere variables DIAN_*)
 */

declare(strict_types=1);

require __DIR__ . '/_config.php';

use RoyaltyFusion\DianPhp\Catalog\TipoFactura;
use RoyaltyFusion\DianPhp\Catalog\TipoOperacion;
use RoyaltyFusion\DianPhp\Model\AllowanceCharge;
use RoyaltyFusion\DianPhp\Model\Invoice;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Payment;
use RoyaltyFusion\DianPhp\Model\Tax;

$invoice = (new Invoice())
    ->setPrefijo('SETP')
    ->setNumero('990000001')
    ->setFecha(new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))
    ->setTipoFactura(TipoFactura::VENTA)
    ->setTipoOperacion(TipoOperacion::ESTANDAR)
    ->setDueDate(new DateTimeImmutable('+30 days'))
    ->setCompany(company())
    ->setClient(client())
    ->setSoftware(software())
    ->setResolution(resolution())
    ->setTechnicalKey(technicalKey())
    ->setTestSetId(env('DIAN_TEST_SET_ID'))
    ->addNote('Gracias por su compra.')
    ->addPayment(
        (new Payment())
            ->setMethodId('2')    // crédito
            ->setMeansId('42')    // consignación bancaria
            ->setDueDate(new DateTimeImmutable('+30 days'))
    );

// Each line carries its own tax; the document totals are calculated by the SDK.
for ($i = 1; $i <= 3; $i++) {
    $item = (new Item())
        ->setDescripcion("Servicio de soporte nivel {$i}")
        ->setCantidad(2)
        ->setPrecio(50000 * $i)
        ->setUnitCode('94')
        ->setCode("SOP-{$i}");

    if ($i === 3) {
        $item->addAllowanceCharge(
            (new AllowanceCharge())->setChargeIndicator(false)->setReasonCode('11')
                ->setReason('Descuento por volumen')->setAmount(30000)->setBaseAmount(300000)
        );
    }

    // Base = line value after its discount; amount is calculated when omitted.
    $base = 2 * 50000 * $i - ($i === 3 ? 30000 : 0);
    $item->addTax((new Tax())->setCode('01')->setPercent(19)->setBase($base));

    $invoice->addItem($item);
}

$dian   = dian();
$report = $dian->preflight($invoice);

echo $report->toText(), "\n";
echo "CUFE: {$report->getUuid()}\n";

if (shouldSend() && $report->isReady()) {
    printResult($dian->send($invoice));
}
