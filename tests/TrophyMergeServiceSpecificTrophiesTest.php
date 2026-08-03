<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/GameAvailabilityStatus.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyMergeService.php';

final class TrophyMergeServiceSpecificTrophiesTest extends TestCase
{
    private PDO $database;
    private TrophyMergeService $service;

    protected function setUp(): void
    {
        $this->database = new TrophyMergeServiceSpecificTrophiesTestPDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createTables();

        $this->service = new TrophyMergeService($this->database);
    }

    public function testMergeSpecificTrophiesSetsParentRelationshipAndMergedStatus(): void
    {
        $this->insertTitle(1, 'MERGE_000001', 'PS5');
        $this->insertTitle(2, 'NPWR_CHILD01', 'PS4');
        $this->insertMeta('MERGE_000001');
        $this->insertMeta('NPWR_CHILD01');
        $this->insertTrophy(10, 'MERGE_000001', 'default', 1);
        $this->insertTrophy(20, 'NPWR_CHILD01', 'default', 1);

        $message = $this->service->mergeSpecificTrophies(10, [20]);

        $this->assertSame('The trophies have been merged.', $message);

        $childMeta = $this->database
            ->query("SELECT status, parent_np_communication_id FROM trophy_title_meta WHERE np_communication_id = 'NPWR_CHILD01'")
            ->fetch(PDO::FETCH_ASSOC);

        $this->assertTrue($childMeta !== false);
        $this->assertSame(GameAvailabilityStatus::MERGED->value, (int) $childMeta['status']);
        $this->assertSame('MERGE_000001', $childMeta['parent_np_communication_id']);

        $parentPlatform = $this->database
            ->query("SELECT platform FROM trophy_title WHERE np_communication_id = 'MERGE_000001'")
            ->fetchColumn();
        $this->assertSame('PS4,PS5', $parentPlatform);

        $mappingCount = $this->database
            ->query(
                "SELECT COUNT(*) FROM trophy_merge
                 WHERE child_np_communication_id = 'NPWR_CHILD01'
                   AND parent_np_communication_id = 'MERGE_000001'"
            )
            ->fetchColumn();
        $this->assertSame(1, (int) $mappingCount);
    }

    public function testMergeSpecificTrophiesUpdatesParentForEachDistinctChildGame(): void
    {
        $this->insertTitle(1, 'MERGE_000002', 'PS5');
        $this->insertTitle(2, 'NPWR_CHILD_A', 'PS4');
        $this->insertTitle(3, 'NPWR_CHILD_B', 'PS5');
        $this->insertMeta('MERGE_000002');
        $this->insertMeta('NPWR_CHILD_A');
        $this->insertMeta('NPWR_CHILD_B');
        $this->insertTrophy(11, 'MERGE_000002', 'default', 1);
        $this->insertTrophy(12, 'MERGE_000002', 'default', 2);
        $this->insertTrophy(21, 'NPWR_CHILD_A', 'default', 1);
        $this->insertTrophy(31, 'NPWR_CHILD_B', 'default', 1);

        $this->service->mergeSpecificTrophies(11, [21]);
        $this->service->mergeSpecificTrophies(12, [31]);

        $childAParent = $this->database
            ->query("SELECT parent_np_communication_id FROM trophy_title_meta WHERE np_communication_id = 'NPWR_CHILD_A'")
            ->fetchColumn();
        $childBParent = $this->database
            ->query("SELECT parent_np_communication_id FROM trophy_title_meta WHERE np_communication_id = 'NPWR_CHILD_B'")
            ->fetchColumn();

        $this->assertSame('MERGE_000002', $childAParent);
        $this->assertSame('MERGE_000002', $childBParent);
    }

    public function testMergeSpecificTrophiesAllowsAdditionalTrophiesForSameParent(): void
    {
        $this->insertTitle(1, 'MERGE_000003', 'PS5');
        $this->insertTitle(2, 'NPWR_CHILD01', 'PS4');
        $this->insertMeta('MERGE_000003');
        $this->insertMeta('NPWR_CHILD01');
        $this->insertTrophy(10, 'MERGE_000003', 'default', 1);
        $this->insertTrophy(11, 'MERGE_000003', 'default', 2);
        $this->insertTrophy(20, 'NPWR_CHILD01', 'default', 1);
        $this->insertTrophy(21, 'NPWR_CHILD01', 'default', 2);

        $this->service->mergeSpecificTrophies(10, [20]);
        $message = $this->service->mergeSpecificTrophies(11, [21]);

        $this->assertSame('The trophies have been merged.', $message);
        $this->assertSame(
            'MERGE_000003',
            $this->database
                ->query("SELECT parent_np_communication_id FROM trophy_title_meta WHERE np_communication_id = 'NPWR_CHILD01'")
                ->fetchColumn()
        );
        $this->assertSame(
            2,
            (int) $this->database->query(
                "SELECT COUNT(*) FROM trophy_merge WHERE child_np_communication_id = 'NPWR_CHILD01'"
            )->fetchColumn()
        );
    }

