<?php

declare(strict_types=1);

class PlayerRandomGamesService
{
    private const MIN_POOL_SIZE = 32;

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
        $limit = max(1, $limit);
        $totalEligible = $this->countEligibleGames($accountId, $filter);

        if ($totalEligible === 0) {
            return [];
        }

        $poolSize = (int) min($totalEligible, max($limit * 4, self::MIN_POOL_SIZE));
        $offset = $totalEligible > $poolSize ? random_int(0, $totalEligible - $poolSize) : 0;

        $sql = $this->buildSelectableQuery($filter) . ' ORDER BY tt.id LIMIT :offset, :limit';

        $statement = $this->database->prepare($sql);
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->bindValue(':limit', $poolSize, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows) || $rows === []) {
            return [];
        }

        $rows = array_values(array_filter($rows, 'is_array'));

        if ($rows === []) {
            return [];
        }

        shuffle($rows);
        $selectedRows = array_slice($rows, 0, $limit);

        $games = [];

        foreach ($selectedRows as $gameData) {
            $games[] = new PlayerRandomGame($gameData, $this->utility);
        }

        return $games;
    }

    private function buildSelectableQuery(PlayerRandomGamesFilter $filter): string
    {
        return <<<'SQL'
            SELECT
                tt.id,
                tt.np_communication_id,
                tt.name,
                tt.icon_url,
                tt.platform,
                tt.owners,
                tt.difficulty,
                tt.platinum,
                tt.gold,
                tt.silver,
                tt.bronze,
                tt.rarity_points,
                ttp.progress
            SQL
            . $this->buildBaseQuery($filter);
    }

    private function countEligibleGames(int $accountId, PlayerRandomGamesFilter $filter): int
    {
        $sql = 'SELECT COUNT(*)' . $this->buildBaseQuery($filter);

        $statement = $this->database->prepare($sql);
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $statement->execute();

        $count = $statement->fetchColumn();

        return $count === false ? 0 : (int) $count;
    }

    private function buildBaseQuery(PlayerRandomGamesFilter $filter): string
    {
        $sql = <<<'SQL'
             FROM trophy_title tt
            LEFT JOIN trophy_title_player ttp ON
                ttp.np_communication_id = tt.np_communication_id
                AND ttp.account_id = :account_id
            WHERE
                tt.status = 0
                AND (ttp.progress IS NULL OR ttp.progress < 100)
        SQL;

        $sql .= $this->buildPlatformFilter($filter);

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
