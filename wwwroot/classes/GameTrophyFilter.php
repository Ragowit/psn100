<?php

declare(strict_types=1);

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

    /**
     * @param array<string, mixed>|null $trophyGroupPlayer
     */
    public function shouldDisplayGroup(?array $trophyGroupPlayer): bool
    {
        if (!$this->unearnedOnly || $trophyGroupPlayer === null) {
            return true;
        }

        $progress = isset($trophyGroupPlayer['progress']) ? (int) $trophyGroupPlayer['progress'] : 0;

        return $progress < 100;
    }

    /**
     * @param array<string, mixed> $trophy
     */
    public function shouldDisplayTrophy(array $trophy): bool
    {
        if (!$this->unearnedOnly) {
            return true;
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
