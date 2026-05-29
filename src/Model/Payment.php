<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

class Payment
{
    /** @var string 1: Contado, 2: Credito */
    private string $methodId;
    
    /** @var string e.g. 10: Efectivo, 42: Consignacion, 48: Tarjeta de credito */
    private string $meansId;
    
    private \DateTimeInterface $dueDate;

    public function setMethodId(string $methodId): self
    {
        $this->methodId = $methodId;
        return $this;
    }

    public function getMethodId(): string
    {
        return $this->methodId ?? '';
    }

    public function setMeansId(string $meansId): self
    {
        $this->meansId = $meansId;
        return $this;
    }

    public function getMeansId(): string
    {
        return $this->meansId ?? '';
    }

    public function setDueDate(\DateTimeInterface $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate ?? null;
    }
}
