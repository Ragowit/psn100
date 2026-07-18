<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/ThirtyMinuteCronJob.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/ThirtyMinuteCronJobApplication.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyCalculator.php';
require_once __DIR__ . '/../wwwroot/classes/Psn100Logger.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PlayStationWorkerAuthenticator.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/Worker.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerService.php';

final class ThirtyMinuteCronJobWorkerValidationTest extends TestCase
{
    public function testRunThrowsRuntimeExceptionWhenWorkerDoesNotExist(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE setting (id INTEGER PRIMARY KEY, refresh_token TEXT, npsso TEXT, scanning TEXT, scan_progress TEXT)');
        $database->exec('CREATE TABLE log (message TEXT NOT NULL)');

        $logger = new Psn100Logger($database);
        $cronJob = new ThirtyMinuteCronJob(
            $database,
            new TrophyCalculator($database),
            $logger,
            new TrophyHistoryRecorder($database, $logger),
            999
        );

        try {
            $cronJob->run();
            $this->fail('Expected RuntimeException for unknown worker id.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Worker 999 not found in setting table', $exception->getMessage());
        }

        $statement = $database->query('SELECT message FROM log');
        $message = $statement !== false ? $statement->fetchColumn() : false;
        $this->assertSame('Worker 999 not found in setting table', $message !== false ? (string) $message : '');
    }

    public function testApplicationRejectsNonPositiveWorkerIdBeforeBootstrapping(): void
    {
        $application = ThirtyMinuteCronJobApplication::fromGlobals(__DIR__ . '/../wwwroot', ['30th_minute.php', 'worker=0']);

        try {
            $application->run();
            $this->fail('Expected InvalidArgumentException for worker id 0.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Worker ID must be greater than zero. Received: 0', $exception->getMessage());
        }
    }

    public function testRefreshTokenPersistenceFailureLogsAndDoesNotThrow(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE setting (id INTEGER PRIMARY KEY, npsso TEXT, scanning TEXT, scan_progress TEXT, refresh_token TEXT)');
        $database->exec('CREATE TABLE log (message TEXT NOT NULL)');
        $database->exec("INSERT INTO setting (id, npsso, scanning, scan_progress, refresh_token) VALUES (1, 'token', NULL, NULL, NULL)");

        $logger = new Psn100Logger($database);
        $authenticator = PlayStationWorkerAuthenticator::fromWorkerService(
            new WorkerService($database),
            static fn (): object => new class {
                public function loginWithNpsso(string $npsso): void
                {
                }

                public function getRefreshToken(): object
                {
                    throw new RuntimeException('db unavailable');
                }
            },
            function (int $workerId, string $message) use ($logger): void {
                $logger->log(sprintf('Failed to persist refresh token for worker %d: %s', $workerId, $message));
            },
        );

        $worker = new Worker(1, '', 'token', '', new DateTimeImmutable('2024-01-01'), null);
        $authenticator->authenticateWorker($worker);

        $statement = $database->query('SELECT message FROM log ORDER BY rowid DESC LIMIT 1');
        $message = $statement !== false ? $statement->fetchColumn() : false;
        $this->assertSame(
            'Failed to persist refresh token for worker 1: db unavailable',
            $message !== false ? (string) $message : ''
        );
    }

    public function testRunCatchesTransientScanExceptionsInsteadOfTerminating(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../wwwroot/classes/Cron/ThirtyMinuteCronJob.php');

        $this->assertStringContainsString('} catch (TypeError | Exception $exception) {', $source);
        $this->assertStringContainsString(
            'Transient PSN/network failures (e.g. Guzzle/cURL transfer errors)',
            $source
        );
        $this->assertStringContainsString(
            'Guzzle\'s httpErrors middleware can also TypeError when a null response',
            $source
        );
        $this->assertStringContainsString(
            'Encountered a problem while scanning %s: %s. Waiting %s before retrying.',
            $source
        );
        $this->assertStringContainsString('isLockWaitTimeoutException', $source);
        $this->assertStringContainsString('$waitSeconds = $isLockWaitTimeout ? 5 : 60', $source);
        $this->assertStringContainsString("=== 1205", $source);
    }

    public function testIsLockWaitTimeoutExceptionDetectsMysqlError1205(): void
    {
        $method = new ReflectionMethod(ThirtyMinuteCronJob::class, 'isLockWaitTimeoutException');
        $method->setAccessible(true);

        $database = new PDO('sqlite::memory:');
        $cronJob = new ThirtyMinuteCronJob(
            $database,
            new TrophyCalculator($database),
            new Psn100Logger($database),
            new TrophyHistoryRecorder($database, new Psn100Logger($database)),
            1
        );

        $lockWaitTimeout = new PDOException(
            'SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction'
        );
        $lockWaitTimeout->errorInfo = ['HY000', 1205, 'Lock wait timeout exceeded; try restarting transaction'];

        $this->assertTrue($method->invoke($cronJob, $lockWaitTimeout));
        $this->assertTrue($method->invoke(
            $cronJob,
            new RuntimeException('SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction')
        ));
        $this->assertFalse($method->invoke($cronJob, new RuntimeException('cURL error 18')));
    }
}
