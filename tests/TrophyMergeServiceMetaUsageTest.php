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

    public function testGetMergeParentAndChildrenPrefersMetaParent(): void
    {
        $this->insertMeta('NP_CHILD', 'NP_PARENT_2', 0);
        $this->insertMergeMapping('NP_CHILD', 'NP_PARENT_1');
        $this->insertMergeMapping('NP_CHILD', 'NP_PARENT_2');
        $this->insertMergeMapping('NP_CHILD_2', 'NP_PARENT_2');

        $method = new ReflectionMethod(TrophyMergeService::class, 'getMergeParentAndChildren');
        $method->setAccessible(true);
        $mergeData = $method->invoke($this->service, 'NP_CHILD');

        $this->assertSame('NP_PARENT_2', $mergeData['parent_np_communication_id']);
        $this->assertSame(['NP_CHILD', 'NP_CHILD_2'], $mergeData['child_np_communication_ids']);
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
            'CREATE TABLE trophy_merge (
                child_np_communication_id TEXT NOT NULL,
                child_group_id TEXT NOT NULL,
                child_order_id INTEGER NOT NULL,
                parent_np_communication_id TEXT NOT NULL,
                parent_group_id TEXT NOT NULL,
                parent_order_id INTEGER NOT NULL
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

    private function insertMergeMapping(string $childNpCommunicationId, string $parentNpCommunicationId): void
    {
        $query = $this->database->prepare(
            'INSERT INTO trophy_merge (
                child_np_communication_id,
                child_group_id,
                child_order_id,
                parent_np_communication_id,
                parent_group_id,
                parent_order_id
            ) VALUES (
                :child_np_communication_id,
                :child_group_id,
                :child_order_id,
                :parent_np_communication_id,
                :parent_group_id,
                :parent_order_id
            )'
        );
        $query->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':child_group_id', 'default', PDO::PARAM_STR);
        $query->bindValue(':child_order_id', 0, PDO::PARAM_INT);
        $query->bindValue(':parent_np_communication_id', $parentNpCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':parent_group_id', 'default', PDO::PARAM_STR);
        $query->bindValue(':parent_order_id', 0, PDO::PARAM_INT);
        $query->execute();
    }
}
