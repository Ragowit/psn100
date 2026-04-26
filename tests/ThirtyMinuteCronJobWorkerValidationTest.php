<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/ThirtyMinuteCronJob.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/ThirtyMinuteCronJobApplication.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyCalculator.php';
require_once __DIR__ . '/../wwwroot/classes/Psn100Logger.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyHistoryRecorder.php';

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

    public function testSaveWorkerRefreshTokenBestEffortLogsAndDoesNotThrowOnFailure(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE setting (id INTEGER PRIMARY KEY, npsso TEXT, scanning TEXT, scan_progress TEXT, refresh_token TEXT)');
        $database->exec('CREATE TABLE log (message TEXT NOT NULL)');
        $database->exec("INSERT INTO setting (id, npsso, scanning, scan_progress, refresh_token) VALUES (1, 'token', NULL, NULL, NULL)");

        $logger = new Psn100Logger($database);
        $cronJob = new ThirtyMinuteCronJob(
            $database,
            new TrophyCalculator($database),
            $logger,
            new TrophyHistoryRecorder($database, $logger),
            1
        );

        $method = new ReflectionMethod(ThirtyMinuteCronJob::class, 'saveWorkerRefreshTokenBestEffort');
        $method->setAccessible(true);

        $method->invoke($cronJob, 1, new class {
            public function getRefreshToken(): object
            {
                throw new RuntimeException('db unavailable');
            }
        });

        $statement = $database->query('SELECT message FROM log ORDER BY rowid DESC LIMIT 1');
        $message = $statement !== false ? $statement->fetchColumn() : false;
        $this->assertSame(
            'Failed to persist refresh token for worker 1: db unavailable',
            $message !== false ? (string) $message : ''
        );
    }
}
