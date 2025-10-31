<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/GameHeaderService.php';
require_once __DIR__ . '/../wwwroot/classes/Game/GameDetails.php';

final class GameHeaderServiceTest extends TestCase
{
    private PDO $database;

    private GameHeaderService $service;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->database->exec(
            'CREATE TABLE trophy_title (' .
            'id INTEGER PRIMARY KEY, ' .
            'np_communication_id TEXT NOT NULL, ' .
            '`name` TEXT NOT NULL, ' .
            'platform TEXT NOT NULL)'
        );

        $this->database->exec(
            'CREATE TABLE trophy_title_meta (' .
            'np_communication_id TEXT PRIMARY KEY, ' .
            'parent_np_communication_id TEXT NULL, ' .
            'region TEXT NULL)'
        );

        $this->database->exec(
            'CREATE TABLE trophy (' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
            'np_communication_id TEXT NOT NULL)'
        );

        $this->database->exec(
            'CREATE TABLE trophy_meta (' .
            'trophy_id INTEGER PRIMARY KEY, ' .
            '`status` INTEGER NOT NULL, ' .
            'FOREIGN KEY(trophy_id) REFERENCES trophy(id))'
        );

        $this->service = new GameHeaderService($this->database);
    }

    public function testBuildHeaderDataIncludesParentStacksAndUnobtainableCount(): void
    {
        $this->insertTrophyTitle([
            'id' => 100,
            'np_communication_id' => 'PARENT-GAME',
            'parent_np_communication_id' => null,
            'name' => 'Parent Game',
            'platform' => 'PS4',
            'region' => 'US',
        ]);

        $this->insertTrophyTitle([
            'id' => 101,
            'np_communication_id' => 'STACK-1',
            'parent_np_communication_id' => 'MERGE-123',
            'name' => 'Stack Alpha',
            'platform' => 'PS5',
            'region' => 'NA',
        ]);

        $this->insertTrophyTitle([
            'id' => 102,
            'np_communication_id' => 'STACK-2',
            'parent_np_communication_id' => 'MERGE-123',
            'name' => 'Stack Beta',
            'platform' => 'PS4',
            'region' => '',
        ]);

        $this->insertTrophy([
            'status' => 1,
            'np_communication_id' => 'MERGE-123',
        ]);

        $this->insertTrophy([
            'status' => 1,
            'np_communication_id' => 'MERGE-123',
        ]);

        $this->insertTrophy([
            'status' => 0,
            'np_communication_id' => 'MERGE-123',
        ]);

        $game = $this->createGameDetails([
            'status' => 2,
            'np_communication_id' => 'MERGE-123',
            'parent_np_communication_id' => 'PARENT-GAME',
        ]);

        $headerData = $this->service->buildHeaderData($game);

        $this->assertTrue($headerData->hasMergedParent());
        $this->assertSame(100, $headerData->getParentGame()?->getId());
        $this->assertSame('Parent Game', $headerData->getParentGame()?->getName());

        $this->assertTrue($headerData->hasStacks());
        $this->assertCount(2, $headerData->getStacks());
        $this->assertSame('Stack Alpha', $headerData->getStacks()[0]->getName());
        $this->assertSame('PS5', $headerData->getStacks()[0]->getPlatform());
        $this->assertSame('NA', $headerData->getStacks()[0]->getRegion());
        $this->assertSame('Stack Beta', $headerData->getStacks()[1]->getName());
        $this->assertSame('PS4', $headerData->getStacks()[1]->getPlatform());
        $this->assertSame(null, $headerData->getStacks()[1]->getRegion());

        $this->assertSame(2, $headerData->getUnobtainableTrophyCount());
        $this->assertTrue($headerData->hasUnobtainableTrophies());
    }

    public function testBuildHeaderDataOmitsOptionalDataWhenNotAvailable(): void
    {
        $game = $this->createGameDetails([
            'status' => 1,
            'np_communication_id' => 'NPWR-1',
            'parent_np_communication_id' => 'PARENT-GAME',
        ]);

        $headerData = $this->service->buildHeaderData($game);

        $this->assertFalse($headerData->hasMergedParent());
        $this->assertSame(null, $headerData->getParentGame());
        $this->assertFalse($headerData->hasStacks());
        $this->assertSame([], $headerData->getStacks());
        $this->assertSame(0, $headerData->getUnobtainableTrophyCount());
        $this->assertFalse($headerData->hasUnobtainableTrophies());
    }

    /**
     * @param array{status:int,np_communication_id:string} $trophy
     */
    private function insertTrophy(array $trophy): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO trophy (np_communication_id) VALUES (:np_communication_id)'
        );
        $statement->bindValue(':np_communication_id', $trophy['np_communication_id'], PDO::PARAM_STR);
        $statement->execute();

        $trophyId = (int) $this->database->lastInsertId();

        $metaStatement = $this->database->prepare(
            'INSERT INTO trophy_meta (trophy_id, `status`) VALUES (:trophy_id, :status)'
        );
        $metaStatement->bindValue(':trophy_id', $trophyId, PDO::PARAM_INT);
        $metaStatement->bindValue(':status', $trophy['status'], PDO::PARAM_INT);
        $metaStatement->execute();
    }

    /**
     * @param array{id:int,np_communication_id:string,parent_np_communication_id:?string,name:string,platform:string,region:?string} $row
     */
    private function insertTrophyTitle(array $row): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO trophy_title (id, np_communication_id, `name`, platform) VALUES (:id, :np, :name, :platform)'
        );
        $statement->bindValue(':id', $row['id'], PDO::PARAM_INT);
        $statement->bindValue(':np', $row['np_communication_id'], PDO::PARAM_STR);
        $statement->bindValue(':name', $row['name'], PDO::PARAM_STR);
        $statement->bindValue(':platform', $row['platform'], PDO::PARAM_STR);
        $statement->execute();

        $meta = $this->database->prepare(
            'INSERT INTO trophy_title_meta (np_communication_id, parent_np_communication_id, region) VALUES (:np, :parent, :region)'
        );
        $meta->bindValue(':np', $row['np_communication_id'], PDO::PARAM_STR);
        if ($row['parent_np_communication_id'] === null) {
            $meta->bindValue(':parent', null, PDO::PARAM_NULL);
        } else {
            $meta->bindValue(':parent', $row['parent_np_communication_id'], PDO::PARAM_STR);
        }
        if ($row['region'] === null || $row['region'] === '') {
            $meta->bindValue(':region', null, PDO::PARAM_NULL);
        } else {
            $meta->bindValue(':region', $row['region'], PDO::PARAM_STR);
        }
        $meta->execute();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createGameDetails(array $overrides): GameDetails
    {
        $defaults = [
            'id' => 1,
            'name' => 'Example Game',
            'np_communication_id' => 'NPWR-DEFAULT',
            'parent_np_communication_id' => null,
            'platform' => 'PS4',
            'icon_url' => 'icon.png',
            'set_version' => '01.00',
            'region' => 'US',
            'message' => null,
            'platinum' => 0,
            'gold' => 0,
            'silver' => 0,
            'bronze' => 0,
            'owners_completed' => 0,
            'owners' => 0,
            'difficulty' => 'Normal',
            'status' => 0,
            'rarity_points' => 0,
        ];

        return GameDetails::fromArray(array_merge($defaults, $overrides));
    }
}
