<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerRankingUpdater.php';
require_once __DIR__ . '/../wwwroot/classes/Psn100Logger.php';

final class PlayerRankingUpdaterTest extends TestCase
{
    public function testUsesDropTableIfExistsForOrphanCleanup(): void
    {
        $source = file_get_contents(__DIR__ . '/../wwwroot/classes/Cron/PlayerRankingUpdater.php');
        $this->assertTrue(is_string($source));
        $this->assertStringContainsString("DROP TABLE IF EXISTS %s", $source);
        $this->assertStringContainsString("PREVIOUS_TABLE = 'player_ranking_old'", $source);
    }

    public function testUsesMysqlAdvisoryLock(): void
    {
        $source = file_get_contents(__DIR__ . '/../wwwroot/classes/Cron/PlayerRankingUpdater.php');
        $this->assertTrue(is_string($source));
        $this->assertStringContainsString('SELECT GET_LOCK(:lock_name, 0)', $source);
        $this->assertStringContainsString('SELECT RELEASE_LOCK(:lock_name)', $source);
        $this->assertStringContainsString('psn100:player_ranking_recalc', $source);
    }

    public function testRecalculateUsesSeparateBuildSwapAndCleanupPhases(): void
    {
        $source = file_get_contents(__DIR__ . '/../wwwroot/classes/Cron/PlayerRankingUpdater.php');
        $this->assertTrue(is_string($source));
        $this->assertStringContainsString("'build'", $source);
        $this->assertStringContainsString("'swap'", $source);
        $this->assertStringContainsString("'cleanup'", $source);
        $this->assertStringContainsString('cleanupOrphanedTables();', $source);
    }

    public function testExecuteWithRetryHasNoMaxAttempts(): void
    {
        $source = file_get_contents(__DIR__ . '/../wwwroot/classes/Cron/PlayerRankingUpdater.php');
        $this->assertTrue(is_string($source));
        $this->assertStringContainsString('while (true)', $source);
        $this->assertFalse(str_contains($source, 'maxAttempt'));
    }

    public function testCalculateRetryDelayUsesExponentialBackoffWithCap(): void
    {
        $updater = new PlayerRankingUpdater(
            new PDO('sqlite::memory:'),
            retryDelaySeconds: 3,
            maxRetryDelaySeconds: 60,
        );
        $method = new ReflectionMethod($updater, 'calculateRetryDelay');
        $method->setAccessible(true);

        $this->assertSame(3, $method->invoke($updater, 1));
        $this->assertSame(6, $method->invoke($updater, 2));
        $this->assertSame(12, $method->invoke($updater, 3));
        $this->assertSame(60, $method->invoke($updater, 10));
    }

    public function testRecalculateRetriesUntilBuildPhaseSucceeds(): void
    {
        $database = new PlayerRankingUpdaterRetryTestDatabase();
        $logDatabase = new PDO('sqlite::memory:');
        $logDatabase->exec('CREATE TABLE log (id INTEGER PRIMARY KEY AUTOINCREMENT, message TEXT NOT NULL)');
        $logger = new Psn100Logger($logDatabase);
        $sleepCalls = [];

        $updater = new PlayerRankingUpdater(
            $database,
            retryDelaySeconds: 1,
            maxRetryDelaySeconds: 1,
            logger: $logger,
            sleeper: static function (int $seconds) use (&$sleepCalls): void {
                $sleepCalls[] = $seconds;
            },
        );

        $updater->recalculate();

        $this->assertSame([1, 1], $sleepCalls);

        $query = $logDatabase->query('SELECT message FROM log ORDER BY id');
        $this->assertTrue($query instanceof PDOStatement);
        $messages = $query->fetchAll(PDO::FETCH_COLUMN);
        $this->assertCount(2, $messages);
        $this->assertStringContainsString('build failed (attempt 1)', $messages[0]);
        $this->assertStringContainsString('build failed (attempt 2)', $messages[1]);
    }

