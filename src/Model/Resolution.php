<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

class Resolution
{
    private string $number;
    private string $prefix;
    private string $from;
    private string $to;
    private \DateTimeInterface $dateFrom;
    private \DateTimeInterface $dateTo;

    public function setNumber(string $number): self
    {
        $this->number = $number;
        return $this;
    }

    public function getNumber(): string
    {
        return $this->number ?? '';
    }

    public function setPrefix(string $prefix): self
    {
        $this->prefix = $prefix;
        return $this;
    }

    public function getPrefix(): string
    {
        return $this->prefix ?? '';
    }

    public function setFrom(string $from): self
    {
        $this->from = $from;
        return $this;
    }

    public function getFrom(): string
    {
        return $this->from ?? '';
    }

    public function setTo(string $to): self
    {
        $this->to = $to;
        return $this;
    }

    public function getTo(): string
    {
        return $this->to ?? '';
    }

    public function setDateFrom(\DateTimeInterface $dateFrom): self
    {
        $this->dateFrom = $dateFrom;
        return $this;
    }

    public function getDateFrom(): ?\DateTimeInterface
    {
        return $this->dateFrom ?? null;
    }

    public function setDateTo(\DateTimeInterface $dateTo): self
    {
        $this->dateTo = $dateTo;
        return $this;
    }

    public function getDateTo(): ?\DateTimeInterface
    {
        return $this->dateTo ?? null;
    }
}
