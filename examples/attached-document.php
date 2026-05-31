<?php

/**
 * Ejemplo: AttachedDocument — el "contenedor" que envuelve la factura firmada
 * + el ApplicationResponse de DIAN. Es el formato que B2B se intercambia por
 * email entre emisor y adquiriente.
 *
 * Demuestra los dos lados:
 *   1) PARSEAR un AttachedDocument recibido (caso: tu ERP recibe una factura
 *      de un proveedor)
 *   2) CONSTRUIR un AttachedDocument para enviarle al adquiriente
 *
 *   $ php examples/attached-document.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use RoyaltyFusion\DianPhp\AttachedDocument\AttachedDocument;
use RoyaltyFusion\DianPhp\AttachedDocument\AttachedDocumentBuilder;
use RoyaltyFusion\DianPhp\AttachedDocument\AttachedDocumentParser;
use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;

echo "===== 1) PARSEAR un AttachedDocument recibido =====\n";

$xmlRecibido = file_get_contents(__DIR__ . '/../resources/fixtures/siigo-blindacces-FV5.xml');
$parser      = new AttachedDocumentParser();
$adRecibido  = $parser->parse($xmlRecibido);

echo "CUFE:                {$adRecibido->getId()}\n";
echo "Factura referenciada: {$adRecibido->getParentDocumentId()}\n";
echo "DIAN aprobó:         " . ($parser->isAccepted($adRecibido) ? 'SÍ' : 'NO') . "\n";
echo "Factura embebida:    " . strlen($adRecibido->getSignedInvoiceXml()) . " bytes\n";
echo "AR embebido:         " . strlen($adRecibido->getApplicationResponseXml()) . " bytes\n";

// Archivar para auditoría:
//   file_put_contents("storage/{$adRecibido->getParentDocumentId()}.xml", $adRecibido->getSignedInvoiceXml());

echo "\n===== 2) CONSTRUIR un AttachedDocument para enviarle al cliente =====\n";

$emisor    = (new Company())->setNit('901234567')->setRazonSocial('Royalty Fusion S.A.S')->setTipoDocumento('31')->setResponsabilidades('O-13');
$receptor  = (new Client())->setNumeroDocumento('1010101010')->setRazonSocial('Juan Pérez')->setTipoDocumento('13')->setResponsabilidades('R-99-PN');

$facturaXml         = '<?xml version="1.0"?><Invoice><!-- aquí va el XML firmado --></Invoice>';
$applicationResponse = '<?xml version="1.0"?><ApplicationResponse><!-- AR de DIAN --></ApplicationResponse>';

$ad = (new AttachedDocument())
    ->setId('cufe-de-la-factura')
    ->setParentDocumentId('FV-67890')
    ->setIssueDate(new DateTimeImmutable('now'))
    ->setSender($emisor)
    ->setReceiver($receptor)
    ->setSignedInvoiceXml($facturaXml)
    ->setApplicationResponseXml($applicationResponse);

$xml = (new AttachedDocumentBuilder())->build($ad);
echo "XML del contenedor: " . strlen($xml) . " bytes\n";
echo "Listo para email B2B.\n";
