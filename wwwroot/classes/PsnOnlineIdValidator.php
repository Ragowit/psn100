<?php

declare(strict_types=1);

/**
 * Validates PlayStation Network online IDs used for queue submissions and routing.
 *
 * Encapsulates validation rules that were previously embedded in PlayerQueueService.
 */
final class PsnOnlineIdValidator
{
    public const string HTML_PATTERN = '[A-Za-z][A-Za-z0-9_-]{2,15}';
    public const string INVALID_MESSAGE = 'PSN name must contain between three and 16 characters, start with a letter, and can consist of letters, numbers, hyphens (-) and underscores (_). Letters are not case-sensitive.';
    private const string PATTERN = '/^[a-zA-Z][a-zA-Z0-9_-]{2,15}$/';

    public static function isValidOnlineId(string $onlineId): bool
    {
        return $onlineId !== '' && preg_match(self::PATTERN, $onlineId) === 1;
    }
}
