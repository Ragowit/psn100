<?php

declare(strict_types=1);

require_once __DIR__ . '/Game/GameTrophyGroupPlayer.php';
require_once __DIR__ . '/Game/GameTrophyRow.php';
require_once __DIR__ . '/RequestParameter.php';

final readonly class GameTrophyFilter
{
    private function __construct(private bool $unearnedOnly)
    {
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    #[\NoDiscard]
    public static function fromQueryParameters(array $queryParameters, bool $allowUnearnedFilter): self
    {
        if (!$allowUnearnedFilter) {
            return new self(false);
        }

        $unearnedOnly = RequestParameter::toBool($queryParameters['unearned'] ?? null);

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

        return (int) ($trophy['earned'] ?? 0) !== 1;
    }
}
