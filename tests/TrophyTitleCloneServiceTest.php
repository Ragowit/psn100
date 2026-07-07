<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/NestedDatabaseTransactionRunner.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyTitleCloneService.php';

final class TrophyTitleCloneServiceTest extends TestCase
{
    private PDO $database;

    private TrophyTitleCloneService $service;

    protected function setUp(): void
    {
        $this->database = new TrophyTitleCloneServiceTestDatabase();
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createSchema();

        $this->service = new TrophyTitleCloneService(
            $this->database,
            new NestedDatabaseTransactionRunner($this->database),
        );
    }

    public function testCloneFromGameIdRejectsMergeTitle(): void
    {
        $this->insertGame(1, 'MERGE_000001', 'PS4');

        try {
            $this->service->cloneFromGameId(1);
            $this->fail("Expected InvalidArgumentException for cloning a MERGE title.");
        } catch (InvalidArgumentException $exception) {
            $this->assertSame("Can't clone an already cloned game.", $exception->getMessage());
        }
    }

    public function testCloneFromGameIdRejectsMissingGame(): void
    {
        try {
            $this->service->cloneFromGameId(99);
            $this->fail('Expected InvalidArgumentException for a missing game.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Game not found.', $exception->getMessage());
        }
    }

    public function testCloneFromGameIdClonesCatalogGroupsTrophiesAndHistory(): void
    {
        $this->seedSourceGame();

        $result = $this->service->cloneFromGameId(1);

        $this->assertSame(2, $result['clone_game_id']);
        $this->assertSame('MERGE_000002', $result['clone_np_communication_id']);

        $cloneTitle = $this->database
            ->query("SELECT name, detail, platform, bronze, silver, gold, platinum, set_version FROM trophy_title WHERE id = 2")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Example Game', $cloneTitle['name']);
        $this->assertSame('Original detail', $cloneTitle['detail']);
        $this->assertSame('PS4', $cloneTitle['platform']);
        $this->assertSame(1, (int) $cloneTitle['bronze']);
        $this->assertSame('01.00', $cloneTitle['set_version']);

        $cloneMeta = $this->database
            ->query("SELECT status, parent_np_communication_id, region FROM trophy_title_meta WHERE np_communication_id = 'MERGE_000002'")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(0, (int) $cloneMeta['status']);
        $this->assertSame(null, $cloneMeta['parent_np_communication_id']);
        $this->assertSame('', $cloneMeta['region']);

        $this->assertSame(1, (int) $this->database->query("SELECT COUNT(*) FROM trophy_group WHERE np_communication_id = 'MERGE_000002'")->fetchColumn());
        $this->assertSame(1, (int) $this->database->query("SELECT COUNT(*) FROM trophy WHERE np_communication_id = 'MERGE_000002'")->fetchColumn());
        $this->assertSame(
            1,
            (int) $this->database->query(
                "SELECT COUNT(*)
                 FROM trophy_meta tm
                 INNER JOIN trophy t ON t.id = tm.trophy_id
                 WHERE t.np_communication_id = 'MERGE_000002'"
            )->fetchColumn()
        );

        $clonedHistoryCount = (int) $this->database
            ->query('SELECT COUNT(*) FROM trophy_title_history WHERE trophy_title_id = 2')
            ->fetchColumn();
        $this->assertSame(1, $clonedHistoryCount);

        $historyId = (int) $this->database
            ->query('SELECT id FROM trophy_title_history WHERE trophy_title_id = 2')
            ->fetchColumn();
        $this->assertSame(1, (int) $this->database
            ->query("SELECT COUNT(*) FROM trophy_group_history WHERE title_history_id = {$historyId}")
            ->fetchColumn());
        $this->assertSame(1, (int) $this->database
            ->query("SELECT COUNT(*) FROM trophy_history WHERE title_history_id = {$historyId}")
            ->fetchColumn());

        $change = $this->database
            ->query("SELECT change_type, param_1, param_2 FROM psn100_change WHERE change_type = 'GAME_CLONE'")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('GAME_CLONE', $change['change_type']);
        $this->assertSame(1, (int) $change['param_1']);
        $this->assertSame(2, (int) $change['param_2']);
    }

    public function testCloneFromGameIdResetsMergedStatusOnCloneMeta(): void
    {
        $this->insertGame(1, 'NPWR00001_00', 'PS4');
        $this->database->exec(
            "INSERT INTO trophy_title_meta (np_communication_id, status, message, region, rarity_points)
             VALUES ('NPWR00001_00', 2, '', '', 0)"
        );

        $this->service->cloneFromGameId(1);

        $status = (int) $this->database
            ->query("SELECT status FROM trophy_title_meta WHERE np_communication_id = 'MERGE_000002'")
            ->fetchColumn();
        $this->assertSame(0, $status);
    }

