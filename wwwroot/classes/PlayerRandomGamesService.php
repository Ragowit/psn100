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
        'psvr' => "tt.platform LIKE '%PSVR' OR tt.platform LIKE '%PSVR,%'",
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
     * @param array<string, mixed> $filters
     * @return PlayerRandomGame[]
     */
    public function getRandomGames(int $accountId, array $filters, int $limit = 8): array
    {
        $sql = $this->buildSqlQuery($filters, $limit);

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

    /**
     * @param array<string, mixed> $filters
     */
    private function buildSqlQuery(array $filters, int $limit): string
    {
        $sql = "SELECT tt.id, tt.np_communication_id, tt.name, tt.icon_url, tt.platform, tt.owners, tt.difficulty, tt.platinum, tt.gold, tt.silver, tt.bronze, tt.rarity_points, ttp.progress" .
            " FROM trophy_title tt" .
            " LEFT JOIN trophy_title_player ttp ON ttp.np_communication_id = tt.np_communication_id AND ttp.account_id = :account_id" .
            " WHERE tt.status = 0 AND (ttp.progress != 100 OR ttp.progress IS NULL)";

        $sql .= $this->buildPlatformFilter($filters);

        $limit = max(1, $limit);
        $sql .= ' ORDER BY RAND() LIMIT ' . $limit;

        return $sql;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function buildPlatformFilter(array $filters): string
    {
        $conditions = [];
        foreach (self::PLATFORM_FILTERS as $filterKey => $condition) {
            if (!empty($filters[$filterKey])) {
                $conditions[] = $condition;
            }
        }

        if ($conditions === []) {
            return '';
        }

        return ' AND (' . implode(' OR ', $conditions) . ')';
    }
}
