<?php

/**
 * Nota Débito por intereses de mora sobre una factura.
 *
 *   php examples/debit-note.php [--send]
 */

declare(strict_types=1);

require __DIR__ . '/_config.php';

use RoyaltyFusion\DianPhp\Catalog\TipoNotaDebito;
use RoyaltyFusion\DianPhp\Model\BillingReference;
use RoyaltyFusion\DianPhp\Model\DebitNote;
use RoyaltyFusion\DianPhp\Model\DiscrepancyResponse;
use RoyaltyFusion\DianPhp\Model\Item;
use RoyaltyFusion\DianPhp\Model\Payment;
use RoyaltyFusion\DianPhp\Model\Tax;

$debitNote = (new DebitNote())
    ->setPrefijo('ND')
    ->setNumero('1')
    ->setFecha(new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))
    ->setCompany(company())
    ->setClient(client())
    ->setSoftware(software())
    ->setTestSetId(env('DIAN_TEST_SET_ID'))
    ->setBillingReference(
        (new BillingReference())
            ->setNumber('SETP990000001')
            ->setUuid(env('DIAN_REFERENCED_CUFE', str_repeat('a', 96)))
            ->setDate(new DateTimeImmutable('-1 day'))
    )
    ->setDiscrepancyResponse(
        (new DiscrepancyResponse())
            ->setReferenceId('SETP990000001')
            ->setResponseCode(TipoNotaDebito::INTERESES->code())
            ->setDescription('Intereses de mora')
    )
    ->addPayment((new Payment())->setMethodId('1')->setMeansId('10'))
    ->addItem(
        (new Item())->setDescripcion('Intereses de mora mayo')->setCantidad(1)->setPrecio(15000)
            ->addTax((new Tax())->setCode('01')->setPercent(19)->setBase(15000))
    );

$dian   = dian();
$report = $dian->preflight($debitNote);

echo $report->toText(), "\n";
echo "CUDE: {$report->getUuid()}\n";

if (shouldSend() && $report->isReady()) {
    printResult($dian->send($debitNote));
}
