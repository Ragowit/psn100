<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerGame.php';
require_once __DIR__ . '/PlayerGamesFilter.php';

class PlayerGamesService
{
    private const PLATFORM_CONDITIONS = [
        PlayerGamesFilter::PLATFORM_PC => "tt.platform LIKE '%PC%'",
        PlayerGamesFilter::PLATFORM_PS3 => "tt.platform LIKE '%PS3%'",
        PlayerGamesFilter::PLATFORM_PS4 => "tt.platform LIKE '%PS4%'",
        PlayerGamesFilter::PLATFORM_PS5 => "tt.platform LIKE '%PS5%'",
        PlayerGamesFilter::PLATFORM_PSVITA => "tt.platform LIKE '%PSVITA%'",
        PlayerGamesFilter::PLATFORM_PSVR => "(tt.platform LIKE '%PSVR' OR tt.platform LIKE '%PSVR,%')",
        PlayerGamesFilter::PLATFORM_PSVR2 => "tt.platform LIKE '%PSVR2%'",
    ];

    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function countPlayerGames(int $accountId, PlayerGamesFilter $filter): int
    {
        $sql = sprintf(
            'SELECT COUNT(*)
            FROM trophy_title_player ttp
                JOIN trophy_title tt USING (np_communication_id)
                JOIN trophy_group_player tgp USING (account_id, np_communication_id)
            WHERE %s',
            $this->buildWhereClause($filter, true)
        );

        $statement = $this->database->prepare($sql);
        $this->bindCommonParameters($statement, $accountId, $filter);
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
            'tt.status',
            'tt.rarity_points AS max_rarity_points',
            'ttp.bronze',
            'ttp.silver',
            'ttp.gold',
            'ttp.platinum',
            'ttp.progress',
            'ttp.last_updated_date',
            'ttp.rarity_points',
        ];

        if ($filter->shouldIncludeScoreColumn()) {
            $columns[] = 'tt.name = :search AS exact_match';
            $columns[] = 'MATCH(tt.name) AGAINST (:search) AS score';
        }

        $sql = sprintf(
            'SELECT %s
            FROM trophy_title_player ttp
                JOIN trophy_title tt USING (np_communication_id)
                JOIN trophy_group_player tgp USING (account_id, np_communication_id)
            WHERE %s
            %s
            LIMIT :offset, :limit',
            implode(', ', $columns),
            $this->buildWhereClause($filter, false),
            $this->buildOrderByClause($filter)
        );

        $statement = $this->database->prepare($sql);
        $this->bindCommonParameters($statement, $accountId, $filter);
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

    private function buildWhereClause(PlayerGamesFilter $filter, bool $forCount): string
    {
        $conditions = [
            'tt.status != 2',
            'ttp.account_id = :account_id',
            "tgp.group_id = 'default'",
        ];

        if ($filter->shouldApplyFulltextCondition()) {
            $matchCondition = '(MATCH(tt.name) AGAINST (:search)) > 0';

            if ($filter->getSearch() !== '') {
                $conditions[] = '(' . $matchCondition . ' OR tt.name LIKE :search_like)';
            } else {
                $conditions[] = $matchCondition;
            }
        }

        if ($filter->isCompletedSelected()) {
            $conditions[] = 'ttp.progress = 100';
        }

        if ($filter->isUncompletedSelected()) {
            $conditions[] = 'ttp.progress != 100';
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
            PlayerGamesFilter::SORT_MAX_RARITY => 'ORDER BY max_rarity_points DESC, `name`',
            PlayerGamesFilter::SORT_NAME => 'ORDER BY `name`',
            PlayerGamesFilter::SORT_RARITY => 'ORDER BY rarity_points DESC, `name`',
            PlayerGamesFilter::SORT_SEARCH => 'ORDER BY exact_match DESC, score DESC, `name`',
            default => 'ORDER BY last_updated_date DESC',
        };
    }

    private function bindCommonParameters(PDOStatement $statement, int $accountId, PlayerGamesFilter $filter): void
    {
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_INT);

        if ($filter->shouldApplyFulltextCondition()) {
            $search = $filter->getSearch();
            $statement->bindValue(':search', $search, PDO::PARAM_STR);

            if ($search !== '') {
                $statement->bindValue(':search_like', $this->buildSearchLikeParameter($search), PDO::PARAM_STR);
            }
        }
    }

    private function buildSearchLikeParameter(string $search): string
    {
        return '%' . addcslashes($search, "\\%_") . '%';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, string>
     */
    private function fetchCompletionLabels(int $accountId, array $rows): array
    {
        $npCommunicationIds = [];
        foreach ($rows as $row) {
            if ((int) ($row['progress'] ?? 0) === 100) {
                $npCommunicationId = (string) ($row['np_communication_id'] ?? '');
                if ($npCommunicationId !== '') {
                    $npCommunicationIds[$npCommunicationId] = $npCommunicationId;
                }
            }
        }

        if ($npCommunicationIds === []) {
            return [];
        }

        $placeholders = [];
        $index = 0;
        foreach (array_values($npCommunicationIds) as $npCommunicationId) {
            $placeholders[] = ':np_' . $index;
            $index++;
        }

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

        $index = 0;
        foreach (array_values($npCommunicationIds) as $npCommunicationId) {
            $statement->bindValue(':np_' . $index, $npCommunicationId, PDO::PARAM_STR);
            $index++;
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

    private function formatCompletionLabel(mixed $firstTrophy, mixed $lastTrophy): ?string
    {
        if (!is_string($firstTrophy) || $firstTrophy === '' || !is_string($lastTrophy) || $lastTrophy === '') {
            return null;
        }

        try {
            $start = new \DateTimeImmutable($firstTrophy);
            $end = new \DateTimeImmutable($lastTrophy);
        } catch (\Exception) {
            return null;
        }

        $interval = $start->diff($end);
        $formatted = $interval->format('%y years, %m months, %d days, %h hours, %i minutes, %s seconds');
        $parts = explode(', ', $formatted);

        $nonZeroParts = [];
        foreach ($parts as $part) {
            if ($part !== '' && $part[0] !== '0') {
                $nonZeroParts[] = $part;
            }
        }

        if ($nonZeroParts === []) {
            return null;
        }

        if (count($nonZeroParts) >= 2) {
            return 'Completed in ' . $nonZeroParts[0] . ', ' . $nonZeroParts[1];
        }

        return 'Completed in ' . $nonZeroParts[0];
    }
}
