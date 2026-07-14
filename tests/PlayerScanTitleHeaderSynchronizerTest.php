<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/ImageHashCalculatorTest.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyCatalogSynchronizer.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyImageDirectories.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyImageDownloader.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanTitleHeaderSynchronizer.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanTitleHeaderSyncResult.php';

final class PlayerScanTitleHeaderSynchronizerTest extends TestCase
{
    private PDO $database;
    private string $titleIconDirectory;
    private PlayerScanTitleHeaderSynchronizer $synchronizer;

    protected function setUp(): void
    {
        $this->database = new PlayerScanTitleHeaderSynchronizerTestPDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec(
            'CREATE TABLE trophy_title (
                np_communication_id TEXT PRIMARY KEY,
                name TEXT,
                detail TEXT,
                icon_url TEXT,
                platform TEXT,
                set_version TEXT
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy_title_meta (
                np_communication_id TEXT PRIMARY KEY,
                message TEXT NOT NULL
            )'
        );

        $this->titleIconDirectory = sys_get_temp_dir() . '/psn100-title-header-sync-' . uniqid('', true) . '/';
        mkdir($this->titleIconDirectory, 0777, true);

        $imageDownloader = new TrophyImageDownloader(
            new ImageHashCalculator(new FakeImageProcessor(supported: false)),
            null,
            static fn (string $url): ?string => $url === 'https://example.test/icon.png' ? 'icon-bytes' : null,
        );

        $this->synchronizer = new PlayerScanTitleHeaderSynchronizer(
            $this->database,
            new TrophyCatalogSynchronizer($this->database),
            new TrophyImageDirectories($this->titleIconDirectory, '/tmp/group', '/tmp/trophy', '/tmp/reward'),
            $imageDownloader,
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->titleIconDirectory);
    }

    public function testFormatPlatformListFromTitleMapsPspcToPc(): void
    {
        $platforms = $this->synchronizer->formatPlatformListFromTitle(new PlayerScanTitleHeaderTestTrophyTitle(
            'NPWR12345_00',
            [
                new PlayerScanTitleHeaderTestPlatform('PS5'),
                new PlayerScanTitleHeaderTestPlatform('PSPC'),
            ],
        ));

        $this->assertSame('PS5,PC', $platforms);
    }

    public function testSyncInsertsTrophyTitleMetaRowWhenTitleAlreadyExists(): void
    {
        file_put_contents($this->titleIconDirectory . 'existing.png', 'icon-bytes');
        $this->database->exec(
            "INSERT INTO trophy_title (np_communication_id, name, detail, icon_url, platform, set_version)
            VALUES ('NPWR12345_00', 'Example Game', 'Details', 'existing.png', 'PS5', '01.00')"
        );

        $result = $this->synchronizer->sync(new PlayerScanTitleHeaderTestTrophyTitle('NPWR12345_00'));

        $this->assertFalse($result->titleDataChanged);
        $this->assertFalse($result->titleNeedsUpdate);
        $this->assertFalse($result->isNewTitle);

        $query = $this->database->prepare(
            'SELECT message FROM trophy_title_meta WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', 'NPWR12345_00', PDO::PARAM_STR);
        $query->execute();

        $this->assertSame('', $query->fetchColumn());
    }

    public function testSyncSkipsTitleUpdateWhenIncomingSetVersionIsOlder(): void
    {
        file_put_contents($this->titleIconDirectory . 'existing.png', 'icon-bytes');
        $this->database->exec(
            "INSERT INTO trophy_title (np_communication_id, name, detail, icon_url, platform, set_version)
            VALUES ('NPWR12345_00', 'Example Game', 'Stored detail', 'existing.png', 'PS5', '02.00')"
        );

        $result = $this->synchronizer->sync(new PlayerScanTitleHeaderTestTrophyTitle(
            'NPWR12345_00',
            detail: 'Incoming detail',
            trophySetVersion: '01.00',
        ));

        $this->assertFalse($result->titleDataChanged);
        $this->assertFalse($result->titleNeedsUpdate);
        $this->assertFalse($result->isNewTitle);

        $query = $this->database->prepare(
            'SELECT detail, set_version FROM trophy_title WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', 'NPWR12345_00', PDO::PARAM_STR);
        $query->execute();
        $row = $query->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('Stored detail', $row['detail']);
        $this->assertSame('02.00', $row['set_version']);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path . '/');
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}

final class PlayerScanTitleHeaderTestPlatform
{
    public function __construct(public readonly string $value)
    {
    }
}

final class PlayerScanTitleHeaderTestTrophyTitle
{
    /**
     * @param list<PlayerScanTitleHeaderTestPlatform> $platforms
     */
    public function __construct(
        private readonly string $npCommunicationId,
        private readonly array $platforms = [new PlayerScanTitleHeaderTestPlatform('PS5')],
        private readonly string $detail = 'Details',
        private readonly string $trophySetVersion = '01.00',
    ) {
    }

    public function npCommunicationId(): string
    {
        return $this->npCommunicationId;
    }

    public function name(): string
    {
        return 'Example Game';
    }

    public function detail(): string
    {
        return $this->detail;
    }

    public function iconUrl(): string
    {
        return 'https://example.test/icon.png';
    }

    /**
     * @return list<PlayerScanTitleHeaderTestPlatform>
     */
    public function platform(): array
    {
        return $this->platforms;
    }

    public function trophySetVersion(): string
    {
        return $this->trophySetVersion;
    }
}

final class PlayerScanTitleHeaderSynchronizerTestPDO extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(
            str_replace('INSERT IGNORE', 'INSERT OR IGNORE', $query),
            $options,
        );
    }
}
