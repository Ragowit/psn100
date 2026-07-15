<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Psn100Logger.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanTitleMetadataHelper.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/WorkerScanCoordinator.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanTrophyTitleRefresher.php';

final class PlayerScanTrophyTitleRefresherTest extends TestCase
{
    private PDO $database;
    private PlayerScanTrophyTitleRefresher $refresher;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE log (message TEXT NOT NULL)');

        $logger = new Psn100Logger($this->database);
        $this->refresher = new PlayerScanTrophyTitleRefresher(
            $logger,
            new PlayerScanTitleMetadataHelper(),
            new WorkerScanCoordinator($this->database),
        );
    }

    public function testEnsureValidTrophyTitleLastUpdatedDateReturnsTitleWhenDateIsValid(): void
    {
        $title = new PlayerScanTrophyTitleRefresherTestTrophyTitle('NPWR12345_00', '2024-06-15T10:30:00Z', 'Example Game');
        $user = new PlayerScanTrophyTitleRefresherTestUser([]);

        $result = $this->refresher->ensureValidTrophyTitleLastUpdatedDate(
            $user,
            $title,
            'ExampleUser'
        );

        $this->assertSame($title, $result);
        $this->assertSame(0, $user->getFetchCount());
    }

    public function testEnsureValidTrophyTitleLastUpdatedDateRefetchesSonyDataWhenDateIsInvalid(): void
    {
        $invalidTitle = new PlayerScanTrophyTitleRefresherTestTrophyTitle('NPWR12345_00', 'not-a-valid-date', 'Example Game');
        $user = new PlayerScanTrophyTitleRefresherTestUser([
            [new PlayerScanTrophyTitleRefresherTestTrophyTitle('NPWR12345_00', '2024-06-15T10:30:00Z', 'Example Game')],
        ]);

        $result = $this->refresher->ensureValidTrophyTitleLastUpdatedDate(
            $user,
            $invalidTitle,
            'ExampleUser'
        );

        $this->assertSame('2024-06-15T10:30:00Z', $result->lastUpdatedDateTime());
        $this->assertSame(1, $user->getFetchCount());
    }

    public function testEnsureValidTrophyTitleLastUpdatedDateReturnsNullWhenRefetchStillInvalid(): void
    {
        $invalidTitle = new PlayerScanTrophyTitleRefresherTestTrophyTitle('NPWR12345_00', 'not-a-valid-date', 'Example Game');
        $user = new PlayerScanTrophyTitleRefresherTestUser([
            [new PlayerScanTrophyTitleRefresherTestTrophyTitle('NPWR12345_00', 'still-not-valid', 'Example Game')],
        ]);

        $result = $this->refresher->ensureValidTrophyTitleLastUpdatedDate(
            $user,
            $invalidTitle,
            'ExampleUser'
        );

        $this->assertSame(null, $result);
        $this->assertSame(1, $user->getFetchCount());

        $logMessage = $this->database->query('SELECT message FROM log ORDER BY rowid DESC LIMIT 1')->fetchColumn();
        $this->assertStringContainsString('invalid last updated date', (string) $logMessage);
    }

    public function testEnsureTrophyTitleIconReturnsTitleWhenIconIsPresent(): void
    {
        $title = new PlayerScanTrophyTitleRefresherTestTrophyTitle(
            'NPWR12345_00',
            '2024-06-15T10:30:00Z',
            'Example Game',
            'https://example.com/icon.png'
        );
        $user = new PlayerScanTrophyTitleRefresherTestUser([]);

        $result = $this->refresher->ensureTrophyTitleIcon($user, $title, 'ExampleUser');

        $this->assertSame($title, $result);
        $this->assertSame(0, $user->getFetchCount());
    }

    public function testEnsureTrophyTitleIconRefetchesSonyDataWhenIconIsMissing(): void
    {
        $missingIconTitle = new PlayerScanTrophyTitleRefresherTestTrophyTitle(
            'NPWR12345_00',
            '2024-06-15T10:30:00Z',
            'Example Game',
            ''
        );
        $user = new PlayerScanTrophyTitleRefresherTestUser([
            [new PlayerScanTrophyTitleRefresherTestTrophyTitle(
                'NPWR12345_00',
                '2024-06-15T10:30:00Z',
                'Example Game',
                'https://example.com/icon.png'
            )],
        ]);

        $result = $this->refresher->ensureTrophyTitleIcon($user, $missingIconTitle, 'ExampleUser');

        $this->assertSame('https://example.com/icon.png', $result->iconUrl());
        $this->assertSame(1, $user->getFetchCount());
    }

    public function testEnsureTrophyTitleIconReturnsNullWhenRefetchStillMissingIcon(): void
    {
        $missingIconTitle = new PlayerScanTrophyTitleRefresherTestTrophyTitle(
            'NPWR12345_00',
            '2024-06-15T10:30:00Z',
            'Example Game',
            ''
        );
        $user = new PlayerScanTrophyTitleRefresherTestUser([
            [new PlayerScanTrophyTitleRefresherTestTrophyTitle(
                'NPWR12345_00',
                '2024-06-15T10:30:00Z',
                'Example Game',
                ''
            )],
        ]);

        $result = $this->refresher->ensureTrophyTitleIcon($user, $missingIconTitle, 'ExampleUser');

        $this->assertSame(null, $result);
        $this->assertSame(1, $user->getFetchCount());

        $logMessage = $this->database->query('SELECT message FROM log ORDER BY rowid DESC LIMIT 1')->fetchColumn();
        $this->assertStringContainsString('missing an icon', (string) $logMessage);
    }
}

final class PlayerScanTrophyTitleRefresherTestTrophyTitle
{
    public function __construct(
        private readonly string $npCommunicationId,
        private readonly string $lastUpdatedDateTime,
        private readonly string $name = '',
        private readonly string $iconUrl = 'https://example.com/default.png',
    ) {
    }

    public function npCommunicationId(): string
    {
        return $this->npCommunicationId;
    }

    public function lastUpdatedDateTime(): string
    {
        return $this->lastUpdatedDateTime;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function iconUrl(): string
    {
        return $this->iconUrl;
    }
}

final class PlayerScanTrophyTitleRefresherTestUser
{
    /** @var list<list<PlayerScanTrophyTitleRefresherTestTrophyTitle>> */
    private array $fetchResults;
    private int $fetchCount = 0;

    /**
     * @param list<list<PlayerScanTrophyTitleRefresherTestTrophyTitle>> $fetchResults
     */
    public function __construct(array $fetchResults)
    {
        $this->fetchResults = $fetchResults;
    }

    public function trophyTitles(): PlayerScanTrophyTitleRefresherTestTrophyTitleCollection
    {
        $titles = $this->fetchResults[$this->fetchCount] ?? array_last($this->fetchResults) ?? [];
        $this->fetchCount++;

        return new PlayerScanTrophyTitleRefresherTestTrophyTitleCollection($titles);
    }

    public function getFetchCount(): int
    {
        return $this->fetchCount;
    }
}

final class PlayerScanTrophyTitleRefresherTestTrophyTitleCollection implements IteratorAggregate
{
    /** @param list<PlayerScanTrophyTitleRefresherTestTrophyTitle> $titles */
    public function __construct(private readonly array $titles)
    {
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->titles);
    }
}
