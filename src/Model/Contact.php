<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

/**
 * UBL Contact information for a party (telephone, email, contact person name).
 *
 * Maps to <cac:Contact> inside <cac:Party>.
 */
class Contact
{
    private string $name = '';
    private string $telephone = '';
    private string $electronicMail = '';

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setTelephone(string $telephone): self
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getTelephone(): string
    {
        return $this->telephone;
    }

    public function setElectronicMail(string $electronicMail): self
    {
        $this->electronicMail = $electronicMail;
        return $this;
    }

    public function getElectronicMail(): string
    {
        return $this->electronicMail;
    }
}
