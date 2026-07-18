<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/ImageHashCalculatorTest.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyImageDownloader.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyImageDirectories.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyCatalogSynchronizer.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyMetaRepository.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/GameRescanDifferenceTracker.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/GameRescanProgressReporter.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/GameRescanGroupDataFetcher.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/GameRescanCatalogUpdater.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnTrophyApiAdapter.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnTrophyGroupApiAdapter.php';

if (!class_exists('Tustin\\PlayStation\\Client')) {
    eval('namespace Tustin\\PlayStation; final class Client {}');
}

final class GameRescanCatalogUpdaterTest extends TestCase
{
    private PDO $database;
    private GameRescanCatalogUpdater $catalogUpdater;
    private StubGameRescanGroupDataFetcher $groupDataFetcher;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec(
            'CREATE TABLE trophy_title (
                np_communication_id TEXT PRIMARY KEY,
                detail TEXT,
                icon_url TEXT,
                platform TEXT,
                set_version TEXT
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy_group (
                np_communication_id TEXT NOT NULL,
                group_id TEXT NOT NULL,
                name TEXT,
                detail TEXT,
                icon_url TEXT,
                PRIMARY KEY (np_communication_id, group_id)
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                np_communication_id TEXT NOT NULL,
                group_id TEXT NOT NULL,
                order_id INTEGER NOT NULL,
                hidden INTEGER,
                type TEXT,
                name TEXT,
                detail TEXT,
                icon_url TEXT,
                progress_target_value INTEGER,
                reward_name TEXT,
                reward_image_url TEXT
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy_meta (
                trophy_id INTEGER PRIMARY KEY,
                rarity_percent REAL NOT NULL DEFAULT 0,
                rarity_point INTEGER NOT NULL DEFAULT 0,
                status INTEGER NOT NULL DEFAULT 0,
                owners INTEGER NOT NULL DEFAULT 0,
                rarity_name TEXT NOT NULL DEFAULT "NONE"
            )'
        );

        $imageDownloader = new TrophyImageDownloader(
            new ImageHashCalculator(new FakeImageProcessor(supported: false)),
            null,
            static fn (string $url): ?string => 'image-bytes',
        );
        $this->groupDataFetcher = new StubGameRescanGroupDataFetcher();
        $this->catalogUpdater = new GameRescanCatalogUpdater(
            $this->database,
            new TrophyCatalogSynchronizer($this->database),
            new TrophyMetaRepository($this->database),
            $this->groupDataFetcher,
            new TrophyImageDirectories('/tmp/title', '/tmp/group', '/tmp/trophy', '/tmp/reward'),
            $imageDownloader,
        );
    }

    public function testUpdateFromPsnSkipsWhenIncomingSetVersionIsLower(): void
    {
        $this->database->exec(
            "INSERT INTO trophy_title (np_communication_id, detail, icon_url, platform, set_version)
            VALUES ('NPWR00001_00', 'Old detail', 'old.png', 'PS5', '01.10')"
        );

        $differenceTracker = new GameRescanDifferenceTracker();
        $progressReporter = new GameRescanProgressReporter();
        $trophyTitle = new GameRescanCatalogUpdaterTestTrophyTitle(
            detail: 'New detail',
            setVersion: '01.09',
        );

        $groups = $this->catalogUpdater->updateFromPsn(
            new \Tustin\PlayStation\Client(),
            $trophyTitle,
            'NPWR00001_00',
            $progressReporter,
            $differenceTracker,
        );

        $this->assertSame([], $groups);
        $this->assertSame([], $differenceTracker->getDifferences());

        $detail = $this->database->query(
            "SELECT detail FROM trophy_title WHERE np_communication_id = 'NPWR00001_00'"
        )->fetchColumn();
        $this->assertSame('Old detail', $detail);
    }

