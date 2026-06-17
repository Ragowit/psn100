<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyMergeService.php';

final class TrophyMergeServiceTransactionTest extends TestCase
{
    public function testNestedExecuteTransactionCommitsDatabaseOnlyOnce(): void
    {
        $database = new TransactionDepthTrackingPDO();
        $service = new TrophyMergeService($database);
        $executeTransaction = new ReflectionMethod(TrophyMergeService::class, 'executeTransaction');
        $executeTransaction->setAccessible(true);

        $executeTransaction->invoke($service, function () use ($executeTransaction, $service): void {
            $executeTransaction->invoke($service, static function (): void {
            });
        });

        $this->assertSame(1, $database->beginCount, 'Expected one database transaction to start.');
        $this->assertSame(1, $database->commitCount, 'Expected one database transaction to commit.');
        $this->assertSame(0, $database->rollBackCount, 'Expected no rollback for successful nested transactions.');
    }

    public function testNestedExecuteTransactionRollsBackDatabaseOnInnerFailure(): void
    {
        $database = new TransactionDepthTrackingPDO();
        $service = new TrophyMergeService($database);
        $executeTransaction = new ReflectionMethod(TrophyMergeService::class, 'executeTransaction');
        $executeTransaction->setAccessible(true);

        try {
            $executeTransaction->invoke($service, function () use ($executeTransaction, $service): void {
                $executeTransaction->invoke($service, static function (): void {
                    throw new RuntimeException('Inner failure.');
                });
            });
            $this->fail('Expected inner transaction failure to bubble up.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Inner failure.', $exception->getMessage());
        }

        $this->assertSame(1, $database->beginCount, 'Expected one database transaction to start.');
        $this->assertSame(0, $database->commitCount, 'Expected failed nested transactions to avoid commit.');
        $this->assertSame(1, $database->rollBackCount, 'Expected failed nested transactions to roll back.');
    }

    public function testMergeGamesRollsBackWhenPostMappingStepFails(): void
    {
        $database = new MergeGamesTransactionPDO();
        $service = new TrophyMergeService($database);

        try {
            $service->mergeGames(10, 20, 'order');
            $this->fail('Expected mergeGames to fail when marking the child game as merged.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Failed while marking child game as merged.', $exception->getMessage());
        }

        $this->assertSame(1, $database->beginCount, 'Expected mergeGames to open one database transaction.');
        $this->assertSame(0, $database->commitCount, 'Expected mergeGames to avoid commit when a later step fails.');
        $this->assertSame(1, $database->rollBackCount, 'Expected mergeGames to roll back on failure.');
        $this->assertTrue($database->mappingInserted, 'Expected mapping step to run before the failure.');
    }

    public function testExecuteTransactionRollsBackWhenCommitFails(): void
    {
        $database = new TransactionDepthTrackingPDO(failOnCommit: true);
        $service = new TrophyMergeService($database);
        $executeTransaction = new ReflectionMethod(TrophyMergeService::class, 'executeTransaction');
        $executeTransaction->setAccessible(true);

        try {
            $executeTransaction->invoke($service, static function (): void {
            });
            $this->fail('Expected commit failure to bubble up.');
        } catch (PDOException $exception) {
            $this->assertSame('Commit failed.', $exception->getMessage());
        }

        $this->assertSame(1, $database->beginCount, 'Expected one database transaction to start.');
        $this->assertSame(0, $database->commitCount, 'Expected failed commit to avoid counting as success.');
        $this->assertSame(1, $database->rollBackCount, 'Expected failed commit to roll back open transaction.');
        $this->assertFalse($database->inTransaction(), 'Expected rollback to clear transaction state.');
    }
}

final class TransactionDepthTrackingPDO extends PDO
{
    public int $beginCount = 0;
    public int $commitCount = 0;
    public int $rollBackCount = 0;
    private bool $inTransaction = false;

    public function __construct(private bool $failOnCommit = false)
    {
    }

    public function beginTransaction(): bool
    {
        $this->beginCount++;
        $this->inTransaction = true;

        return true;
    }

    public function commit(): bool
    {
        if ($this->failOnCommit) {
            throw new PDOException('Commit failed.');
        }

        $this->commitCount++;
        $this->inTransaction = false;

        return true;
    }

    public function rollBack(): bool
    {
        $this->rollBackCount++;
        $this->inTransaction = false;

        return true;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function prepare(string $statement, array $options = []): PDOStatement
    {
        throw new RuntimeException('Unexpected SQL in transaction depth test: ' . $statement);
    }
}

final class MergeGamesTransactionPDO extends PDO
{
    public int $beginCount = 0;
    public int $commitCount = 0;
    public int $rollBackCount = 0;
    public bool $mappingInserted = false;
    private bool $inTransaction = false;

    public function __construct()
    {
    }

    public function beginTransaction(): bool
    {
        $this->beginCount++;
        $this->inTransaction = true;

        return true;
    }

    public function commit(): bool
    {
        $this->commitCount++;
        $this->inTransaction = false;

        return true;
    }

    public function rollBack(): bool
    {
        $this->rollBackCount++;
        $this->inTransaction = false;

        return true;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function prepare(string $statement, array $options = []): PDOStatement
    {
        $normalized = preg_replace('/\s+/', ' ', trim($statement)) ?? '';

        if (
            str_contains($normalized, 'SELECT np_communication_id')
            && str_contains($normalized, 'FROM trophy_title')
            && str_contains($normalized, 'WHERE id =')
        ) {
            return new MergeGamesNpCommunicationIdStatement();
        }

        if (str_contains($normalized, 'INSERT IGNORE into trophy_merge') && str_contains($normalized, 'USING (group_id, order_id)')) {
            return new MergeGamesMappingStatement($this);
        }

        if (str_starts_with($normalized, 'UPDATE trophy_title_meta SET status = 2 WHERE np_communication_id = :np_communication_id')) {
            throw new RuntimeException('Failed while marking child game as merged.');
        }

        throw new RuntimeException('Unexpected SQL in mergeGames transaction test: ' . $statement);
    }

    public function recordMappingInsert(): void
    {
        $this->mappingInserted = true;
    }
}

final class MergeGamesNpCommunicationIdStatement extends PDOStatement
{
    private ?int $gameId = null;

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        if ((string) $param === ':game_id' || (string) $param === ':id') {
            $this->gameId = (int) $value;
        }

        return true;
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetchColumn(int $column = 0): string
    {
        return $this->gameId === 20 ? 'MERGE_000020' : 'NP_CHILD';
    }
}

final class MergeGamesMappingStatement extends PDOStatement
{
    public function __construct(private readonly MergeGamesTransactionPDO $database)
    {
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        return true;
    }

    public function execute(?array $params = null): bool
    {
        $this->database->recordMappingInsert();

        return true;
    }
}
