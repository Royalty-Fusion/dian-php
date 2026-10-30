<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Reception;

use RoyaltyFusion\DianPhp\Model\Client;
use RoyaltyFusion\DianPhp\Model\Company;
use RoyaltyFusion\DianPhp\Model\Software;
use RoyaltyFusion\DianPhp\Radian\ApplicationResponseEvent;
use RoyaltyFusion\DianPhp\Radian\EventDocument;

/**
 * A document received from a supplier (factura de compra, nota, documento
 * equivalente), read from its AttachedDocument or from the bare UBL XML.
 *
 * Use it to register the purchase in the ERP and to answer it with the RADIAN
 * events (toEvent() + Dian::sendEvent()):
 *
 *   030 acuse de recibo → 032 recibo del bien/servicio → 033 aceptación expresa
 *   (or 031 reclamo, within 3 business days of 032)
 */
final class ReceivedDocument
{
    /**
     * @param 'Invoice'|'CreditNote'|'DebitNote' $type
     * @param array{nit:string,dv:string,tipoDocumento:string,name:string,email:string,responsabilidades:string,taxSchemeId:string,taxSchemeName:string} $supplier
     * @param array{nit:string,dv:string,tipoDocumento:string,name:string,email:string} $customer
     * @param array{lineExtension:float,taxExclusive:float,taxInclusive:float,allowances:float,charges:float,prepaid:float,payable:float} $totals
     * @param array<string,float> $taxes        tributo code => amount
     * @param array<string,float> $withholdings retención code => amount
     * @param list<array{description:string,quantity:float,unitCode:string,price:float,lineExtension:float,code:string}> $lines
     * @param list<array{number:string,uuid:string,date:string}> $billingReferences notes: corrected invoices
     * @param list<string> $signatureProblems empty = signatures verified (or not checked)
     */
    public function __construct(
        public readonly string $type,
        public readonly string $typeCode,
        public readonly string $number,
        public readonly string $uuid,
        public readonly string $uuidSchemeName,
        public readonly string $customizationId,
        public readonly string $environment,
        public readonly ?\DateTimeImmutable $issueDate,
        public readonly ?\DateTimeImmutable $dueDate,
        public readonly string $currency,
        public readonly array $supplier,
        public readonly array $customer,
        public readonly array $totals,
        public readonly array $taxes,
        public readonly array $withholdings,
        public readonly array $lines,
        public readonly string $paymentMethod,
        public readonly string $paymentMeans,
        public readonly array $billingReferences,
        public readonly string $signedXml,
        public readonly string $applicationResponseXml,
        public readonly bool $dianApproved,
        public readonly array $signatureProblems = [],
    ) {
    }

    /** DIAN validated it (AttachedDocument with ApplicationResponse 02) and the signatures are intact. */
    public function isTrustworthy(): bool
    {
        return $this->dianApproved && $this->signatureProblems === [];
    }

    /** True when the document is addressed to that NIT (use it to reject misdirected invoices). */
    public function isAddressedTo(string $nit): bool
    {
        return ltrim($this->customer['nit'], '0') === ltrim(preg_replace('/\D/', '', $nit) ?? '', '0');
    }

    public function isInvoice(): bool
    {
        return $this->type === 'Invoice';
    }

    /** The supplier as the Client model RADIAN events expect. */
    public function supplierAsClient(): Client
    {
        $client = (new Client())
            ->setTipoDocumento($this->supplier['tipoDocumento'] ?: '31')
            ->setNumeroDocumento($this->supplier['nit'])
            ->setRazonSocial($this->supplier['name'])
            ->setResponsabilidades($this->supplier['responsabilidades'] ?: 'R-99-PN');
        if ($this->supplier['dv'] !== '') {
            $client->setNitDv((int) $this->supplier['dv']);
        }
        if ($this->supplier['email'] !== '') {
            $client->setEmail($this->supplier['email']);
        }

        return $client;
    }

    /**
     * RADIAN event answering this document. $id is your own consecutive for
     * the event (unique per event); $receiver is your company (the buyer).
     */
    public function toEvent(ApplicationResponseEvent $event, string $id, Company $receiver, Software $software): EventDocument
    {
        $doc = (new EventDocument())
            ->setId($id)
            ->setIssueDate(new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota')))
            ->setEvent($event->setCufe($this->uuid))
            ->setInvoiceId($this->number)
            ->setInvoiceCufe($this->uuid)
            ->setInvoiceUuidSchemeName($this->uuidSchemeName ?: 'CUFE-SHA384')
            ->setDocumentTypeCode($this->typeCode ?: '01')
            ->setReceiver($receiver)
            ->setSupplier($this->supplierAsClient())
            ->setSoftware($software);
        if ($this->issueDate !== null) {
            $doc->setInvoiceDate($this->issueDate);
        }

        return $doc;
    }
}
