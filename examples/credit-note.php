<?php

/**
 * Nota Crédito por devolución parcial, referenciando una factura.
 *
 *   php examples/credit-note.php [--send]
 */

declare(strict_types=1);

require __DIR__ . '/_config.php';

use RoyaltyFusion\DianPhp\Catalog\TipoNotaCredito;
use RoyaltyFusion\DianPhp\Model\BillingReference;
use RoyaltyFusion\DianPhp\Model\CreditNote;
use RoyaltyFusion\DianPhp\Model\DiscrepancyResponse;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Payment;
use RoyaltyFusion\DianPhp\Model\Tax;

$creditNote = (new CreditNote())
    ->setPrefijo('NC')
    ->setNumero('1')
    ->setFecha(new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))
    ->setCompany(company())
    ->setClient(client())
    ->setSoftware(software())
    ->setTestSetId(env('DIAN_TEST_SET_ID'))
    // Factura que se corrige: número, CUFE y fecha de emisión.
    ->setBillingReference(
        (new BillingReference())
            ->setNumber('SETP990000001')
            ->setUuid(env('DIAN_REFERENCED_CUFE', str_repeat('a', 96)))
            ->setDate(new DateTimeImmutable('-1 day'))
    )
    ->setDiscrepancyResponse(
        (new DiscrepancyResponse())
            ->setReferenceId('SETP990000001')
            ->setResponseCode(TipoNotaCredito::DEVOLUCION_PARCIAL->code())
            ->setDescription('Devolución de 2 unidades por defecto')
    )
    ->addPayment((new Payment())->setMethodId('1')->setMeansId('10'))
    ->addItem(
        (new Item())->setDescripcion('Producto X')->setCantidad(2)->setPrecio(10000)
            ->addTax((new Tax())->setCode('01')->setPercent(19)->setBase(20000))
    );

$dian   = dian();
$report = $dian->preflight($creditNote);

echo $report->toText(), "\n";
echo "CUDE: {$report->getUuid()}\n";

if (shouldSend() && $report->isReady()) {
    printResult($dian->send($creditNote));
}
