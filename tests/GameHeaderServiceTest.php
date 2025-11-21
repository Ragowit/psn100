<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/GameHeaderService.php';
require_once __DIR__ . '/../wwwroot/classes/Game/GameDetails.php';

final class TestPsnpPlusClient extends PsnpPlusClient
{
    /**
     * @var array<int, string>
     */
    private array $notes = [];

    public function __construct()
    {
    }

    /**
     * @param array<int, string> $notes
     */
    public function withNotes(array $notes): void
    {
        $this->notes = $notes;
    }

    public function getNote(int $psnprofilesId): ?string
    {
        return $this->notes[$psnprofilesId] ?? null;
    }

    /**
     * @return array<int, int[]>
     */
    public function getTrophiesByPsnprofilesId(): array
    {
        return [];
    }
}

final class GameHeaderServiceTest extends TestCase
{
    private PDO $database;

    private GameHeaderService $service;

    private TestPsnpPlusClient $psnpPlusClient;

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
            'region TEXT NULL, ' .
            'obsolete_ids TEXT NULL, ' .
            'psnprofiles_id TEXT NULL)'
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

        $this->psnpPlusClient = new TestPsnpPlusClient();
        $this->service = new GameHeaderService($this->database, $this->psnpPlusClient);
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

        $this->insertTrophyTitle([
            'id' => 201,
            'np_communication_id' => 'REPL-1',
            'parent_np_communication_id' => null,
            'name' => 'Replacement One',
            'platform' => 'PS5',
            'region' => null,
        ]);

