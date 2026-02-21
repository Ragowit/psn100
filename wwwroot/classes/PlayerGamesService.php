<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerGame.php';
require_once __DIR__ . '/PlayerGamesFilter.php';
require_once __DIR__ . '/SearchQueryHelper.php';

final class PlayerGamesService
{
    private const array PLATFORM_CONDITIONS = [
        PlayerGamesFilter::PLATFORM_PC => "tt.platform LIKE '%PC%'",
        PlayerGamesFilter::PLATFORM_PS3 => "tt.platform LIKE '%PS3%'",
        PlayerGamesFilter::PLATFORM_PS4 => "tt.platform LIKE '%PS4%'",
        PlayerGamesFilter::PLATFORM_PS5 => "tt.platform LIKE '%PS5%'",
        PlayerGamesFilter::PLATFORM_PSVITA => "tt.platform LIKE '%PSVITA%'",
        PlayerGamesFilter::PLATFORM_PSVR => "CONCAT(',', REPLACE(tt.platform, ' ', ''), ',') LIKE '%,PSVR,%'",
        PlayerGamesFilter::PLATFORM_PSVR2 => "tt.platform LIKE '%PSVR2%'",
    ];

    private readonly string $databaseDriver;

    public function __construct(
        private readonly PDO $database,
        private readonly SearchQueryHelper $searchQueryHelper
    ) {
        $this->databaseDriver = (string) $this->database->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function countPlayerGames(int $accountId, PlayerGamesFilter $filter): int
    {
        $sql = sprintf(
            'SELECT COUNT(*)
            FROM trophy_title_player ttp
                JOIN trophy_title tt USING (np_communication_id)
                JOIN trophy_title_meta ttm USING (np_communication_id)
                JOIN trophy_group_player tgp USING (account_id, np_communication_id)
            WHERE %s',
            $this->buildWhereClause($filter)
        );

        $statement = $this->database->prepare($sql);
        $this->bindCommonParameters($statement, $accountId, $filter, false);
        $statement->execute();

        $count = $statement->fetchColumn();

        return $count === false ? 0 : (int) $count;
    }

    /**
     * @return PlayerGame[]
     */
    public function getPlayerGames(int $accountId, PlayerGamesFilter $filter): array
    {
        $columns = [
            'tt.id',
            'tt.np_communication_id',
            'tt.name',
            'tt.icon_url',
            'tt.platform',
            'ttm.status AS status',
            'ttm.rarity_points AS max_rarity_points',
            'ttm.in_game_rarity_points AS max_in_game_rarity_points',
            'ttp.bronze',
            'ttp.silver',
            'ttp.gold',
            'ttp.platinum',
            'ttp.progress',
            'ttp.last_updated_date',
            'ttp.rarity_points',
            'ttp.in_game_rarity_points',
        ];

        $columns = $this->searchQueryHelper->addFulltextSelectColumns(
            $columns,
            'tt.name',
            $filter->shouldIncludeScoreColumn(),
            $filter->getSearch()
        );

        $sql = sprintf(
            'SELECT %s
            FROM trophy_title_player ttp
                JOIN trophy_title tt USING (np_communication_id)
                JOIN trophy_title_meta ttm USING (np_communication_id)
                JOIN trophy_group_player tgp USING (account_id, np_communication_id)
            WHERE %s
            %s
            LIMIT :offset, :limit',
            implode(', ', $columns),
            $this->buildWhereClause($filter),
            $this->buildOrderByClause($filter)
        );

        $statement = $this->database->prepare($sql);
        $this->bindCommonParameters($statement, $accountId, $filter, true);
        $statement->bindValue(':offset', $filter->getOffset(), PDO::PARAM_INT);
        $statement->bindValue(':limit', $filter->getLimit(), PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        $completionLabels = $this->fetchCompletionLabels($accountId, $rows);

        $games = [];
        foreach ($rows as $row) {
            $npCommunicationId = (string) ($row['np_communication_id'] ?? '');
            $completionLabel = $completionLabels[$npCommunicationId] ?? null;
            $games[] = PlayerGame::fromArray($row, $completionLabel);
        }

        return $games;
    }

    private function buildWhereClause(PlayerGamesFilter $filter): string
    {
        $conditions = [
            'ttm.status != 2',
            'ttp.account_id = :account_id',
            "tgp.group_id = 'default'",
        ];

        $conditions = $this->searchQueryHelper->appendFulltextCondition(
            $conditions,
            $filter->shouldApplyFulltextCondition(),
            'tt.name',
            $filter->getSearch()
        );

        if ($filter->isCompletedSelected()) {
            $conditions[] = 'ttp.progress = 100';
        }

        if ($filter->isUncompletedSelected()) {
            $conditions[] = 'ttp.progress < 100';
        }

        if ($filter->isBaseSelected()) {
            $conditions[] = 'tgp.progress = 100';
        }

        if ($filter->hasPlatformFilters()) {
            $platformConditions = [];
            foreach ($filter->getPlatforms() as $platformKey) {
                $condition = self::PLATFORM_CONDITIONS[$platformKey] ?? null;
                if ($condition !== null) {
                    $platformConditions[] = $condition;
                }
            }

            if ($platformConditions !== []) {
                $conditions[] = '(' . implode(' OR ', $platformConditions) . ')';
            }
        }

        return implode(' AND ', $conditions);
    }

    private function buildOrderByClause(PlayerGamesFilter $filter): string
    {
        return match ($filter->getSort()) {
            PlayerGamesFilter::SORT_IN_GAME_MAX_RARITY => 'ORDER BY max_in_game_rarity_points DESC, `name`',
            PlayerGamesFilter::SORT_IN_GAME_RARITY => 'ORDER BY in_game_rarity_points DESC, `name`',
            PlayerGamesFilter::SORT_MAX_RARITY => 'ORDER BY max_rarity_points DESC, `name`',
            PlayerGamesFilter::SORT_NAME => 'ORDER BY `name`',
            PlayerGamesFilter::SORT_RARITY => 'ORDER BY rarity_points DESC, `name`',
            PlayerGamesFilter::SORT_SEARCH => 'ORDER BY exact_match DESC, prefix_match DESC, score DESC, `name`, tt.id',
            default => 'ORDER BY last_updated_date DESC',
        };
    }

    private function bindCommonParameters(
        PDOStatement $statement,
        int $accountId,
        PlayerGamesFilter $filter,
        bool $bindPrefix
    ): void
    {
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_INT);

        if ($filter->shouldApplyFulltextCondition()) {
            $this->searchQueryHelper->bindSearchParameters(
                $statement,
                $filter->getSearch(),
                $bindPrefix
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, string>
     */
    private function fetchCompletionLabels(int $accountId, array $rows): array
    {
        $npCommunicationIds = $this->collectCompletedNpCommunicationIds($rows);

        if ($npCommunicationIds === []) {
            return [];
        }

        if ($this->databaseDriver === 'mysql') {
            return $this->fetchCompletionLabelsFromMySql($accountId, $npCommunicationIds);
        }

        return $this->fetchCompletionLabelsWithDateRange($accountId, $npCommunicationIds);
    }

    /**
     * @param list<string> $npCommunicationIds
     * @return array<string, string>
     */
    private function fetchCompletionLabelsFromMySql(int $accountId, array $npCommunicationIds): array
    {
        $placeholders = array_map(
            static fn(int $index): string => ':np_' . $index,
            array_keys($npCommunicationIds)
        );

        $sql = sprintf(
            'WITH completion_window AS (
                SELECT
                    np_communication_id,
                    TIMESTAMPDIFF(SECOND, MIN(earned_date), MAX(earned_date)) AS completion_seconds
                FROM trophy_earned
                WHERE account_id = :account_id
                    AND earned = 1
                    AND np_communication_id IN (%s)
                GROUP BY np_communication_id
            )
            SELECT np_communication_id, completion_seconds
            FROM completion_window
            WHERE completion_seconds > 0',
            implode(', ', $placeholders)
        );

        $statement = $this->database->prepare($sql);
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_INT);

        foreach ($npCommunicationIds as $index => $npCommunicationId) {
            $statement->bindValue(':np_' . $index, $npCommunicationId, PDO::PARAM_STR);
        }

        $statement->execute();
        $completionRows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($completionRows)) {
            return [];
        }

        $labels = [];
        foreach ($completionRows as $completionRow) {
            $npCommunicationId = (string) ($completionRow['np_communication_id'] ?? '');
            $seconds = $completionRow['completion_seconds'] ?? null;
            if (!is_numeric($seconds)) {
                continue;
            }

            $label = $this->formatCompletionLabelFromSeconds((int) $seconds);
            if ($npCommunicationId !== '' && $label !== null) {
                $labels[$npCommunicationId] = $label;
            }
        }

        return $labels;
    }

    /**
     * @param list<string> $npCommunicationIds
     * @return array<string, string>
     */
    private function fetchCompletionLabelsWithDateRange(int $accountId, array $npCommunicationIds): array
    {
        $placeholders = array_map(
            static fn(int $index): string => ':np_' . $index,
            array_keys($npCommunicationIds)
        );

        $sql = sprintf(
            'SELECT np_communication_id, MIN(earned_date) AS first_trophy, MAX(earned_date) AS last_trophy
            FROM trophy_earned
            WHERE account_id = :account_id
                AND earned = 1
                AND np_communication_id IN (%s)
            GROUP BY np_communication_id',
            implode(', ', $placeholders)
        );

        $statement = $this->database->prepare($sql);
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_INT);

        foreach ($npCommunicationIds as $index => $npCommunicationId) {
            $statement->bindValue(':np_' . $index, $npCommunicationId, PDO::PARAM_STR);
        }

        $statement->execute();
        $completionRows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($completionRows)) {
            return [];
        }

