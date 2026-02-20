<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/TrophyMergeService.php';

final class TrophyMergeServiceGroupPlayerMergeTest extends TestCase
{
    public function testUpdateTrophyGroupPlayerUsesAllChildPlayersForMergeTitle(): void
    {
        $pdo = new RecordingGroupPlayerPDO(
            'NP_CHILD_2',
            'MERGE_000010',
            ['NP_CHILD_1', 'NP_CHILD_2'],
            [['parent_group_id' => 'default']]
        );
        $service = new TrophyMergeService($pdo);

        $reflection = new ReflectionMethod(TrophyMergeService::class, 'updateTrophyGroupPlayer');
        $reflection->setAccessible(true);
        $reflection->invoke($service, 42);

        $this->assertSame(
            'MERGE_000010',
            $pdo->groupQueryParameters[':parent_np_communication_id'] ?? null,
            'Expected group query to use the merge parent id.'
        );
        $this->assertSame(
            ['NP_CHILD_1', 'NP_CHILD_2'],
            $pdo->zeroProgressChildIds,
            'Expected zero-progress insert to use all child players.'
        );
        $this->assertSame(
            'MERGE_000010',
            $pdo->zeroProgressParameters[':np_communication_id'] ?? null,
            'Expected zero-progress insert to target the merge title.'
        );
        $this->assertTrue(
            str_contains($pdo->zeroProgressSql ?? '', 'IN (:child_np_0, :child_np_1)'),
            'Expected zero-progress insert to use all child placeholders.'
        );
        $this->assertTrue(
            str_contains($pdo->groupPlayerMergeSql ?? '', 'tg.max_score = 0'),
            'Expected merge progress SQL to branch when a group has no obtainable points.'
        );
        $this->assertTrue(
            str_contains($pdo->groupPlayerMergeSql ?? '', '100,'),
            'Expected merge progress SQL to set 100% when a group has no obtainable points.'
        );
    }
}

final class RecordingGroupPlayerPDO extends PDO
{
    /** @var list<string> */
    public array $zeroProgressChildIds = [];
    /** @var array<string, scalar|null> */
    public array $groupQueryParameters = [];
    /** @var array<string, scalar|null> */
    public array $zeroProgressParameters = [];
    public ?string $zeroProgressSql = null;
    public ?string $groupPlayerMergeSql = null;

    public function __construct(
        private string $childNpCommunicationId,
        private string $parentNpCommunicationId,
        private array $childNpCommunicationIds,
        private array $groupRows
    ) {
    }

    public function prepare(string $statement, array $options = []): PDOStatement
    {
        $trimmed = trim((string) $statement);
        $normalized = preg_replace('/\s+/', ' ', $trimmed) ?? '';

        if (str_starts_with($normalized, 'SELECT np_communication_id FROM trophy_title')) {
            return new RecordingScalarStatement($this->childNpCommunicationId);
        }

        if (str_starts_with($normalized, 'SELECT DISTINCT parent_np_communication_id FROM trophy_merge')) {
            return new RecordingAssocStatement(['parent_np_communication_id' => $this->parentNpCommunicationId]);
        }

        if (str_starts_with($normalized, 'SELECT DISTINCT child_np_communication_id FROM trophy_merge')) {
            return new RecordingColumnStatement($this->childNpCommunicationIds);
        }

        if (str_starts_with($normalized, 'SELECT DISTINCT parent_group_id FROM trophy_merge')) {
            return new RecordingGroupStatement($this, $this->groupRows);
        }

        if (str_starts_with($normalized, 'INSERT INTO trophy_group_player')) {
            $this->groupPlayerMergeSql = $trimmed;

            return new RecordingExecuteStatement();
        }

        if (str_starts_with($normalized, 'INSERT IGNORE INTO trophy_group_player')) {
            $this->zeroProgressSql = $trimmed;

            return new RecordingZeroProgressStatement($this);
        }

        throw new RuntimeException('Unexpected SQL statement: ' . $trimmed);
    }

    public function recordZeroProgress(array $parameters): void
    {
        $this->zeroProgressParameters = $parameters;
        $this->zeroProgressChildIds = array_values(array_filter(
            $parameters,
            static fn ($value, $key): bool => str_starts_with((string) $key, ':child_np_'),
            ARRAY_FILTER_USE_BOTH
        ));
    }

    public function recordGroupQueryParameters(array $parameters): void
    {
        $this->groupQueryParameters = $parameters;
    }
}

final class RecordingScalarStatement extends PDOStatement
{
    public function __construct(private string $value)
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

    public function fetchColumn(int $column = 0): string
    {
        return $this->value;
    }
}

final class RecordingAssocStatement extends PDOStatement
{
    public function __construct(private array $row)
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

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): array|false
    {
        $row = $this->row;
        $this->row = [];

        return $row === [] ? false : $row;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $row = $this->row;
        $this->row = [];

        if ($row === []) {
            return [];
        }

        if ($mode === PDO::FETCH_COLUMN) {
            return array_values($row);
        }

        return [$row];
    }
}

final class RecordingColumnStatement extends PDOStatement
{
    /** @var list<string> */
    private array $rows;

    /** @param list<string> $rows */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
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
        return $this->rows;
    }
}

final class RecordingGroupStatement extends PDOStatement
{
    /** @var list<array{parent_group_id:string}> */
    private array $rows;
    /** @var array<string, scalar|null> */
    private array $parameters = [];

    /** @param list<array{parent_group_id:string}> $rows */
    public function __construct(private RecordingGroupPlayerPDO $pdo, array $rows)
    {
        $this->rows = $rows;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->parameters[(string) $param] = $value;

        return true;
    }

    public function execute(?array $params = null): bool
    {
        $this->pdo->recordGroupQueryParameters($this->parameters);

        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): array|false
    {
        if ($this->rows === []) {
            return false;
        }

        return array_shift($this->rows);
    }
}

final class RecordingExecuteStatement extends PDOStatement
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

final class RecordingZeroProgressStatement extends PDOStatement
{
    /** @var array<string, scalar|null> */
    private array $parameters = [];

    public function __construct(private RecordingGroupPlayerPDO $pdo)
    {
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->parameters[(string) $param] = $value;

        return true;
    }

    public function execute(?array $params = null): bool
    {
        $this->pdo->recordZeroProgress($this->parameters);

        return true;
    }
}
