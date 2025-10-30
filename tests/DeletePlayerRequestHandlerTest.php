<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Admin/DeletePlayerRequestHandler.php';

final class DeletePlayerRequestHandlerTest extends TestCase
{
    private PDO $database;

    private DeletePlayerRequestHandler $handler;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createTables();

        $service = new DeletePlayerService($this->database);
        $this->handler = new DeletePlayerRequestHandler($service);
    }

    public function testHandleRequestReturnsEmptyResultForGet(): void
    {
        $request = new AdminRequest('GET', []);

        $result = $this->handler->handleRequest($request);

        $this->assertSame(null, $result->getSuccessMessage());
        $this->assertSame(null, $result->getErrorMessage());
    }

    public function testHandleRequestReturnsErrorWhenNoIdentifiersProvided(): void
    {
        $request = new AdminRequest('POST', [
            'account_id' => '',
            'online_id' => '',
        ]);

        $result = $this->handler->handleRequest($request);

        $this->assertSame('<p>Please provide an account ID or an online ID.</p>', $result->getErrorMessage());
    }

    public function testHandleRequestReturnsErrorForInvalidAccountId(): void
    {
        $request = new AdminRequest('POST', [
            'account_id' => '12ab',
        ]);

        $result = $this->handler->handleRequest($request);

        $this->assertSame('<p>Please provide a numeric account ID.</p>', $result->getErrorMessage());
    }

    public function testHandleRequestReturnsErrorWhenOnlineIdNotFound(): void
    {
        $request = new AdminRequest('POST', [
            'account_id' => '',
            'online_id' => 'MissingUser',
        ]);

        $result = $this->handler->handleRequest($request);

        $this->assertSame('<p>No player was found with that online ID.</p>', $result->getErrorMessage());
    }

    public function testHandleRequestDeletesPlayerByAccountId(): void
    {
        $this->insertPlayerData('5005', 'DeleteById');

        $request = new AdminRequest('POST', [
            'account_id' => '5005',
            'online_id' => '',
        ]);

        $result = $this->handler->handleRequest($request);

        $this->assertSame(null, $result->getErrorMessage());

        $success = $result->getSuccessMessage();
        $this->assertTrue($success !== null);
        $this->assertStringContainsString('Deleted data for account ID 5005.', $success);
        $this->assertStringContainsString('trophy_earned: 2 rows deleted', $success);
        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM player WHERE account_id = '5005'")->fetchColumn());
    }

    public function testHandleRequestDeletesPlayerByOnlineId(): void
    {
        $this->insertPlayerData('6006', 'DeleteByOnline');

        $request = new AdminRequest('POST', [
            'account_id' => '',
            'online_id' => 'DeleteByOnline',
        ]);

        $result = $this->handler->handleRequest($request);

        $this->assertSame(null, $result->getErrorMessage());

        $success = $result->getSuccessMessage();
        $this->assertTrue($success !== null);
        $this->assertStringContainsString('Deleted data for account ID 6006.', $success);
        $this->assertSame(0, (int) $this->database->query("SELECT COUNT(*) FROM player WHERE account_id = '6006'")->fetchColumn());
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
