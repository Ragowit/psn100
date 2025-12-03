<?php

declare(strict_types=1);

readonly class PlayerScanStatus
{
    public function __construct(private ?PlayerScanProgress $progress)
    {
    }

    public static function withProgress(?PlayerScanProgress $progress): self
    {
        return new self($progress);
    }

    public function hasProgress(): bool
    {
        return $this->progress !== null;
    }

    public function getProgress(): ?PlayerScanProgress
    {
        return $this->progress;
    }
}
