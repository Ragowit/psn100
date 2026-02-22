<?php

declare(strict_types=1);

class PlayerSummaryService
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function getSummary(int $accountId): PlayerSummary
    {
        $summaryRow = $this->fetchSummaryRow($accountId);

        return new PlayerSummary(
            $summaryRow['number_of_games'],
            $summaryRow['number_of_completed_games'],
            $summaryRow['average_progress'],
            $summaryRow['unearned_trophies']
        );
    }

    /**
     * @return array{number_of_games: int, number_of_completed_games: int, average_progress: ?float, unearned_trophies: int}
     */
    private function fetchSummaryRow(int $accountId): array
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT
                CAST(COUNT(*) AS UNSIGNED)                                  AS number_of_games,
                CAST(COALESCE(SUM(ttp.progress = 100), 0) AS UNSIGNED)      AS number_of_completed_games,
                ROUND(AVG(ttp.progress), 2)                                 AS average_progress,
                CAST(COALESCE(SUM(
                    CASE WHEN tt.bronze > ttp.bronze THEN tt.bronze - ttp.bronze ELSE 0 END +
                    CASE WHEN tt.silver > ttp.silver THEN tt.silver - ttp.silver ELSE 0 END +
                    CASE WHEN tt.gold > ttp.gold THEN tt.gold - ttp.gold ELSE 0 END +
                    CASE WHEN tt.platinum > ttp.platinum THEN tt.platinum - ttp.platinum ELSE 0 END
                ), 0) AS UNSIGNED)                                          AS unearned_trophies
            FROM
                trophy_title_player ttp
                INNER JOIN trophy_title tt ON tt.np_communication_id = ttp.np_communication_id
                INNER JOIN trophy_title_meta ttm ON ttm.np_communication_id = tt.np_communication_id
            WHERE
                ttm.status = 0
                AND ttp.account_id = :account_id
            SQL
        );

        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();

        /** @var array{number_of_games?: int|string|null, number_of_completed_games?: int|string|null, average_progress?: float|string|null, unearned_trophies?: int|string|null}|false $row */
        $row = $query->fetch(PDO::FETCH_ASSOC);
        $row = $row === false ? [] : $row;

        return [
            'number_of_games' => (int) ($row['number_of_games'] ?? 0),
            'number_of_completed_games' => (int) ($row['number_of_completed_games'] ?? 0),
            'average_progress' => isset($row['average_progress']) ? (float) $row['average_progress'] : null,
            'unearned_trophies' => (int) ($row['unearned_trophies'] ?? 0),
        ];
    }
}