    public function testAcquireLockReturnsFalseWhenMysqlLockIsUnavailable(): void
    {
        $database = new PlayerRankingUpdaterLockTestDatabase(0);
        $updater = new PlayerRankingUpdater($database);
        $method = new ReflectionMethod($updater, 'acquireLock');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($updater));
    }

    public function testAcquireLockReturnsTrueWhenMysqlLockIsAvailable(): void
    {
        $database = new PlayerRankingUpdaterLockTestDatabase(1);
        $updater = new PlayerRankingUpdater($database);
        $method = new ReflectionMethod($updater, 'acquireLock');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($updater));
    }

    public function testAcquireLockThrowsWhenGetLockReturnsNull(): void
    {
        $database = new PlayerRankingUpdaterLockTestDatabase(null);
        $updater = new PlayerRankingUpdater($database);
        $method = new ReflectionMethod($updater, 'acquireLock');
        $method->setAccessible(true);

        try {
            $method->invoke($updater);
            $this->fail('Expected acquireLock to throw when GET_LOCK returns NULL.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Unable to acquire player ranking recalculation lock', $exception->getMessage());
        }
    }

    public function testRecalculateRetriesLockAcquisitionWhenGetLockReturnsNull(): void
    {
        $database = new PlayerRankingUpdaterLockTestDatabase(1, 2);
        $logDatabase = new PDO('sqlite::memory:');
        $logDatabase->exec('CREATE TABLE log (id INTEGER PRIMARY KEY AUTOINCREMENT, message TEXT NOT NULL)');
        $logger = new Psn100Logger($logDatabase);
        $sleepCalls = [];

        $updater = new PlayerRankingUpdater(
            $database,
            retryDelaySeconds: 1,
            maxRetryDelaySeconds: 1,
            logger: $logger,
            sleeper: static function (int $seconds) use (&$sleepCalls): void {
                $sleepCalls[] = $seconds;
            },
        );

        $updater->recalculate();

        $this->assertSame([1, 1], $sleepCalls);

        $messages = $logDatabase->query('SELECT message FROM log ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertCount(2, $messages);
        $this->assertStringContainsString('lock acquisition failed (attempt 1)', $messages[0]);
        $this->assertStringContainsString('lock acquisition failed (attempt 2)', $messages[1]);
    }

    public function testRecalculateContinuesRetryingWhenDatabaseLoggingFails(): void
    {
        $database = new PlayerRankingUpdaterRetryTestDatabase();
        $logDatabase = new PlayerRankingUpdaterFailingLogDatabase();
        $logger = new Psn100Logger($logDatabase);
        $sleepCalls = [];

        $updater = new PlayerRankingUpdater(
            $database,
            retryDelaySeconds: 1,
            maxRetryDelaySeconds: 1,
            logger: $logger,
            sleeper: static function (int $seconds) use (&$sleepCalls): void {
                $sleepCalls[] = $seconds;
            },
        );

        $updater->recalculate();

        $this->assertSame([1, 1], $sleepCalls);
    }

    public function testRecalculateSkipsWhenLockIsAlreadyHeld(): void
    {
        $database = new PlayerRankingUpdaterLockTestDatabase(0);
        $logDatabase = new PDO('sqlite::memory:');
        $logDatabase->exec('CREATE TABLE log (id INTEGER PRIMARY KEY AUTOINCREMENT, message TEXT NOT NULL)');
        $logger = new Psn100Logger($logDatabase);

        $updater = new PlayerRankingUpdater(
            $database,
            logger: $logger,
            sleeper: static function (int $seconds): void {
                throw new RuntimeException('Should not sleep when lock is unavailable.');
            },
        );

        $updater->recalculate();

        $query = $logDatabase->query('SELECT message FROM log ORDER BY id');
        $this->assertTrue($query instanceof PDOStatement);
        $messages = $query->fetchAll(PDO::FETCH_COLUMN);
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('skipped because another run is in progress', $messages[0]);
    }
}

final class PlayerRankingUpdaterFailingLogDatabase extends PDO
{
    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        throw new RuntimeException('Simulated log insert failure.');
    }
}

final class PlayerRankingUpdaterRetryTestDatabase extends PDO
{
    private int $populateAttempts = 0;

    public function __construct()
    {
    }

    public function getAttribute($attribute): mixed
    {
        if ($attribute === PDO::ATTR_DRIVER_NAME) {
            return 'sqlite';
        }

        return parent::getAttribute($attribute);
    }

    public function exec(string $statement): int|false
    {
        if (str_contains($statement, 'INSERT INTO player_ranking_new')) {
            $this->populateAttempts++;

            if ($this->populateAttempts < 3) {
                throw new RuntimeException('Simulated populate failure.');
            }
        }

        return 0;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        throw new RuntimeException('Unexpected prepare call: ' . $query);
    }
}

final class PlayerRankingUpdaterLockTestDatabase extends PDO
{
    private int $lockAttempts = 0;

    public function __construct(
        private readonly mixed $lockResult,
        private readonly ?int $failuresBeforeSuccess = null,
    ) {
    }

    public function getAttribute($attribute): mixed
    {
        if ($attribute === PDO::ATTR_DRIVER_NAME) {
            return 'mysql';
        }

        return parent::getAttribute($attribute);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'GET_LOCK')) {
            return new PlayerRankingUpdaterLockTestStatement($this->resolveLockResult());
        }

        if (str_contains($query, 'RELEASE_LOCK')) {
            return new PlayerRankingUpdaterLockTestStatement(1);
        }

        throw new RuntimeException('Unexpected prepare call: ' . $query);
    }

    private function resolveLockResult(): mixed
    {
        $this->lockAttempts++;

        if (
            $this->failuresBeforeSuccess !== null
            && $this->lockAttempts <= $this->failuresBeforeSuccess
        ) {
            return null;
        }

        return $this->lockResult;
    }

    public function exec(string $statement): int|false
    {
        if (
            str_contains($statement, 'DROP TABLE IF EXISTS')
            || str_contains($statement, 'CREATE TABLE IF NOT EXISTS')
            || str_contains($statement, 'TRUNCATE TABLE')
            || str_contains($statement, 'INSERT INTO player_ranking_new')
            || str_contains($statement, 'RENAME TABLE')
        ) {
            return 0;
        }

        throw new RuntimeException('Unexpected exec call: ' . $statement);
    }
}

final class PlayerRankingUpdaterLockTestStatement extends PDOStatement
{
    public function __construct(private readonly mixed $lockResult)
    {
    }

    public function bindValue($param, $value, $type = PDO::PARAM_STR): bool
    {
        return true;
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->lockResult;
    }
}
