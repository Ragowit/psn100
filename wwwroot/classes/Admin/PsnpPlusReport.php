<?php

declare(strict_types=1);

require_once __DIR__ . '/PsnpPlusMissingGame.php';
require_once __DIR__ . '/PsnpPlusGameDifference.php';
require_once __DIR__ . '/PsnpPlusFixedGame.php';

final readonly class PsnpPlusReport
{
    /**
     * @param PsnpPlusMissingGame[] $missingGames
     * @param PsnpPlusGameDifference[] $gameDifferences
     * @param PsnpPlusFixedGame[] $fixedGames
     */
    public function __construct(
        private array $missingGames,
        private array $gameDifferences,
        private array $fixedGames,
    ) {
    }

    /**
     * @return PsnpPlusMissingGame[]
     */
    public function getMissingGames(): array
    {
        return $this->missingGames;
    }

    public function hasMissingGames(): bool
    {
        return $this->missingGames !== [];
    }

    /**
     * @return PsnpPlusGameDifference[]
     */
    public function getGameDifferences(): array
    {
        return $this->gameDifferences;
    }

    public function hasGameDifferences(): bool
    {
        return $this->gameDifferences !== [];
    }

    /**
     * @return PsnpPlusFixedGame[]
     */
    public function getFixedGames(): array
    {
        return $this->fixedGames;
    }

    public function hasFixedGames(): bool
    {
        return $this->fixedGames !== [];
    }
}
