<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/HomepageContentService.php';
require_once __DIR__ . '/../wwwroot/classes/Homepage/HomepageNewGame.php';
require_once __DIR__ . '/../wwwroot/classes/Homepage/HomepageDlc.php';
require_once __DIR__ . '/../wwwroot/classes/Homepage/HomepagePopularGame.php';

final class HomepageContentServiceTest extends TestCase
{
    private PDO $pdo;

    private HomepageContentService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec(
            <<<SQL
            CREATE TABLE trophy_title (
                id INTEGER PRIMARY KEY,
                np_communication_id TEXT,
                name TEXT,
                icon_url TEXT,
                platform TEXT,
                platinum INTEGER,
                gold INTEGER,
                silver INTEGER,
                bronze INTEGER
            )
            SQL
        );

        $this->pdo->exec(
            <<<SQL
            CREATE TABLE trophy_title_meta (
                np_communication_id TEXT PRIMARY KEY,
                owners INTEGER DEFAULT 0,
                difficulty REAL DEFAULT 0,
                message TEXT DEFAULT NULL,
                status INTEGER NOT NULL,
                recent_players INTEGER DEFAULT 0,
                owners_completed INTEGER DEFAULT 0,
                psnprofiles_id INTEGER DEFAULT NULL,
                parent_np_communication_id TEXT DEFAULT NULL,
                region TEXT DEFAULT NULL,
                obsolete_ids TEXT DEFAULT NULL,
                rarity_points INTEGER DEFAULT 0,
                in_game_rarity_points INTEGER DEFAULT 0
            )
            SQL
        );

        $this->pdo->exec(
            <<<SQL
            CREATE TABLE trophy_group (
                id INTEGER PRIMARY KEY,
                np_communication_id TEXT,
                icon_url TEXT,
                name TEXT,
                group_id TEXT,
                bronze INTEGER,
                silver INTEGER,
                gold INTEGER
            )
            SQL
        );

        $this->service = new HomepageContentService($this->pdo);
    }

    public function testGetNewGamesReturnsNewestNonHiddenTitles(): void
    {
        $this->pdo->exec(
            "INSERT INTO trophy_title " .
            "(id, np_communication_id, name, icon_url, platform, platinum, gold, silver, bronze) VALUES" .
            " (1, 'NPWR001', 'First Game', 'first.png', 'PS4', 1, 2, 3, 4)," .
            " (2, 'NPWR002', 'Hidden Game', 'hidden.png', 'PS5', 5, 5, 5, 5)," .
            " (3, 'NPWR003', 'Latest Game', 'latest.png', 'PS5', 0, 1, 1, 1)"
        );

        $this->pdo->exec(
            "INSERT INTO trophy_title_meta (np_communication_id, status, recent_players) VALUES" .
            " ('NPWR001', 0, 10)," .
            " ('NPWR002', 2, 5)," .
            " ('NPWR003', 0, 20)"
        );

        $games = $this->service->getNewGames(2);

        $this->assertCount(2, $games);
        foreach ($games as $game) {
            $this->assertTrue($game instanceof HomepageNewGame, 'Expected HomepageNewGame instances.');
        }

        $this->assertSame('Latest Game', $games[0]->getName());
        $this->assertSame(0, $games[0]->getPlatinum());
        $this->assertSame('First Game', $games[1]->getName());
        $this->assertSame(1, $games[1]->getPlatinum());
    }

    public function testGetNewDlcsReturnsRecentGroupsForVisibleTitles(): void
    {
        $this->pdo->exec(
            "INSERT INTO trophy_title " .
            "(id, np_communication_id, name, icon_url, platform, platinum, gold, silver, bronze) VALUES" .
            " (1, 'NPWR100', 'Visible Game', 'visible.png', 'PS5', 0, 0, 0, 0)," .
            " (2, 'NPWR200', 'Hidden Game', 'hidden.png', 'PS4', 0, 0, 0, 0)"
        );

        $this->pdo->exec(
            "INSERT INTO trophy_title_meta (np_communication_id, status, recent_players) VALUES" .
            " ('NPWR100', 0, 100)," .
            " ('NPWR200', 2, 10)"
        );

        $this->pdo->exec(
            "INSERT INTO trophy_group " .
            "(id, np_communication_id, icon_url, name, group_id, bronze, silver, gold) VALUES" .
            " (1, 'NPWR100', 'dlc-one.png', 'DLC One', 'dlc-one', 3, 2, 1)," .
            " (2, 'NPWR100', 'default.png', 'Default Group', 'default', 0, 0, 0)," .
            " (3, 'NPWR200', 'dlc-hidden.png', 'Hidden DLC', 'dlc-hidden', 1, 1, 1)"
        );

        $dlcs = $this->service->getNewDlcs(5);

        $this->assertCount(1, $dlcs);
        foreach ($dlcs as $dlcItem) {
            $this->assertTrue($dlcItem instanceof HomepageDlc, 'Expected HomepageDlc instances.');
        }

        $dlc = $dlcs[0];
        $this->assertSame('DLC One', $dlc->getGroupName());
        $this->assertSame('dlc-one', $dlc->getGroupId());
        $this->assertSame(1, $dlc->getGold());
        $this->assertSame(2, $dlc->getSilver());
        $this->assertSame(3, $dlc->getBronze());
    }

    public function testGetPopularGamesOrdersByRecentPlayersAndLimitsResults(): void
    {
        $this->pdo->exec(
            "INSERT INTO trophy_title " .
            "(id, np_communication_id, name, icon_url, platform, platinum, gold, silver, bronze) VALUES" .
            " (1, 'NPWR300', 'Moderate Game', 'moderate.png', 'PS4', 0, 0, 0, 0)," .
            " (2, 'NPWR400', 'Hidden Game', 'hidden.png', 'PS5', 0, 0, 0, 0)," .
            " (3, 'NPWR500', 'Popular Game', 'popular.png', 'PS5', 0, 0, 0, 0)"
        );

        $this->pdo->exec(
            "INSERT INTO trophy_title_meta (np_communication_id, status, recent_players) VALUES" .
            " ('NPWR300', 0, 500)," .
            " ('NPWR400', 2, 1000)," .
            " ('NPWR500', 0, 1500)"
        );

        $popularGames = $this->service->getPopularGames(2);

        $this->assertCount(2, $popularGames);
        foreach ($popularGames as $popularGame) {
            $this->assertTrue($popularGame instanceof HomepagePopularGame, 'Expected HomepagePopularGame instances.');
        }

        $this->assertSame('Popular Game', $popularGames[0]->getName());
        $this->assertSame(1500, $popularGames[0]->getRecentPlayers());
        $this->assertSame('Moderate Game', $popularGames[1]->getName());
        $this->assertSame(500, $popularGames[1]->getRecentPlayers());
    }
}
