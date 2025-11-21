<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/GameHistoryPage.php';

final class GameHistoryPageTest extends TestCase
{
    public function testGetHistoryEntriesOmitsUnchangedRowsAndHighlightsDifferences(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $database->exec('CREATE TABLE trophy_title_history (id INTEGER PRIMARY KEY AUTOINCREMENT, trophy_title_id INTEGER, detail TEXT, icon_url TEXT, set_version TEXT, discovered_at TEXT)');
        $database->exec('CREATE TABLE trophy_group_history (title_history_id INTEGER, group_id TEXT, name TEXT, detail TEXT, icon_url TEXT)');
        $database->exec('CREATE TABLE trophy_history (title_history_id INTEGER, group_id TEXT, order_id INTEGER, name TEXT, detail TEXT, icon_url TEXT, progress_target_value INTEGER)');
        $database->exec('CREATE TABLE trophy_title (id INTEGER PRIMARY KEY AUTOINCREMENT, np_communication_id TEXT)');
        $database->exec('CREATE TABLE trophy (id INTEGER PRIMARY KEY AUTOINCREMENT, np_communication_id TEXT, group_id TEXT, order_id INTEGER)');
        $database->exec('CREATE TABLE trophy_meta (trophy_id INTEGER, status INTEGER)');

        $database->exec("INSERT INTO trophy_title_history (id, trophy_title_id, detail, icon_url, set_version, discovered_at) VALUES (1, 42, 'Initial detail', 'icon-a.png', '01.00', '2024-01-01 00:00:00')");
        $database->exec("INSERT INTO trophy_title_history (id, trophy_title_id, detail, icon_url, set_version, discovered_at) VALUES (2, 42, NULL, NULL, '01.05', '2024-02-01 00:00:00')");
        $database->exec("INSERT INTO trophy_title_history (id, trophy_title_id, detail, icon_url, set_version, discovered_at) VALUES (3, 42, NULL, NULL, '01.10', '2024-03-05 00:00:00')");
        $database->exec("INSERT INTO trophy_title_history (id, trophy_title_id, detail, icon_url, set_version, discovered_at) VALUES (4, 42, NULL, NULL, '01.10', '2024-04-01 00:00:00')");

        $database->exec("INSERT INTO trophy_title (id, np_communication_id) VALUES (42, 'NPWR12345_00')");

        $database->exec("INSERT INTO trophy (id, np_communication_id, group_id, order_id) VALUES (100, 'NPWR12345_00', 'default', 1)");
        $database->exec("INSERT INTO trophy (id, np_communication_id, group_id, order_id) VALUES (101, 'NPWR12345_00', '001', 5)");
        $database->exec("INSERT INTO trophy_meta (trophy_id, status) VALUES (100, 0)");
        $database->exec("INSERT INTO trophy_meta (trophy_id, status) VALUES (101, 1)");

        $database->exec("INSERT INTO trophy_group_history (title_history_id, group_id, name, detail, icon_url) VALUES (1, 'default', 'Base', 'Base detail', 'group-a.png')");
        $database->exec("INSERT INTO trophy_group_history (title_history_id, group_id, name, detail, icon_url) VALUES (2, 'default', 'Base', 'Base detail', 'group-a.png')");
        $database->exec("INSERT INTO trophy_group_history (title_history_id, group_id, name, detail, icon_url) VALUES (2, '001', 'Expansion', 'Expansion detail', '.png')");
        $database->exec("INSERT INTO trophy_group_history (title_history_id, group_id, name, detail, icon_url) VALUES (3, 'default', 'Base', 'Base detail', 'group-a.png')");
        $database->exec("INSERT INTO trophy_group_history (title_history_id, group_id, name, detail, icon_url) VALUES (3, '001', 'Expansion', 'Expansion detail', '.png')");
        $database->exec("INSERT INTO trophy_group_history (title_history_id, group_id, name, detail, icon_url) VALUES (4, 'default', 'Base', 'Base detail', 'group-a.png')");
        $database->exec("INSERT INTO trophy_group_history (title_history_id, group_id, name, detail, icon_url) VALUES (4, '001', 'Expansion', 'Expansion detail', '.png')");

        $database->exec("INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value) VALUES (1, 'default', 1, 'First Trophy', 'Earn it', 'trophy-a.png', NULL)");
        $database->exec("INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value) VALUES (2, 'default', 1, 'First Trophy', 'Earn it', 'trophy-a.png', NULL)");
        $database->exec("INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value) VALUES (2, '001', 5, 'Challenger', 'Reach level 5', '.png', 50)");
        $database->exec("INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value) VALUES (3, 'default', 1, 'First Trophy', 'Earn it', 'trophy-a.png', NULL)");
        $database->exec("INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value) VALUES (3, '001', 5, 'Challenger', 'Reach level 5', '.png', 100)");
        $database->exec("INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value) VALUES (4, 'default', 1, 'First Trophy', 'Earn it', 'trophy-a.png', NULL)");
        $database->exec("INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value) VALUES (4, '001', 5, 'Challenger', 'Reach level 5', '.png', 100)");

        $game = GameDetails::fromArray([
            'id' => 42,
            'name' => 'Example Game',
            'np_communication_id' => 'NPWR12345_00',
            'detail' => 'Detail',
            'icon_url' => 'icon.png',
            'platform' => 'PS5',
            'bronze' => 10,
            'silver' => 5,
            'gold' => 2,
            'platinum' => 1,
            'set_version' => '01.00',
            'message' => null,
            'status' => 0,
            'recent_players' => 0,
            'owners_completed' => 0,
            'owners' => 0,
            'difficulty' => '0',
            'psnprofiles_id' => null,
            'parent_np_communication_id' => null,
            'region' => null,
            'rarity_points' => 0,
        ]);

        $gameService = new class ($game) extends GameService {
            private GameDetails $game;

            public function __construct(GameDetails $game)
            {
                $this->game = $game;
            }

            public function getGame(int $gameId): ?GameDetails
            {
                return $this->game;
            }
        };

        $historyService = new GameHistoryService($database);

        $headerService = new class extends GameHeaderService {
            public function __construct()
            {
            }

            public function buildHeaderData(GameDetails $game): GameHeaderData
            {
                return new GameHeaderData(null, [], 0, [], null);
            }
        };

        $page = new GameHistoryPage(
            $gameService,
            $historyService,
            $headerService,
            new Utility(),
            42
        );

        $entries = $page->getHistoryEntries();

        $this->assertCount(3, $entries);
        $this->assertSame([3, 2, 1], array_column($entries, 'historyId'));

        $latestEntry = $entries[0];
        $this->assertTrue($latestEntry['hasTitleChanges']);
        $this->assertTrue($latestEntry['titleHighlights']['set_version']);
        $this->assertFalse($latestEntry['titleHighlights']['detail']);
        $this->assertSame([
            'set_version' => [
                'previous' => '01.05',
                'current' => '01.10',
            ],
        ], $latestEntry['titleFieldDiffs']);
        $this->assertSame([], $latestEntry['groups']);
        $this->assertCount(1, $latestEntry['trophies']);
        $this->assertTrue($latestEntry['trophies'][0]['changedFields']['progress_target_value']);

        $midEntry = $entries[1];
        $this->assertTrue($midEntry['hasTitleChanges']);
        $this->assertTrue($midEntry['titleHighlights']['set_version']);
        $this->assertSame([
            'set_version' => [
                'previous' => '01.00',
                'current' => '01.05',
            ],
        ], $midEntry['titleFieldDiffs']);
        $this->assertCount(1, $midEntry['groups']);
        $this->assertTrue($midEntry['groups'][0]['isNewRow']);
        $this->assertCount(1, $midEntry['trophies']);
        $this->assertTrue($midEntry['trophies'][0]['isNewRow']);
        $this->assertTrue($midEntry['trophies'][0]['is_unobtainable']);

        $earliestEntry = $entries[2];
        $this->assertTrue($earliestEntry['hasTitleChanges']);
        $this->assertTrue($earliestEntry['titleHighlights']['icon_url']);
        $this->assertTrue($earliestEntry['titleHighlights']['detail']);
        $this->assertSame([
            'detail' => [
                'previous' => null,
                'current' => 'Initial detail',
            ],
            'icon_url' => [
                'previous' => null,
                'current' => 'icon-a.png',
            ],
            'set_version' => [
                'previous' => null,
                'current' => '01.00',
            ],
        ], $earliestEntry['titleFieldDiffs']);
        $this->assertCount(1, $earliestEntry['groups']);
        $this->assertTrue($earliestEntry['groups'][0]['isNewRow']);
        $this->assertCount(1, $earliestEntry['trophies']);
        $this->assertTrue($earliestEntry['trophies'][0]['isNewRow']);
        $this->assertFalse($earliestEntry['trophies'][0]['is_unobtainable']);
    }
}
