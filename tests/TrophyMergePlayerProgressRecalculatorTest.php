<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyMergePlayerProgressRecalculator.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyMergePlayerProgressUpdater.php';

final class TrophyMergePlayerProgressRecalculatorTest extends TestCase
{
    public function testRecalculateGroupPlayerRejectsEmptyChildren(): void
    {
        $recalculator = new TrophyMergePlayerProgressRecalculator(new RecordingMergeProgressPDO());

        try {
            $recalculator->recalculateGroupPlayer('MERGE_000010', []);
            $this->fail('Expected RuntimeException when child list is empty.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unable to locate child trophy titles.', $exception->getMessage());
        }
    }

    public function testRecalculateTitlePlayerRejectsEmptyChildren(): void
    {
        $recalculator = new TrophyMergePlayerProgressRecalculator(new RecordingMergeProgressPDO());

        try {
            $recalculator->recalculateTitlePlayer('MERGE_000010', []);
            $this->fail('Expected RuntimeException when child list is empty.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unable to locate child trophy titles.', $exception->getMessage());
        }
    }

    public function testRecalculateGroupPlayerBuildsProgressAndZeroInsertSql(): void
    {
        $database = new RecordingMergeProgressPDO(
            groupRows: [['parent_group_id' => 'default']],
        );
        $recalculator = new TrophyMergePlayerProgressRecalculator($database);

        $recalculator->recalculateGroupPlayer('MERGE_000010', ['NP_CHILD_1', 'NP_CHILD_2']);

        $this->assertSame('MERGE_000010', $database->groupQueryParameters[':parent_np_communication_id'] ?? null);
        $this->assertTrue(
            str_contains($database->groupPlayerMergeSql ?? '', 'tg.max_score = 0'),
            'Expected group progress SQL to branch when a group has no obtainable points.'
        );
        $this->assertTrue(
            str_contains($database->groupPlayerMergeSql ?? '', '100,'),
            'Expected group progress SQL to set 100% when a group has no obtainable points.'
        );
        $this->assertTrue(
            str_contains($database->zeroProgressSql ?? '', 'IN (:child_np_0, :child_np_1)'),
            'Expected zero-progress insert to use all child placeholders.'
        );
        $this->assertSame(
            ['NP_CHILD_1', 'NP_CHILD_2'],
            $database->zeroProgressChildIds,
            'Expected zero-progress insert to bind all child np communication ids.'
        );
        $this->assertSame('MERGE_000010', $database->zeroProgressParameters[':np_communication_id'] ?? null);
    }

    public function testRecalculateTitlePlayerSetsFullProgressWhenMaxScoreIsZero(): void
    {
        $database = new RecordingMergeProgressPDO(
            trophyTitle: ['platinum' => 0, 'max_score' => 0],
        );
        $recalculator = new TrophyMergePlayerProgressRecalculator($database);

        $recalculator->recalculateTitlePlayer('MERGE_000010', ['NP_CHILD_1', 'NP_CHILD_2']);

        $this->assertTrue(
            str_contains($database->titlePlayerMergeSql ?? '', 'WHEN :max_score = 0 THEN 100'),
            'Expected title progress SQL to set 100% when a title has no obtainable points.'
        );
        $this->assertTrue(
            str_contains($database->titlePlayerMergeSql ?? '', 'IN (:child_np_0, :child_np_1)'),
            'Expected title progress SQL to use all child placeholders.'
        );
        $this->assertTrue(
            str_contains($database->titleZeroProgressSql ?? '', 'IN (:child_np_0, :child_np_1)'),
            'Expected title zero-progress insert to use all child placeholders.'
        );
    }

    public function testUpdaterDelegatesGroupRecalculation(): void
    {
        $recalculator = new RecordingTrophyMergePlayerProgressRecalculator();
        $updater = new TrophyMergePlayerProgressUpdater(
            new RelationshipLookupPDO(
                childNpCommunicationId: 'NP_CHILD_2',
                parentNpCommunicationId: 'MERGE_000010',
                childNpCommunicationIds: ['NP_CHILD_1', 'NP_CHILD_2'],
            ),
            progressRecalculator: $recalculator,
        );

        $updater->updateTrophyGroupPlayer(42);

        $this->assertSame(
            [['MERGE_000010', ['NP_CHILD_1', 'NP_CHILD_2']]],
            $recalculator->groupCalls,
        );
        $this->assertSame([], $recalculator->titleCalls);
    }

    public function testUpdaterDelegatesTitleRecalculation(): void
    {
        $recalculator = new RecordingTrophyMergePlayerProgressRecalculator();
        $updater = new TrophyMergePlayerProgressUpdater(
            new RelationshipLookupPDO(
                childNpCommunicationId: 'NP_CHILD_2',
                parentNpCommunicationId: 'MERGE_000010',
                childNpCommunicationIds: ['NP_CHILD_1', 'NP_CHILD_2'],
            ),
            progressRecalculator: $recalculator,
        );

        $updater->updateTrophyTitlePlayer(42);

        $this->assertSame([], $recalculator->groupCalls);
        $this->assertSame(
            [['MERGE_000010', ['NP_CHILD_1', 'NP_CHILD_2']]],
            $recalculator->titleCalls,
        );
    }

    public function testRecomputeByParentDelegatesBothRecalculations(): void
    {
        $recalculator = new RecordingTrophyMergePlayerProgressRecalculator();
        $updater = new TrophyMergePlayerProgressUpdater(
            new RelationshipLookupPDO(
                childNpCommunicationId: 'NP_CHILD_2',
                parentNpCommunicationId: 'MERGE_000010',
                childNpCommunicationIds: ['NP_CHILD_1', 'NP_CHILD_2'],
            ),
            progressRecalculator: $recalculator,
        );

        $updater->recomputeByParent('MERGE_000010');

        $this->assertSame(
            [['MERGE_000010', ['NP_CHILD_1', 'NP_CHILD_2']]],
            $recalculator->groupCalls,
        );
        $this->assertSame(
            [['MERGE_000010', ['NP_CHILD_1', 'NP_CHILD_2']]],
            $recalculator->titleCalls,
        );
    }
}

