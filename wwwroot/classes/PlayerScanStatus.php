<?php

declare(strict_types=1);

final class PlayerScanStatus
{
    private ?PlayerScanProgress $progress;

    public function __construct(?PlayerScanProgress $progress)
    {
        $this->progress = $progress;
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
