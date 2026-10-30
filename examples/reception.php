<?php

/**
 * Recepción de una factura de proveedor (AttachedDocument recibido por correo):
 * lectura, verificación y respuesta con eventos RADIAN.
 *
 *   php examples/reception.php [archivo.xml]          # lee y verifica
 *   php examples/reception.php archivo.xml --send     # además envía el acuse de recibo (030)
 */

declare(strict_types=1);

require __DIR__ . '/_config.php';

use RoyaltyFusion\DianPhp\Radian\ApplicationResponseEvent;

$file = $argv[1] ?? __DIR__ . '/../resources/fixtures/siigo-blindacces-FV7.xml';
if (!is_file($file) || str_starts_with($file, '--')) {
    $file = __DIR__ . '/../resources/fixtures/siigo-blindacces-FV7.xml';
}

$dian = dian();
$doc  = $dian->receive((string) file_get_contents($file));

printf("%s %s (tipo %s) del %s\n", $doc->type, $doc->number, $doc->typeCode, $doc->issueDate?->format('Y-m-d H:i'));
printf("Proveedor: %s NIT %s-%s\n", $doc->supplier['name'], $doc->supplier['nit'], $doc->supplier['dv']);
printf("Adquiriente: NIT %s\n", $doc->customer['nit']);
printf("Total a pagar: %s %s\n", number_format($doc->totals['payable'], 2, ',', '.'), $doc->currency);
foreach ($doc->taxes as $code => $amount) {
    printf("  Tributo %s: %s\n", $code, number_format($amount, 2, ',', '.'));
}
foreach ($doc->withholdings as $code => $amount) {
    printf("  Retención %s: %s\n", $code, number_format($amount, 2, ',', '.'));
}
foreach ($doc->lines as $line) {
    printf("  - %s × %s @ %s\n", $line['description'], $line['quantity'], number_format($line['price'], 2, ',', '.'));
}
echo 'Validado por la DIAN: ', $doc->dianApproved ? 'sí' : 'no', "\n";
echo 'Firmas: ', $doc->signatureProblems === [] ? 'íntegras' : implode('; ', $doc->signatureProblems), "\n";

if (shouldSend()) {
    // company() debe ser el adquiriente del documento (DIAN_NIT).
    printResult($dian->acknowledge($doc, ApplicationResponseEvent::acuseRecibo(), 'EV-' . date('YmdHis'), company(), software()));
}
