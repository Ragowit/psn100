<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/TrophyStatusService.php';

final class TrophyStatusServiceTest extends TestCase
{
    public function testUpdateTrophiesCountsOnlyEarnedTrophiesForAllTypes(): void
    {
        $database = new RecordingTrophyStatusPDO();
        $service = new TrophyStatusService($database);

        $service->updateTrophies([10], 1);

        $this->assertSame(4, count($database->playerTrophyCountQueries));

        foreach (['bronze', 'silver', 'gold', 'platinum'] as $type) {
            $matchingQueries = array_values(array_filter(
                $database->playerTrophyCountQueries,
                static fn (string $query): bool => str_contains($query, "AND t.type = '{$type}'")
            ));

            $this->assertSame(1, count($matchingQueries), 'Expected one player trophy count query for type ' . $type);

            $query = $matchingQueries[0];
            $this->assertTrue(str_contains($query, 'trophy_group_player tgp'), 'Expected player trophy count SQL to anchor on trophy_group_player for ' . $type);
            $this->assertTrue(str_contains($query, 'AND te.earned = 1'), 'Expected earned filter in player trophy count SQL for ' . $type);
            $this->assertTrue(str_contains($query, 'AND tm.status = 0'), 'Expected unobtainable filter in player trophy count SQL for ' . $type);
        }

        $this->assertTrue(
            str_contains($database->titlePlayerCountQuery ?? '', 'trophy_group_player'),
            'Expected title-player recalculation to aggregate from trophy_group_player.'
        );
    }
}

final class RecordingTrophyStatusPDO extends PDO
{
    /** @var list<string> */
    public array $playerTrophyCountQueries = [];

    public ?string $titlePlayerCountQuery = null;

    private bool $inTransaction = false;

    public function __construct()
    {
    }

    public function beginTransaction(): bool
    {
        $this->inTransaction = true;

        return true;
    }

    public function commit(): bool
    {
        $this->inTransaction = false;

        return true;
    }

    public function rollBack(): bool
    {
        $this->inTransaction = false;

        return true;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function prepare(string $query, array $options = []): PDOStatement
    {
        $trimmedQuery = trim($query);

        if (str_starts_with($trimmedQuery, 'UPDATE trophy_meta SET status = :status WHERE trophy_id = :trophy_id')) {
            return new RecordingExecuteOnlyStatement();
        }

        if (str_starts_with($trimmedQuery, 'SELECT np_communication_id, group_id, name FROM trophy WHERE id = :trophy_id')) {
            return new RecordingFetchAssocStatement([
                'np_communication_id' => 'NPWR00001_00',
                'group_id' => 'default',
                'name' => 'Test Trophy',
            ]);
        }

        if (str_contains($trimmedQuery, 'WITH player_trophy_count AS(') && str_contains($trimmedQuery, 'trophy_earned te')) {
            $this->playerTrophyCountQueries[] = $trimmedQuery;

            return new RecordingExecuteOnlyStatement();
        }

        if (str_contains($trimmedQuery, 'trophy_title_player ttp,') && str_contains($trimmedQuery, 'player_trophy_count ptc')) {
            $this->titlePlayerCountQuery = $trimmedQuery;

            return new RecordingExecuteOnlyStatement();
        }

        if (str_starts_with($trimmedQuery, 'SELECT')) {
            if (str_contains($trimmedQuery, 'AS max_score')) {
                return new RecordingFetchColumnStatement(100);
            }

            if (str_contains($trimmedQuery, 'SELECT id FROM trophy_title WHERE np_communication_id = :np_communication_id')) {
                return new RecordingFetchColumnStatement(false);
            }
        }

        return new RecordingExecuteOnlyStatement();
    }
}

final class RecordingExecuteOnlyStatement extends PDOStatement
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

final class RecordingFetchAssocStatement extends PDOStatement
{
    /** @var array<string, string>|null */
    private ?array $row;

    /** @param array<string, string> $row */
    public function __construct(array $row)
    {
        $this->row = $row;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        return true;
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): array|false
    {
        if ($this->row === null) {
            return false;
        }

        $row = $this->row;
        $this->row = null;

        return $row;
    }
}

final class RecordingFetchColumnStatement extends PDOStatement
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
