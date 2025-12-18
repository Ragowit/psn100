<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/GameDetail.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/GameDetailService.php';
require_once __DIR__ . '/../wwwroot/classes/GameAvailabilityStatus.php';

final class GameDetailServiceTest extends TestCase
{
    private PDO $database;

    private GameDetailService $service;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->database->exec(
            'CREATE TABLE trophy_title (' .
            'id INTEGER PRIMARY KEY, ' .
            'np_communication_id TEXT NOT NULL, ' .
            'name TEXT NOT NULL, ' .
            'icon_url TEXT NOT NULL, ' .
            'platform TEXT NOT NULL, ' .
            'set_version TEXT NOT NULL)'
        );

        $this->database->exec(
            'CREATE TABLE trophy_title_meta (' .
            'np_communication_id TEXT PRIMARY KEY, ' .
            'message TEXT NOT NULL, ' .
            'region TEXT NULL, ' .
            'psnprofiles_id TEXT NULL, ' .
            'in_game_rarity_points INTEGER NOT NULL DEFAULT 0, ' .
            'status INTEGER NOT NULL DEFAULT 0, ' .
            'obsolete_ids TEXT NULL)'
        );

        $this->database->exec(
            "CREATE TABLE psn100_change (change_type TEXT, param_1 INTEGER)"
        );

        $this->service = new GameDetailService($this->database);
    }

    public function testGetGameDetailLoadsMetaFields(): void
    {
        $this->insertGame([
            'id' => 10,
            'np_communication_id' => 'NPWR-123',
            'name' => 'Example Game',
            'icon_url' => 'icon.png',
            'platform' => 'PS5',
            'set_version' => '01.00',
        ], [
            'message' => 'hello world',
            'region' => 'US',
            'psnprofiles_id' => '12345',
            'status' => 3,
            'obsolete_ids' => '42,84',
        ]);

        $detail = $this->service->getGameDetail(10);

        $this->assertTrue($detail instanceof GameDetail, 'Expected game detail to be loaded.');
        if (!$detail instanceof GameDetail) {
            return;
        }

        $this->assertSame('hello world', $detail->getMessage());
        $this->assertSame('US', $detail->getRegion());
        $this->assertSame('12345', $detail->getPsnprofilesId());
        $this->assertSame(GameAvailabilityStatus::OBSOLETE, $detail->getStatus());
        $this->assertSame('42,84', $detail->getObsoleteIds());
    }

    public function testUpdateGameDetailPersistsMetaFields(): void
    {
        $this->insertGame([
            'id' => 20,
            'np_communication_id' => 'NPWR-456',
            'name' => 'Original Name',
            'icon_url' => 'original.png',
            'platform' => 'PS4',
            'set_version' => '01.00',
        ], [
            'message' => 'original message',
            'region' => 'EU',
            'psnprofiles_id' => '67890',
            'obsolete_ids' => '91',
        ]);

        $detail = new GameDetail(
            20,
            'NPWR-456',
            'Updated Name',
            'updated.png',
            'PS5',
            'updated message',
            '02.00',
            null,
            null,
            GameAvailabilityStatus::NORMAL,
            '55,66'
        );

        $updatedDetail = $this->service->updateGameDetail($detail);

        $this->assertSame('Updated Name', $updatedDetail->getName());
        $this->assertSame('updated.png', $updatedDetail->getIconUrl());
        $this->assertSame('PS5', $updatedDetail->getPlatform());
        $this->assertSame('updated message', $updatedDetail->getMessage());
        $this->assertSame(null, $updatedDetail->getRegion());
        $this->assertSame(null, $updatedDetail->getPsnprofilesId());
        $this->assertSame(GameAvailabilityStatus::NORMAL, $updatedDetail->getStatus());
        $this->assertSame('55,66', $updatedDetail->getObsoleteIds());

        $metaRow = $this->database->query(
            "SELECT message, region, psnprofiles_id, status, obsolete_ids FROM trophy_title_meta WHERE np_communication_id = 'NPWR-456'"
        )->fetch(PDO::FETCH_ASSOC);

        $this->assertSame([
            'message' => 'updated message',
            'region' => null,
            'psnprofiles_id' => null,
            'status' => 0,
            'obsolete_ids' => '55,66',
        ], $metaRow);
    }

    /**
     * @param array<string, mixed> $title
     * @param array<string, mixed> $meta
     */
    private function insertGame(array $title, array $meta): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO trophy_title (id, np_communication_id, name, icon_url, platform, set_version)
            VALUES (:id, :np_communication_id, :name, :icon_url, :platform, :set_version)'
        );
        $statement->execute([
            ':id' => $title['id'],
            ':np_communication_id' => $title['np_communication_id'],
            ':name' => $title['name'],
            ':icon_url' => $title['icon_url'],
            ':platform' => $title['platform'],
            ':set_version' => $title['set_version'],
        ]);

        $metaStatement = $this->database->prepare(
            'INSERT INTO trophy_title_meta (np_communication_id, message, region, psnprofiles_id, status, obsolete_ids)
            VALUES (:np_communication_id, :message, :region, :psnprofiles_id, :status, :obsolete_ids)'
        );
        $metaStatement->execute([
            ':np_communication_id' => $title['np_communication_id'],
            ':message' => $meta['message'],
            ':region' => $meta['region'] ?? null,
            ':psnprofiles_id' => $meta['psnprofiles_id'] ?? null,
            ':status' => $meta['status'] ?? 0,
            ':obsolete_ids' => $meta['obsolete_ids'] ?? null,
        ]);
    }
}

