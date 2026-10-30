<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

use RoyaltyFusion\DianPhp\Catalog\TipoOperacion;

/**
 * Options shared by Factura, Nota Crédito and Nota Débito: tipo de operación
 * (CustomizationID), fecha de vencimiento and periodo de facturación.
 */
trait HasDocumentOptions
{
    private ?string $customizationId = null;
    private ?\DateTimeInterface $dueDate = null;
    private ?\DateTimeInterface $invoicePeriodStart = null;
    private ?\DateTimeInterface $invoicePeriodEnd = null;

    /**
     * Tipo de operación (cbc:CustomizationID), e.g. TipoOperacion::ESTANDAR (10),
     * TipoOperacion::AIU (09), TipoOperacion::MANDATOS (11), TipoOperacion::TRANSPORTE (12).
     */
    public function setTipoOperacion(TipoOperacion|string $tipoOperacion): self
    {
        $this->customizationId = $tipoOperacion instanceof TipoOperacion ? $tipoOperacion->value : $tipoOperacion;
        return $this;
    }

    public function getCustomizationId(): string
    {
        return $this->customizationId ?? $this->defaultCustomizationId();
    }

    abstract protected function defaultCustomizationId(): string;

    public function setDueDate(\DateTimeInterface $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    /**
     * Periodo facturado (cac:InvoicePeriod). Required by DIAN for notes
     * without reference to an invoice (CustomizationID 22 / 32).
     */
    public function setInvoicePeriod(\DateTimeInterface $start, \DateTimeInterface $end): self
    {
        $this->invoicePeriodStart = $start;
        $this->invoicePeriodEnd   = $end;
        return $this;
    }

    public function getInvoicePeriodStart(): ?\DateTimeInterface
    {
        return $this->invoicePeriodStart;
    }

    public function getInvoicePeriodEnd(): ?\DateTimeInterface
    {
        return $this->invoicePeriodEnd;
    }

    private ?Delivery $delivery = null;

    /** Entrega y condiciones de entrega (Incoterm) — facturas de exportación. */
    public function setDelivery(Delivery $delivery): self
    {
        $this->delivery = $delivery;
        return $this;
    }

    public function getDelivery(): ?Delivery
    {
        return $this->delivery;
    }
}