    private function createSchema(): void
    {
        $this->database->exec(
            'CREATE TABLE trophy_title (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                np_communication_id TEXT NOT NULL,
                name TEXT,
                detail TEXT,
                icon_url TEXT,
                platform TEXT,
                bronze INTEGER,
                silver INTEGER,
                gold INTEGER,
                platinum INTEGER,
                set_version TEXT
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy_title_meta (
                np_communication_id TEXT PRIMARY KEY,
                owners INTEGER,
                difficulty INTEGER,
                message TEXT,
                status INTEGER NOT NULL DEFAULT 0,
                recent_players INTEGER,
                owners_completed INTEGER,
                parent_np_communication_id TEXT,
                region TEXT,
                rarity_points INTEGER
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy_group (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                np_communication_id TEXT NOT NULL,
                group_id TEXT NOT NULL,
                name TEXT,
                detail TEXT,
                icon_url TEXT,
                bronze INTEGER,
                silver INTEGER,
                gold INTEGER,
                platinum INTEGER
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                np_communication_id TEXT NOT NULL,
                group_id TEXT NOT NULL,
                order_id INTEGER NOT NULL,
                hidden INTEGER,
                type TEXT,
                name TEXT,
                detail TEXT,
                icon_url TEXT,
                progress_target_value INTEGER,
                reward_name TEXT,
                reward_image_url TEXT
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy_meta (
                trophy_id INTEGER PRIMARY KEY,
                rarity_percent REAL,
                rarity_point INTEGER,
                status INTEGER,
                owners INTEGER,
                rarity_name TEXT
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy_title_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                trophy_title_id INTEGER NOT NULL,
                detail TEXT,
                icon_url TEXT,
                set_version TEXT,
                discovered_at TEXT
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy_group_history (
                title_history_id INTEGER NOT NULL,
                group_id TEXT NOT NULL,
                name TEXT,
                detail TEXT,
                icon_url TEXT
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy_history (
                title_history_id INTEGER NOT NULL,
                group_id TEXT NOT NULL,
                order_id INTEGER NOT NULL,
                name TEXT,
                detail TEXT,
                icon_url TEXT,
                progress_target_value INTEGER
            )'
        );
        $this->database->exec(
            'CREATE TABLE psn100_change (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                change_type TEXT NOT NULL,
                param_1 INTEGER NOT NULL,
                param_2 INTEGER NOT NULL
            )'
        );
    }

    private function insertGame(int $id, string $npCommunicationId, string $platform): void
    {
        $query = $this->database->prepare(
            'INSERT INTO trophy_title (id, np_communication_id, name, detail, icon_url, platform, bronze, silver, gold, platinum, set_version)
             VALUES (:id, :np_communication_id, :name, :detail, :icon_url, :platform, :bronze, :silver, :gold, :platinum, :set_version)'
        );
        $query->execute([
            ':id' => $id,
            ':np_communication_id' => $npCommunicationId,
            ':name' => 'Example Game',
            ':detail' => 'Original detail',
            ':icon_url' => 'title-icon.png',
            ':platform' => $platform,
            ':bronze' => 1,
            ':silver' => 0,
            ':gold' => 0,
            ':platinum' => 0,
            ':set_version' => '01.00',
        ]);
    }

    private function seedSourceGame(): void
    {
        $this->insertGame(1, 'NPWR00001_00', 'PS4');
        $this->database->exec(
            "INSERT INTO trophy_title_meta (np_communication_id, status, message, region, rarity_points)
             VALUES ('NPWR00001_00', 0, '', '', 0)"
        );
        $this->database->exec(
            "INSERT INTO trophy_group (np_communication_id, group_id, name, detail, icon_url, bronze, silver, gold, platinum)
             VALUES ('NPWR00001_00', 'default', 'Base Game', 'Group detail', 'group-icon.png', 1, 0, 0, 0)"
        );
        $this->database->exec(
            "INSERT INTO trophy (np_communication_id, group_id, order_id, hidden, type, name, detail, icon_url)
             VALUES ('NPWR00001_00', 'default', 1, 0, 'bronze', 'First Trophy', 'Trophy detail', 'trophy-icon.png')"
        );
        $this->database->exec(
            'INSERT INTO trophy_meta (trophy_id, rarity_percent, rarity_point, status, owners, rarity_name)
             VALUES (1, 10.5, 5, 0, 100, "Common")'
        );
        $this->database->exec(
            "INSERT INTO trophy_title_history (trophy_title_id, detail, icon_url, set_version, discovered_at)
             VALUES (1, 'Original detail', 'title-icon.png', '01.00', '2024-01-01 00:00:00')"
        );
        $this->database->exec(
            "INSERT INTO trophy_group_history (title_history_id, group_id, name, detail, icon_url)
             VALUES (1, 'default', 'Base Game', 'Group detail', 'group-icon.png')"
        );
        $this->database->exec(
            "INSERT INTO trophy_history (title_history_id, group_id, order_id, name, detail, icon_url, progress_target_value)
             VALUES (1, 'default', 1, 'First Trophy', 'Trophy detail', 'trophy-icon.png', NULL)"
        );
    }
}

/**
 * SQLite test double that stubs MySQL-specific next-id lookup used during cloning.
 */
final class TrophyTitleCloneServiceTestDatabase extends PDO
{
    private const NEXT_TROPHY_TITLE_ID = 2;

    public function __construct()
    {
        parent::__construct('sqlite::memory:');
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'information_schema.tables')) {
            return new TrophyTitleCloneServiceNextIdStatement(self::NEXT_TROPHY_TITLE_ID);
        }

        if (str_starts_with(trim($query), 'ANALYZE TABLE')) {
            return new TrophyTitleCloneServiceNoOpStatement();
        }

        return parent::prepare($query, $options);
    }
}

final class TrophyTitleCloneServiceNextIdStatement extends PDOStatement
{
    public function __construct(private readonly int $nextId)
    {
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->nextId;
    }
}

final class TrophyTitleCloneServiceNoOpStatement extends PDOStatement
{
    public function execute(?array $params = null): bool
    {
        return true;
    }
}
