<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerTimelineData.php';
require_once __DIR__ . '/PlayerTimelineEntry.php';
class PlayerTimelineService
{
    private readonly PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function getTimelineData(int $accountId): ?PlayerTimelineData
    {
        $sql = <<<'SQL'
            WITH timeline AS (
                SELECT
                    tt.id AS game_id,
                    tt.name,
                    ttp.progress,
                    DATE(MIN(te.earned_date)) AS first_trophy,
                    DATE(MAX(te.earned_date)) AS last_trophy
                FROM trophy_title_player ttp
                JOIN trophy_earned te
                    ON te.np_communication_id = ttp.np_communication_id
                    AND te.account_id = ttp.account_id
                JOIN trophy_title tt ON tt.np_communication_id = ttp.np_communication_id
                JOIN trophy_title_meta ttm ON ttm.np_communication_id = ttp.np_communication_id
                WHERE ttp.account_id = :account_id
                    AND ttm.status = 0
                GROUP BY ttp.np_communication_id, tt.id, tt.name, ttp.progress
            )
            SELECT
                game_id,
                name,
                progress,
                first_trophy,
                last_trophy,
                MIN(first_trophy) OVER () AS timeline_start,
                MAX(last_trophy) OVER () AS timeline_end
            FROM timeline
            ORDER BY first_trophy
            SQL;

        $statement = $this->database->prepare($sql);
        $statement->execute([':account_id' => $accountId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return null;
        }

        $timelineStart = new DateTimeImmutable((string) $rows[0]['timeline_start']);
        $timelineEnd = new DateTimeImmutable((string) $rows[0]['timeline_end']);
        $startDate = $timelineStart->modify('first day of this month');
        $endDate = $timelineEnd->modify('first day of next month');

        $entries = array_map(
            fn(array $row): PlayerTimelineEntry => PlayerTimelineEntry::fromRow($row),
            $rows
        );

        return new PlayerTimelineData($startDate, $endDate, $entries);
    }
}
