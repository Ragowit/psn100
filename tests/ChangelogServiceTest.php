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
            'region TEXT NULL)'
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

