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

    public function testCreateAuthenticatedClientUsesPrimaryFactoryRegardlessOfModeFlag(): void
    {
        $primaryCounter = (object) ['count' => 0];
        $secondaryCounter = (object) ['count' => 0];

        $service = new GameRescanService(
            $this->database,
            new TrophyCalculator($this->database),
            playStationClientFactory: new CountingPlayStationClientFactory($primaryCounter),
            shadowPlayStationClientFactory: new CountingPlayStationClientFactory($secondaryCounter),
            psnClientMode: PsnClientMode::fromValue('shadow')
        );

        $method = new ReflectionMethod(GameRescanService::class, 'createAuthenticatedClient');
        $method->setAccessible(true);
        $method->invoke($service, 'worker-token');

        $this->assertSame(1, $primaryCounter->count);
        $this->assertSame(0, $secondaryCounter->count);
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
