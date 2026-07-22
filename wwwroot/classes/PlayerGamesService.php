<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerGame.php';
require_once __DIR__ . '/PlayerGamesFilter.php';
require_once __DIR__ . '/PlayerGamesSort.php';
require_once __DIR__ . '/PlatformSql.php';
require_once __DIR__ . '/SearchQueryHelper.php';
require_once __DIR__ . '/DateDurationSummary.php';

final class PlayerGamesService
{
    public function __construct(
        private readonly PDO $database,
        private readonly SearchQueryHelper $searchQueryHelper
    ) {
    }

    public function countPlayerGames(int $accountId, PlayerGamesFilter $filter): int
    {
        $sql = sprintf(
            'SELECT COUNT(*)
            FROM trophy_title_player ttp
                JOIN trophy_title tt USING (np_communication_id)
                JOIN trophy_title_meta ttm USING (np_communication_id)
                %s
            WHERE %s',
            $this->buildBaseGameJoin($filter),
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
                %s
            WHERE %s
            %s
            LIMIT :offset, :limit',
            implode(', ', $columns),
            $this->buildBaseGameJoin($filter),
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

    private function buildBaseGameJoin(PlayerGamesFilter $filter): string
    {
        if (!$filter->isBaseSelected()) {
            return '';
        }

        return "JOIN trophy_group_player tgp ON tgp.account_id = ttp.account_id
                AND tgp.np_communication_id = ttp.np_communication_id
                AND tgp.group_id = 'default'";
    }

    private function buildWhereClause(PlayerGamesFilter $filter): string
    {
        $conditions = [
            'ttm.status != 2',
            'ttp.account_id = :account_id',
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
            $platformExpression = PlatformSql::buildOrExpression($filter->getPlatforms());
            if ($platformExpression !== null) {
                $conditions[] = $platformExpression;
            }
        }

        return implode(' AND ', $conditions);
    }

    private function buildOrderByClause(PlayerGamesFilter $filter): string
    {
        return match ($filter->getSort()) {
            PlayerGamesSort::InGameMaxRarity => 'ORDER BY max_in_game_rarity_points DESC, `name`',
            PlayerGamesSort::InGameRarity => 'ORDER BY in_game_rarity_points DESC, `name`',
            PlayerGamesSort::MaxRarity => 'ORDER BY max_rarity_points DESC, `name`',
            PlayerGamesSort::Name => 'ORDER BY `name`',
            PlayerGamesSort::Rarity => 'ORDER BY rarity_points DESC, `name`',
            PlayerGamesSort::Search => 'ORDER BY exact_match DESC, prefix_match DESC, score DESC, `name`, tt.id',
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
            GROUP BY np_communication_id
            HAVING MIN(earned_date) <> MAX(earned_date)',
            implode(', ', $placeholders)
        );

        $statement = $this->database->prepare($sql);
        $this->bindCompletionLabelParameters($statement, $accountId, $npCommunicationIds);

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
     * @param list<string> $npCommunicationIds
     */
    private function bindCompletionLabelParameters(PDOStatement $statement, int $accountId, array $npCommunicationIds): void
    {
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_INT);

        foreach ($npCommunicationIds as $index => $npCommunicationId) {
            $statement->bindValue(':np_' . $index, $npCommunicationId, PDO::PARAM_STR);
        }
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

        $summary = DateDurationSummary::significantParts($start, $end);
        if ($summary === []) {
            return null;
        }

        return 'Completed in ' . implode(', ', $summary);
    }
}
