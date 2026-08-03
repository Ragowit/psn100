<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/GameAvailabilityStatus.php';
require_once __DIR__ . '/../wwwroot/classes/GameResetAction.php';
require_once __DIR__ . '/../wwwroot/classes/GameResetService.php';

final class GameResetServiceTest extends TestCase
{
    private PDO $database;
    private GameResetService $service;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createTables();

        $this->service = new GameResetService($this->database);
    }

    public function testProcessResetsMergedGame(): void
    {
        $this->insertMergedGame('MERGE-123', 1, 'Merged Game', 25, 10);
        $this->insertChildGame('NPWR-OTHER', 2, 'Child Game', 'MERGE-123', GameAvailabilityStatus::MERGED);

        $this->database->exec("INSERT INTO trophy_merge (parent_np_communication_id) VALUES ('MERGE-123')");
        $this->database->exec("INSERT INTO trophy_title_player (np_communication_id, account_id) VALUES ('MERGE-123', 1001)");
        $this->database->exec("INSERT INTO trophy_earned (np_communication_id, account_id) VALUES ('MERGE-123', 1001)");
        $this->database->exec("INSERT INTO trophy_group_player (np_communication_id) VALUES ('MERGE-123')");

        $message = $this->service->process(1, GameResetAction::RESET);

        $this->assertSame('Game 1 was reset.', $message);

        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM trophy_merge")->fetchColumn());
        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM trophy_earned")->fetchColumn());
        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM trophy_group_player")->fetchColumn());
        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM trophy_title_player")->fetchColumn());

        $owners = $this->database->query("SELECT owners FROM trophy_title_meta WHERE np_communication_id = 'MERGE-123'")->fetchColumn();
        $ownersCompleted = $this->database->query("SELECT owners_completed FROM trophy_title_meta WHERE np_communication_id = 'MERGE-123'")->fetchColumn();
        $this->assertSame(0, (int) $owners);
        $this->assertSame(0, (int) $ownersCompleted);

        $this->assertChildRestoredToNormal('NPWR-OTHER');

        $changes = $this->database
            ->query('SELECT change_type, param_1, extra FROM psn100_change')
            ->fetchAll(PDO::FETCH_ASSOC);

        $this->assertSame(
            [
                [
                    'change_type' => 'GAME_RESET',
                    'param_1' => 1,
                    'extra' => null,
                ],
            ],
            array_map(
                static fn (array $row): array => [
                    'change_type' => $row['change_type'],
                    'param_1' => (int) $row['param_1'],
                    'extra' => $row['extra'],
                ],
                $changes
            )
        );
    }

    public function testProcessDeletesMergedGame(): void
    {
        $this->insertMergedGame('MERGE-456', 1, 'Merged Game', 12, 4);
        $this->insertChildGame('NPWR-OTHER', 2, 'Child Game', 'MERGE-456', GameAvailabilityStatus::MERGED);

        $this->database->exec("INSERT INTO trophy_merge (parent_np_communication_id) VALUES ('MERGE-456')");
        $this->database->exec("INSERT INTO trophy (np_communication_id) VALUES ('MERGE-456')");
        $this->database->exec("INSERT INTO trophy_title_player (np_communication_id, account_id) VALUES ('MERGE-456', 1002)");
        $this->database->exec("INSERT INTO trophy_earned (np_communication_id, account_id) VALUES ('MERGE-456', 1002)");
        $this->database->exec("INSERT INTO trophy_group_player (np_communication_id) VALUES ('MERGE-456')");
        $this->database->exec("INSERT INTO trophy_group (np_communication_id) VALUES ('MERGE-456')");

        $tables = [
            'trophy_merge',
            'trophy',
            'trophy_earned',
            'trophy_group_player',
            'trophy_title_player',
            'trophy_group',
        ];

        $message = $this->service->process(1, GameResetAction::DELETE);

        $this->assertSame('Game 1 was deleted.', $message);

        foreach ($tables as $table) {
            $this->assertSame(0, (int) $this->database->query(sprintf('SELECT COUNT(*) FROM %s', $table))->fetchColumn());
        }

        $remainingTitle = $this->database->query('SELECT COUNT(*) FROM trophy_title WHERE id = 1')->fetchColumn();
        $this->assertSame(0, (int) $remainingTitle);

        $this->assertChildRestoredToNormal('NPWR-OTHER');

        $changes = $this->database
            ->query('SELECT change_type, param_1, extra FROM psn100_change')
            ->fetchAll(PDO::FETCH_ASSOC);

        $this->assertSame(
            [
                [
                    'change_type' => 'GAME_DELETE',
                    'param_1' => 1,
                    'extra' => 'Merged Game',
                ],
            ],
            array_map(
                static fn (array $row): array => [
                    'change_type' => $row['change_type'],
                    'param_1' => (int) $row['param_1'],
                    'extra' => $row['extra'],
                ],
                $changes
            )
        );
    }

    public function testProcessDeleteRestoresMultipleMergedChildrenToNormal(): void
    {
        $this->insertMergedGame('MERGE-789', 1, 'Merged Game', 8, 3);
        $this->insertChildGame('NPWR-CHILD-A', 2, 'Child A', 'MERGE-789', GameAvailabilityStatus::MERGED);
        $this->insertChildGame('NPWR-CHILD-B', 3, 'Child B', 'MERGE-789', GameAvailabilityStatus::MERGED);
        // Unrelated title must keep its own status and parent link.
        $this->insertChildGame('NPWR-OTHER-PARENT', 4, 'Other Child', 'MERGE-OTHER', GameAvailabilityStatus::MERGED);
        $this->database->exec("INSERT INTO trophy_title (id, np_communication_id, name) VALUES (5, 'NPWR-DELISTED', 'Delisted Sibling')");
        $this->database->exec(
            "INSERT INTO trophy_title_meta (np_communication_id, owners, owners_completed, parent_np_communication_id, status)
             VALUES ('NPWR-DELISTED', 1, 0, 'MERGE-789', " . GameAvailabilityStatus::DELISTED->value . ')'
        );

        $this->service->process(1, GameResetAction::DELETE);

        $this->assertChildRestoredToNormal('NPWR-CHILD-A');
        $this->assertChildRestoredToNormal('NPWR-CHILD-B');

        $otherChild = $this->database
            ->query("SELECT parent_np_communication_id, status FROM trophy_title_meta WHERE np_communication_id = 'NPWR-OTHER-PARENT'")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('MERGE-OTHER', $otherChild['parent_np_communication_id']);
        $this->assertSame(GameAvailabilityStatus::MERGED->value, (int) $otherChild['status']);

        $delistedSibling = $this->database
            ->query("SELECT parent_np_communication_id, status FROM trophy_title_meta WHERE np_communication_id = 'NPWR-DELISTED'")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(null, $delistedSibling['parent_np_communication_id']);
        $this->assertSame(GameAvailabilityStatus::DELISTED->value, (int) $delistedSibling['status']);
    }

    public function testProcessThrowsWhenGameEntryIsMissing(): void
    {
        try {
            $this->service->process(99, GameResetAction::RESET);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Can only reset/delete merged game entries.', $exception->getMessage());
        }
    }

    public function testProcessThrowsWhenGameIsNotMerged(): void
    {
        $this->database->exec("INSERT INTO trophy_title (id, np_communication_id, name) VALUES (5, 'NPWR-123', 'Regular Game')");
        $this->database->exec(
            "INSERT INTO trophy_title_meta (np_communication_id, owners, owners_completed, parent_np_communication_id, status)
             VALUES ('NPWR-123', 1, 0, NULL, " . GameAvailabilityStatus::NORMAL->value . ')'
        );

        try {
            $this->service->process(5, GameResetAction::RESET);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Can only reset/delete merged game entries.', $exception->getMessage());
        }
    }

    public function testProcessDeletesAllTrophyEarnedRowsForTitleIncludingOrphans(): void
    {
        $this->insertMergedGame('MERGE-ACC', 11, 'Merged Game', 3, 1);
        $this->database->exec("INSERT INTO trophy_title_player (np_communication_id, account_id) VALUES ('MERGE-ACC', 2001)");
        $this->database->exec("INSERT INTO trophy_earned (np_communication_id, account_id) VALUES ('MERGE-ACC', 2001)");
        // Orphan row without trophy_title_player must still be removed on reset/delete.
        $this->database->exec("INSERT INTO trophy_earned (np_communication_id, account_id) VALUES ('MERGE-ACC', 2002)");

        $this->service->process(11, GameResetAction::RESET);

        $this->assertSame(
            0,
            (int) $this->database->query("SELECT COUNT(*) FROM trophy_earned WHERE np_communication_id = 'MERGE-ACC'")->fetchColumn()
        );
    }

    public function testProcessRollsBackWhenStatementFails(): void
    {
        $this->insertMergedGame('MERGE-999', 9, 'Merge Failure Game', 33, 12);
        $this->database->exec("INSERT INTO trophy_merge (parent_np_communication_id) VALUES ('MERGE-999')");
        $this->database->exec('CREATE TRIGGER fail_delete BEFORE DELETE ON trophy_merge BEGIN SELECT RAISE(ABORT, "delete failure"); END;');

        try {
            $this->service->process(9, GameResetAction::RESET);
            $this->fail('Expected exception was not thrown.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('delete failure', $exception->getMessage());
        }

        $owners = $this->database->query("SELECT owners FROM trophy_title_meta WHERE np_communication_id = 'MERGE-999'")->fetchColumn();
        $ownersCompleted = $this->database->query("SELECT owners_completed FROM trophy_title_meta WHERE np_communication_id = 'MERGE-999'")->fetchColumn();
        $this->assertSame(33, (int) $owners);
        $this->assertSame(12, (int) $ownersCompleted);

        $changeCount = $this->database
            ->query('SELECT COUNT(*) FROM psn100_change')
            ->fetchColumn();
        $this->assertSame(0, (int) $changeCount);
    }

    private function createTables(): void
    {
        $this->database->exec('CREATE TABLE trophy_title (
            id INTEGER PRIMARY KEY,
            np_communication_id TEXT,
            name TEXT
        )');
        $this->database->exec('CREATE TABLE trophy_title_meta (
            np_communication_id TEXT PRIMARY KEY,
            owners INTEGER DEFAULT 0,
            owners_completed INTEGER DEFAULT 0,
            rarity_points INTEGER NOT NULL DEFAULT 0,
            in_game_rarity_points INTEGER NOT NULL DEFAULT 0,
            parent_np_communication_id TEXT,
            status INTEGER NOT NULL DEFAULT 0,
            obsolete_ids TEXT NULL,
            psnprofiles_id TEXT NULL
        )');
        $this->database->exec('CREATE TABLE trophy_merge (parent_np_communication_id TEXT)');
        $this->database->exec('CREATE TABLE trophy_earned (
            np_communication_id TEXT,
            account_id INTEGER
        )');
        $this->database->exec('CREATE TABLE trophy_group_player (np_communication_id TEXT)');
        $this->database->exec('CREATE TABLE trophy_title_player (
            np_communication_id TEXT,
            account_id INTEGER,
            rarity_points INTEGER NOT NULL DEFAULT 0,
            in_game_rarity_points INTEGER NOT NULL DEFAULT 0,
            in_game_common INTEGER NOT NULL DEFAULT 0,
            in_game_uncommon INTEGER NOT NULL DEFAULT 0,
            in_game_rare INTEGER NOT NULL DEFAULT 0,
            in_game_epic INTEGER NOT NULL DEFAULT 0,
            in_game_legendary INTEGER NOT NULL DEFAULT 0
        )');
        $this->database->exec('CREATE TABLE trophy (np_communication_id TEXT)');
        $this->database->exec('CREATE TABLE trophy_group (np_communication_id TEXT)');
        $this->database->exec('CREATE TABLE psn100_change (change_type TEXT, param_1 INTEGER, extra TEXT)');
    }

    private function insertMergedGame(string $npCommunicationId, int $gameId, string $name, int $owners, int $ownersCompleted): void
    {
        $statement = $this->database->prepare('INSERT INTO trophy_title (id, np_communication_id, name) VALUES (:id, :np, :name)');
        $statement->bindValue(':id', $gameId, PDO::PARAM_INT);
        $statement->bindValue(':np', $npCommunicationId, PDO::PARAM_STR);
        $statement->bindValue(':name', $name, PDO::PARAM_STR);
        $statement->execute();

        $statement = $this->database->prepare(
            'INSERT INTO trophy_title_meta (np_communication_id, owners, owners_completed, parent_np_communication_id, status)
             VALUES (:np, :owners, :owners_completed, NULL, :status)'
        );
        $statement->bindValue(':np', $npCommunicationId, PDO::PARAM_STR);
        $statement->bindValue(':owners', $owners, PDO::PARAM_INT);
        $statement->bindValue(':owners_completed', $ownersCompleted, PDO::PARAM_INT);
        $statement->bindValue(':status', GameAvailabilityStatus::NORMAL->value, PDO::PARAM_INT);
        $statement->execute();
    }

    private function insertChildGame(
        string $npCommunicationId,
        int $gameId,
        string $name,
        string $parentNpCommunicationId,
        GameAvailabilityStatus $status
    ): void {
        $statement = $this->database->prepare('INSERT INTO trophy_title (id, np_communication_id, name) VALUES (:id, :np, :name)');
        $statement->bindValue(':id', $gameId, PDO::PARAM_INT);
        $statement->bindValue(':np', $npCommunicationId, PDO::PARAM_STR);
        $statement->bindValue(':name', $name, PDO::PARAM_STR);
        $statement->execute();

        $statement = $this->database->prepare(
            'INSERT INTO trophy_title_meta (np_communication_id, owners, owners_completed, parent_np_communication_id, status)
             VALUES (:np, 5, 2, :parent, :status)'
        );
        $statement->bindValue(':np', $npCommunicationId, PDO::PARAM_STR);
        $statement->bindValue(':parent', $parentNpCommunicationId, PDO::PARAM_STR);
        $statement->bindValue(':status', $status->value, PDO::PARAM_INT);
        $statement->execute();
    }

    private function assertChildRestoredToNormal(string $npCommunicationId): void
    {
        $child = $this->database
            ->query(
                "SELECT parent_np_communication_id, status FROM trophy_title_meta WHERE np_communication_id = "
                . $this->database->quote($npCommunicationId)
            )
            ->fetch(PDO::FETCH_ASSOC);

        $this->assertTrue($child !== false, 'Expected child trophy_title_meta row to exist.');
        $this->assertSame(null, $child['parent_np_communication_id']);
        $this->assertSame(GameAvailabilityStatus::NORMAL->value, (int) $child['status']);
    }
}
