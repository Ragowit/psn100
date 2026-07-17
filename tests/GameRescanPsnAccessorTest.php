<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/GameRescanPsnAccessor.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PlayStationWorkerAuthenticator.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/Worker.php';

final class GameRescanPsnAccessorTest extends TestCase
{
    public function testIsOriginalGameAcceptsNpwrIds(): void
    {
        $accessor = $this->createAccessor();

        $this->assertTrue($accessor->isOriginalGame('NPWR00001_00'));
        $this->assertFalse($accessor->isOriginalGame('CUSA12345_00'));
    }

    public function testGetGameNpCommunicationIdReturnsStoredValue(): void
    {
        $database = $this->createDatabaseWithTitle('NPWR12345_00');
        $accessor = $this->createAccessor($database);

        $this->assertSame('NPWR12345_00', $accessor->getGameNpCommunicationId(7));
    }

    public function testGetGameNpCommunicationIdThrowsWhenGameMissing(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE trophy_title (id INTEGER PRIMARY KEY, np_communication_id TEXT NOT NULL)');

        $accessor = $this->createAccessor($database);

        try {
            $accessor->getGameNpCommunicationId(99);
            $this->fail('Expected RuntimeException when game is missing.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unable to find the specified game.', $exception->getMessage());
        }
    }

    public function testFindTrophyTitleForUserReturnsMatchingTitle(): void
    {
        $accessor = $this->createAccessor();
        $user = new GameRescanPsnAccessorTestUser([
            new GameRescanPsnAccessorTestTrophyTitle('NPWR11111_00'),
            new GameRescanPsnAccessorTestTrophyTitle('NPWR22222_00'),
        ]);

        $title = $accessor->findTrophyTitleForUser($user, 'NPWR22222_00');

        $this->assertTrue($title !== null);
        $this->assertSame('NPWR22222_00', $title->npCommunicationId());
    }

    public function testFindTrophyTitleForUserReturnsNullWhenNoMatch(): void
    {
        $accessor = $this->createAccessor();
        $user = new GameRescanPsnAccessorTestUser([
            new GameRescanPsnAccessorTestTrophyTitle('NPWR11111_00'),
        ]);

        $this->assertTrue($accessor->findTrophyTitleForUser($user, 'NPWR99999_00') === null);
    }

    public function testFindAccessibleUserWithGameSkipsPrivatePlayers(): void
    {
        $database = $this->createDatabaseWithTitlePlayers([
            ['account_id' => 100, 'np_communication_id' => 'NPWR00001_00'],
            ['account_id' => 200, 'np_communication_id' => 'NPWR00001_00'],
        ]);
        $accessor = $this->createAccessor($database);
        $client = new GameRescanPsnAccessorTestClient([
            100 => new GameRescanPsnAccessorTestUser([], privateProfile: true),
            200 => new GameRescanPsnAccessorTestUser([], privateProfile: false),
        ]);

        $user = $accessor->findAccessibleUserWithGame($client, 'NPWR00001_00');

        $this->assertTrue($user !== null);
        $this->assertSame('200', $user->accountId());
    }

    public function testFindAccessibleUserWithGamePagesOwnerProbes(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../wwwroot/classes/Admin/GameRescanPsnAccessor.php');

        $this->assertTrue(str_contains($source, 'ACCESSIBLE_PLAYER_PROBE_BATCH_SIZE'));
        $this->assertTrue(str_contains($source, 'LIMIT \' . self::ACCESSIBLE_PLAYER_PROBE_BATCH_SIZE . \' OFFSET \' . $offset'));
        $this->assertFalse(str_contains($source, 'ACCESSIBLE_PLAYER_PROBE_LIMIT'));
    }

    public function testFindAccessibleUserWithGameContinuesPastFullPrivateBatch(): void
    {
        // Higher account ids are more recent, so the first batch is 101..2 (private)
        // and the second batch starts at account 1 (public).
        $rows = [];
        $users = [];
        for ($accountId = 1; $accountId <= 101; $accountId++) {
            $rows[] = [
                'account_id' => $accountId,
                'np_communication_id' => 'NPWR00001_00',
                'last_updated_date' => sprintf(
                    '2024-02-%02dT%02d:00:00Z',
                    1 + intdiv($accountId - 1, 24),
                    ($accountId - 1) % 24
                ),
            ];
            $users[$accountId] = new GameRescanPsnAccessorTestUser([], privateProfile: $accountId !== 1);
        }

        $database = $this->createDatabaseWithTitlePlayers($rows);
        $accessor = $this->createAccessor($database);
        $client = new GameRescanPsnAccessorTestClient($users);

        $user = $accessor->findAccessibleUserWithGame($client, 'NPWR00001_00');

        $this->assertTrue($user !== null);
        $this->assertSame('1', $user->accountId());
    }

