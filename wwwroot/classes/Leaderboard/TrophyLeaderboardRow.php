<?php

declare(strict_types=1);

require_once __DIR__ . '/AbstractLeaderboardRow.php';

class TrophyLeaderboardRow extends AbstractLeaderboardRow
{
    /**
     * @param array<string, mixed> $player
     * @param array<string, int|string> $filterParameters
     */
    public function __construct(
        array $player,
        PlayerLeaderboardFilter $filter,
        Utility $utility,
        ?string $highlightedPlayerId,
        array $filterParameters
    ) {
        parent::__construct(
            $player,
            $filter,
            $utility,
            $highlightedPlayerId,
            $filterParameters,
            'ranking',
            'rank_last_week',
            'ranking_country',
            'rank_country_last_week'
        );
    }

    public function getPlatinumCount(): int
    {
        return $this->getInt('platinum');
    }

    public function getGoldCount(): int
    {
        return $this->getInt('gold');
    }

    public function getSilverCount(): int
    {
        return $this->getInt('silver');
    }

    public function getBronzeCount(): int
    {
        return $this->getInt('bronze');
    }

    public function getTotalTrophies(): int
    {
        return $this->getPlatinumCount()
            + $this->getGoldCount()
            + $this->getSilverCount()
            + $this->getBronzeCount();
    }

    public function getPoints(): int
    {
        return $this->getInt('points');
    }
}
