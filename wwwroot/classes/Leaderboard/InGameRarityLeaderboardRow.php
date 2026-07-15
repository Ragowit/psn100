<?php

declare(strict_types=1);

require_once __DIR__ . '/AbstractLeaderboardRow.php';

class InGameRarityLeaderboardRow extends AbstractLeaderboardRow
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
            'in_game_rarity_rank_last_week',
            'ranking_country',
            'in_game_rarity_rank_country_last_week'
        );
    }

    public function getLegendaryCount(): int
    {
        return $this->getInt('in_game_legendary');
    }

    public function getEpicCount(): int
    {
        return $this->getInt('in_game_epic');
    }

    public function getRareCount(): int
    {
        return $this->getInt('in_game_rare');
    }

    public function getUncommonCount(): int
    {
        return $this->getInt('in_game_uncommon');
    }

    public function getCommonCount(): int
    {
        return $this->getInt('in_game_common');
    }

    public function getRarityPoints(): int
    {
        return $this->getInt('in_game_rarity_points');
    }
}