    public function testMergeSpecificTrophiesRejectsSecondParent(): void
    {
        $this->insertTitle(1, 'MERGE_000004', 'PS5');
        $this->insertTitle(2, 'MERGE_000005', 'PS5');
        $this->insertTitle(3, 'NPWR_CHILD01', 'PS4');
        $this->insertMeta('MERGE_000004');
        $this->insertMeta('MERGE_000005');
        $this->insertMeta('NPWR_CHILD01');
        $this->insertTrophy(10, 'MERGE_000004', 'default', 1);
        $this->insertTrophy(11, 'MERGE_000005', 'default', 1);
        $this->insertTrophy(20, 'NPWR_CHILD01', 'default', 1);
        $this->insertTrophy(21, 'NPWR_CHILD01', 'default', 2);

        $this->service->mergeSpecificTrophies(10, [20]);

        try {
            $this->service->mergeSpecificTrophies(11, [21]);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'A child game can only have one parent. This game is already merged into MERGE_000004.',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            'MERGE_000004',
            $this->database
                ->query("SELECT parent_np_communication_id FROM trophy_title_meta WHERE np_communication_id = 'NPWR_CHILD01'")
                ->fetchColumn()
        );
        $this->assertSame(
            0,
            (int) $this->database->query(
                "SELECT COUNT(*) FROM trophy_merge WHERE parent_np_communication_id = 'MERGE_000005'"
            )->fetchColumn()
        );
    }

