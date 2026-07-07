<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Psn100Logger.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanTitleCatalogSynchronizer.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanTitleCatalogSyncResult.php';

final class PlayerScanTitleCatalogSynchronizerTest extends TestCase
{
    public function testFormatPlatformListFromTitleMapsPspcToPc(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE log (message TEXT NOT NULL)');

        $synchronizer = new PlayerScanTitleCatalogSynchronizer(
            $database,
            new Psn100Logger($database),
        );

        $platforms = $synchronizer->formatPlatformListFromTitle(new PlayerScanTitleCatalogTestTrophyTitle(
            'NPWR12345_00',
            [
                new PlayerScanTitleCatalogTestPlatform('PS5'),
                new PlayerScanTitleCatalogTestPlatform('PSPC'),
            ],
        ));

        $this->assertSame('PS5,PC', $platforms);
    }

    public function testSynchronizeCatalogResultFactoryPreservesMergeParents(): void
    {
        $result = PlayerScanTitleCatalogSyncResult::synced(
            newTrophies: true,
            isNewTitle: true,
            titleId: 42,
            mergeParentsToRecompute: ['NPWR99999_00'],
        );

        $this->assertFalse($result->restartScan);
        $this->assertTrue($result->newTrophies);
        $this->assertTrue($result->isNewTitle);
        $this->assertSame(42, $result->titleId);
        $this->assertSame(['NPWR99999_00'], $result->mergeParentsToRecompute);
    }
}

final class PlayerScanTitleCatalogTestPlatform
{
    public function __construct(public readonly string $value)
    {
    }
}

final class PlayerScanTitleCatalogTestTrophyTitle
{
    /**
     * @param list<PlayerScanTitleCatalogTestPlatform> $platforms
     */
    public function __construct(
        private readonly string $npCommunicationId,
        private readonly array $platforms = [new PlayerScanTitleCatalogTestPlatform('PS5')],
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
        return 'Details';
    }

    public function iconUrl(): string
    {
        return 'https://example.test/icon.png';
    }

    /**
     * @return list<PlayerScanTitleCatalogTestPlatform>
     */
    public function platform(): array
    {
        return $this->platforms;
    }

    public function trophySetVersion(): string
    {
        return '01.00';
    }
}
