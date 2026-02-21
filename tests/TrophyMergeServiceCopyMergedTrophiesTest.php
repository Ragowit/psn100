<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/TrophyMergeService.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/TrophyMergeProgressListener.php';

final class TrophyMergeServiceCopyMergedTrophiesTest extends TestCase
{
    public function testCopyMergedTrophiesBulkCopiesEarnedProgressWithoutCtes(): void
    {
        $pdo = new RecordingPDO(2);
        $service = new TrophyMergeService($pdo);
        $progress = new RecordingProgressListener();

        $reflection = new ReflectionMethod(TrophyMergeService::class, 'copyMergedTrophies');
        $reflection->setAccessible(true);
        $reflection->invoke($service, 'NP_CHILD', $progress);

        $this->assertSame(1, $pdo->insertExecutions, 'Expected a single bulk insert operation.');
        $this->assertSame(1, $pdo->updateExecutions, 'Expected a single synchronization update.');

        $insertStatements = array_values(array_filter(
            $pdo->executedSql,
            static fn (string $sql): bool => str_contains($sql, 'INSERT INTO trophy_earned')
        ));

        $this->assertCount(1, $insertStatements, 'Expected insert statement for merged trophies.');

        $this->assertTrue(
            str_contains($insertStatements[0], 'FROM (')
                && str_contains($insertStatements[0], ') AS source'),
            'Insert statement should use a derived table alias for merge source data.'
        );

        $updateStatements = array_values(array_filter(
            $pdo->executedSql,
            static fn (string $sql): bool => str_contains($sql, 'UPDATE trophy_earned AS parent')
        ));

        $this->assertCount(1, $updateStatements, 'Expected update statement for merged trophies.');

        $this->assertTrue(
            str_contains($updateStatements[0], 'JOIN (')
                && str_contains($updateStatements[0], ') AS source ON'),
            'Update statement should join a derived table alias for merge source data.'
        );

        $expectedParameters = [':child_np_communication_id' => 'NP_CHILD'];
        $this->assertSame([$expectedParameters], $pdo->insertParameters, 'Unexpected insert parameters.');
        $this->assertSame([$expectedParameters], $pdo->updateParameters, 'Unexpected update parameters.');

        $expectedEvents = [
            [73, 'Found 2 merged trophies to copy…'],
            [75, 'Copying merged trophies… (2/2)'],
        ];
        $this->assertSame($expectedEvents, $progress->events, 'Unexpected progress events recorded.');
    }
}

final class RecordingPDO extends PDO
{
    /** @var list<string> */
    public array $executedSql = [];
    public int $insertExecutions = 0;
    public int $updateExecutions = 0;
    /** @var list<array<string, scalar|null>> */
    public array $insertParameters = [];
    /** @var list<array<string, scalar|null>> */
    public array $updateParameters = [];

    private int $mergeRowCount;

    public function __construct(int $mergeRowCount)
    {
        $this->mergeRowCount = $mergeRowCount;
    }

    public function prepare(string $statement, array $options = []): PDOStatement
    {
        $trimmed = trim((string) $statement);
        $normalized = preg_replace('/\s+/', ' ', $trimmed) ?? '';
        $this->executedSql[] = $trimmed;

        if (str_starts_with($normalized, 'SELECT COUNT(*) FROM trophy_merge')) {
            return new RecordingCountStatement($this->mergeRowCount);
        }

        if (str_contains($normalized, 'INSERT INTO trophy_earned')) {
            return new RecordingInsertStatement($this);
        }

        if (str_contains($normalized, 'UPDATE trophy_earned AS parent')) {
            return new RecordingUpdateStatement($this);
        }

        throw new RuntimeException('Unexpected SQL statement: ' . $trimmed);
    }

    /** @param array<string, scalar|null> $parameters */
    public function recordInsert(array $parameters): void
    {
        $this->insertExecutions++;
        $this->insertParameters[] = $parameters;
    }

    /** @param array<string, scalar|null> $parameters */
    public function recordUpdate(array $parameters): void
    {
        $this->updateExecutions++;
        $this->updateParameters[] = $parameters;
    }
}

final class RecordingCountStatement extends PDOStatement
{
    private int $count;

    public function __construct(int $count)
    {
        $this->count = $count;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        // No-op for testing purposes.

        return true;
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetchColumn(int $column = 0): int
    {
        return $this->count;
    }
}

final class RecordingInsertStatement extends PDOStatement
{
    private RecordingPDO $pdo;
    /** @var array<string, scalar|null> */
    private array $parameters = [];

    public function __construct(RecordingPDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->parameters[$param] = $value;

        return true;
    }

    public function execute(?array $params = null): bool
    {
        $this->pdo->recordInsert($this->parameters);

        return true;
    }
}

final class RecordingUpdateStatement extends PDOStatement
{
    private RecordingPDO $pdo;
    /** @var array<string, scalar|null> */
    private array $parameters = [];

    public function __construct(RecordingPDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->parameters[$param] = $value;

        return true;
    }

    public function execute(?array $params = null): bool
    {
        $this->pdo->recordUpdate($this->parameters);

        return true;
    }
}

final class RecordingProgressListener implements TrophyMergeProgressListener
{
    /** @var list<array{int, string}> */
    public array $events = [];

    public function onProgress(int $percent, string $message): void
    {
        $this->events[] = [$percent, $message];
    }
}
