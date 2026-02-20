<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/TrophyMergeService.php';

final class TrophyMergeServiceTitlePlayerMergeTest extends TestCase
{
    public function testUpdateTrophyTitlePlayerSetsFullProgressWhenMaxScoreIsZero(): void
    {
        $pdo = new RecordingTitlePlayerPDO(
            'NP_CHILD_2',
            'MERGE_000010',
            ['NP_CHILD_1', 'NP_CHILD_2'],
            ['platinum' => 0, 'max_score' => 0]
        );
        $service = new TrophyMergeService($pdo);

        $reflection = new ReflectionMethod(TrophyMergeService::class, 'updateTrophyTitlePlayer');
        $reflection->setAccessible(true);
        $reflection->invoke($service, 42);

        $this->assertTrue(
            str_contains($pdo->titlePlayerMergeSql ?? '', 'WHEN :max_score = 0 THEN 100'),
            'Expected title progress SQL to set 100% when a title has no obtainable points.'
        );
    }
}

final class RecordingTitlePlayerPDO extends PDO
{
    public ?string $titlePlayerMergeSql = null;

    public function __construct(
        private string $childNpCommunicationId,
        private string $parentNpCommunicationId,
        private array $childNpCommunicationIds,
        private array $trophyTitle
    ) {
    }

    public function prepare(string $statement, array $options = []): PDOStatement
    {
        $trimmed = trim((string) $statement);
        $normalized = preg_replace('/\s+/', ' ', $trimmed) ?? '';

        if (str_starts_with($normalized, 'SELECT np_communication_id FROM trophy_title')) {
            return new RecordingTitlePlayerScalarStatement($this->childNpCommunicationId);
        }

        if (str_starts_with($normalized, 'SELECT DISTINCT parent_np_communication_id FROM trophy_merge')) {
            return new RecordingTitlePlayerAssocStatement(['parent_np_communication_id' => $this->parentNpCommunicationId]);
        }

        if (str_starts_with($normalized, 'SELECT DISTINCT child_np_communication_id FROM trophy_merge')) {
            return new RecordingTitlePlayerColumnStatement($this->childNpCommunicationIds);
        }

        if (str_starts_with($normalized, 'SELECT platinum, bronze * 15 + silver * 30 + gold * 90 AS max_score FROM trophy_title')) {
            return new RecordingTitlePlayerAssocStatement($this->trophyTitle);
        }

        if (str_starts_with($normalized, 'INSERT INTO trophy_title_player')) {
            $this->titlePlayerMergeSql = $trimmed;

            return new RecordingTitlePlayerExecuteStatement();
        }

        if (str_starts_with($normalized, 'INSERT IGNORE INTO trophy_title_player')) {
            return new RecordingTitlePlayerExecuteStatement();
        }

        throw new RuntimeException('Unexpected SQL statement: ' . $trimmed);
    }
}

final class RecordingTitlePlayerScalarStatement extends PDOStatement
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

final class RecordingTitlePlayerAssocStatement extends PDOStatement
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

final class RecordingTitlePlayerColumnStatement extends PDOStatement
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

final class RecordingTitlePlayerExecuteStatement extends PDOStatement
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
