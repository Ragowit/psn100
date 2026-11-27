<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/GameService.php';

final class GameServiceTest extends TestCase
{
    public function testPlayerTrophiesUseTheFullUnsignedAccountId(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->exec(
            <<<'SQL'
            CREATE TABLE player (account_id TEXT, online_id TEXT);
            CREATE TABLE trophy_title_player (
                np_communication_id TEXT, account_id TEXT,
                bronze INTEGER, silver INTEGER, gold INTEGER, platinum INTEGER, progress INTEGER
            );
            CREATE TABLE trophy_group_player (
                np_communication_id TEXT, group_id TEXT, account_id TEXT,
                bronze INTEGER, silver INTEGER, gold INTEGER, platinum INTEGER, progress INTEGER
            );
            CREATE TABLE trophy_earned (
                np_communication_id TEXT, group_id TEXT, order_id INTEGER, account_id TEXT,
                earned_date TEXT, progress INTEGER, earned INTEGER
            );
            CREATE TABLE trophy (
                id INTEGER, np_communication_id TEXT, group_id TEXT, order_id INTEGER, type TEXT,
                name TEXT, detail TEXT, icon_url TEXT, progress_target_value INTEGER,
                reward_name TEXT, reward_image_url TEXT
            );
            CREATE TABLE trophy_meta (trophy_id INTEGER, rarity_percent REAL, in_game_rarity_percent REAL, status INTEGER);

            INSERT INTO player VALUES ('18446744073709551615', 'LargeAccount');
            INSERT INTO trophy_title_player VALUES ('NPWR00001_00', '18446744073709551615', 1, 0, 0, 0, 50);
            INSERT INTO trophy_group_player
            VALUES ('NPWR00001_00', 'default', '18446744073709551615', 1, 0, 0, 0, 50);
            INSERT INTO trophy VALUES
                (1, 'NPWR00001_00', 'default', 0, 'bronze', 'First trophy', '', '', NULL, NULL, NULL),
                (2, 'NPWR00001_00', 'default', 1, 'bronze', 'Second trophy', '', '', NULL, NULL, NULL);
            INSERT INTO trophy_meta VALUES (1, 100, 100, 0), (2, 100, 100, 0);
            INSERT INTO trophy_earned VALUES
                ('NPWR00001_00', 'default', 0, '18446744073709551615', '2026-01-01 00:00:00', NULL, 1),
                ('NPWR00001_00', 'default', 1, '9223372036854775807', '2026-01-01 00:00:00', NULL, 1);
            SQL
        );
        $service = new GameService($database);

        $accountId = $service->getPlayerAccountId('LargeAccount');
        $this->assertSame('18446744073709551615', $accountId);
        $this->assertSame(50, $service->getGamePlayer('NPWR00001_00', $accountId)?->getProgress());

        $groupPlayer = $service->getTrophyGroupPlayer('NPWR00001_00', 'default', $accountId);
        $this->assertSame($accountId, $groupPlayer?->getAccountId());
        $this->assertSame(50, $groupPlayer?->getProgress());

        $trophies = $service->getTrophies('NPWR00001_00', 'default', $accountId, GameTrophySort::Default);
        $this->assertSame([1, null], array_column($trophies, 'earned'));
    }
}
