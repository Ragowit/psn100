<?php

declare(strict_types=1);

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
        $this->database->exec("INSERT INTO trophy_title (id, np_communication_id, name) VALUES (2, 'NPWR-OTHER', 'Child Game')");
        $this->database->exec("INSERT INTO trophy_title_meta (np_communication_id, owners, owners_completed, parent_np_communication_id) VALUES ('NPWR-OTHER', 5, 2, 'MERGE-123')");

        $this->database->exec("INSERT INTO trophy_merge (parent_np_communication_id) VALUES ('MERGE-123')");
        $this->database->exec("INSERT INTO trophy_earned (np_communication_id) VALUES ('MERGE-123')");
        $this->database->exec("INSERT INTO trophy_group_player (np_communication_id) VALUES ('MERGE-123')");
        $this->database->exec("INSERT INTO trophy_title_player (np_communication_id) VALUES ('MERGE-123')");

        $message = $this->service->process(1, 0);

        $this->assertSame('Game 1 was reset.', $message);

        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM trophy_merge")->fetchColumn());
        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM trophy_earned")->fetchColumn());
        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM trophy_group_player")->fetchColumn());
        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM trophy_title_player")->fetchColumn());

        $owners = $this->database->query("SELECT owners FROM trophy_title_meta WHERE np_communication_id = 'MERGE-123'")->fetchColumn();
        $ownersCompleted = $this->database->query("SELECT owners_completed FROM trophy_title_meta WHERE np_communication_id = 'MERGE-123'")->fetchColumn();
        $this->assertSame(0, (int) $owners);
        $this->assertSame(0, (int) $ownersCompleted);

        $childParent = $this->database->query("SELECT parent_np_communication_id FROM trophy_title_meta WHERE np_communication_id = 'NPWR-OTHER'")->fetchColumn();
        $this->assertSame(null, $childParent);

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
        $this->database->exec("INSERT INTO trophy_title (id, np_communication_id, name) VALUES (2, 'NPWR-OTHER', 'Child Game')");
        $this->database->exec("INSERT INTO trophy_title_meta (np_communication_id, owners, owners_completed, parent_np_communication_id) VALUES ('NPWR-OTHER', 5, 2, 'MERGE-456')");

        $tables = [
            'trophy_merge' => 'parent_np_communication_id',
            'trophy' => 'np_communication_id',
            'trophy_earned' => 'np_communication_id',
            'trophy_group_player' => 'np_communication_id',
            'trophy_title_player' => 'np_communication_id',
            'trophy_group' => 'np_communication_id',
        ];

        foreach ($tables as $table => $column) {
            $this->database->exec(sprintf(
                "INSERT INTO %s (%s) VALUES ('MERGE-456')",
                $table,
                $column
            ));
        }

        $message = $this->service->process(1, 1);

        $this->assertSame('Game 1 was deleted.', $message);

        foreach (array_keys($tables) as $table) {
            $this->assertSame(0, (int) $this->database->query(sprintf('SELECT COUNT(*) FROM %s', $table))->fetchColumn());
        }

        $remainingTitle = $this->database->query('SELECT COUNT(*) FROM trophy_title WHERE id = 1')->fetchColumn();
        $this->assertSame(0, (int) $remainingTitle);

        $childParent = $this->database->query("SELECT parent_np_communication_id FROM trophy_title_meta WHERE np_communication_id = 'NPWR-OTHER'")->fetchColumn();
        $this->assertSame(null, $childParent);

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

    public function testProcessThrowsWhenGameEntryIsMissing(): void
    {
        try {
            $this->service->process(99, 0);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Can only reset/delete merged game entries.', $exception->getMessage());
        }
    }

    public function testProcessThrowsWhenGameIsNotMerged(): void
    {
        $this->database->exec("INSERT INTO trophy_title (id, np_communication_id, name) VALUES (5, 'NPWR-123', 'Regular Game')");
        $this->database->exec("INSERT INTO trophy_title_meta (np_communication_id, owners, owners_completed, parent_np_communication_id) VALUES ('NPWR-123', 1, 0, NULL)");

        try {
            $this->service->process(5, 0);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Can only reset/delete merged game entries.', $exception->getMessage());
        }
    }

    public function testProcessThrowsForUnknownAction(): void
    {
        $this->insertMergedGame('MERGE-789', 7, 'Merged Game', 2, 1);

        try {
            $this->service->process(7, 42);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Unknown method.', $exception->getMessage());
        }
    }

    public function testProcessRollsBackWhenStatementFails(): void
    {
        $this->insertMergedGame('MERGE-999', 9, 'Merge Failure Game', 33, 12);
        $this->database->exec("INSERT INTO trophy_merge (parent_np_communication_id) VALUES ('MERGE-999')");
        $this->database->exec('CREATE TRIGGER fail_delete BEFORE DELETE ON trophy_merge BEGIN SELECT RAISE(ABORT, "delete failure"); END;');

        try {
            $this->service->process(9, 0);
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
            parent_np_communication_id TEXT
        )');
        $this->database->exec('CREATE TABLE trophy_merge (parent_np_communication_id TEXT)');
        $this->database->exec('CREATE TABLE trophy_earned (np_communication_id TEXT)');
        $this->database->exec('CREATE TABLE trophy_group_player (np_communication_id TEXT)');
        $this->database->exec('CREATE TABLE trophy_title_player (np_communication_id TEXT)');
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

        $statement = $this->database->prepare('INSERT INTO trophy_title_meta (np_communication_id, owners, owners_completed, parent_np_communication_id) VALUES (:np, :owners, :owners_completed, NULL)');
        $statement->bindValue(':np', $npCommunicationId, PDO::PARAM_STR);
        $statement->bindValue(':owners', $owners, PDO::PARAM_INT);
        $statement->bindValue(':owners_completed', $ownersCompleted, PDO::PARAM_INT);
        $statement->execute();
    }
}
