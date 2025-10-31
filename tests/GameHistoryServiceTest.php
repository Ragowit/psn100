<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/GameHistoryService.php';

final class GameHistoryServiceTest extends TestCase
{
    private PDO $database;

    private GameHistoryService $service;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createSchema();

        $this->service = new GameHistoryService($this->database);
    }

    public function testGetHistoryForGameReturnsEntriesWithRelatedData(): void
    {
        $this->database->exec("INSERT INTO trophy_title_history (id, trophy_title_id, detail, icon_url, set_version, discovered_at) VALUES (1, 42, 'Detail A', 'icon-a.png', '01.00', '2024-02-01 10:11:12')");
        $this->database->exec("INSERT INTO trophy_title_history (id, trophy_title_id, detail, icon_url, set_version, discovered_at) VALUES (2, 42, 'Detail B', '.png', '01.10', '2024-03-05 05:04:03')");

        $this->database->exec("INSERT INTO trophy_group_history (title_history_id, group_id, name, detail, icon_url) VALUES (1, 'default', 'Base', 'Base detail', 'group-a.png')");
        $this->database->exec("INSERT INTO trophy_group_history (title_history_id, group_id, name, detail, icon_url) VALUES (2, '001', 'Expansion', 'Expansion detail', '.png')");

        $this->database->exec("INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value) VALUES (1, 'default', 1, 'First Trophy', 'Earn the first trophy', 'trophy-a.png', NULL)");
        $this->database->exec("INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value) VALUES (2, '001', 5, 'Challenger', 'Reach level 5', '.png', 100)");

        $entries = $this->service->getHistoryForGame(42);

        $this->assertCount(2, $entries);
        $this->assertSame(2, $entries[0]['historyId']);
        $this->assertSame('2024-03-05 05:04:03', $entries[0]['discoveredAt']->format('Y-m-d H:i:s'));
        $this->assertSame('01.10', $entries[0]['title']['set_version']);
        $this->assertCount(1, $entries[0]['groups']);
        $this->assertSame('Expansion', $entries[0]['groups'][0]['name']);
        $this->assertCount(1, $entries[0]['trophies']);
        $this->assertSame(100, $entries[0]['trophies'][0]['progress_target_value']);

        $this->assertSame(1, $entries[1]['historyId']);
        $this->assertSame('2024-02-01 10:11:12', $entries[1]['discoveredAt']->format('Y-m-d H:i:s'));
        $this->assertSame('Detail A', $entries[1]['title']['detail']);
        $this->assertSame('Base', $entries[1]['groups'][0]['name']);
        $this->assertSame('First Trophy', $entries[1]['trophies'][0]['name']);
    }

    public function testGetHistoryForGameReturnsEmptyArrayWhenNoHistoryExists(): void
    {
        $entries = $this->service->getHistoryForGame(42);

        $this->assertSame([], $entries);
    }

    private function createSchema(): void
    {
        $this->database->exec('CREATE TABLE trophy_title_history (id INTEGER PRIMARY KEY AUTOINCREMENT, trophy_title_id INTEGER, detail TEXT, icon_url TEXT, set_version TEXT, discovered_at TEXT)');
        $this->database->exec('CREATE TABLE trophy_group_history (title_history_id INTEGER, group_id TEXT, name TEXT, detail TEXT, icon_url TEXT)');
        $this->database->exec('CREATE TABLE trophy_history (title_history_id INTEGER, group_id TEXT, order_id INTEGER, name TEXT, detail TEXT, icon_url TEXT, progress_target_value INTEGER)');
    }
}
