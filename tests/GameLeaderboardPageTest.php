<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/GameLeaderboardPage.php';
require_once __DIR__ . '/../wwwroot/classes/GameLeaderboardService.php';
require_once __DIR__ . '/../wwwroot/classes/GameHeaderService.php';
require_once __DIR__ . '/../wwwroot/classes/Game/GameHeaderData.php';

final class GameLeaderboardPageTest extends TestCase
{
    private PDO $database;

    private GameLeaderboardService $leaderboardService;

    private GameHeaderService $headerService;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec(
            <<<'SQL'
            CREATE TABLE trophy_title (
                id INTEGER PRIMARY KEY,
                np_communication_id TEXT NOT NULL,
                name TEXT NOT NULL,
                detail TEXT NOT NULL DEFAULT '',
                icon_url TEXT NOT NULL DEFAULT '',
                platform TEXT NOT NULL DEFAULT 'PS4',
                bronze INTEGER NOT NULL DEFAULT 0,
                silver INTEGER NOT NULL DEFAULT 0,
                gold INTEGER NOT NULL DEFAULT 0,
                platinum INTEGER NOT NULL DEFAULT 0,
                set_version TEXT NOT NULL DEFAULT '01.00'
            );

            CREATE TABLE trophy_title_meta (
                np_communication_id TEXT PRIMARY KEY,
                message TEXT,
                status INTEGER NOT NULL DEFAULT 0,
                recent_players INTEGER NOT NULL DEFAULT 0,
                owners_completed INTEGER NOT NULL DEFAULT 0,
                owners INTEGER NOT NULL DEFAULT 0,
                difficulty INTEGER NOT NULL DEFAULT 0,
                psnprofiles_id TEXT,
                parent_np_communication_id TEXT,
                region TEXT,
                rarity_points INTEGER NOT NULL DEFAULT 0,
                in_game_rarity_points INTEGER NOT NULL DEFAULT 0,
                obsolete_ids TEXT
            );

            CREATE TABLE player (
                account_id TEXT PRIMARY KEY,
                online_id TEXT NOT NULL,
                avatar_url TEXT NOT NULL DEFAULT '',
                country TEXT NOT NULL DEFAULT 'us',
                status INTEGER NOT NULL DEFAULT 0,
                trophy_count_npwr INTEGER NOT NULL DEFAULT 0,
                trophy_count_sony INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE player_ranking (
                account_id TEXT PRIMARY KEY,
                ranking INTEGER NOT NULL
            );

            CREATE TABLE trophy_title_player (
                np_communication_id TEXT NOT NULL,
                account_id TEXT NOT NULL,
                bronze INTEGER NOT NULL DEFAULT 0,
                silver INTEGER NOT NULL DEFAULT 0,
                gold INTEGER NOT NULL DEFAULT 0,
                platinum INTEGER NOT NULL DEFAULT 0,
                progress INTEGER NOT NULL DEFAULT 0,
                last_updated_date TEXT NOT NULL DEFAULT '2024-01-01'
            );
            SQL
        );

        $this->insertGame(1, 'NPWR00001_00', 'Example Game');
        $this->leaderboardService = new GameLeaderboardService($this->database);
        $this->headerService = new class extends GameHeaderService {
            public function __construct()
            {
            }

            public function buildHeaderData(GameDetails $game): GameHeaderData
            {
                return new GameHeaderData(null, [], 0, [], null);
            }
        };
    }

    public function testPageIsClampedWhenRequestedPageExceedsTotalPages(): void
    {
        $this->insertLeaderboardPlayer('player-1', 'PlayerOne', 100);
        $this->insertLeaderboardPlayer('player-2', 'PlayerTwo', 90);
        $this->insertLeaderboardPlayer('player-3', 'PlayerThree', 80);

        $page = GameLeaderboardPage::create(
            $this->leaderboardService,
            $this->headerService,
            1,
            null,
            ['page' => '999']
        );

        $this->assertSame(1, $page->getPage());
        $this->assertSame(1, $page->getTotalPagesCount());
        $this->assertSame(0, $page->getOffset());
        $this->assertSame(3, $page->getTotalPlayers());
        $this->assertCount(3, $page->getRows());
        $this->assertSame('PlayerOne', $page->getRows()[0]->getOnlineId());
    }

    public function testOutOfRangePageReturnsLastPageResults(): void
    {
        for ($index = 1; $index <= 55; $index++) {
            $this->insertLeaderboardPlayer(
                sprintf('player-%02d', $index),
                sprintf('Player%02d', $index),
                $index
            );
        }

        $page = GameLeaderboardPage::create(
            $this->leaderboardService,
            $this->headerService,
            1,
            null,
            ['page' => '5']
        );

        $this->assertSame(2, $page->getPage());
        $this->assertSame(2, $page->getTotalPagesCount());
        $this->assertSame(50, $page->getOffset());
        $this->assertSame(55, $page->getTotalPlayers());
        $this->assertCount(5, $page->getRows());
        $this->assertSame('Player05', $page->getRows()[0]->getOnlineId());
        $this->assertSame('Player01', $page->getRows()[4]->getOnlineId());
    }

    public function testPageDefaultsToOneWhenLeaderboardIsEmpty(): void
    {
        $page = GameLeaderboardPage::create(
            $this->leaderboardService,
            $this->headerService,
            1,
            null,
            ['page' => '10']
        );

        $this->assertSame(1, $page->getPage());
        $this->assertSame(0, $page->getTotalPagesCount());
        $this->assertSame(0, $page->getOffset());
        $this->assertSame(0, $page->getTotalPlayers());
        $this->assertCount(0, $page->getRows());
    }

    private function insertGame(int $id, string $npCommunicationId, string $name): void
    {
        $titleStatement = $this->database->prepare(
            <<<'SQL'
            INSERT INTO trophy_title (
                id,
                np_communication_id,
                name
            ) VALUES (
                :id,
                :np_communication_id,
                :name
            )
            SQL
        );
        $titleStatement->execute([
            ':id' => $id,
            ':np_communication_id' => $npCommunicationId,
            ':name' => $name,
        ]);

        $metaStatement = $this->database->prepare(
            <<<'SQL'
            INSERT INTO trophy_title_meta (
                np_communication_id
            ) VALUES (
                :np_communication_id
            )
            SQL
        );
        $metaStatement->execute([
            ':np_communication_id' => $npCommunicationId,
        ]);
    }

    private function insertLeaderboardPlayer(string $accountId, string $onlineId, int $progress): void
    {
        $playerStatement = $this->database->prepare(
            <<<'SQL'
            INSERT INTO player (
                account_id,
                online_id
            ) VALUES (
                :account_id,
                :online_id
            )
            SQL
        );
        $playerStatement->execute([
            ':account_id' => $accountId,
            ':online_id' => $onlineId,
        ]);

        $rankingStatement = $this->database->prepare(
            <<<'SQL'
            INSERT INTO player_ranking (
                account_id,
                ranking
            ) VALUES (
                :account_id,
                :ranking
            )
            SQL
        );
        $rankingStatement->execute([
            ':account_id' => $accountId,
            ':ranking' => 1,
        ]);

        $progressStatement = $this->database->prepare(
            <<<'SQL'
            INSERT INTO trophy_title_player (
                np_communication_id,
                account_id,
                progress
            ) VALUES (
                :np_communication_id,
                :account_id,
                :progress
            )
            SQL
        );
        $progressStatement->execute([
            ':np_communication_id' => 'NPWR00001_00',
            ':account_id' => $accountId,
            ':progress' => $progress,
        ]);
    }
}
