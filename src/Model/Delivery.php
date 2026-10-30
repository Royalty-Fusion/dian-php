<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

/**
 * Entrega de la mercancía (cac:Delivery) y condiciones de entrega
 * (cac:DeliveryTerms) — required for facturas de exportación.
 */
final class Delivery
{
    /** Incoterms 2020 accepted by DIAN for exports. */
    public const INCOTERMS = [
        'EXW' => 'En fábrica', 'FCA' => 'Franco transportista', 'CPT' => 'Transporte pagado hasta',
        'CIP' => 'Transporte y seguro pagados hasta', 'DAP' => 'Entregada en lugar', 'DPU' => 'Entregada en lugar descargada',
        'DDP' => 'Entregada derechos pagados', 'FAS' => 'Franco al costado del buque', 'FOB' => 'Franco a bordo',
        'CFR' => 'Costo y flete', 'CIF' => 'Costo, seguro y flete',
    ];

    private ?Address $address = null;
    private ?\DateTimeInterface $date = null;
    private string $incoterm = '';
    private string $lossRisk = '';
    private string $specialTerms = '';

    public function setAddress(Address $address): self { $this->address = $address; return $this; }
    public function getAddress(): ?Address { return $this->address; }
    public function setDate(\DateTimeInterface $date): self { $this->date = $date; return $this; }
    public function getDate(): ?\DateTimeInterface { return $this->date; }

    /** Incoterm (LossRiskResponsibilityCode); the description defaults to the official one. */
    public function setIncoterm(string $incoterm, string $description = ''): self
    {
        $this->incoterm = strtoupper($incoterm);
        $this->lossRisk = $description !== '' ? $description : (self::INCOTERMS[$this->incoterm] ?? '');
        return $this;
    }

    public function getIncoterm(): string { return $this->incoterm; }
    public function getLossRisk(): string { return $this->lossRisk; }
    public function setSpecialTerms(string $specialTerms): self { $this->specialTerms = $specialTerms; return $this; }
    public function getSpecialTerms(): string { return $this->specialTerms; }
}
