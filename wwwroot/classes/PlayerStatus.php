<?php

declare(strict_types=1);

enum PlayerStatus: int
{
    case NORMAL = 0;
    case FLAGGED = 1;
    case PRIVATE = 3;

    public static function fromValue(int|string|null $status): self
    {
        $normalized = is_numeric($status) ? (int) $status : 0;

        return match ($normalized) {
            self::FLAGGED->value => self::FLAGGED,
            self::PRIVATE->value => self::PRIVATE,
            default => self::NORMAL,
        };
    }

    /**
     * @param array<string, mixed> $playerData
     */
    public static function fromPlayerData(array $playerData): self
    {
        return self::fromValue($playerData['status'] ?? null);
    }

    public function isFlagged(): bool
    {
        return $this === self::FLAGGED;
    }

    public function isPrivate(): bool
    {
        return $this === self::PRIVATE;
    }

    public function isVisible(): bool
    {
        return !$this->isFlagged() && !$this->isPrivate();
    }
}
