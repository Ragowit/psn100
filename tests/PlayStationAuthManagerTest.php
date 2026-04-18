<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayStation/Auth/PlayStationAuthManager.php';
require_once __DIR__ . '/../wwwroot/classes/PlayStation/Contracts/PlayStationClientFactoryInterface.php';
require_once __DIR__ . '/../wwwroot/classes/PlayStation/Contracts/PlayStationApiClientInterface.php';

final class PlayStationAuthManagerTest extends TestCase
{
    public function testAuthenticateSpecificWorkerReturnsRequestedWorker(): void
    {
        $database = $this->createDatabaseWithWorkers([
            ['id' => 1, 'npsso' => 'worker-1-npsso'],
            ['id' => 2, 'npsso' => 'worker-2-npsso'],
        ]);

        $factory = new PlayStationAuthManagerTestFactory();
        $manager = new PlayStationAuthManager($database, $factory);

        $result = $manager->authenticateSpecificWorker(2);

        $this->assertSame(2, $result['worker_id']);
        $this->assertSame(['worker-2-npsso'], $factory->getLoggedInNpssoValues());
    }

    public function testAuthenticateSpecificWorkerThrowsWhenWorkerDoesNotExist(): void
    {
        $database = $this->createDatabaseWithWorkers([
            ['id' => 1, 'npsso' => 'worker-1-npsso'],
        ]);

        $manager = new PlayStationAuthManager($database, new PlayStationAuthManagerTestFactory());

        try {
            $manager->authenticateSpecificWorker(999);
            $this->fail('Expected RuntimeException for missing worker.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Worker 999 not found in setting table.', $exception->getMessage());
        }
    }

    public function testAuthenticateSpecificWorkerThrowsWhenWorkerCannotAuthenticate(): void
    {
        $database = $this->createDatabaseWithWorkers([
            ['id' => 1, 'npsso' => ''],
        ]);

        $manager = new PlayStationAuthManager($database, new PlayStationAuthManagerTestFactory());

        try {
            $manager->authenticateSpecificWorker(1);
            $this->fail('Expected RuntimeException for unauthenticated worker.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unable to authenticate worker 1.', $exception->getMessage());
        }
    }

    /**
     * @param list<array{id: int, npsso: string}> $workers
     */
    private function createDatabaseWithWorkers(array $workers): PDO
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE setting (id INTEGER PRIMARY KEY, npsso TEXT)');
        $database->exec('CREATE TABLE log (message TEXT NOT NULL)');

        $insert = $database->prepare('INSERT INTO setting (id, npsso) VALUES (:id, :npsso)');

        foreach ($workers as $worker) {
            $insert->bindValue(':id', $worker['id'], PDO::PARAM_INT);
            $insert->bindValue(':npsso', $worker['npsso'], PDO::PARAM_STR);
            $insert->execute();
        }

        return $database;
    }
}

final class PlayStationAuthManagerTestFactory implements PlayStationClientFactoryInterface
{
    /**
     * @var list<string>
     */
    private array $loggedInNpssoValues = [];

    public function createClient(): PlayStationApiClientInterface
    {
        return new class($this) implements PlayStationApiClientInterface {
            public function __construct(private readonly PlayStationAuthManagerTestFactory $factory)
            {
            }

            public function loginWithNpsso(string $npsso): void
            {
                $this->factory->recordNpssoLogin($npsso);
            }

            public function acquireAccessToken(): ?string
            {
                return 'token';
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

    public function recordNpssoLogin(string $npsso): void
    {
        $this->loggedInNpssoValues[] = $npsso;
    }

    /**
     * @return list<string>
     */
    public function getLoggedInNpssoValues(): array
    {
        return $this->loggedInNpssoValues;
    }
}
