<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/PlayerEarnedTrophyPersisterTest.php';
require_once __DIR__ . '/TrophyCalculatorTest.php';
require_once __DIR__ . '/AutomaticTrophyTitleMergeServiceTest.php';
require_once __DIR__ . '/../wwwroot/classes/Psn100Logger.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanTrophyProgressSynchronizer.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerEarnedTrophyPersister.php';
require_once __DIR__ . '/../wwwroot/classes/AutomaticTrophyTitleMergeService.php';

use Tustin\Haste\Exception\NotFoundHttpException;

final class PlayerScanTrophyProgressSynchronizerTest extends TestCase
{
    public function testSynchronizeTrophyProgressPersistsEarnedTrophiesAndRecalculatesGroups(): void
    {
        $mergeDatabase = new PlayerScanTrophyProgressSynchronizerMergePDO([]);
        $earnedDatabase = new PlayerEarnedTrophyPersisterRecordingPDO(null);
        $calculatorDatabase = new PlayerScanTrophyProgressSynchronizerCalculatorPDO();
        $calculatorDatabase->setTrophyGroup('NPWR12345_00', 'default');
        $calculatorDatabase->addTrophy('NPWR12345_00', 'default', 1, 'bronze');

        $loggerDatabase = new PDO('sqlite::memory:');
        $loggerDatabase->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $loggerDatabase->exec('CREATE TABLE log (message TEXT NOT NULL)');

        $mergeService = new RecordingTrophyMergeService($mergeDatabase);
        $synchronizer = new PlayerScanTrophyProgressSynchronizer(
            $mergeDatabase,
            new TrophyCalculator($calculatorDatabase),
            new Psn100Logger($loggerDatabase),
            new PlayerEarnedTrophyPersister($earnedDatabase),
            new AutomaticTrophyTitleMergeService($mergeDatabase, $mergeService),
        );

        $user = new PlayerScanTrophyProgressSynchronizerTestUser(42);
        $trophyTitle = new PlayerScanTrophyProgressSynchronizerTestTrophyTitle(
            'NPWR12345_00',
            'Example Game',
            '2024-06-15T10:30:00Z',
            [
                new PlayerScanTrophyProgressSynchronizerTestTrophyGroup(
                    'default',
                    [
                        new PlayerScanTrophyProgressSynchronizerTestTrophy(1, true, '', '2024-06-15T10:30:00Z'),
                        new PlayerScanTrophyProgressSynchronizerTestTrophy(2, false, '0', ''),
                    ],
                ),
            ],
        );

        $synchronizer->synchronizeTrophyProgress($user, $trophyTitle, 'NPWR12345_00', false, []);

        $this->assertSame(1, $earnedDatabase->upsertExecutions, 'Only earned trophies should be persisted.');
        $this->assertSame('42', $earnedDatabase->upsertParameters[0][':account_id']);
        $this->assertTrue(
            $calculatorDatabase->getTrophyGroupPlayer('NPWR12345_00', 'default', 42) !== null,
            'Group progress should be recalculated.'
        );
        $this->assertSame([], $mergeService->recomputedParents);
    }

