<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/TrophyMergeService.php';

final class TrophyMergeServiceMetaUsageTest extends TestCase
{
    private PDO $database;
    private TrophyMergeService $service;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createTables();

        $this->service = new TrophyMergeService($this->database);
    }

    public function testUpdateParentRelationshipStoresParentInMeta(): void
    {
        $this->insertGame(1, 'NP_PARENT', 'PS4');
        $this->insertGame(2, 'NP_CHILD', 'PS4,PS5');
        $this->insertMeta('NP_PARENT', null, 0);
        $this->insertMeta('NP_CHILD', null, 0);

        $method = new ReflectionMethod(TrophyMergeService::class, 'updateParentRelationship');
        $method->setAccessible(true);
        $method->invoke($this->service, 'NP_CHILD', 'NP_PARENT');

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

        $method = new ReflectionMethod(TrophyMergeService::class, 'markGameAsMergedByNpId');
        $method->setAccessible(true);
        $method->invoke($this->service, 'NP_CHILD');

        $status = $this->database
            ->query("SELECT status FROM trophy_title_meta WHERE np_communication_id = 'NP_CHILD'")
            ->fetchColumn();
        $this->assertSame(2, (int) $status, 'Expected merged game status to be stored in meta table.');
    }

    public function testMarkGameAsMergedByIdUpdatesMetaStatus(): void
    {
        $this->insertGame(42, 'NP_CHILD', 'PS4');
        $this->insertMeta('NP_CHILD', null, 0);

        $method = new ReflectionMethod(TrophyMergeService::class, 'markGameAsMergedById');
        $method->setAccessible(true);
        $method->invoke($this->service, 42);

        $status = $this->database
            ->query("SELECT status FROM trophy_title_meta WHERE np_communication_id = 'NP_CHILD'")
            ->fetchColumn();
        $this->assertSame(2, (int) $status, 'Expected merged game status to be stored in meta table.');
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
                status INTEGER NOT NULL DEFAULT 0
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
