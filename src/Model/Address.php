<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

/**
 * UBL Address used for both AccountingSupplier/Customer parties.
 *
 * Maps to <cac:PhysicalLocation><cac:Address> inside <cac:Party>.
 */
class Address
{
    private string $line = '';
    private string $cityCode = '';      // DANE municipality code (5 digits)
    private string $cityName = '';
    private string $departmentCode = ''; // DANE department code (2 digits)
    private string $departmentName = '';
    private string $countryCode = 'CO';  // ISO 3166-1 alpha-2
    private string $countryName = 'Colombia';
    private string $postalZone = '';

    public function setLine(string $line): self
    {
        $this->line = $line;
        return $this;
    }

    public function getLine(): string
    {
        return $this->line;
    }

    public function setCityCode(string $cityCode): self
    {
        $this->cityCode = $cityCode;
        return $this;
    }

    public function getCityCode(): string
    {
        return $this->cityCode;
    }

    public function setCityName(string $cityName): self
    {
        $this->cityName = $cityName;
        return $this;
    }

    public function getCityName(): string
    {
        return $this->cityName;
    }

    public function setDepartmentCode(string $departmentCode): self
    {
        $this->departmentCode = $departmentCode;
        return $this;
    }

    public function getDepartmentCode(): string
    {
        return $this->departmentCode;
    }

    public function setDepartmentName(string $departmentName): self
    {
        $this->departmentName = $departmentName;
        return $this;
    }

    public function getDepartmentName(): string
    {
        return $this->departmentName;
    }

    public function setCountryCode(string $countryCode): self
    {
        $this->countryCode = $countryCode;
        return $this;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setCountryName(string $countryName): self
    {
        $this->countryName = $countryName;
        return $this;
    }

    public function getCountryName(): string
    {
        return $this->countryName;
    }

    public function setPostalZone(string $postalZone): self
    {
        $this->postalZone = $postalZone;
        return $this;
    }

    public function getPostalZone(): string
    {
        return $this->postalZone;
    }
}
