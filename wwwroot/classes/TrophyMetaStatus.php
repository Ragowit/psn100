<?php

declare(strict_types=1);

require_once __DIR__ . '/ChangelogEntry.php';

enum TrophyMetaStatus: int
{
    case Obtainable = 0;
    case Unobtainable = 1;

    #[\NoDiscard]
    public static function fromValue(int $status): self
    {
        return self::tryFrom($status) ?? self::Obtainable;
    }

    #[\NoDiscard]
    public static function fromMixed(mixed $status): self
    {
        if ($status instanceof self) {
            return $status;
        }

        if (is_int($status) || (is_string($status) && is_numeric($status))) {
            return self::fromValue((int) $status);
        }

        return self::Obtainable;
    }

    public function isUnobtainable(): bool
    {
        return $this === self::Unobtainable;
    }

    public function isObtainable(): bool
    {
        return $this === self::Obtainable;
    }

    public function label(): string
    {
        return match ($this) {
            self::Unobtainable => 'unobtainable',
            self::Obtainable => 'obtainable',
        };
    }

    public function changeType(): ChangelogEntryType
    {
        return match ($this) {
            self::Unobtainable => ChangelogEntryType::GAME_UNOBTAINABLE,
            self::Obtainable => ChangelogEntryType::GAME_OBTAINABLE,
        };
    }
}
