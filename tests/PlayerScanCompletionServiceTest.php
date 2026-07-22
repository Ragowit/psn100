<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanCompletionResult.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanCompletionService.php';

final class PlayerScanCompletionServiceTest extends TestCase
{
    private PDO $database;

    private PlayerScanCompletionService $service;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec(
            'CREATE TABLE player (
                account_id INTEGER PRIMARY KEY,
                bronze INTEGER NOT NULL DEFAULT 0,
                silver INTEGER NOT NULL DEFAULT 0,
                gold INTEGER NOT NULL DEFAULT 0,
                platinum INTEGER NOT NULL DEFAULT 0,
                level INTEGER NOT NULL DEFAULT 0,
                progress INTEGER NOT NULL DEFAULT 0,
                points INTEGER NOT NULL DEFAULT 0,
                status INTEGER NOT NULL DEFAULT 0,
                trophy_count_npwr INTEGER NOT NULL DEFAULT 0,
                trophy_count_sony INTEGER NOT NULL DEFAULT 0,
                rarity_points INTEGER NOT NULL DEFAULT 0,
                common INTEGER NOT NULL DEFAULT 0,
                uncommon INTEGER NOT NULL DEFAULT 0,
                rare INTEGER NOT NULL DEFAULT 0,
                epic INTEGER NOT NULL DEFAULT 0,
                legendary INTEGER NOT NULL DEFAULT 0,
                in_game_rarity_points INTEGER NOT NULL DEFAULT 0,
                in_game_common INTEGER NOT NULL DEFAULT 0,
                in_game_uncommon INTEGER NOT NULL DEFAULT 0,
                in_game_rare INTEGER NOT NULL DEFAULT 0,
                in_game_epic INTEGER NOT NULL DEFAULT 0,
                in_game_legendary INTEGER NOT NULL DEFAULT 0
            )'
        );
        $this->database->exec(
            'CREATE TABLE player_queue (
                online_id TEXT PRIMARY KEY
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy_title (
                id INTEGER PRIMARY KEY,
                np_communication_id TEXT NOT NULL UNIQUE
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy_title_meta (
                np_communication_id TEXT PRIMARY KEY,
                status INTEGER NOT NULL DEFAULT 0
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy_title_player (
                account_id INTEGER NOT NULL,
                np_communication_id TEXT NOT NULL,
                bronze INTEGER NOT NULL DEFAULT 0,
                silver INTEGER NOT NULL DEFAULT 0,
                gold INTEGER NOT NULL DEFAULT 0,
                platinum INTEGER NOT NULL DEFAULT 0,
                last_updated_date TEXT,
                rarity_points INTEGER NOT NULL DEFAULT 0,
                in_game_rarity_points INTEGER NOT NULL DEFAULT 0,
                common INTEGER NOT NULL DEFAULT 0,
                uncommon INTEGER NOT NULL DEFAULT 0,
                rare INTEGER NOT NULL DEFAULT 0,
                epic INTEGER NOT NULL DEFAULT 0,
                legendary INTEGER NOT NULL DEFAULT 0,
                in_game_common INTEGER NOT NULL DEFAULT 0,
                in_game_uncommon INTEGER NOT NULL DEFAULT 0,
                in_game_rare INTEGER NOT NULL DEFAULT 0,
                in_game_epic INTEGER NOT NULL DEFAULT 0,
                in_game_legendary INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (account_id, np_communication_id)
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy_earned (
                account_id INTEGER NOT NULL,
                np_communication_id TEXT NOT NULL,
                order_id INTEGER NOT NULL,
                earned INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (account_id, np_communication_id, order_id)
            )'
        );

        $this->service = new PlayerScanCompletionService($this->database);
    }

    public function testRecalculatePlayerTrophyStatsAndStatusRequestsRescanWhenNpwrCountExceedsSonyTotal(): void
    {
        $this->seedActiveTitle('NPWR00003_00');
        $this->database->exec("INSERT INTO player (account_id, status, trophy_count_npwr) VALUES (9, 0, 10)");
        $this->database->exec(
            "INSERT INTO trophy_title_player (
                account_id, np_communication_id, bronze, last_updated_date
            ) VALUES (9, 'NPWR00003_00', 1, datetime('now'))"
        );
        $this->database->exec(
            "INSERT INTO trophy_earned (account_id, np_communication_id, order_id, earned)
            VALUES (9, 'NPWR00003_00', 1, 1)"
        );

        $result = $this->service->recalculatePlayerTrophyStatsAndStatus('9', 5, 'recheck');

        $this->assertTrue($result->shouldContinueScan());
        $npwrCount = $this->database->query('SELECT trophy_count_npwr FROM player WHERE account_id = 9')->fetchColumn();
        $this->assertSame(1, (int) $npwrCount);
    }

    public function testRecalculatePlayerTrophyStatsAndStatusRequestsRescanForHiddenTrophies(): void
    {
        $this->seedActiveTitle('NPWR00003_00');
        $this->database->exec("INSERT INTO player (account_id, status, trophy_count_npwr) VALUES (9, 0, 1)");
        $this->database->exec(
            "INSERT INTO trophy_title_player (
                account_id, np_communication_id, bronze, last_updated_date
            ) VALUES (9, 'NPWR00003_00', 1, datetime('now'))"
        );

        $result = $this->service->recalculatePlayerTrophyStatsAndStatus('9', 5, 'recheck');

        $this->assertTrue($result->shouldContinueScan());
    }

    public function testServiceSourceRetainsMysqlSpecificInactiveStatusQuery(): void
    {
        $source = file_get_contents(__DIR__ . '/../wwwroot/classes/Cron/PlayerScanCompletionService.php');

        $this->assertStringContainsString('INTERVAL 1 YEAR', $source);
        $this->assertStringContainsString('PlayerStatus::FLAGGED->value', $source);
        $this->assertStringContainsString('AND p.status != {$flaggedStatus}', $source);
    }

    public function testRemovePlayerFromScanQueueDeletesOnlyMatchingOnlineId(): void
    {
        $this->database->exec("INSERT INTO player_queue (online_id) VALUES ('CurrentPsnName')");
        $this->database->exec("INSERT INTO player_queue (online_id) VALUES ('SomeoneElse')");

        $this->service->removePlayerFromScanQueue('CurrentPsnName');

        $remainingQueueEntries = $this->database->query('SELECT online_id FROM player_queue ORDER BY online_id')->fetchAll(
            PDO::FETCH_COLUMN
        );
        $this->assertSame(['SomeoneElse'], $remainingQueueEntries);
    }

    public function testFinalizeSuccessfulScanSourceUsesMysqlTimestampUpdate(): void
    {
        $source = file_get_contents(__DIR__ . '/../wwwroot/classes/Cron/PlayerScanCompletionService.php');

        $this->assertStringContainsString('last_updated_date = Now()', $source);
        $this->assertStringContainsString('DELETE FROM player_queue', $source);
    }

    public function testUpdateRarityPointsForActivePlayerSkipsNonActiveStatuses(): void
    {
        $this->database->exec("INSERT INTO player (account_id, status, rarity_points) VALUES (15, 1, 0)");

        $this->service->updateRarityPointsForActivePlayer('15');

        $rarityPoints = $this->database->query('SELECT rarity_points FROM player WHERE account_id = 15')->fetchColumn();
        $this->assertSame(0, (int) $rarityPoints);
    }

    private function seedActiveTitle(string $npCommunicationId): void
    {
        $this->database->exec("INSERT INTO trophy_title (id, np_communication_id) VALUES (1, '{$npCommunicationId}')");
        $this->database->exec("INSERT INTO trophy_title_meta (np_communication_id, status) VALUES ('{$npCommunicationId}', 0)");
    }
}
