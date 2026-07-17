<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/TrophyStatusProgressRecalculator.php';

final class TrophyStatusProgressRecalculatorTest extends TestCase
{
    public function testRecalculateGroupCountsOnlyEarnedTrophiesForAllTypes(): void
    {
        $database = new RecordingTrophyStatusProgressPDO();
        $recalculator = new TrophyStatusProgressRecalculator($database);

        $recalculator->recalculateGroup('NPWR00001_00', 'default', [10]);

        $this->assertSame(1, count($database->playerTrophyCountQueries));
        $query = $database->playerTrophyCountQueries[0];
        $this->assertTrue(str_contains($query, "COALESCE(SUM(CASE WHEN tm.trophy_id IS NOT NULL AND t.type = 'bronze' THEN 1 ELSE 0 END), 0) AS bronze"));
        $this->assertTrue(str_contains($query, "COALESCE(SUM(CASE WHEN tm.trophy_id IS NOT NULL AND t.type = 'silver' THEN 1 ELSE 0 END), 0) AS silver"));
        $this->assertTrue(str_contains($query, "COALESCE(SUM(CASE WHEN tm.trophy_id IS NOT NULL AND t.type = 'gold' THEN 1 ELSE 0 END), 0) AS gold"));
        $this->assertTrue(str_contains($query, "COALESCE(SUM(CASE WHEN tm.trophy_id IS NOT NULL AND t.type = 'platinum' THEN 1 ELSE 0 END), 0) AS platinum"));
        $this->assertTrue(str_contains($query, 'FROM
            temp_impacted_accounts tia'));
        $this->assertTrue(str_contains($query, 'LEFT JOIN trophy_earned te ON te.account_id = tia.account_id'));
        $this->assertTrue(str_contains($query, 'AND te.earned = 1'));
        $this->assertTrue(str_contains($query, 'AND tm.status = 0'));
        $this->assertTrue(str_contains($query, 'AND aggregate.account_id IS NOT NULL'));

        $this->assertTrue($database->impactedAccountsQuery !== null);
        $this->assertTrue(str_contains((string) $database->impactedAccountsQuery, 'FROM trophy_title_player ttp'));
        $this->assertTrue(str_contains((string) $database->impactedAccountsQuery, 'te.account_id = ttp.account_id'));
        $this->assertTrue(str_contains((string) $database->impactedAccountsQuery, 'te.order_id IN ('));
        $this->assertTrue(str_contains((string) $database->impactedAccountsQuery, 'AND te.earned = 1'));
        $this->assertTrue(str_contains((string) $database->impactedAccountsQuery, 'WHERE ttp.np_communication_id = ?'));
        $this->assertFalse(str_contains((string) $database->impactedAccountsQuery, 'INNER JOIN trophy t ON'));
        $this->assertSame('SELECT DISTINCT order_id FROM trophy WHERE id IN (?)', $database->orderIdsQuery);
    }

    public function testRecalculateTitleAggregatesFromTrophyGroupPlayer(): void
    {
        $database = new RecordingTrophyStatusProgressPDO();
        $recalculator = new TrophyStatusProgressRecalculator($database);

        $recalculator->recalculateTitle('NPWR00001_00', 1, [10]);

        $this->assertTrue(
            str_contains($database->titlePlayerCountQuery ?? '', 'trophy_group_player'),
            'Expected title-player recalculation to aggregate from trophy_group_player.'
        );
    }

    public function testRecalculateTitleInsertsUnobtainableChangelogWhenGameExists(): void
    {
        $database = new RecordingTrophyStatusProgressPDO();
        $database->gameId = 42;
        $recalculator = new TrophyStatusProgressRecalculator($database);

        $recalculator->recalculateTitle('NPWR00001_00', 1, [10]);

        $this->assertSame('GAME_UNOBTAINABLE', $database->insertedChangeType);
        $this->assertSame(42, $database->insertedChangeParam1);
    }

    public function testRecalculateTitleInsertsObtainableChangelogWhenGameExists(): void
    {
        $database = new RecordingTrophyStatusProgressPDO();
        $database->gameId = 7;
        $recalculator = new TrophyStatusProgressRecalculator($database);

        $recalculator->recalculateTitle('NPWR00001_00', 0, [10]);

        $this->assertSame('GAME_OBTAINABLE', $database->insertedChangeType);
        $this->assertSame(7, $database->insertedChangeParam1);
    }
}

final class RecordingTrophyStatusProgressPDO extends PDO
{
    /** @var list<string> */
    public array $playerTrophyCountQueries = [];

    public ?string $titlePlayerCountQuery = null;

    public ?string $impactedAccountsQuery = null;

    public ?string $orderIdsQuery = null;

    public string|int|false|null $gameId = false;

    public ?string $insertedChangeType = null;

    public int|string|null $insertedChangeParam1 = null;

    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement
    {
        $trimmedQuery = trim($query);

        if (str_starts_with($trimmedQuery, 'SELECT DISTINCT order_id FROM trophy WHERE id IN')) {
            $this->orderIdsQuery = $trimmedQuery;

            return new RecordingTrophyStatusProgressFetchAllStatement([7]);
        }

        if (str_starts_with($trimmedQuery, 'INSERT IGNORE INTO temp_impacted_accounts')) {
            $this->impactedAccountsQuery = $trimmedQuery;

            return new RecordingTrophyStatusProgressExecuteOnlyStatement();
        }

        if (str_starts_with($trimmedQuery, 'UPDATE') && str_contains($trimmedQuery, 'LEFT JOIN (') && str_contains($trimmedQuery, 'trophy_earned te')) {
            $this->playerTrophyCountQueries[] = $trimmedQuery;

            return new RecordingTrophyStatusProgressExecuteOnlyStatement();
        }

        if (str_contains($trimmedQuery, 'INNER JOIN player_trophy_count ptc ON ptc.account_id = ttp.account_id')) {
            $this->titlePlayerCountQuery = $trimmedQuery;

            return new RecordingTrophyStatusProgressExecuteOnlyStatement();
        }

        if (str_starts_with($trimmedQuery, 'INSERT INTO `psn100_change`')) {
            return new RecordingTrophyStatusProgressChangeInsertStatement($this);
        }

        if (str_starts_with($trimmedQuery, 'SELECT')) {
            if (str_contains($trimmedQuery, 'AS max_score')) {
                return new RecordingTrophyStatusProgressFetchColumnStatement(100);
            }

            if (str_contains($trimmedQuery, 'SELECT id FROM trophy_title WHERE np_communication_id = :np_communication_id')) {
                return new RecordingTrophyStatusProgressFetchColumnStatement($this->gameId);
            }
        }

        return new RecordingTrophyStatusProgressExecuteOnlyStatement();
    }

    public function exec(string $statement): int|false
    {
        return 0;
    }
}

final class RecordingTrophyStatusProgressExecuteOnlyStatement extends PDOStatement
{
    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        return true;
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }
}

final class RecordingTrophyStatusProgressChangeInsertStatement extends PDOStatement
{
    private RecordingTrophyStatusProgressPDO $database;

    private ?string $changeType = null;

    private int|string|null $param1 = null;

    public function __construct(RecordingTrophyStatusProgressPDO $database)
    {
        $this->database = $database;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        if ($param === ':change_type') {
            $this->changeType = (string) $value;
        }

        if ($param === ':param_1') {
            $this->param1 = $value;
        }

        return true;
    }

    public function execute(?array $params = null): bool
    {
        $this->database->insertedChangeType = $this->changeType;
        $this->database->insertedChangeParam1 = $this->param1;

        return true;
    }
}

final class RecordingTrophyStatusProgressFetchColumnStatement extends PDOStatement
{
    private string|int|bool|null $value;

    public function __construct(string|int|bool|null $value)
    {
        $this->value = $value;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        return true;
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetchColumn(int $column = 0): string|int|false|null
    {
        return $this->value;
    }
}

final class RecordingTrophyStatusProgressFetchAllStatement extends PDOStatement
{
    /** @param list<int|string> $values */
    public function __construct(private readonly array $values)
    {
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        return true;
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->values;
    }
}