    public function testSynchronizeTrophyProgressRecalculatesMergeParentsFromDatabaseAndCatalog(): void
    {
        $mergeDatabase = new PlayerScanTrophyProgressSynchronizerMergePDO([
            [
                'parent_np_communication_id' => 'NPWR99999_00',
                'parent_group_id' => 'default',
            ],
        ]);
        $earnedDatabase = new PlayerEarnedTrophyPersisterRecordingPDO(null);
        $calculatorDatabase = new PlayerScanTrophyProgressSynchronizerCalculatorPDO();
        $calculatorDatabase->setTrophyGroup('NPWR12345_00', 'default');
        $calculatorDatabase->setTrophyGroup('NPWR99999_00', 'default');

        $loggerDatabase = new PDO('sqlite::memory:');
        $loggerDatabase->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $loggerDatabase->exec('CREATE TABLE log (message TEXT NOT NULL)');

        $mergeService = new RecordingTrophyMergeService($mergeDatabase);
        $synchronizer = new PlayerScanTrophyProgressSynchronizer(
            $mergeDatabase,
            new TrophyCalculator($calculatorDatabase),
            new Psn100Logger($loggerDatabase),
            new PlayerEarnedTrophyPersister($earnedDatabase),
            new AutomaticTrophyTitleMergeService($mergeDatabase, $mergeService),
        );

        $user = new PlayerScanTrophyProgressSynchronizerTestUser(7);
        $trophyTitle = new PlayerScanTrophyProgressSynchronizerTestTrophyTitle(
            'NPWR12345_00',
            'Example Game',
            '2024-06-15T10:30:00Z',
            [
                new PlayerScanTrophyProgressSynchronizerTestTrophyGroup('default', []),
            ],
        );

        $synchronizer->synchronizeTrophyProgress(
            $user,
            $trophyTitle,
            'NPWR12345_00',
            false,
            ['NPWR88888_00'],
        );

        $this->assertSame(
            ['NPWR88888_00'],
            $mergeService->recomputedParents,
            'Catalog merge parents should be recomputed via merge service.'
        );
        $this->assertTrue(
            $calculatorDatabase->getTrophyGroupPlayer('NPWR99999_00', 'default', 7) !== null,
            'Merge parent group progress should be recalculated.'
        );
    }

    public function testRetryNotFoundReturnsResultAfterTransientNotFound(): void
    {
        $synchronizer = $this->createSynchronizerForRetryTests();
        $retryMethod = new ReflectionMethod(PlayerScanTrophyProgressSynchronizer::class, 'retryNotFound');
        $retryMethod->setAccessible(true);

        $attempts = 0;
        $result = $retryMethod->invoke($synchronizer, function () use (&$attempts): string {
            $attempts++;

            if ($attempts < 2) {
                throw new NotFoundHttpException('temporary');
            }

            return 'ok';
        }, 'test operation');

        $this->assertSame('ok', $result);
        $this->assertSame(2, $attempts);
    }

    private function createSynchronizerForRetryTests(): PlayerScanTrophyProgressSynchronizer
    {
        $loggerDatabase = new PDO('sqlite::memory:');
        $loggerDatabase->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $loggerDatabase->exec('CREATE TABLE log (message TEXT NOT NULL)');

        return new PlayerScanTrophyProgressSynchronizer(
            new PlayerScanTrophyProgressSynchronizerMergePDO([]),
            new TrophyCalculator(new FakePDO()),
            new Psn100Logger($loggerDatabase),
            new PlayerEarnedTrophyPersister(new PlayerEarnedTrophyPersisterRecordingPDO(null)),
            new AutomaticTrophyTitleMergeService(
                new PlayerScanTrophyProgressSynchronizerMergePDO([]),
                new RecordingTrophyMergeService(new PlayerScanTrophyProgressSynchronizerMergePDO([])),
            ),
        );
    }
}

/**
 * @phpstan-type MergeParentRow array{
 *     parent_np_communication_id: string,
 *     parent_group_id: string
 * }
 */
final class PlayerScanTrophyProgressSynchronizerCalculatorPDO extends PDO
{
    private FakePDO $delegate;

    public function __construct()
    {
        $this->delegate = new FakePDO();
    }

    public function prepare(string $query, array $options = []): PDOStatement
    {
        $trimmed = trim($query);

        if (str_starts_with($trimmed, 'SELECT SUM(bronze)')) {
            return new PlayerScanTrophyProgressSynchronizerNoOpStatement([
                'bronze' => 0,
                'silver' => 0,
                'gold' => 0,
                'platinum' => 0,
            ]);
        }

        if (
            str_starts_with($trimmed, 'UPDATE trophy_title')
            || str_contains($trimmed, 'INSERT INTO trophy_title_player')
        ) {
            return new PlayerScanTrophyProgressSynchronizerNoOpStatement(null);
        }

        return $this->delegate->prepare($query, $options);
    }

    public function setTrophyGroup(string $npCommunicationId, string $groupId): void
    {
        $this->delegate->setTrophyGroup($npCommunicationId, $groupId);
    }

    public function addTrophy(string $npCommunicationId, string $groupId, int $orderId, string $type, int $status = 0): void
    {
        $this->delegate->addTrophy($npCommunicationId, $groupId, $orderId, $type, $status);
    }

