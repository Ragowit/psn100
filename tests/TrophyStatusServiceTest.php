<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/TrophyStatusService.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/TrophyStatusProgressRecalculator.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/TrophyStatusUpdateResult.php';

final class TrophyStatusServiceTest extends TestCase
{
    public function testUpdateTrophiesRejectsEmptyTrophyList(): void
    {
        $database = new RecordingTrophyStatusServicePDO();
        $service = new TrophyStatusService($database, new RecordingTrophyStatusProgressRecalculator());

        try {
            $service->updateTrophies([], 1);
            $this->fail('Expected InvalidArgumentException for an empty trophy list.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('No trophies were provided.', $exception->getMessage());
        }
    }

    public function testUpdateTrophiesDelegatesGroupAndTitleRecalculation(): void
    {
        $database = new RecordingTrophyStatusServicePDO();
        $recalculator = new RecordingTrophyStatusProgressRecalculator();
        $service = new TrophyStatusService($database, $recalculator);

        $result = $service->updateTrophies([10, 10], 1);

        $this->assertSame(['10 (Test Trophy)'], $result->getTrophyNames());
        $this->assertSame('unobtainable', $result->getStatusText());

        $this->assertSame(
            [
                [
                    'np_communication_id' => 'NPWR00001_00',
                    'group_id' => 'default',
                    'trophy_ids' => [10],
                ],
            ],
            $recalculator->groupCalls
        );
        $this->assertSame(
            [
                [
                    'np_communication_id' => 'NPWR00001_00',
                    'status' => 1,
                    'trophy_ids' => [10],
                ],
            ],
            $recalculator->titleCalls
        );
    }

    public function testUpdateTrophiesUsesObtainableStatusText(): void
    {
        $database = new RecordingTrophyStatusServicePDO();
        $recalculator = new RecordingTrophyStatusProgressRecalculator();
        $service = new TrophyStatusService($database, $recalculator);

        $result = $service->updateTrophies([10], 0);

        $this->assertSame('obtainable', $result->getStatusText());
        $this->assertSame(0, $recalculator->titleCalls[0]['status']);
    }
}

final class RecordingTrophyStatusProgressRecalculator extends TrophyStatusProgressRecalculator
{
    /** @var list<array{np_communication_id: string, group_id: string, trophy_ids: list<int>}> */
    public array $groupCalls = [];

    /** @var list<array{np_communication_id: string, status: int, trophy_ids: list<int>}> */
    public array $titleCalls = [];

    public function __construct()
    {
        // Parent requires a PDO, but this stub never uses it.
        parent::__construct(new RecordingTrophyStatusServicePDO());
    }

    public function recalculateGroup(string $npCommunicationId, string $groupId, array $affectedTrophyIds): void
    {
        $this->groupCalls[] = [
            'np_communication_id' => $npCommunicationId,
            'group_id' => $groupId,
            'trophy_ids' => $affectedTrophyIds,
        ];
    }

    public function recalculateTitle(string $npCommunicationId, int $status, array $affectedTrophyIds): void
    {
        $this->titleCalls[] = [
            'np_communication_id' => $npCommunicationId,
            'status' => $status,
            'trophy_ids' => $affectedTrophyIds,
        ];
    }
}

final class RecordingTrophyStatusServicePDO extends PDO
{
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
            return new RecordingTrophyStatusServiceExecuteOnlyStatement();
        }

        if (str_starts_with($trimmedQuery, 'SELECT np_communication_id, group_id, name FROM trophy WHERE id = :trophy_id')) {
            return new RecordingTrophyStatusServiceFetchAssocStatement([
                'np_communication_id' => 'NPWR00001_00',
                'group_id' => 'default',
                'name' => 'Test Trophy',
            ]);
        }

        return new RecordingTrophyStatusServiceExecuteOnlyStatement();
    }
}

final class RecordingTrophyStatusServiceExecuteOnlyStatement extends PDOStatement
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

final class RecordingTrophyStatusServiceFetchAssocStatement extends PDOStatement
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
