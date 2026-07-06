<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerEarnedTrophyPersister.php';

final class PlayerEarnedTrophyPersisterTest extends TestCase
{
    public function testPersistEarnedTrophyUpsertsChildWithoutMergeParent(): void
    {
        $pdo = new PlayerEarnedTrophyPersisterRecordingPDO(null);
        $persister = new PlayerEarnedTrophyPersister($pdo);

        $persister->persistEarnedTrophy(
            'NPWR12345_00',
            'default',
            3,
            42,
            true,
            '',
            '2024-06-15T10:30:00Z',
        );

        $this->assertSame(1, $pdo->upsertExecutions, 'Expected a single child trophy upsert.');
        $this->assertSame(1, $pdo->mergeLookupExecutions, 'Expected a merge-parent lookup.');

        $this->assertTrue(
            str_contains($pdo->upsertSql[0], 'IF(trophy_earned.earned = 0, new.earned_date, trophy_earned.earned_date)'),
            'Child upsert should use child-specific earned_date merge rule.'
        );

        $this->assertSame([
            ':np_communication_id' => 'NPWR12345_00',
            ':group_id' => 'default',
            ':order_id' => 3,
            ':account_id' => 42,
            ':earned_date' => '2024-06-15 10:30:00',
            ':progress' => null,
            ':earned' => true,
        ], $pdo->upsertParameters[0], 'Unexpected child upsert parameters.');
    }

    public function testPersistEarnedTrophyPropagatesToMergeParent(): void
    {
        $pdo = new PlayerEarnedTrophyPersisterRecordingPDO([
            'parent_np_communication_id' => 'NPWR99999_00',
            'parent_group_id' => 'default',
            'parent_order_id' => 7,
        ]);
        $persister = new PlayerEarnedTrophyPersister($pdo);

        $persister->persistEarnedTrophy(
            'NPWR12345_00',
            'default',
            3,
            42,
            false,
            '55',
            '',
        );

        $this->assertSame(2, $pdo->upsertExecutions, 'Expected child and parent trophy upserts.');
        $this->assertTrue(
            str_contains($pdo->upsertSql[1], 'IF(trophy_earned.earned = 1, trophy_earned.earned, new.earned)'),
            'Parent upsert should use parent-specific earned merge rule.'
        );

        $this->assertSame([
            ':np_communication_id' => 'NPWR99999_00',
            ':group_id' => 'default',
            ':order_id' => 7,
            ':account_id' => 42,
            ':earned_date' => null,
            ':progress' => 55,
            ':earned' => false,
        ], $pdo->upsertParameters[1], 'Unexpected parent upsert parameters.');
    }
}

/**
 * @phpstan-type MergeParentRow array{
 *     parent_np_communication_id: string,
 *     parent_group_id: string,
 *     parent_order_id: int|string
 * }
 */
final class PlayerEarnedTrophyPersisterRecordingPDO extends PDO
{
    /** @var list<string> */
    public array $upsertSql = [];

    /** @var list<array<string, scalar|null|bool>> */
    public array $upsertParameters = [];

    public int $upsertExecutions = 0;

    public int $mergeLookupExecutions = 0;

    /** @var MergeParentRow|null */
    private ?array $mergeParent;

    /**
     * @param MergeParentRow|null $mergeParent
     */
    public function __construct(?array $mergeParent)
    {
        $this->mergeParent = $mergeParent;
    }

    public function prepare(string $statement, array $options = []): PDOStatement
    {
        $trimmed = trim($statement);

        if (str_contains($trimmed, 'INSERT INTO trophy_earned')) {
            return new PlayerEarnedTrophyPersisterRecordingUpsertStatement($this, $trimmed);
        }

        if (str_contains($trimmed, 'FROM   trophy_merge')) {
            return new PlayerEarnedTrophyPersisterRecordingMergeLookupStatement($this, $this->mergeParent);
        }

        throw new RuntimeException('Unexpected SQL statement: ' . $trimmed);
    }

    /** @param array<string, scalar|null|bool> $parameters */
    public function recordUpsert(string $sql, array $parameters): void
    {
        $this->upsertSql[] = $sql;
        $this->upsertParameters[] = $parameters;
        $this->upsertExecutions++;
    }

    public function recordMergeLookup(): void
    {
        $this->mergeLookupExecutions++;
    }
}

final class PlayerEarnedTrophyPersisterRecordingUpsertStatement extends PDOStatement
{
    /** @var array<string, scalar|null|bool> */
    private array $parameters = [];

    public function __construct(
        private readonly PlayerEarnedTrophyPersisterRecordingPDO $pdo,
        private readonly string $sql,
    ) {
    }

    public function bindValue(int|string $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->parameters[(string) $param] = $value;

        return true;
    }

    public function execute(?array $params = null): bool
    {
        $this->pdo->recordUpsert($this->sql, $this->parameters);

        return true;
    }
}

final class PlayerEarnedTrophyPersisterRecordingMergeLookupStatement extends PDOStatement
{
    /** @var MergeParentRow|null */
    private ?array $mergeParent;

    /**
     * @param MergeParentRow|null $mergeParent
     */
    public function __construct(
        private readonly PlayerEarnedTrophyPersisterRecordingPDO $pdo,
        ?array $mergeParent,
    ) {
        $this->mergeParent = $mergeParent;
    }

    public function bindValue(int|string $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        return true;
    }

    public function execute(?array $params = null): bool
    {
        $this->pdo->recordMergeLookup();

        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->mergeParent ?? false;
    }
}
