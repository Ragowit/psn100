<?php

declare(strict_types=1);

class PlayerTimelineService
{
    private readonly PDO $database;

    private readonly Utility $utility;

    public function __construct(PDO $database, Utility $utility)
    {
        $this->database = $database;
        $this->utility = $utility;
    }

    // /**
    //  * @return PlayerTimeline[]
    //  */
    public function getTimelines(int $accountId): array
    {
        return null;
    }

    // private function buildSelectableQuery(PlayerRandomGamesFilter $filter): string
    // {
    //     return <<<'SQL'
    //         SELECT
    //             tt.id,
    //             tt.np_communication_id,
    //             tt.name,
    //             tt.icon_url,
    //             tt.platform,
    //             ttm.owners,
    //             ttm.difficulty,
    //             tt.platinum,
    //             tt.gold,
    //             tt.silver,
    //             tt.bronze,
    //             ttm.rarity_points,
    //             ttm.in_game_rarity_points,
    //             ttp.progress
    //         SQL
    //         . $this->buildBaseQuery($filter);
    // }

    // private function buildBaseQuery(PlayerRandomGamesFilter $filter): string
    // {
    //     $sql = <<<'SQL'
    //          FROM trophy_title tt
    //         JOIN trophy_title_meta ttm ON ttm.np_communication_id = tt.np_communication_id
    //         LEFT JOIN trophy_title_player ttp ON
    //             ttp.np_communication_id = tt.np_communication_id
    //             AND ttp.account_id = :account_id
    //         WHERE
    //             ttm.status = 0
    //             AND (ttp.progress IS NULL OR ttp.progress < 100)
    //     SQL;

    //     $sql .= $this->buildPlatformFilter($filter);

    //     return $sql;
    // }
}
