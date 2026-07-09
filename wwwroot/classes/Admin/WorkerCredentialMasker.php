<?php

declare(strict_types=1);

final class WorkerCredentialMasker
{
    private const string MASK_CHAR = '•';
    private const int MASK_LENGTH = 8;
    private const int VISIBLE_SUFFIX_LENGTH = 4;

    public static function mask(string $secret): string
    {
        if ($secret === '') {
            return 'Not configured';
        }

        if (strlen($secret) <= self::VISIBLE_SUFFIX_LENGTH) {
            return str_repeat(self::MASK_CHAR, self::MASK_LENGTH);
        }

        return str_repeat(self::MASK_CHAR, self::MASK_LENGTH)
            . substr($secret, -self::VISIBLE_SUFFIX_LENGTH);
    }

    public static function isConfigured(string $secret): bool
    {
        return $secret !== '';
    }
}
