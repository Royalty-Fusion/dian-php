<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Radian;

use RoyaltyFusion\DianPhp\Xml\DocumentCalculator;

/**
 * CUDE of a RADIAN event (ApplicationResponse), Anexo Técnico RADIAN:
 *
 *   CUDE = SHA-384(Num_DE + Fec_Emi + Hor_Emi + NitFE + DocAdq + ResponseCode
 *                  + ID + DocumentTypeCode + Software-PIN)
 *
 *   Num_DE           número del evento (cbc:ID)
 *   NitFE            NIT de quien genera el evento (SenderParty)
 *   DocAdq           documento del receptor del evento (ReceiverParty)
 *   ResponseCode     código del evento (030, 031, 032, 033, 034...)
 *   ID               número del documento referenciado (factura)
 *   DocumentTypeCode tipo del documento referenciado (01)
 */
final class EventCudeGenerator
{
    public function generate(EventDocument $event, string $pinSoftware): string
    {
        return hash('sha384', $this->buildString($event, $pinSoftware));
    }

    public function buildString(EventDocument $event, string $pinSoftware): string
    {
        [$date, $time] = DocumentCalculator::issueDateTime($event->getIssueDate());

        return $event->getId()
            . $date
            . $time
            . ($event->getReceiver()?->getNit() ?? '')
            . ($event->getSupplier()?->getNumeroDocumento() ?? '')
            . ($event->getEvent()?->getCode() ?? '')
            . $event->getInvoiceId()
            . $event->getDocumentTypeCode()
            . $pinSoftware;
    }
}
