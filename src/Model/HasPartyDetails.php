<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

/**
 * Trait shared by Company and Client to expose UBL <cac:Party> sub-elements:
 * address, contact, industry code (CIIU), municipality.
 */
trait HasPartyDetails
{
    private ?Address $address = null;
    private ?Contact $contact = null;
    private string $municipalityCode = '';
    private string $industryClassificationCode = ''; // CIIU
    private string $taxLevelCode = '';                // 48/49 (Régimen)

    public function setAddress(Address $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setContact(Contact $contact): self
    {
        $this->contact = $contact;
        return $this;
    }

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setMunicipalityCode(string $municipalityCode): self
    {
        $this->municipalityCode = $municipalityCode;
        return $this;
    }

    public function getMunicipalityCode(): string
    {
        return $this->municipalityCode;
    }

    public function setIndustryClassificationCode(string $industryClassificationCode): self
    {
        $this->industryClassificationCode = $industryClassificationCode;
        return $this;
    }

    public function getIndustryClassificationCode(): string
    {
        return $this->industryClassificationCode;
    }

    public function setTaxLevelCode(string $taxLevelCode): self
    {
        $this->taxLevelCode = $taxLevelCode;
        return $this;
    }

    public function getTaxLevelCode(): string
    {
        return $this->taxLevelCode;
    }
}