        $this->insertTrophyTitle([
            'id' => 202,
            'np_communication_id' => 'REPL-2',
            'parent_np_communication_id' => null,
            'name' => 'Replacement Two',
            'platform' => 'PS4',
            'region' => null,
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
            'obsolete_ids' => '202,201',
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
        $this->assertTrue($headerData->hasObsoleteReplacements());
        $this->assertCount(2, $headerData->getObsoleteReplacements());
        $this->assertSame(202, $headerData->getObsoleteReplacements()[0]->getId());
        $this->assertSame('Replacement Two', $headerData->getObsoleteReplacements()[0]->getName());
        $this->assertSame(201, $headerData->getObsoleteReplacements()[1]->getId());
        $this->assertSame('Replacement One', $headerData->getObsoleteReplacements()[1]->getName());
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
        $this->assertFalse($headerData->hasObsoleteReplacements());
        $this->assertSame([], $headerData->getObsoleteReplacements());
        $this->assertFalse($headerData->hasPsnpPlusNote());
    }

    public function testBuildHeaderDataIncludesPsnpPlusNote(): void
    {
        $this->psnpPlusClient->withNotes([
            321 => 'Servers were shutdown.',
        ]);

        $game = $this->createGameDetails([
            'psnprofiles_id' => 321,
        ]);

        $headerData = $this->service->buildHeaderData($game);

        $this->assertTrue($headerData->hasPsnpPlusNote());
        $this->assertSame('Servers were shutdown.', $headerData->getPsnpPlusNote());
    }

    public function testBuildHeaderDataConvertsPsnpPlusNoteMarkdownLinks(): void
    {
        $this->psnpPlusClient->withNotes([
            555 => 'Server fix in progress. [More info](https://example.com/fix).',
        ]);

        $game = $this->createGameDetails([
            'psnprofiles_id' => 555,
        ]);

        $headerData = $this->service->buildHeaderData($game);

        $this->assertSame(
            'Server fix in progress. <a href="https://example.com/fix" target="_blank" rel="noopener">More info</a>.',
            $headerData->getPsnpPlusNote()
        );
    }

    public function testBuildHeaderDataFallsBackToParentPsnprofilesNote(): void
    {
        $this->insertTrophyTitle([
            'id' => 999,
            'np_communication_id' => 'PARENT-GAME',
            'parent_np_communication_id' => null,
            'name' => 'Parent Game',
            'platform' => 'PS4',
            'region' => null,
            'psnprofiles_id' => '654',
        ]);

        $this->psnpPlusClient->withNotes([
            654 => 'See parent entry for details.',
        ]);

        $game = $this->createGameDetails([
            'np_communication_id' => 'CHILD-GAME',
            'parent_np_communication_id' => 'PARENT-GAME',
            'psnprofiles_id' => null,
        ]);

        $headerData = $this->service->buildHeaderData($game);

        $this->assertTrue($headerData->hasPsnpPlusNote());
        $this->assertSame('See parent entry for details.', $headerData->getPsnpPlusNote());
    }

    public function testBuildHeaderDataUsesChildNoteForMergeTitles(): void
    {
        $this->insertTrophyTitle([
            'id' => 555,
            'np_communication_id' => 'STACK-WITH-NOTE',
            'parent_np_communication_id' => 'MERGE-555',
            'name' => 'Stack With Note',
            'platform' => 'PS5',
            'region' => 'US',
            'psnprofiles_id' => '888',
        ]);

        $this->insertTrophyTitle([
            'id' => 556,
            'np_communication_id' => 'STACK-WITHOUT-NOTE',
            'parent_np_communication_id' => 'MERGE-555',
            'name' => 'Stack Without Note',
            'platform' => 'PS4',
            'region' => null,
            'psnprofiles_id' => null,
        ]);

        $this->psnpPlusClient->withNotes([
            888 => 'Child stack note.',
        ]);

        $game = $this->createGameDetails([
            'np_communication_id' => 'MERGE-555',
            'psnprofiles_id' => null,
        ]);

        $headerData = $this->service->buildHeaderData($game);

        $this->assertTrue($headerData->hasPsnpPlusNote());
        $this->assertSame('Child stack note.', $headerData->getPsnpPlusNote());
    }

    public function testBuildHeaderDataPrefersRegionalOrderForMergePsnpPlusNotes(): void
    {
        $this->insertTrophyTitle([
            'id' => 601,
            'np_communication_id' => 'STACK-EU',
            'parent_np_communication_id' => 'MERGE-600',
            'name' => 'Stack EU',
            'platform' => 'PS5',
            'region' => 'EU',
            'psnprofiles_id' => '3001',
        ]);

        $this->insertTrophyTitle([
            'id' => 602,
            'np_communication_id' => 'STACK-NA',
            'parent_np_communication_id' => 'MERGE-600',
            'name' => 'Stack NA',
            'platform' => 'PS5',
            'region' => 'NA',
            'psnprofiles_id' => '3002',
        ]);

        $this->insertTrophyTitle([
            'id' => 603,
            'np_communication_id' => 'STACK-NO-REGION',
            'parent_np_communication_id' => 'MERGE-600',
            'name' => 'Stack Without Region',
            'platform' => 'PS5',
            'region' => null,
            'psnprofiles_id' => '3003',
        ]);

        $this->insertTrophyTitle([
            'id' => 604,
            'np_communication_id' => 'STACK-HK',
            'parent_np_communication_id' => 'MERGE-600',
            'name' => 'Stack HK',
            'platform' => 'PS5',
            'region' => 'HK',
            'psnprofiles_id' => '3004',
        ]);

        $this->psnpPlusClient->withNotes([
            3001 => 'EU note.',
            3002 => 'NA note.',
            3003 => 'No region note.',
            3004 => 'HK note.',
        ]);

        $game = $this->createGameDetails([
            'np_communication_id' => 'MERGE-600',
            'psnprofiles_id' => null,
        ]);

        $headerData = $this->service->buildHeaderData($game);

        $this->assertTrue($headerData->hasPsnpPlusNote());
        $this->assertSame('NA note.', $headerData->getPsnpPlusNote());
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
     * @param array{id:int,np_communication_id:string,parent_np_communication_id:?string,name:string,platform:string,region:?string,obsolete_ids:?string} $row
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
            'INSERT INTO trophy_title_meta (np_communication_id, parent_np_communication_id, region, obsolete_ids, psnprofiles_id) VALUES (:np, :parent, :region, :obsolete_ids, :psnprofiles_id)'
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
        if (isset($row['obsolete_ids']) && $row['obsolete_ids'] !== null && $row['obsolete_ids'] !== '') {
            $meta->bindValue(':obsolete_ids', $row['obsolete_ids'], PDO::PARAM_STR);
        } else {
            $meta->bindValue(':obsolete_ids', null, PDO::PARAM_NULL);
        }
        if (isset($row['psnprofiles_id']) && $row['psnprofiles_id'] !== null && $row['psnprofiles_id'] !== '') {
            $meta->bindValue(':psnprofiles_id', $row['psnprofiles_id'], PDO::PARAM_STR);
        } else {
            $meta->bindValue(':psnprofiles_id', null, PDO::PARAM_NULL);
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
            'obsolete_ids' => null,
            'psnprofiles_id' => null,
        ];

        return GameDetails::fromArray(array_merge($defaults, $overrides));
    }
}
