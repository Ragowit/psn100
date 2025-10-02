<?php

declare(strict_types=1);

class PlayerLeaderboardRankChange
{
    private const NEW_RANK_SENTINEL = 16777215;

    private ?int $delta;

    private bool $isNew;

    private function __construct(?int $delta, bool $isNew)
    {
        $this->delta = $delta;
        $this->isNew = $isNew;
    }

    public static function fromRanks(int $currentRank, int $previousRank): self
    {
        if ($previousRank === 0 || $previousRank === self::NEW_RANK_SENTINEL) {
            return new self(null, true);
        }

        return new self($previousRank - $currentRank, false);
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }

    public function shouldDisplay(): bool
    {
        if ($this->isNew) {
            return true;
        }

        return $this->delta !== null;
    }

    public function getDisplayText(): string
    {
        if ($this->isNew) {
            return '(New!)';
        }

        if ($this->delta === null) {
            return '';
        }

        if ($this->delta > 0) {
            return '(+' . $this->delta . ')';
        }

        if ($this->delta < 0) {
            return '(' . $this->delta . ')';
        }

        return '(=)';
    }

    public function getColor(): ?string
    {
        if ($this->isNew || $this->delta === null) {
            return null;
        }

        if ($this->delta > 0) {
            return '#0bd413';
        }

        if ($this->delta < 0) {
            return '#d40b0b';
        }

        return '#0070d1';
    }
}

