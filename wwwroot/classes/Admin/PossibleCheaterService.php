<?php

declare(strict_types=1);

require_once __DIR__ . '/PossibleCheaterRulesCatalog.php';
require_once __DIR__ . '/PossibleCheaterReport.php';

class PossibleCheaterService
{
    private PDO $database;
    private PossibleCheaterRulesCatalog $rulesCatalog;

    public function __construct(PDO $database, ?PossibleCheaterRulesCatalog $rulesCatalog = null)
    {
        $this->database = $database;
        $this->rulesCatalog = $rulesCatalog ?? new PossibleCheaterRulesCatalog();
    }

    public function createReport(): PossibleCheaterReport
    {
        return new PossibleCheaterReport(
            $this->buildGeneralReportEntries(),
            $this->buildSectionReports()
        );
    }

    private function buildGeneralWhereClause(): string
    {
        $conditions = [];

        foreach ($this->rulesCatalog->getGeneralRuleGroups() as $group) {
            foreach ($group->getRules() as $rule) {
                $conditions[] = '(' . $rule->getCondition() . ')';
            }
        }

        if ($conditions === []) {
            return '()';
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }

    /**
     * @return PossibleCheaterReportEntry[]
     */
    private function buildGeneralReportEntries(): array
    {
        return array_map(
            static fn(array $row): PossibleCheaterReportEntry => PossibleCheaterReportEntry::fromArray($row),
            $this->fetchGeneralPossibleCheaterRows()
        );
    }

    /**
     * @return PossibleCheaterReportSection[]
     */
    private function buildSectionReports(): array
    {
        $sections = [];

        foreach ($this->rulesCatalog->getSectionDefinitions() as $definition) {
            $entries = array_map(
                static function (array $row) use ($definition): PossibleCheaterReportSectionEntry {
                    $onlineId = (string) $row['online_id'];

                    return new PossibleCheaterReportSectionEntry(
                        $definition->buildLink($onlineId),
                        $onlineId,
                        (int) $row['account_id']
                    );
                },
                $this->fetchAll($definition->getQuery())
            );

            $sections[] = new PossibleCheaterReportSection(
                $definition->getTitle(),
                $entries
            );
        }

        return $sections;
    }

    /**
     * @return list<array{account_id:int, player_name:string, game_id:int, game_name:string}>
     */
    private function fetchGeneralPossibleCheaterRows(): array
    {
        $whereClause = $this->buildGeneralWhereClause();

        $sql = <<<'SQL'
        SELECT
            first_games.account_id,
            first_games.player_name,
            tt_first.id AS game_id,
            tt_first.name AS game_name
        FROM (
            SELECT
                p.account_id,
                p.online_id AS player_name,
                MIN(tt.np_communication_id) AS first_np_communication_id
            FROM
                trophy_earned te
            JOIN player p ON p.account_id = te.account_id
            JOIN trophy_title tt ON tt.np_communication_id = te.np_communication_id
            WHERE
                __WHERE_CLAUSE__
                AND p.status != 1
            GROUP BY
                p.account_id,
                p.online_id
        ) AS first_games
        JOIN trophy_title tt_first ON tt_first.np_communication_id = first_games.first_np_communication_id
        ORDER BY
            first_games.player_name
        SQL;

        $sql = str_replace('__WHERE_CLAUSE__', $whereClause, $sql);

        $rows = $this->fetchAll($sql);

        return array_map(
            static fn(array $row): array => [
                'account_id' => (int) $row['account_id'],
                'player_name' => (string) $row['player_name'],
                'game_id' => (int) $row['game_id'],
                'game_name' => (string) $row['game_name'],
            ],
            $rows
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAll(string $sql): array
    {
        $statement = $this->database->prepare($sql);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }
}
