<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Admin/GameCopyService.php';

final class GameCopyServiceParentOwnershipTest extends TestCase
{
    private PDO $database;
    private GameCopyService $service;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createTables();
        $this->service = new GameCopyService($this->database);
    }

    public function testCopyChildToParentRejectsDifferentExistingParent(): void
    {
        $this->insertTitle(1, 'MERGE_000001');
        $this->insertTitle(2, 'MERGE_000002');
        $this->insertTitle(3, 'NPWR_CHILD01');
        $this->insertMeta('MERGE_000001');
        $this->insertMeta('MERGE_000002');
        $this->insertMeta('NPWR_CHILD01', 'MERGE_000001');

        try {
            $this->service->copyChildToParent(3, 2);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'A child game can only have one parent. This game is already merged into MERGE_000001.',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            'MERGE_000001',
            $this->database
                ->query("SELECT parent_np_communication_id FROM trophy_title_meta WHERE np_communication_id = 'NPWR_CHILD01'")
                ->fetchColumn()
        );
    }

    public function testCopyChildToParentRejectsLegacyConflictingMergeMappings(): void
    {
        $this->insertTitle(1, 'MERGE_000003');
        $this->insertTitle(2, 'MERGE_000004');
        $this->insertTitle(3, 'NPWR_CHILD01');
        $this->insertMeta('MERGE_000003');
        $this->insertMeta('MERGE_000004');
        $this->insertMeta('NPWR_CHILD01');
        $this->database->exec(
            "INSERT INTO trophy_merge (
                child_np_communication_id, child_group_id, child_order_id,
                parent_np_communication_id, parent_group_id, parent_order_id
             ) VALUES
             ('NPWR_CHILD01', 'default', 1, 'MERGE_000003', 'default', 1),
             ('NPWR_CHILD01', 'default', 2, 'MERGE_000004', 'default', 1)"
        );

        try {
            $this->service->copyChildToParent(3, 1);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'A child game can only have one parent. This game has conflicting merge mappings to '
                . 'MERGE_000003, MERGE_000004 and must be repaired before continuing.',
                $exception->getMessage()
            );
        }
    }

    private function createTables(): void
    {
        $this->database->exec(
            'CREATE TABLE trophy_title (
                id INTEGER PRIMARY KEY,
                np_communication_id TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy_title_meta (
                np_communication_id TEXT PRIMARY KEY,
                message TEXT NOT NULL DEFAULT \'\',
                status INTEGER NOT NULL DEFAULT 0,
                parent_np_communication_id TEXT NULL
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

    private function insertTitle(int $id, string $npCommunicationId): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO trophy_title (id, np_communication_id) VALUES (:id, :np)'
        );
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->bindValue(':np', $npCommunicationId, PDO::PARAM_STR);
        $statement->execute();
    }

    private function insertMeta(string $npCommunicationId, ?string $parentNpCommunicationId = null): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO trophy_title_meta (np_communication_id, status, parent_np_communication_id, message)
             VALUES (:np, :status, :parent, \'\')'
        );
        $statement->bindValue(':np', $npCommunicationId, PDO::PARAM_STR);
        $statement->bindValue(
            ':status',
            $parentNpCommunicationId === null ? 0 : 2,
            PDO::PARAM_INT
        );
        $statement->bindValue(
            ':parent',
            $parentNpCommunicationId,
            $parentNpCommunicationId === null ? PDO::PARAM_NULL : PDO::PARAM_STR
        );
        $statement->execute();
    }
}
