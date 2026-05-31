<?php

/**
 * Ejemplo: Evento RADIAN — Acuse de Recibo de una factura.
 *
 * Tu empresa (adquiriente) emite el evento de Acuse de Recibo sobre el CUFE
 * de una factura previamente emitida por un proveedor.
 *
 *   $ php examples/radian-event.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Radian\ApplicationResponseBuilder;
use RoyaltyFusion\DianPhp\Radian\ApplicationResponseEvent;
use RoyaltyFusion\DianPhp\Radian\EventDocument;

$event = (new ApplicationResponseEvent())
    ->setCode(ApplicationResponseEvent::ACUSE_RECIBO)
    ->setDescription('Acuse de recibo de la factura electrónica');

$evento = (new EventDocument())
    ->setId('EVT-001')
    ->setCude(str_repeat('e', 96))
    ->setIssueDate(new DateTimeImmutable('now'))
    ->setEvent($event)
    ->setInvoiceCufe('a7516114373f44f2160cca5d079afbb8bc865db2c249e4699b19c2de2d5ef9890d9955584f6e86cb49807bbbfd78a61e')
    ->setInvoiceId('FV-12345')
    ->setInvoiceDate(new DateTimeImmutable('-1 day'))
    // Receptor = quien firma el evento (tu empresa)
    ->setReceiver(
        (new Company())
            ->setNit('901234567')
            ->setRazonSocial('Royalty Fusion S.A.S')
            ->setTipoDocumento('31')
            ->setResponsabilidades('O-13')
    )
    // Proveedor original de la factura
    ->setSupplier(
        (new Client())
            ->setNumeroDocumento('900123456')
            ->setRazonSocial('Proveedor de Servicios S.A.S')
            ->setTipoDocumento('31')
            ->setResponsabilidades('R-99-PN')
    );

$xml = (new ApplicationResponseBuilder())->build($evento);

echo "Evento RADIAN: " . ApplicationResponseEvent::ACUSE_RECIBO . " (Acuse de Recibo)\n";
echo "CUFE referenciado: {$evento->getInvoiceCufe()}\n";
echo "XML generado: " . strlen($xml) . " bytes\n";

// Otros eventos disponibles:
//   ApplicationResponseEvent::RECLAMO              // 031
//   ApplicationResponseEvent::RECIBO_BIEN_SERVICIO // 032
//   ApplicationResponseEvent::ACEPTACION_EXPRESA   // 033
//   ApplicationResponseEvent::ACEPTACION_TACITA    // 034
//   ApplicationResponseEvent::ENDOSO_PROPIEDAD     // 036
//   ApplicationResponseEvent::NOTIFICACION_PAGO    // 040
//   ApplicationResponseEvent::PAGO                  // 042
