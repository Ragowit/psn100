<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/NestedDatabaseTransactionRunner.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyMergeMetadataRepository.php';

final class TrophyMergeMetadataRepositoryTest extends TestCase
{
    private PDO $database;
    private TrophyMergeMetadataRepository $repository;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createTables();

        $this->repository = new TrophyMergeMetadataRepository(
            $this->database,
            new NestedDatabaseTransactionRunner($this->database)
        );
    }

    public function testUpdateParentRelationshipStoresParentInMeta(): void
    {
        $this->insertGame(1, 'NP_PARENT', 'PS4');
        $this->insertGame(2, 'NP_CHILD', 'PS4,PS5');
        $this->insertMeta('NP_PARENT', null, 0);
        $this->insertMeta('NP_CHILD', null, 0);

        $this->repository->updateParentRelationship('NP_CHILD', 'NP_PARENT');

        $parent = $this->database
            ->query("SELECT parent_np_communication_id FROM trophy_title_meta WHERE np_communication_id = 'NP_CHILD'")
            ->fetchColumn();
        $this->assertSame('NP_PARENT', $parent, 'Expected child to reference parent in meta table.');

        $platform = $this->database
            ->query("SELECT platform FROM trophy_title WHERE np_communication_id = 'NP_PARENT'")
            ->fetchColumn();
        $this->assertSame('PS4,PS5', $platform, 'Expected parent platforms to include child platforms.');
    }

    public function testMarkGameAsMergedByNpIdUpdatesMetaStatus(): void
    {
        $this->insertGame(1, 'NP_CHILD', 'PS4');
        $this->insertMeta('NP_CHILD', null, 0);

        $this->repository->markGameAsMergedByNpId('NP_CHILD');

        $status = $this->database
            ->query("SELECT status FROM trophy_title_meta WHERE np_communication_id = 'NP_CHILD'")
            ->fetchColumn();
        $this->assertSame(2, (int) $status, 'Expected merged game status to be stored in meta table.');
    }

    public function testMarkGameAsMergedByIdUpdatesMetaStatus(): void
    {
        $this->insertGame(42, 'NP_CHILD', 'PS4');
        $this->insertMeta('NP_CHILD', null, 0);

        $this->repository->markGameAsMergedById(42);

        $status = $this->database
            ->query("SELECT status FROM trophy_title_meta WHERE np_communication_id = 'NP_CHILD'")
            ->fetchColumn();
        $this->assertSame(2, (int) $status, 'Expected merged game status to be stored in meta table.');
    }

    public function testLogChangeInsertsChangelogRow(): void
    {
        $this->repository->logChange('GAME_MERGE', 10, 20);

        $row = $this->database
            ->query("SELECT change_type, param_1, param_2 FROM psn100_change")
            ->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('GAME_MERGE', $row['change_type']);
        $this->assertSame(10, (int) $row['param_1']);
        $this->assertSame(20, (int) $row['param_2']);
    }

    private function createTables(): void
    {
        $this->database->exec(
            'CREATE TABLE trophy_title (
                id INTEGER PRIMARY KEY,
                np_communication_id TEXT NOT NULL UNIQUE,
                platform TEXT NOT NULL
            )'
        );

        $this->database->exec(
            'CREATE TABLE trophy_title_meta (
                np_communication_id TEXT PRIMARY KEY,
                parent_np_communication_id TEXT NULL,
                status INTEGER NOT NULL DEFAULT 0,
                obsolete_ids TEXT NULL,
                psnprofiles_id TEXT NULL,
                in_game_rarity_points INTEGER NOT NULL DEFAULT 0
            )'
        );

        $this->database->exec(
            'CREATE TABLE psn100_change (
                change_type TEXT NOT NULL,
                param_1 INTEGER NOT NULL,
                param_2 INTEGER NOT NULL
            )'
        );
    }

    private function insertGame(int $id, string $npCommunicationId, string $platform): void
    {
        $query = $this->database->prepare(
            'INSERT INTO trophy_title (id, np_communication_id, platform) VALUES (:id, :np_communication_id, :platform)'
        );
        $query->bindValue(':id', $id, PDO::PARAM_INT);
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':platform', $platform, PDO::PARAM_STR);
        $query->execute();
    }

    private function insertMeta(string $npCommunicationId, ?string $parent, int $status): void
    {
        $query = $this->database->prepare(
            'INSERT INTO trophy_title_meta (np_communication_id, parent_np_communication_id, status) VALUES (:np_communication_id, :parent_np_communication_id, :status)'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);

        if ($parent === null) {
            $query->bindValue(':parent_np_communication_id', null, PDO::PARAM_NULL);
        } else {
            $query->bindValue(':parent_np_communication_id', $parent, PDO::PARAM_STR);
        }

        $query->bindValue(':status', $status, PDO::PARAM_INT);
        $query->execute();
    }
}
