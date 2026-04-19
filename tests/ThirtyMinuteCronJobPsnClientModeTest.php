<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Psn100Logger.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyCalculator.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyHistoryRecorder.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/ThirtyMinuteCronJob.php';

final class ThirtyMinuteCronJobPsnClientModeTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE log (message TEXT NOT NULL)');
    }

    public function testCreateAuthenticatedClientUsesNewFactoryInNewMode(): void
    {
        $legacyCounter = (object) ['count' => 0];
        $newCounter = (object) ['count' => 0];

        $cronJob = new ThirtyMinuteCronJob(
            $this->database,
            new TrophyCalculator($this->database),
            new Psn100Logger($this->database),
            new TrophyHistoryRecorder($this->database),
            1,
            playStationClientFactory: new CronCountingPlayStationClientFactory($legacyCounter),
            shadowPlayStationClientFactory: new CronCountingPlayStationClientFactory($newCounter),
            psnClientMode: PsnClientMode::fromValue('new')
        );

        $method = new ReflectionMethod(ThirtyMinuteCronJob::class, 'createAuthenticatedClient');
        $method->setAccessible(true);
        $method->invoke($cronJob, 'worker-token');

        $this->assertSame(0, $legacyCounter->count);
        $this->assertSame(1, $newCounter->count);
    }

    public function testCreateAuthenticatedClientInShadowModeKeepsLegacyTruthAndOptionallyRunsShadow(): void
    {
        $legacyCounter = (object) ['count' => 0];
        $newCounter = (object) ['count' => 0];

        $cronJob = new ThirtyMinuteCronJob(
            $this->database,
            new TrophyCalculator($this->database),
            new Psn100Logger($this->database),
            new TrophyHistoryRecorder($this->database),
            1,
            playStationClientFactory: new CronCountingPlayStationClientFactory($legacyCounter),
            shadowPlayStationClientFactory: new CronCountingPlayStationClientFactory($newCounter),
            psnClientMode: PsnClientMode::fromValue('shadow')
        );

        $method = new ReflectionMethod(ThirtyMinuteCronJob::class, 'createAuthenticatedClient');
        $method->setAccessible(true);
        $method->invoke($cronJob, 'worker-token');

        $this->assertSame(1, $legacyCounter->count);
        $expectedShadowExecutions = (
            function_exists('pcntl_signal')
            && function_exists('pcntl_async_signals')
            && function_exists('pcntl_setitimer')
        ) ? 1 : 0;
        $this->assertSame($expectedShadowExecutions, $newCounter->count);
    }
}

final class CronCountingPlayStationClientFactory implements PlayStationClientFactoryInterface
{
    public function __construct(private readonly object $counter)
    {
    }

    public function createClient(): PlayStationApiClientInterface
    {
        $this->counter->count++;

        return new class () implements PlayStationApiClientInterface {
            public function loginWithNpsso(string $npsso): void
            {
            }

            public function acquireAccessToken(): ?string
            {
                return null;
            }

            public function refreshAccessToken(): void
            {
            }

            public function lookupProfileByOnlineId(string $onlineId): mixed
            {
                return (object) [];
            }

            public function findUserByAccountId(string $accountId): object
            {
                return (object) [];
            }

            public function requestTrophyEndpoint(string $path, array $query = [], array $headers = []): mixed
            {
                return (object) [];
            }

            public function searchUsers(string $onlineId): iterable
            {
                return [];
            }
        };
    }
}
