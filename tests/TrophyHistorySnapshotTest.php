<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Psn100Logger.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyCalculator.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyHistoryRecorder.php';

final class TrophyHistorySnapshotTest extends TestCase
{
    private PDO $database;

    private TrophyHistoryRecorder $historyRecorder;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createSchema();

        $logger = new Psn100Logger($this->database);
        $this->historyRecorder = new TrophyHistoryRecorder($this->database, $logger);
    }

    public function testSnapshotCapturesChangesWhenDetailsChangeWithoutVersionBump(): void
    {
        $this->seedInitialData();

        $this->historyRecorder->recordByTitleId(1);

        $this->database->exec("UPDATE trophy_title SET detail = 'Updated detail' WHERE id = 1");
        $this->database->exec("UPDATE trophy_group SET detail = 'Updated group detail' WHERE id = 1");
        $this->database->exec("UPDATE trophy SET detail = 'Updated trophy detail' WHERE id = 1");

        $this->historyRecorder->recordByTitleId(1);

        $titleHistoryRows = $this->database->query('SELECT detail, set_version FROM trophy_title_history WHERE trophy_title_id = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(2, $titleHistoryRows);
        $this->assertSame('Original detail', $titleHistoryRows[0]['detail']);
        $this->assertSame('Updated detail', $titleHistoryRows[1]['detail']);
        $this->assertSame('01.00', $titleHistoryRows[1]['set_version']);

        $historyIds = $this->database->query('SELECT id FROM trophy_title_history WHERE trophy_title_id = 1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        $latestHistoryId = (int) $historyIds[1];

        $groupHistoryQuery = $this->database->prepare('SELECT detail FROM trophy_group_history WHERE title_history_id = :title_history_id AND group_id = :group_id');
        $groupHistoryQuery->bindValue(':title_history_id', $latestHistoryId, PDO::PARAM_INT);
        $groupHistoryQuery->bindValue(':group_id', 'default', PDO::PARAM_STR);
        $groupHistoryQuery->execute();
        $this->assertSame('Updated group detail', $groupHistoryQuery->fetchColumn());

        $trophyHistoryQuery = $this->database->prepare('SELECT detail FROM trophy_history WHERE title_history_id = :title_history_id AND group_id = :group_id AND order_id = :order_id');
        $trophyHistoryQuery->bindValue(':title_history_id', $latestHistoryId, PDO::PARAM_INT);
        $trophyHistoryQuery->bindValue(':group_id', 'default', PDO::PARAM_STR);
        $trophyHistoryQuery->bindValue(':order_id', 1, PDO::PARAM_INT);
        $trophyHistoryQuery->execute();
        $this->assertSame('Updated trophy detail', $trophyHistoryQuery->fetchColumn());
    }

    private function createSchema(): void
    {
        $this->database->exec('CREATE TABLE log (id INTEGER PRIMARY KEY AUTOINCREMENT, message TEXT)');
        $this->database->exec('CREATE TABLE trophy_title (id INTEGER PRIMARY KEY, np_communication_id TEXT, name TEXT, detail TEXT, icon_url TEXT, platform TEXT, bronze INTEGER, silver INTEGER, gold INTEGER, platinum INTEGER, set_version TEXT)');
        $this->database->exec('CREATE TABLE trophy_group (id INTEGER PRIMARY KEY, np_communication_id TEXT, group_id TEXT, name TEXT, detail TEXT, icon_url TEXT)');
        $this->database->exec('CREATE TABLE trophy (id INTEGER PRIMARY KEY, np_communication_id TEXT, group_id TEXT, order_id INTEGER, hidden INTEGER, type TEXT, name TEXT, detail TEXT, icon_url TEXT, progress_target_value INTEGER, reward_name TEXT, reward_image_url TEXT)');
        $this->database->exec('CREATE TABLE trophy_title_history (id INTEGER PRIMARY KEY AUTOINCREMENT, trophy_title_id INTEGER, detail TEXT, icon_url TEXT, set_version TEXT, discovered_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->database->exec('CREATE TABLE trophy_group_history (title_history_id INTEGER, group_id TEXT, name TEXT, detail TEXT, icon_url TEXT)');
        $this->database->exec('CREATE TABLE trophy_history (title_history_id INTEGER, group_id TEXT, order_id INTEGER, name TEXT, detail TEXT, icon_url TEXT, progress_target_value INTEGER)');
    }

    private function seedInitialData(): void
    {
        $this->database->exec("INSERT INTO trophy_title (id, np_communication_id, name, detail, icon_url, platform, bronze, silver, gold, platinum, set_version) VALUES (1, 'NPWR00001_00', 'Example Game', 'Original detail', 'title-icon.png', 'ps5', 0, 0, 0, 0, '01.00')");
        $this->database->exec("INSERT INTO trophy_group (id, np_communication_id, group_id, name, detail, icon_url) VALUES (1, 'NPWR00001_00', 'default', 'Base Game', 'Original group detail', 'group-icon.png')");
        $this->database->exec("INSERT INTO trophy (id, np_communication_id, group_id, order_id, hidden, type, name, detail, icon_url, progress_target_value, reward_name, reward_image_url) VALUES (1, 'NPWR00001_00', 'default', 1, 0, 'bronze', 'First Trophy', 'Original trophy detail', 'trophy-icon.png', NULL, NULL, NULL)");
    }
}
