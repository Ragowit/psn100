<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Admin/DeletePlayerService.php';

final class DeletePlayerServiceTest extends TestCase
{
    private PDO $database;

    private DeletePlayerService $service;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createTables();

        $this->service = new DeletePlayerService($this->database);
    }

    public function testFindAccountIdByOnlineIdReturnsAccountId(): void
    {
        $this->database->exec("INSERT INTO player (account_id, online_id) VALUES ('1001', 'ExampleUser')");

        $accountId = $this->service->findAccountIdByOnlineId('ExampleUser');

        $this->assertSame('1001', $accountId);
    }

    public function testFindAccountIdByOnlineIdReturnsNullWhenNotFound(): void
    {
        $accountId = $this->service->findAccountIdByOnlineId('MissingUser');

        $this->assertSame(null, $accountId);
    }

    public function testDeletePlayerByAccountIdDeletesData(): void
    {
        $this->insertPlayerData('2002', 'PlayerOne');
        $this->insertPlayerData('3003', 'PlayerTwo');

        $counts = $this->service->deletePlayerByAccountId('2002');

        $this->assertSame(
            [
                'trophy_earned' => 2,
                'trophy_group_player' => 1,
                'trophy_title_player' => 1,
                'player' => 1,
                'log' => 1,
            ],
            $counts
        );

        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM trophy_earned WHERE account_id = '2002'")->fetchColumn());
        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM trophy_group_player WHERE account_id = '2002'")->fetchColumn());
        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM trophy_title_player WHERE account_id = '2002'")->fetchColumn());
        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM player WHERE account_id = '2002'")->fetchColumn());
        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM log WHERE message LIKE '%2002%'")->fetchColumn());

        $this->assertSame(1, (int) $this->database->query("SELECT COUNT(*) FROM player WHERE account_id = '3003'")->fetchColumn());
    }

    public function testDeletePlayerByAccountIdRollsBackOnFailure(): void
    {
        $this->insertPlayerData('4004', 'FailureUser');

        $this->database->exec('CREATE TRIGGER trigger_fail_delete BEFORE DELETE ON trophy_group_player
            BEGIN
                SELECT RAISE(ABORT, "delete failure");
            END;');

        try {
            $this->service->deletePlayerByAccountId('4004');
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Failed to delete player data.', $exception->getMessage());
        }

        $this->assertSame(2, (int) $this->database->query("SELECT COUNT(*) FROM trophy_earned WHERE account_id = '4004'")->fetchColumn());
        $this->assertSame(1, (int) $this->database->query("SELECT COUNT(*) FROM trophy_group_player WHERE account_id = '4004'")->fetchColumn());
        $this->assertSame(1, (int) $this->database->query("SELECT COUNT(*) FROM trophy_title_player WHERE account_id = '4004'")->fetchColumn());
        $this->assertSame(1, (int) $this->database->query("SELECT COUNT(*) FROM player WHERE account_id = '4004'")->fetchColumn());
        $this->assertSame(1, (int) $this->database->query("SELECT COUNT(*) FROM log WHERE message LIKE '%4004%'")->fetchColumn());
    }

    private function createTables(): void
    {
        $this->database->exec('CREATE TABLE trophy_earned (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id TEXT NOT NULL
        )');
        $this->database->exec('CREATE TABLE trophy_group_player (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id TEXT NOT NULL
        )');
        $this->database->exec('CREATE TABLE trophy_title_player (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id TEXT NOT NULL
        )');
        $this->database->exec('CREATE TABLE player (
            account_id TEXT PRIMARY KEY,
            online_id TEXT
        )');
        $this->database->exec('CREATE TABLE log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message TEXT
        )');
    }

    private function insertPlayerData(string $accountId, string $onlineId): void
    {
        $this->database->exec(sprintf(
            "INSERT INTO player (account_id, online_id) VALUES ('%s', '%s')",
            $accountId,
            $onlineId
        ));
        $this->database->exec(sprintf(
            "INSERT INTO trophy_group_player (account_id) VALUES ('%s')",
            $accountId
        ));
        $this->database->exec(sprintf(
            "INSERT INTO trophy_title_player (account_id) VALUES ('%s')",
            $accountId
        ));
        $this->database->exec(sprintf(
            "INSERT INTO log (message) VALUES ('Player (%s) deleted')",
            $accountId
        ));
        $this->database->exec(sprintf(
            "INSERT INTO trophy_earned (account_id) VALUES ('%s')",
            $accountId
        ));
        $this->database->exec(sprintf(
            "INSERT INTO trophy_earned (account_id) VALUES ('%s')",
            $accountId
        ));
    }
}