    public function testMergeGamesRejectsSecondParent(): void
    {
        $this->insertTitle(1, 'MERGE_000006', 'PS5');
        $this->insertTitle(2, 'MERGE_000007', 'PS5');
        $this->insertTitle(3, 'NPWR_CHILD01', 'PS4');
        $this->insertMeta('MERGE_000006');
        $this->insertMeta('MERGE_000007');
        $this->insertMeta('NPWR_CHILD01', 'MERGE_000006');

        try {
            $this->service->mergeGames(3, 2, TrophyMergeMethod::Order);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'A child game can only have one parent. This game is already merged into MERGE_000006.',
                $exception->getMessage()
            );
        }
    }

    public function testMergeSpecificTrophiesRejectsLegacyConflictingMergeMappings(): void
    {
        $this->insertTitle(1, 'MERGE_000008', 'PS5');
        $this->insertTitle(2, 'MERGE_000009', 'PS5');
        $this->insertTitle(3, 'NPWR_CHILD01', 'PS4');
        $this->insertMeta('MERGE_000008');
        $this->insertMeta('MERGE_000009');
        $this->insertMeta('NPWR_CHILD01');
        $this->insertTrophy(10, 'MERGE_000008', 'default', 1);
        $this->insertTrophy(20, 'NPWR_CHILD01', 'default', 3);
        // Legacy state: trophies from the same child were mapped to two different parents.
        $this->insertTrophyMergeMapping('NPWR_CHILD01', 'default', 1, 'MERGE_000008', 'default', 1);
        $this->insertTrophyMergeMapping('NPWR_CHILD01', 'default', 2, 'MERGE_000009', 'default', 1);

        try {
            // Even merging into the lexicographically first parent must fail until repaired.
            $this->service->mergeSpecificTrophies(10, [20]);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'A child game can only have one parent. This game has conflicting merge mappings to '
                . 'MERGE_000008, MERGE_000009 and must be repaired before merging again.',
                $exception->getMessage()
            );
        }
    }

    public function testMergeSpecificTrophiesRejectsMetaAndMergeParentMismatch(): void
    {
        $this->insertTitle(1, 'MERGE_000010', 'PS5');
        $this->insertTitle(2, 'MERGE_000011', 'PS5');
        $this->insertTitle(3, 'NPWR_CHILD01', 'PS4');
        $this->insertMeta('MERGE_000010');
        $this->insertMeta('MERGE_000011');
        $this->insertMeta('NPWR_CHILD01', 'MERGE_000010');
        $this->insertTrophy(10, 'MERGE_000010', 'default', 1);
        $this->insertTrophy(20, 'NPWR_CHILD01', 'default', 2);
        $this->insertTrophyMergeMapping('NPWR_CHILD01', 'default', 1, 'MERGE_000011', 'default', 1);

        try {
            $this->service->mergeSpecificTrophies(10, [20]);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'A child game can only have one parent. This game has conflicting parents '
                . 'MERGE_000010 and MERGE_000011 and must be repaired before merging again.',
                $exception->getMessage()
            );
        }
    }

    private function createTables(): void
    {
        $this->database->exec(
            'CREATE TABLE trophy_title (
                id INTEGER PRIMARY KEY,
                np_communication_id TEXT NOT NULL,
                platform TEXT NOT NULL DEFAULT \'\',
                platinum INTEGER NOT NULL DEFAULT 0,
                bronze INTEGER NOT NULL DEFAULT 0,
                silver INTEGER NOT NULL DEFAULT 0,
                gold INTEGER NOT NULL DEFAULT 0
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy_title_meta (
                np_communication_id TEXT PRIMARY KEY,
                status INTEGER NOT NULL DEFAULT 0,
                parent_np_communication_id TEXT NULL,
                message TEXT NOT NULL DEFAULT \'\'
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy (
                id INTEGER PRIMARY KEY,
                np_communication_id TEXT NOT NULL,
                group_id TEXT NOT NULL,
                order_id INTEGER NOT NULL
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

    private function insertTitle(int $id, string $npCommunicationId, string $platform): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO trophy_title (id, np_communication_id, platform) VALUES (:id, :np, :platform)'
        );
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->bindValue(':np', $npCommunicationId, PDO::PARAM_STR);
        $statement->bindValue(':platform', $platform, PDO::PARAM_STR);
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
            $parentNpCommunicationId === null
                ? GameAvailabilityStatus::NORMAL->value
                : GameAvailabilityStatus::MERGED->value,
            PDO::PARAM_INT
        );
        $statement->bindValue(
            ':parent',
            $parentNpCommunicationId,
            $parentNpCommunicationId === null ? PDO::PARAM_NULL : PDO::PARAM_STR
        );
        $statement->execute();
    }

    private function insertTrophy(int $id, string $npCommunicationId, string $groupId, int $orderId): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO trophy (id, np_communication_id, group_id, order_id)
             VALUES (:id, :np, :group_id, :order_id)'
        );
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->bindValue(':np', $npCommunicationId, PDO::PARAM_STR);
        $statement->bindValue(':group_id', $groupId, PDO::PARAM_STR);
        $statement->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $statement->execute();
    }

    private function insertTrophyMergeMapping(
        string $childNpCommunicationId,
        string $childGroupId,
        int $childOrderId,
        string $parentNpCommunicationId,
        string $parentGroupId,
        int $parentOrderId
    ): void {
        $statement = $this->database->prepare(
            'INSERT INTO trophy_merge (
                child_np_communication_id,
                child_group_id,
                child_order_id,
                parent_np_communication_id,
                parent_group_id,
                parent_order_id
             ) VALUES (
                :child_np,
                :child_group,
                :child_order,
                :parent_np,
                :parent_group,
                :parent_order
             )'
        );
        $statement->bindValue(':child_np', $childNpCommunicationId, PDO::PARAM_STR);
        $statement->bindValue(':child_group', $childGroupId, PDO::PARAM_STR);
        $statement->bindValue(':child_order', $childOrderId, PDO::PARAM_INT);
        $statement->bindValue(':parent_np', $parentNpCommunicationId, PDO::PARAM_STR);
        $statement->bindValue(':parent_group', $parentGroupId, PDO::PARAM_STR);
        $statement->bindValue(':parent_order', $parentOrderId, PDO::PARAM_INT);
        $statement->execute();
    }
}

/**
 * SQLite stand-in that accepts MySQL INSERT IGNORE and no-ops heavy player-progress SQL.
 */
final class TrophyMergeServiceSpecificTrophiesTestPDO extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $query = str_ireplace('INSERT IGNORE', 'INSERT OR IGNORE', $query);
        $normalized = preg_replace('/\s+/', ' ', trim($query)) ?? '';

        if (
            str_contains($normalized, 'ON DUPLICATE KEY')
            || str_contains($normalized, 'INSERT INTO trophy_group_player')
            || str_contains($normalized, 'INSERT INTO trophy_title_player')
            || str_contains($normalized, 'FROM player')
        ) {
            return new TrophyMergeServiceSpecificTrophiesNoOpStatement();
        }

        return parent::prepare($query, $options);
    }
}

final class TrophyMergeServiceSpecificTrophiesNoOpStatement extends PDOStatement
{
    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        return true;
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return [];
    }
}
