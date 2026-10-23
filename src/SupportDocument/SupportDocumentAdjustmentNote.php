<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\SupportDocument;

use RoyaltyFusion\DianPhp\Model\BillingReference;
use RoyaltyFusion\DianPhp\Model\DiscrepancyResponse;

/**
 * Nota de ajuste al Documento Soporte (tipo de documento 95).
 *
 * Same parties and lines as {@see SupportDocument}, plus the reference to the
 * adjusted DS (number, CUDS and date) and the reason (DiscrepancyResponse,
 * códigos 1-5 de la tabla de conceptos de corrección de notas crédito).
 */
class SupportDocumentAdjustmentNote extends SupportDocument
{
    private ?BillingReference $billingReference = null;
    private ?DiscrepancyResponse $discrepancyResponse = null;

    /** Documento Soporte being adjusted: number, CUDS (as uuid) and issue date. */
    public function setBillingReference(BillingReference $billingReference): self
    {
        $this->billingReference = $billingReference;
        return $this;
    }

    public function getBillingReference(): ?BillingReference
    {
        return $this->billingReference;
    }

    public function setDiscrepancyResponse(DiscrepancyResponse $discrepancyResponse): self
    {
        $this->discrepancyResponse = $discrepancyResponse;
        return $this;
    }

    public function getDiscrepancyResponse(): ?DiscrepancyResponse
    {
        return $this->discrepancyResponse;
    }
}