    public function addEarnedTrophy(string $npCommunicationId, string $groupId, int $orderId, int $accountId, int $earned = 1): void
    {
        $this->delegate->addEarnedTrophy($npCommunicationId, $groupId, $orderId, $accountId, $earned);
    }

    /**
     * @return array{np_communication_id:string,group_id:string,account_id:int,bronze:int,silver:int,gold:int,platinum:int,progress:int}|null
     */
    public function getTrophyGroupPlayer(string $npCommunicationId, string $groupId, int $accountId): ?array
    {
        return $this->delegate->getTrophyGroupPlayer($npCommunicationId, $groupId, $accountId);
    }
}

final class PlayerScanTrophyProgressSynchronizerNoOpStatement extends PDOStatement
{
    /** @var array<string, int>|null */
    private ?array $row;

    /** @param array<string, int>|null $row */
    public function __construct(?array $row)
    {
        $this->row = $row;
    }

    public function bindValue(int|string $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        return true;
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if ($this->row === null) {
            return false;
        }

        $row = $this->row;
        $this->row = null;

        return $row;
    }
}

/**
 * @phpstan-type MergeParentRow array{
 *     parent_np_communication_id: string,
 *     parent_group_id: string
 * }
 */
final class PlayerScanTrophyProgressSynchronizerMergePDO extends PDO
{
    /** @var list<MergeParentRow> */
    private array $mergeRows;

    /** @param list<MergeParentRow> $mergeRows */
    public function __construct(array $mergeRows)
    {
        $this->mergeRows = $mergeRows;
    }

    public function prepare(string $statement, array $options = []): PDOStatement
    {
        if (str_contains($statement, 'FROM   trophy_merge')) {
            return new PlayerScanTrophyProgressSynchronizerMergeStatement($this->mergeRows);
        }

        throw new RuntimeException('Unexpected SQL statement: ' . $statement);
    }
}

final class PlayerScanTrophyProgressSynchronizerMergeStatement extends PDOStatement
{
    /** @var list<MergeParentRow> */
    private array $rows;

    private int $position = 0;

    /** @param list<MergeParentRow> $rows */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function bindValue(int|string $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        return true;
    }

    public function execute(?array $params = null): bool
    {
        $this->position = 0;

        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if (!isset($this->rows[$this->position])) {
            return false;
        }

        return $this->rows[$this->position++];
    }
}

final class PlayerScanTrophyProgressSynchronizerTestUser
{
    public function __construct(private readonly int $accountId)
    {
    }

    public function accountId(): int
    {
        return $this->accountId;
    }
}

final class PlayerScanTrophyProgressSynchronizerTestTrophyTitle
{
    /**
     * @param list<PlayerScanTrophyProgressSynchronizerTestTrophyGroup> $groups
     */
    public function __construct(
        private readonly string $npCommunicationId,
        private readonly string $name,
        private readonly string $lastUpdatedDateTime,
        private readonly array $groups,
    ) {
    }

    public function npCommunicationId(): string
    {
        return $this->npCommunicationId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function lastUpdatedDateTime(): string
    {
        return $this->lastUpdatedDateTime;
    }

    /** @return list<PlayerScanTrophyProgressSynchronizerTestTrophyGroup> */
    public function trophyGroups(): array
    {
        return $this->groups;
    }
}

final class PlayerScanTrophyProgressSynchronizerTestTrophyGroup
{
    /**
     * @param list<PlayerScanTrophyProgressSynchronizerTestTrophy> $trophies
     */
    public function __construct(
        private readonly string $id,
        private readonly array $trophies,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    /** @return list<PlayerScanTrophyProgressSynchronizerTestTrophy> */
    public function trophies(): array
    {
        return $this->trophies;
    }
}

final class PlayerScanTrophyProgressSynchronizerTestTrophy
{
    public function __construct(
        private readonly int $id,
        private readonly bool $earned,
        private readonly string $progress,
        private readonly string $earnedDateTime,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function earned(): bool
    {
        return $this->earned;
    }

    public function progress(): string
    {
        return $this->progress;
    }

    public function earnedDateTime(): string
    {
        return $this->earnedDateTime;
    }
}
