<?php

declare(strict_types=1);

/**
 * Helpers for synthetic MERGE_* NP communication IDs used by cloned/merged titles.
 */
final readonly class MergeNpCommunicationId
{
    public const string PREFIX = 'MERGE';

    #[\NoDiscard]
    public static function matches(string $npCommunicationId): bool
    {
        return str_starts_with($npCommunicationId, self::PREFIX);
    }

    #[\NoDiscard]
    public static function forGameId(int $gameId): string
    {
        return self::PREFIX . '_' . str_pad((string) $gameId, 6, '0', STR_PAD_LEFT);
    }
}
