<?php

declare(strict_types=1);

use Random\Randomizer;

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

    private readonly PDO $database;

    private readonly Utility $utility;

    private readonly Randomizer $randomizer;

    public function __construct(PDO $database, Utility $utility, ?Randomizer $randomizer = null)
    {
        $this->database = $database;
        $this->utility = $utility;
        $this->randomizer = $randomizer ?? new Randomizer();
    }

    /**
     * @return PlayerRandomGame[]
     */
    public function getRandomGames(int $accountId, PlayerRandomGamesFilter $filter, int $limit = 8): array
    {
        $limit = max(1, $limit);

        $bounds = $this->fetchIdBounds($accountId, $filter);

        if ($bounds === null) {
            return [];
        }

        [$minId, $maxId] = $bounds;
        $rangeSize = $maxId - $minId + 1;

        if ($rangeSize <= 0) {
            return [];
        }

        $sampleSize = (int) min(max($limit * 4, 20), $rangeSize);
        $sampleSize = max($sampleSize, $limit);
        $maxAttempts = max(1, min(5, (int) ceil($rangeSize / $sampleSize)));

        $games = [];
        $seenIds = [];

        for ($attempt = 0; $attempt < $maxAttempts && count($games) < $limit; $attempt++) {
            $sampleIds = $this->generateRandomIds($minId, $maxId, $sampleSize);
            if ($sampleIds === []) {
                break;
            }

            $rows = $this->fetchSampledGames($accountId, $filter, $sampleIds);

            if ($rows === []) {
                continue;
            }

            foreach ($rows as $gameData) {
                $id = isset($gameData['id']) ? (int) $gameData['id'] : 0;
                if ($id === 0 || isset($seenIds[$id])) {
                    continue;
                }

                $seenIds[$id] = true;
                $games[] = PlayerRandomGame::fromArray($gameData, $this->utility);

                if (count($games) >= $limit) {
                    break;
                }
            }
        }

        if (count($games) < $limit) {
            $fallbackRows = $this->fetchFallbackGames($accountId, $filter, $limit - count($games), array_keys($seenIds));

            foreach ($fallbackRows as $gameData) {
                $id = isset($gameData['id']) ? (int) $gameData['id'] : 0;
                if ($id === 0 || isset($seenIds[$id])) {
                    continue;
                }

                $seenIds[$id] = true;
                $games[] = PlayerRandomGame::fromArray($gameData, $this->utility);

                if (count($games) >= $limit) {
                    break;
                }
            }
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
                ttm.owners,
                ttm.difficulty,
                tt.platinum,
                tt.gold,
                tt.silver,
                tt.bronze,
                ttm.rarity_points,
                ttm.in_game_rarity_points,
                ttp.progress
            SQL
            . $this->buildBaseQuery($filter);
    }

    private function buildBaseQuery(PlayerRandomGamesFilter $filter): string
    {
        $sql = <<<'SQL'
             FROM trophy_title tt
            JOIN trophy_title_meta ttm ON ttm.np_communication_id = tt.np_communication_id
            LEFT JOIN trophy_title_player ttp ON
                ttp.np_communication_id = tt.np_communication_id
                AND ttp.account_id = :account_id
            WHERE
                ttm.status = 0
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

    /**
     * @return array{0: int, 1: int}|null
     */
    private function fetchIdBounds(int $accountId, PlayerRandomGamesFilter $filter): ?array
    {
        $sql = 'SELECT MIN(tt.id) AS min_id, MAX(tt.id) AS max_id' . $this->buildBaseQuery($filter);

        $statement = $this->database->prepare($sql);
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($result)) {
            return null;
        }

        $minId = isset($result['min_id']) ? (int) $result['min_id'] : null;
        $maxId = isset($result['max_id']) ? (int) $result['max_id'] : null;

        if ($minId === null || $maxId === null) {
            return null;
        }

        return [$minId, $maxId];
    }

    /**
     * @return list<int>
     */
    private function generateRandomIds(int $minId, int $maxId, int $count): array
    {
        $available = $maxId - $minId + 1;

        if ($available <= 0) {
            return [];
        }

        $count = max(1, min($count, $available));

        if ($available <= $count * 2) {
            $pool = range($minId, $maxId);
            $pool = $this->randomizer->shuffleArray($pool);

            return array_slice($pool, 0, $count);
        }

        $ids = [];
        $selected = [];

        while (count($ids) < $count) {
            $candidate = $this->randomizer->getInt($minId, $maxId);

            if (isset($selected[$candidate])) {
                continue;
            }

            $selected[$candidate] = true;
            $ids[] = $candidate;
        }

        return $ids;
    }

    /**
     * @param list<int> $ids
     * @return array<int, array<string, mixed>>
     */
    private function fetchSampledGames(
        int $accountId,
        PlayerRandomGamesFilter $filter,
        array $ids,
        array $excludeIds = []
    ): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        foreach ($ids as $index => $_) {
            $placeholders[] = ':id_' . $index;
        }

        $sql = $this->buildSelectableQuery($filter)
            . sprintf(' AND tt.id IN (%s)', implode(', ', $placeholders))
            . $this->buildExcludeSeenIdsClause($excludeIds);

        $statement = $this->database->prepare($sql);
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_INT);

        foreach ($ids as $index => $id) {
            $statement->bindValue(':id_' . $index, $id, PDO::PARAM_INT);
        }
        $this->bindSeenIdParameters($statement, $excludeIds);

        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows) || $rows === []) {
            return [];
        }

        $rows = array_values(array_filter($rows, 'is_array'));

        $rows = $this->randomizer->shuffleArray($rows);

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchFallbackGames(int $accountId, PlayerRandomGamesFilter $filter, int $limit, array $seenIds = []): array
    {
        if ($limit <= 0) {
            return [];
        }

        $seenIds = array_values(array_unique(array_map('intval', $seenIds)));

        if ($limit <= 25) {
            return $this->fetchFallbackRowsWithRandomOrder($accountId, $filter, $limit, $seenIds);
        }

        $rows = $this->fetchFallbackRowsByRandomIdSampling($accountId, $filter, $limit, $seenIds);

        if (count($rows) >= $limit) {
            return $rows;
        }

        $additionalRows = $this->fetchFallbackRowsWithRandomOrder(
            $accountId,
            $filter,
            $limit - count($rows),
            [...$seenIds, ...array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $rows)]
        );

        if ($additionalRows === []) {
            return $rows;
        }

        return [...$rows, ...$additionalRows];
    }

    /**
     * @param list<int> $seenIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchFallbackRowsWithRandomOrder(int $accountId, PlayerRandomGamesFilter $filter, int $limit, array $seenIds): array
    {
        $sql = $this->buildSelectableQuery($filter)
            . $this->buildExcludeSeenIdsClause($seenIds)
            . ' ORDER BY ' . $this->resolveRandomOrderExpression() . ' LIMIT :limit';

        $statement = $this->database->prepare($sql);
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $this->bindSeenIdParameters($statement, $seenIds);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows) || $rows === []) {
            return [];
        }

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * @param list<int> $seenIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchFallbackRowsByRandomIdSampling(int $accountId, PlayerRandomGamesFilter $filter, int $limit, array $seenIds): array
    {
        $bounds = $this->fetchIdBoundsWithExclusions($accountId, $filter, $seenIds);

        if ($bounds === null) {
            return [];
        }

        [$minId, $maxId] = $bounds;
        $rangeSize = $maxId - $minId + 1;

        if ($rangeSize <= 0) {
            return [];
        }

        $sampleSize = (int) min(max($limit * 6, 60), $rangeSize);
        $sampleIds = $this->generateRandomIds($minId, $maxId, $sampleSize);
        if ($sampleIds === []) {
            return [];
        }

        $rows = $this->fetchSampledGames($accountId, $filter, $sampleIds, $seenIds);

        return array_slice($rows, 0, $limit);
    }

    /**
     * @param list<int> $seenIds
     * @return array{0: int, 1: int}|null
     */
    private function fetchIdBoundsWithExclusions(int $accountId, PlayerRandomGamesFilter $filter, array $seenIds): ?array
    {
        $sql = 'SELECT MIN(tt.id) AS min_id, MAX(tt.id) AS max_id'
            . $this->buildBaseQuery($filter)
            . $this->buildExcludeSeenIdsClause($seenIds);

        $statement = $this->database->prepare($sql);
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $this->bindSeenIdParameters($statement, $seenIds);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($result)) {
            return null;
        }

        $minId = isset($result['min_id']) ? (int) $result['min_id'] : null;
        $maxId = isset($result['max_id']) ? (int) $result['max_id'] : null;

        if ($minId === null || $maxId === null) {
            return null;
        }

        return [$minId, $maxId];
    }

    /**
     * @param list<int> $seenIds
     */
    private function buildExcludeSeenIdsClause(array $seenIds): string
    {
        if ($seenIds === []) {
            return '';
        }

        $placeholders = [];
        foreach ($seenIds as $index => $_) {
            $placeholders[] = ':seen_id_' . $index;
        }

        return ' AND tt.id NOT IN (' . implode(', ', $placeholders) . ')';
    }

    /**
     * @param list<int> $seenIds
     */
    private function bindSeenIdParameters(PDOStatement $statement, array $seenIds): void
    {
        foreach ($seenIds as $index => $seenId) {
            $statement->bindValue(':seen_id_' . $index, $seenId, PDO::PARAM_INT);
        }
    }

    private function resolveRandomOrderExpression(): string
    {
        $driverName = strtolower((string) $this->database->getAttribute(PDO::ATTR_DRIVER_NAME));

        return $driverName === 'mysql' ? 'RAND()' : 'RANDOM()';
    }
}
