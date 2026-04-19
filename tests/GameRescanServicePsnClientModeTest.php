<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyCalculator.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/GameRescanService.php';

final class GameRescanServicePsnClientModeTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function testCreateAuthenticatedClientUsesNewFactoryInNewMode(): void
    {
        $legacyCounter = (object) ['count' => 0];
        $newCounter = (object) ['count' => 0];

        $service = new GameRescanService(
            $this->database,
            new TrophyCalculator($this->database),
            playStationClientFactory: new CountingPlayStationClientFactory($legacyCounter),
            shadowPlayStationClientFactory: new CountingPlayStationClientFactory($newCounter),
            psnClientMode: PsnClientMode::fromValue('new')
        );

        $method = new ReflectionMethod(GameRescanService::class, 'createAuthenticatedClient');
        $method->setAccessible(true);
        $method->invoke($service, 'worker-token');

        $this->assertSame(0, $legacyCounter->count);
        $this->assertSame(1, $newCounter->count);
    }

    public function testCreateAuthenticatedClientInShadowModeKeepsLegacyTruthAndOptionallyRunsShadow(): void
    {
        $legacyCounter = (object) ['count' => 0];
        $newCounter = (object) ['count' => 0];

        $service = new GameRescanService(
            $this->database,
            new TrophyCalculator($this->database),
            playStationClientFactory: new CountingPlayStationClientFactory($legacyCounter),
            shadowPlayStationClientFactory: new CountingPlayStationClientFactory($newCounter),
            psnClientMode: PsnClientMode::fromValue('shadow')
        );

        $method = new ReflectionMethod(GameRescanService::class, 'createAuthenticatedClient');
        $method->setAccessible(true);
        $method->invoke($service, 'worker-token');

        $this->assertSame(1, $legacyCounter->count);
        $expectedShadowExecutions = (
            function_exists('pcntl_signal')
            && function_exists('pcntl_async_signals')
            && function_exists('pcntl_setitimer')
        ) ? 1 : 0;
        $this->assertSame($expectedShadowExecutions, $newCounter->count);
    }
}

final class CountingPlayStationClientFactory implements PlayStationClientFactoryInterface
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