final class RecordingTrophyMergePlayerProgressRecalculator extends TrophyMergePlayerProgressRecalculator
{
    /** @var list<array{0:string,1:list<string>}> */
    public array $groupCalls = [];

    /** @var list<array{0:string,1:list<string>}> */
    public array $titleCalls = [];

    public function __construct()
    {
        // Parent requires a PDO, but this stub never uses it.
        parent::__construct(new RelationshipLookupPDO('unused', 'unused', []));
    }

    public function recalculateGroupPlayer(string $parentNpCommunicationId, array $childNpCommunicationIds): void
    {
        $this->groupCalls[] = [$parentNpCommunicationId, $childNpCommunicationIds];
    }

    public function recalculateTitlePlayer(string $parentNpCommunicationId, array $childNpCommunicationIds): void
    {
        $this->titleCalls[] = [$parentNpCommunicationId, $childNpCommunicationIds];
    }
}

final class RelationshipLookupPDO extends PDO
{
    /**
     * @param list<string> $childNpCommunicationIds
     */
    public function __construct(
        private string $childNpCommunicationId,
        private string $parentNpCommunicationId,
        private array $childNpCommunicationIds,
    ) {
    }

    public function prepare(string $statement, array $options = []): PDOStatement
    {
        $normalized = preg_replace('/\s+/', ' ', trim($statement)) ?? '';

        if (str_starts_with($normalized, 'SELECT np_communication_id FROM trophy_title')) {
            return new RelationshipLookupScalarStatement($this->childNpCommunicationId);
        }

        if (str_starts_with($normalized, 'SELECT DISTINCT parent_np_communication_id FROM trophy_merge')) {
            return new RelationshipLookupColumnStatement([$this->parentNpCommunicationId]);
        }

        if (str_starts_with($normalized, 'SELECT DISTINCT child_np_communication_id FROM trophy_merge')) {
            return new RelationshipLookupColumnStatement($this->childNpCommunicationIds);
        }

        throw new RuntimeException('Unexpected SQL statement: ' . $statement);
    }
}

final class RelationshipLookupScalarStatement extends PDOStatement
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

final class RelationshipLookupColumnStatement extends PDOStatement
{
    /** @param list<string> $rows */
    public function __construct(private array $rows)
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
        return $this->rows;
    }
}

final class RecordingMergeProgressPDO extends PDO
{
    /** @var list<string> */
    public array $zeroProgressChildIds = [];

    /** @var array<string, scalar|null> */
    public array $groupQueryParameters = [];

    /** @var array<string, scalar|null> */
    public array $zeroProgressParameters = [];

    public ?string $zeroProgressSql = null;

    public ?string $groupPlayerMergeSql = null;

    public ?string $titlePlayerMergeSql = null;

    public ?string $titleZeroProgressSql = null;

    /**
     * @param list<array{parent_group_id:string}> $groupRows
     * @param array{platinum:int|string,max_score:int|string}|null $trophyTitle
     */
    public function __construct(
        private array $groupRows = [],
        private ?array $trophyTitle = null,
    ) {
    }

    public function prepare(string $statement, array $options = []): PDOStatement
    {
        $trimmed = trim($statement);
        $normalized = preg_replace('/\s+/', ' ', $trimmed) ?? '';

        if (str_starts_with($normalized, 'SELECT DISTINCT parent_group_id FROM trophy_merge')) {
            return new RecordingMergeGroupStatement($this, $this->groupRows);
        }

        if (str_starts_with($normalized, 'INSERT INTO trophy_group_player')) {
            $this->groupPlayerMergeSql = $trimmed;

            return new RecordingMergeExecuteStatement();
        }

        if (str_starts_with($normalized, 'INSERT IGNORE INTO trophy_group_player')) {
            $this->zeroProgressSql = $trimmed;

            return new RecordingMergeZeroProgressStatement($this);
        }

        if (str_starts_with($normalized, 'SELECT platinum, bronze * 15 + silver * 30 + gold * 90 AS max_score FROM trophy_title')) {
            return new RecordingMergeTitleStatement($this->trophyTitle ?? ['platinum' => 0, 'max_score' => 0]);
        }

        if (str_starts_with($normalized, 'INSERT INTO trophy_title_player')) {
            $this->titlePlayerMergeSql = $trimmed;

            return new RecordingMergeExecuteStatement();
        }

        if (str_starts_with($normalized, 'INSERT IGNORE INTO trophy_title_player')) {
            $this->titleZeroProgressSql = $trimmed;

            return new RecordingMergeExecuteStatement();
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

final class RecordingMergeGroupStatement extends PDOStatement
{
    /** @var list<array{parent_group_id:string}> */
    private array $rows;

    /** @var array<string, scalar|null> */
    private array $parameters = [];

    /** @param list<array{parent_group_id:string}> $rows */
    public function __construct(private RecordingMergeProgressPDO $pdo, array $rows)
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

final class RecordingMergeTitleStatement extends PDOStatement
{
    /** @param array{platinum:int|string,max_score:int|string} $row */
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
        return $this->row;
    }
}

final class RecordingMergeExecuteStatement extends PDOStatement
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

final class RecordingMergeZeroProgressStatement extends PDOStatement
{
    /** @var array<string, scalar|null> */
    private array $parameters = [];

    public function __construct(private RecordingMergeProgressPDO $pdo)
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
