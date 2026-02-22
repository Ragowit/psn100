<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/ChangelogService.php';
require_once __DIR__ . '/../wwwroot/classes/ChangelogPaginator.php';

final class ChangelogServiceTest extends TestCase
{
    private PDO $database;

    private ChangelogService $service;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->database->exec(
            'CREATE TABLE psn100_change (' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
            'time TEXT NOT NULL, ' .
            'change_type TEXT NOT NULL, ' .
            'param_1 INTEGER NULL, ' .
            'param_2 INTEGER NULL, ' .
            'extra TEXT NULL)'
        );

        $this->database->exec(
            'CREATE TABLE trophy_title (' .
            'id INTEGER PRIMARY KEY, ' .
            'np_communication_id TEXT NOT NULL, ' .
            'name TEXT NOT NULL, ' .
            'platform TEXT NOT NULL)'
        );

        $this->database->exec(
            'CREATE TABLE trophy_title_meta (' .
            'np_communication_id TEXT PRIMARY KEY, ' .
            'region TEXT NULL, ' .
            'in_game_rarity_points INTEGER NOT NULL DEFAULT 0, ' .
            'obsolete_ids TEXT NULL, ' .
            'psnprofiles_id TEXT NULL)'
        );

        $this->service = new ChangelogService($this->database);
    }

    public function testGetChangesLoadsRegionsFromMeta(): void
    {
        $this->insertTitle(1, 'NPWR-1', 'Parent Game', 'PS5', 'US');
        $this->insertTitle(2, 'NPWR-2', 'Child Game', 'PS4', 'EU');

        $this->database->exec(
            "INSERT INTO psn100_change (time, change_type, param_1, param_2, extra) VALUES " .
            "('2024-01-01 00:00:00', 'GAME_MERGE', 2, 1, NULL)"
        );

        $paginator = new ChangelogPaginator(1, 1, ChangelogService::PAGE_SIZE);

        $entries = $this->service->getChanges($paginator);

        $this->assertCount(1, $entries);

        $entry = $entries[0];
        $this->assertSame('EU', $entry->getParam1Region());
        $this->assertSame('US', $entry->getParam2Region());
    }


    public function testGetChangesAndCountExcludeGameRescanAndGameVersion(): void
    {
        $this->database->exec(
            "INSERT INTO psn100_change (time, change_type, param_1, param_2, extra) VALUES " .
            "('2024-01-03 00:00:00', 'GAME_MERGE', 1, 2, NULL), " .
            "('2024-01-02 00:00:00', 'GAME_VERSION', 1, 2, NULL), " .
            "('2024-01-01 00:00:00', 'GAME_UPDATE', 1, 2, NULL), " .
            "('2023-12-31 12:00:00', 'GAME_RESCAN', 1, 2, NULL), " .
            "('2023-12-31 00:00:00', 'PLAYER_CREATE', 1, 2, NULL)"
        );

        $this->assertSame(3, $this->service->getTotalChangeCount());

        $pageOnePaginator = new ChangelogPaginator(1, 1, 1);
        $pageOneEntries = $this->service->getChanges($pageOnePaginator);

        $this->assertCount(1, $pageOneEntries);
        $this->assertSame(ChangelogEntryType::GAME_MERGE, $pageOneEntries[0]->getChangeType());

        $pageTwoPaginator = new ChangelogPaginator(2, 3, 1);
        $pageTwoEntries = $this->service->getChanges($pageTwoPaginator);

        $this->assertCount(1, $pageTwoEntries);
        $this->assertSame(ChangelogEntryType::GAME_UPDATE, $pageTwoEntries[0]->getChangeType());
        $this->assertSame('GAME_UPDATE', $pageTwoEntries[0]->getChangeTypeValue());

        $pageThreePaginator = new ChangelogPaginator(3, 3, 1);
        $pageThreeEntries = $this->service->getChanges($pageThreePaginator);

        $this->assertCount(1, $pageThreeEntries);
        $this->assertSame('PLAYER_CREATE', $pageThreeEntries[0]->getChangeTypeValue());
    }

    private function insertTitle(int $id, string $npCommunicationId, string $name, string $platform, string $region): void
    {
        $titleStatement = $this->database->prepare(
            'INSERT INTO trophy_title (id, np_communication_id, name, platform) VALUES (:id, :np, :name, :platform)'
        );
        $titleStatement->bindValue(':id', $id, PDO::PARAM_INT);
        $titleStatement->bindValue(':np', $npCommunicationId, PDO::PARAM_STR);
        $titleStatement->bindValue(':name', $name, PDO::PARAM_STR);
        $titleStatement->bindValue(':platform', $platform, PDO::PARAM_STR);
        $titleStatement->execute();

        $metaStatement = $this->database->prepare(
            'INSERT INTO trophy_title_meta (np_communication_id, region) VALUES (:np, :region)'
        );
        $metaStatement->bindValue(':np', $npCommunicationId, PDO::PARAM_STR);
        $metaStatement->bindValue(':region', $region, PDO::PARAM_STR);
        $metaStatement->execute();
    }
}
