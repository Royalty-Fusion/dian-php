<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Model;

/**
 * UBL <cbc:Note> — free text note attached at document or line level.
 */
class Note
{
    private string $text;

    public function __construct(string $text = '')
    {
        $this->text = $text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function __toString(): string
    {
        return $this->text;
    }
}
