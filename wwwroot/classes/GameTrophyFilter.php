<?php

declare(strict_types=1);

require_once __DIR__ . '/Game/GameTrophyGroupPlayer.php';
require_once __DIR__ . '/Game/GameTrophyRow.php';

class GameTrophyFilter
{
    private bool $unearnedOnly;

    private function __construct(bool $unearnedOnly)
    {
        $this->unearnedOnly = $unearnedOnly;
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    public static function fromQueryParameters(array $queryParameters, bool $allowUnearnedFilter): self
    {
        if (!$allowUnearnedFilter) {
            return new self(false);
        }

        $unearnedOnly = self::resolveBoolean($queryParameters['unearned'] ?? null);

        return new self($unearnedOnly);
    }

    public function shouldShowUnearnedOnly(): bool
    {
        return $this->unearnedOnly;
    }

    public function shouldDisplayGroup(?GameTrophyGroupPlayer $trophyGroupPlayer): bool
    {
        if (!$this->unearnedOnly || $trophyGroupPlayer === null) {
            return true;
        }

        return !$trophyGroupPlayer->isComplete();
    }

    /**
     * @param array<string, mixed>|GameTrophyRow $trophy
     */
    public function shouldDisplayTrophy(array|GameTrophyRow $trophy): bool
    {
        if (!$this->unearnedOnly) {
            return true;
        }

        if ($trophy instanceof GameTrophyRow) {
            return !$trophy->isEarned();
        }

        $earned = isset($trophy['earned']) ? (int) $trophy['earned'] : 0;

        return $earned !== 1;
    }

    private static function resolveBoolean(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ($normalized === '' || in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
                return false;
            }

            return true;
        }

        return true;
    }
}
