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

    public function testFindPlayerByOnlineIdReturnsPlayer(): void
    {
        $this->database->exec("INSERT INTO player (account_id, online_id) VALUES ('1001', 'ExampleUser')");

        $player = $this->service->findPlayerByOnlineId('ExampleUser');

        $this->assertSame([
            'account_id' => '1001',
            'online_id' => 'ExampleUser',
        ], $player);
    }

    public function testFindPlayerByOnlineIdReturnsNullWhenNotFound(): void
    {
        $player = $this->service->findPlayerByOnlineId('MissingUser');

        $this->assertSame(null, $player);
    }

    public function testFindPlayerByAccountIdReturnsPlayer(): void
    {
        $this->database->exec("INSERT INTO player (account_id, online_id) VALUES ('2002', 'AccountUser')");

        $player = $this->service->findPlayerByAccountId('2002');

        $this->assertSame([
            'account_id' => '2002',
            'online_id' => 'AccountUser',
        ], $player);
    }

    public function testFindPlayerByAccountIdReturnsNullWhenNotFound(): void
    {
        $player = $this->service->findPlayerByAccountId('9999');

        $this->assertSame(null, $player);
    }

    public function testDeletePlayerByAccountIdDeletesRelatedData(): void
    {
        $this->insertPlayerData('2002', 'PlayerOne');
        $this->insertPlayerData('3003', 'PlayerTwo');

        $counts = $this->service->deletePlayerByAccountId('2002');

        $this->assertSame(
            [
                'trophy_earned' => 2,
                'trophy_group_player' => 1,
                'trophy_title_player' => 1,
                'player_ranking' => 1,
                'player_report' => 1,
                'player_queue' => 1,
                'player' => 1,
                'log' => 1,
            ],
            $counts
        );

        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM trophy_earned WHERE account_id = '2002'")->fetchColumn());
        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM trophy_group_player WHERE account_id = '2002'")->fetchColumn());
        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM trophy_title_player WHERE account_id = '2002'")->fetchColumn());
        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM player_ranking WHERE account_id = '2002'")->fetchColumn());
        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM player_report WHERE account_id = '2002'")->fetchColumn());
        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM player_queue WHERE online_id = 'PlayerOne'")->fetchColumn());
        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM player WHERE account_id = '2002'")->fetchColumn());
        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM log WHERE message = 'Player (2002) deleted'")->fetchColumn());

        $this->assertSame(1, (int) $this->database->query("SELECT COUNT(*) FROM player WHERE account_id = '3003'")->fetchColumn());
        $this->assertSame(1, (int) $this->database->query("SELECT COUNT(*) FROM player_queue WHERE online_id = 'PlayerTwo'")->fetchColumn());
    }

    public function testDeletePlayerByAccountIdDoesNotDeleteLogsWithSimilarNumericSubstrings(): void
    {
        $this->insertPlayerData('2002', 'PlayerOne');
        $this->database->exec("INSERT INTO log (message) VALUES ('Scan failed for player 92002')");
        $this->database->exec("INSERT INTO log (message) VALUES ('Sony issues with OtherUser (92002).')");

        $this->service->deletePlayerByAccountId('2002');

        $this->assertSame(1, (int) $this->database->query("SELECT COUNT(*) FROM log WHERE message = 'Scan failed for player 92002'")->fetchColumn());
        $this->assertSame(1, (int) $this->database->query("SELECT COUNT(*) FROM log WHERE message = 'Sony issues with OtherUser (92002).'")->fetchColumn());
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
        $this->assertSame(1, (int) $this->database->query("SELECT COUNT(*) FROM player_ranking WHERE account_id = '4004'")->fetchColumn());
        $this->assertSame(1, (int) $this->database->query("SELECT COUNT(*) FROM player_report WHERE account_id = '4004'")->fetchColumn());
        $this->assertSame(1, (int) $this->database->query("SELECT COUNT(*) FROM player_queue WHERE online_id = 'FailureUser'")->fetchColumn());
        $this->assertSame(1, (int) $this->database->query("SELECT COUNT(*) FROM player WHERE account_id = '4004'")->fetchColumn());
        $this->assertSame(1, (int) $this->database->query("SELECT COUNT(*) FROM log WHERE message = 'Player (4004) deleted'")->fetchColumn());
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
            account_id TEXT NOT NULL,
            np_communication_id TEXT,
            rarity_points INTEGER NOT NULL DEFAULT 0,
            in_game_rarity_points INTEGER NOT NULL DEFAULT 0,
            in_game_common INTEGER NOT NULL DEFAULT 0,
            in_game_uncommon INTEGER NOT NULL DEFAULT 0,
            in_game_rare INTEGER NOT NULL DEFAULT 0,
            in_game_epic INTEGER NOT NULL DEFAULT 0,
            in_game_legendary INTEGER NOT NULL DEFAULT 0
        )');
        $this->database->exec('CREATE TABLE player_ranking (
            account_id TEXT PRIMARY KEY,
            ranking INTEGER NOT NULL
        )');
        $this->database->exec('CREATE TABLE player_report (
            report_id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id TEXT NOT NULL,
            ip_address TEXT NOT NULL,
            explanation TEXT NOT NULL
        )');
        $this->database->exec('CREATE TABLE player_queue (
            online_id TEXT PRIMARY KEY,
            ip_address TEXT NOT NULL
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
            "INSERT INTO player_ranking (account_id, ranking) VALUES ('%s', 1)",
            $accountId
        ));
        $this->database->exec(sprintf(
            "INSERT INTO player_report (account_id, ip_address, explanation) VALUES ('%s', '127.0.0.1', 'test')",
            $accountId
        ));
        $this->database->exec(sprintf(
            "INSERT INTO player_queue (online_id, ip_address) VALUES ('%s', '127.0.0.1')",
            $onlineId
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
