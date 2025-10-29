<?php

declare(strict_types=1);

class PlayerSummaryService
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
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
                COUNT(*)                                                   AS number_of_games,
                SUM(CASE WHEN ttp.progress = 100 THEN 1 ELSE 0 END)       AS number_of_completed_games,
                ROUND(AVG(ttp.progress), 2)                               AS average_progress,
                SUM(
                    tt.bronze - ttp.bronze +
                    tt.silver - ttp.silver +
                    tt.gold - ttp.gold +
                    tt.platinum - ttp.platinum
                )                                                         AS unearned_trophies
            FROM
                trophy_title_player ttp
                INNER JOIN trophy_title tt ON tt.np_communication_id = ttp.np_communication_id
            WHERE
                tt.status = 0
                AND ttp.account_id = :account_id
            SQL
        );

        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return [
                'number_of_games' => 0,
                'number_of_completed_games' => 0,
                'average_progress' => null,
                'unearned_trophies' => 0,
            ];
        }

        return [
            'number_of_games' => $this->toInt($row['number_of_games'] ?? null),
            'number_of_completed_games' => $this->toInt($row['number_of_completed_games'] ?? null),
            'average_progress' => $this->toFloat($row['average_progress'] ?? null),
            'unearned_trophies' => $this->toInt($row['unearned_trophies'] ?? null),
        ];
    }

    private function toInt(mixed $value): int
    {
        if ($value === false || $value === null) {
            return 0;
        }

        return (int) $value;
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === false || $value === null) {
            return null;
        }

        return (float) $value;
    }
}
