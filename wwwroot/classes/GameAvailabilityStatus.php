<?php

declare(strict_types=1);

require_once __DIR__ . '/ChangelogEntry.php';

enum GameAvailabilityStatus: int
{
    case NORMAL = 0;
    case DELISTED = 1;
    case MERGED = 2;
    case OBSOLETE = 3;
    case DELISTED_AND_OBSOLETE = 4;

    #[\NoDiscard]
    public static function fromInt(int $status): self
    {
        return self::tryFrom($status) ?? self::NORMAL;
    }

    public function isUnavailable(): bool
    {
        return match ($this) {
            self::DELISTED,
            self::OBSOLETE,
            self::DELISTED_AND_OBSOLETE => true,
            self::NORMAL,
            self::MERGED => false,
        };
    }

    public function changeType(): ChangelogEntryType
    {
        return match ($this) {
            self::DELISTED => ChangelogEntryType::GAME_DELISTED,
            self::MERGED => ChangelogEntryType::GAME_MERGE,
            self::OBSOLETE => ChangelogEntryType::GAME_OBSOLETE,
            self::DELISTED_AND_OBSOLETE => ChangelogEntryType::GAME_DELISTED_AND_OBSOLETE,
            self::NORMAL => ChangelogEntryType::GAME_NORMAL,
        };
    }

    public function statusText(): string
    {
        return match ($this) {
            self::DELISTED => 'delisted',
            self::MERGED => 'merged',
            self::OBSOLETE => 'obsolete',
            self::DELISTED_AND_OBSOLETE => 'delisted & obsolete',
            self::NORMAL => 'normal',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::DELISTED => 'Delisted',
            self::MERGED => 'Merged',
            self::OBSOLETE => 'Obsolete',
            self::DELISTED_AND_OBSOLETE => 'Delisted & Obsolete',
            self::NORMAL => 'Normal',
        };
    }

    public function warningMessage(): ?string
    {
        return match ($this) {
            self::DELISTED => 'This game is delisted, no trophies will be accounted for on any leaderboard.',
            self::OBSOLETE => 'This game is obsolete, no trophies will be accounted for on any leaderboard.',
            self::DELISTED_AND_OBSOLETE => 'This game is delisted &amp; obsolete, no trophies will be accounted for on any leaderboard.',
            self::NORMAL,
            self::MERGED => null,
        };
    }

    public function badgeLabel(): ?string
    {
        return match ($this) {
            self::DELISTED => 'Delisted',
            self::OBSOLETE => 'Obsolete',
            self::DELISTED_AND_OBSOLETE => 'Delisted & Obsolete',
            self::NORMAL,
            self::MERGED => null,
        };
    }
}
