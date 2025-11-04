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

        $this->database->exec("INSERT INTO trophy_title (id, np_communication_id) VALUES (42, 'NPWRTEST_00')");

        $this->database->exec("INSERT INTO trophy (id, np_communication_id, group_id, order_id) VALUES (100, 'NPWRTEST_00', 'default', 1)");
        $this->database->exec("INSERT INTO trophy (id, np_communication_id, group_id, order_id) VALUES (101, 'NPWRTEST_00', '001', 5)");
        $this->database->exec("INSERT INTO trophy_meta (trophy_id, status) VALUES (100, 0)");
        $this->database->exec("INSERT INTO trophy_meta (trophy_id, status) VALUES (101, 1)");

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
        $this->assertFalse($entries[1]['trophies'][0]['is_unobtainable']);

        $this->assertTrue($entries[0]['trophies'][0]['is_unobtainable']);
    }

    public function testGetHistoryForGameReturnsEmptyArrayWhenNoHistoryExists(): void
    {
        $entries = $this->service->getHistoryForGame(42);

        $this->assertSame([], $entries);
    }

    public function testGetHistoryForGameOrdersGroupsAndTrophies(): void
    {
        $this->database->exec("INSERT INTO trophy_title_history (id, trophy_title_id, detail, icon_url, set_version, discovered_at) VALUES (3, 42, NULL, NULL, NULL, '2024-04-01 00:00:00')");

        $this->database->exec("INSERT INTO trophy_title (id, np_communication_id) VALUES (42, 'NPWRORDER_00')");

        $this->database->exec("INSERT INTO trophy_group_history (title_history_id, group_id, name, detail, icon_url) VALUES (3, '002', 'Third Group', 'Third detail', NULL)");
        $this->database->exec("INSERT INTO trophy_group_history (title_history_id, group_id, name, detail, icon_url) VALUES (3, 'default', 'Base Game', 'Base detail', NULL)");
        $this->database->exec("INSERT INTO trophy_group_history (title_history_id, group_id, name, detail, icon_url) VALUES (3, '001', 'Second Group', 'Second detail', NULL)");
        $this->database->exec("INSERT INTO trophy_group_history (title_history_id, group_id, name, detail, icon_url) VALUES (3, '101', 'Fourth Group', 'Fourth detail', NULL)");
        $this->database->exec("INSERT INTO trophy_group_history (title_history_id, group_id, name, detail, icon_url) VALUES (3, '201', 'Fifth Group', 'Fifth detail', NULL)");
        $this->database->exec("INSERT INTO trophy_group_history (title_history_id, group_id, name, detail, icon_url) VALUES (3, '102', 'Fourth Group Part 2', 'Fourth detail part 2', NULL)");
        $this->database->exec("INSERT INTO trophy_group_history (title_history_id, group_id, name, detail, icon_url) VALUES (3, '202', 'Fifth Group Part 2', 'Fifth detail part 2', NULL)");
        $this->database->exec("INSERT INTO trophy_group_history (title_history_id, group_id, name, detail, icon_url) VALUES (3, '103', 'Fourth Group Part 3', 'Fourth detail part 3', NULL)");

        $this->database->exec("INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value) VALUES (3, 'default', 0, 'Base Game First Trophy', 'Base first detail', NULL, NULL)");
        $this->database->exec("INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value) VALUES (3, 'default', 1, 'Base Game Later Trophy', 'Base later detail', NULL, NULL)");
        $this->database->exec("INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value) VALUES (3, '001', 2, 'Second Group Trophy', 'Second group trophy detail', NULL, NULL)");
        $this->database->exec("INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value) VALUES (3, '001', 3, 'Second Group Additional Trophy', 'Second group trophy detail additional', NULL, NULL)");
        $this->database->exec("INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value) VALUES (3, '002', 4, 'Third Group Trophy', 'Third group trophy detail', NULL, NULL)");
        $this->database->exec("INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value) VALUES (3, '101', 5, 'Fourth Group Trophy', 'Fourth group trophy detail', NULL, NULL)");
        $this->database->exec("INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value) VALUES (3, '102', 6, 'Fourth Group Trophy Part 2', 'Fourth group trophy detail part 2', NULL, NULL)");
        $this->database->exec("INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value) VALUES (3, '103', 7, 'Fourth Group Trophy Part 3', 'Fourth group trophy detail part 3', NULL, NULL)");
        $this->database->exec("INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value) VALUES (3, '201', 8, 'Fifth Group Trophy', 'Fifth group trophy detail', NULL, NULL)");
        $this->database->exec("INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value) VALUES (3, '202', 9, 'Fifth Group Trophy Part 2', 'Fifth group trophy detail part 2', NULL, NULL)");

        $trophies = [
            ['default', 0],
            ['default', 1],
            ['001', 2],
            ['001', 3],
            ['002', 4],
            ['101', 5],
            ['102', 6],
            ['103', 7],
            ['201', 8],
            ['202', 9],
        ];

        foreach ($trophies as $index => $trophy) {
            $trophyId = 200 + $index;
            $this->database->exec(sprintf(
                "INSERT INTO trophy (id, np_communication_id, group_id, order_id) VALUES (%d, 'NPWRORDER_00', '%s', %d)",
                $trophyId,
                $trophy[0],
                $trophy[1]
            ));
            $this->database->exec(sprintf(
                "INSERT INTO trophy_meta (trophy_id, status) VALUES (%d, 0)",
                $trophyId
            ));
        }

        $entries = $this->service->getHistoryForGame(42);

        $this->assertCount(1, $entries);

        $groups = array_column($entries[0]['groups'], 'group_id');
        $this->assertSame(['default', '001', '002', '101', '102', '103', '201', '202'], $groups);

        $trophies = array_map(
            static fn (array $trophy): string => $trophy['group_id'] . ':' . $trophy['order_id'],
            $entries[0]['trophies']
        );

        $this->assertSame([
            'default:0',
            'default:1',
            '001:2',
            '001:3',
            '002:4',
            '101:5',
            '102:6',
            '103:7',
            '201:8',
            '202:9',
        ], $trophies);
    }

    private function createSchema(): void
    {
        $this->database->exec('CREATE TABLE trophy_title_history (id INTEGER PRIMARY KEY AUTOINCREMENT, trophy_title_id INTEGER, detail TEXT, icon_url TEXT, set_version TEXT, discovered_at TEXT)');
        $this->database->exec('CREATE TABLE trophy_group_history (title_history_id INTEGER, group_id TEXT, name TEXT, detail TEXT, icon_url TEXT)');
        $this->database->exec('CREATE TABLE trophy_history (title_history_id INTEGER, group_id TEXT, order_id INTEGER, name TEXT, detail TEXT, icon_url TEXT, progress_target_value INTEGER)');
        $this->database->exec('CREATE TABLE trophy_title (id INTEGER PRIMARY KEY AUTOINCREMENT, np_communication_id TEXT)');
        $this->database->exec('CREATE TABLE trophy (id INTEGER PRIMARY KEY AUTOINCREMENT, np_communication_id TEXT, group_id TEXT, order_id INTEGER)');
        $this->database->exec('CREATE TABLE trophy_meta (trophy_id INTEGER, status INTEGER)');
    }
}