    public function testLoginToWorkerDelegatesToAuthenticator(): void
    {
        $worker = new Worker(5, 'refresh-token', '', '', new DateTimeImmutable('2024-01-01'), null);
        $authenticator = new PlayStationWorkerAuthenticator(
            static fn (): array => [$worker],
            static fn (): object => new GameRescanPsnAccessorTestClient([]),
        );
        $accessor = new GameRescanPsnAccessor(new PDO('sqlite::memory:'), $authenticator);

        $client = $accessor->loginToWorker();

        $this->assertTrue($client instanceof GameRescanPsnAccessorTestClient);
    }

    private function createAccessor(?PDO $database = null): GameRescanPsnAccessor
    {
        $database ??= new PDO('sqlite::memory:');

        return new GameRescanPsnAccessor(
            $database,
            new PlayStationWorkerAuthenticator(static fn (): array => []),
        );
    }

    private function createDatabaseWithTitle(string $npCommunicationId): PDO
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE trophy_title (id INTEGER PRIMARY KEY, np_communication_id TEXT NOT NULL)');
        $statement = $database->prepare(
            'INSERT INTO trophy_title (id, np_communication_id) VALUES (7, :np_communication_id)'
        );
        $statement->execute(['np_communication_id' => $npCommunicationId]);

        return $database;
    }

    /**
     * @param list<array{account_id: int, np_communication_id: string, last_updated_date?: string}> $rows
     */
    private function createDatabaseWithTitlePlayers(array $rows): PDO
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE player (account_id INTEGER PRIMARY KEY)');
        $database->exec(
            'CREATE TABLE trophy_title_player (
                account_id INTEGER NOT NULL,
                np_communication_id TEXT NOT NULL,
                last_updated_date TEXT NOT NULL
            )'
        );

        foreach ($rows as $row) {
            $database->exec('INSERT INTO player (account_id) VALUES (' . (int) $row['account_id'] . ')');
            $statement = $database->prepare(
                'INSERT INTO trophy_title_player (account_id, np_communication_id, last_updated_date)
                VALUES (:account_id, :np_communication_id, :last_updated_date)'
            );
            $statement->execute([
                'account_id' => $row['account_id'],
                'np_communication_id' => $row['np_communication_id'],
                'last_updated_date' => $row['last_updated_date'] ?? '2024-01-01T00:00:00Z',
            ]);
        }

        return $database;
    }
}

final class GameRescanPsnAccessorTestTrophyTitle
{
    public function __construct(private string $npCommunicationId)
    {
    }

    public function npCommunicationId(): string
    {
        return $this->npCommunicationId;
    }
}

final class GameRescanPsnAccessorTestUser
{
    /**
     * @param list<GameRescanPsnAccessorTestTrophyTitle> $trophyTitles
     */
    public function __construct(
        private array $trophyTitles,
        private bool $privateProfile = false,
        private string $accountId = '0',
    ) {
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    /**
     * @return list<GameRescanPsnAccessorTestTrophyTitle>
     */
    public function trophyTitles(): array
    {
        return $this->trophyTitles;
    }

    public function isPrivateProfile(): bool
    {
        return $this->privateProfile;
    }

    public function trophySummary(): object
    {
        if ($this->privateProfile) {
            throw new RuntimeException('Private profile');
        }

        return new class {
            public function level(): int
            {
                return 100;
            }
        };
    }
}

final class GameRescanPsnAccessorTestClient
{
    /**
     * @param array<int|string, GameRescanPsnAccessorTestUser> $users
     */
    public function __construct(private array $users)
    {
    }

    public function loginWithRefreshToken(string $refreshToken): void
    {
    }

    public function users(): object
    {
        $users = $this->users;

        return new class($users) {
            /**
             * @param array<int|string, GameRescanPsnAccessorTestUser> $users
             */
            public function __construct(private array $users)
            {
            }

            public function find(string $accountId): GameRescanPsnAccessorTestUser
            {
                $user = $this->users[$accountId] ?? new GameRescanPsnAccessorTestUser([]);

                return new GameRescanPsnAccessorTestUser(
                    $user->trophyTitles(),
                    $user->isPrivateProfile(),
                    $accountId,
                );
            }
        };
    }
}