    public function testUpdateFromPsnRecordsTitleDetailChanges(): void
    {
        $this->database->exec(
            "INSERT INTO trophy_title (np_communication_id, detail, icon_url, platform, set_version)
            VALUES ('NPWR00001_00', 'Old detail', 'old.png', 'PS5', '01.00')"
        );
        $this->groupDataFetcher->setGroupData([]);

        $differenceTracker = new GameRescanDifferenceTracker();
        $progressReporter = new GameRescanProgressReporter();
        $trophyTitle = new GameRescanCatalogUpdaterTestTrophyTitle(
            detail: 'New detail',
            setVersion: '01.10',
        );

        $this->catalogUpdater->updateFromPsn(
            new \Tustin\PlayStation\Client(),
            $trophyTitle,
            'NPWR00001_00',
            $progressReporter,
            $differenceTracker,
        );

        $differences = $differenceTracker->getDifferences();
        $detailDifference = null;
        foreach ($differences as $difference) {
            if ($difference['field'] === 'Detail') {
                $detailDifference = $difference;
                break;
            }
        }

        $this->assertTrue($detailDifference !== null);
        $this->assertSame('Trophy Title', $detailDifference['context']);
        $this->assertSame('Old detail', $detailDifference['previous']);
        $this->assertSame('New detail', $detailDifference['current']);

        $detail = $this->database->query(
            "SELECT detail FROM trophy_title WHERE np_communication_id = 'NPWR00001_00'"
        )->fetchColumn();
        $this->assertSame('New detail', $detail);
    }

    public function testUpdateFromPsnPreservesPsvr2PlatformWhenMissingFromIncomingTitle(): void
    {
        $this->database->exec(
            "INSERT INTO trophy_title (np_communication_id, detail, icon_url, platform, set_version)
            VALUES ('NPWR00001_00', 'Details', 'title.png', 'PSVR2', '01.00')"
        );
        $this->groupDataFetcher->setGroupData([]);

        $differenceTracker = new GameRescanDifferenceTracker();
        $progressReporter = new GameRescanProgressReporter();
        $trophyTitle = new GameRescanCatalogUpdaterTestTrophyTitle(
            detail: 'Details',
            setVersion: '01.00',
            platforms: [new GameRescanCatalogUpdaterTestPlatform('PS5')],
        );

        $this->catalogUpdater->updateFromPsn(
            new \Tustin\PlayStation\Client(),
            $trophyTitle,
            'NPWR00001_00',
            $progressReporter,
            $differenceTracker,
        );

        $platform = $this->database->query(
            "SELECT platform FROM trophy_title WHERE np_communication_id = 'NPWR00001_00'"
        )->fetchColumn();

        $this->assertSame('PSVR2', $platform);
    }
}

final class StubGameRescanGroupDataFetcher implements GameRescanGroupDataFetcher
{
    /**
     * @var array<int, array{group: PsnTrophyGroupApiAdapter, trophies: array<int, PsnTrophyApiAdapter>}>
     */
    private array $groupData = [];

    /**
     * @param array<int, array{group: PsnTrophyGroupApiAdapter, trophies: array<int, PsnTrophyApiAdapter>}> $groupData
     */
    public function setGroupData(array $groupData): void
    {
        $this->groupData = $groupData;
    }

    #[\Override]
    public function fetchGroupData(\Tustin\PlayStation\Client $client, string $npCommunicationId): array
    {
        return $this->groupData;
    }
}

final class GameRescanCatalogUpdaterTestTrophyTitle
{
    /**
     * @param list<GameRescanCatalogUpdaterTestPlatform> $platforms
     */
    public function __construct(
        private readonly string $detail,
        private readonly string $setVersion,
        private readonly array $platforms = [new GameRescanCatalogUpdaterTestPlatform('PS5')],
    ) {
    }

    public function detail(): string
    {
        return $this->detail;
    }

    public function iconUrl(): string
    {
        return 'https://example.test/title.png';
    }

    public function trophySetVersion(): string
    {
        return $this->setVersion;
    }

    /**
     * @return list<GameRescanCatalogUpdaterTestPlatform>
     */
    public function platform(): array
    {
        return $this->platforms;
    }
}

final class GameRescanCatalogUpdaterTestPlatform
{
    public function __construct(public readonly string $value)
    {
    }
}
