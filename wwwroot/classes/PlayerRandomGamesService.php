<?php

declare(strict_types=1);

class PlayerRandomGamesService
{
    private const PLATFORM_FILTERS = [
        'pc' => "tt.platform LIKE '%PC%'",
        'ps3' => "tt.platform LIKE '%PS3%'",
        'ps4' => "tt.platform LIKE '%PS4%'",
        'ps5' => "tt.platform LIKE '%PS5%'",
        'psvita' => "tt.platform LIKE '%PSVITA%'",
        'psvr' => "CONCAT(',', REPLACE(tt.platform, ' ', ''), ',') LIKE '%,PSVR,%'",
        'psvr2' => "tt.platform LIKE '%PSVR2%'",
    ];

    private PDO $database;

    private Utility $utility;

    public function __construct(PDO $database, Utility $utility)
    {
        $this->database = $database;
        $this->utility = $utility;
    }

    /**
     * @return PlayerRandomGame[]
     */
    public function getRandomGames(int $accountId, PlayerRandomGamesFilter $filter, int $limit = 8): array
    {
        $sql = $this->buildSqlQuery($filter, $limit);

        $statement = $this->database->prepare($sql);
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $statement->execute();

        $games = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $gameData) {
            if (!is_array($gameData)) {
                continue;
            }

            $games[] = new PlayerRandomGame($gameData, $this->utility);
        }

        return $games;
    }

    private function buildSqlQuery(PlayerRandomGamesFilter $filter, int $limit): string
    {
        $sql = "SELECT tt.id, tt.np_communication_id, tt.name, tt.icon_url, tt.platform, tt.owners, tt.difficulty, tt.platinum, tt.gold, tt.silver, tt.bronze, tt.rarity_points, ttp.progress" .
            " FROM trophy_title tt" .
            " LEFT JOIN trophy_title_player ttp ON ttp.np_communication_id = tt.np_communication_id AND ttp.account_id = :account_id" .
            " WHERE tt.status = 0 AND (ttp.progress != 100 OR ttp.progress IS NULL)";

        $sql .= $this->buildPlatformFilter($filter);

        $limit = max(1, $limit);
        $sql .= ' ORDER BY RAND() LIMIT ' . $limit;

        return $sql;
    }

    private function buildPlatformFilter(PlayerRandomGamesFilter $filter): string
    {
        $conditions = [];
        foreach (self::PLATFORM_FILTERS as $filterKey => $condition) {
            if ($filter->isPlatformSelected($filterKey)) {
                $conditions[] = $condition;
            }
        }

        if ($conditions === []) {
            return '';
        }

        return ' AND (' . implode(' OR ', $conditions) . ')';
    }
}