        $labels = [];
        foreach ($completionRows as $completionRow) {
            $npCommunicationId = (string) ($completionRow['np_communication_id'] ?? '');
            $label = $this->formatCompletionLabel(
                $completionRow['first_trophy'] ?? null,
                $completionRow['last_trophy'] ?? null
            );

            if ($npCommunicationId !== '' && $label !== null) {
                $labels[$npCommunicationId] = $label;
            }
        }

        return $labels;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return list<string>
     */
    private function collectCompletedNpCommunicationIds(array $rows): array
    {
        $uniqueIds = [];

        foreach ($rows as $row) {
            if ((int) ($row['progress'] ?? 0) !== 100) {
                continue;
            }

            $npCommunicationId = (string) ($row['np_communication_id'] ?? '');
            if ($npCommunicationId === '') {
                continue;
            }

            $uniqueIds[$npCommunicationId] = true;
        }

        return array_keys($uniqueIds);
    }


    private function formatCompletionLabelFromSeconds(int $seconds): ?string
    {
        if ($seconds <= 0) {
            return null;
        }

        $units = [
            'days' => 86400,
            'hours' => 3600,
            'minutes' => 60,
            'seconds' => 1,
        ];

        $parts = [];
        foreach ($units as $label => $unitSeconds) {
            if ($seconds < $unitSeconds) {
                continue;
            }

            $value = intdiv($seconds, $unitSeconds);
            $seconds %= $unitSeconds;
            if ($value <= 0) {
                continue;
            }

            $parts[] = sprintf('%d %s', $value, $label);
            if (count($parts) === 2) {
                break;
            }
        }

        if ($parts === []) {
            return null;
        }

        return 'Completed in ' . implode(', ', $parts);
    }


    private function formatCompletionLabel(mixed $firstTrophy, mixed $lastTrophy): ?string
    {
        if (!is_string($firstTrophy) || $firstTrophy === '' || !is_string($lastTrophy) || $lastTrophy === '') {
            return null;
        }

        try {
            $start = new \DateTimeImmutable($firstTrophy);
            $end = new \DateTimeImmutable($lastTrophy);
        } catch (\DateMalformedStringException) {
            return null;
        }

        $interval = $start->diff($end);
        $formatted = $interval->format('%y years, %m months, %d days, %h hours, %i minutes, %s seconds');
        $parts = explode(', ', $formatted);

        $nonZeroParts = array_values(array_filter(
            $parts,
            static fn(string $part): bool => $part !== '' && $part[0] !== '0'
        ));

        if ($nonZeroParts === []) {
            return null;
        }

        $summary = array_slice($nonZeroParts, 0, 2);

        return 'Completed in ' . implode(', ', $summary);
    }
}
