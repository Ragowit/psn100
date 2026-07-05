<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerRankingUpdater.php';
require_once __DIR__ . '/../wwwroot/classes/Psn100Logger.php';

final class PlayerRankingUpdaterIntegrationTest extends TestCase
{
    private ?PDO $database = null;

    protected function tearDown(): void
    {
        if ($this->database instanceof PDO) {
            $this->releaseRankingLock();
            $this->database->exec('DROP TABLE IF EXISTS player_ranking_old');
            $this->database->exec('DROP TABLE IF EXISTS player_ranking_new');
            $this->database->exec('DROP TABLE IF EXISTS player_ranking');
            $this->database->exec('DROP TABLE IF EXISTS player');
            $this->database->exec('DROP TABLE IF EXISTS log');
        }

        $this->database = null;
    }

    public function testRecalculateRebuildsRankingsFromActivePlayers(): void
    {
        $database = $this->createMysqlDatabase();
        if ($database === null) {
            return;
        }

        $this->seedPlayers($database);

        $updater = new PlayerRankingUpdater(
            $database,
            retryDelaySeconds: 1,
            maxRetryDelaySeconds: 1,
        );
        $updater->recalculate();

        $rows = $database->query('SELECT account_id, ranking, ranking_country FROM player_ranking ORDER BY ranking')
            ->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(2, $rows);
        $this->assertSame('100', (string) $rows[0]['account_id']);
        $this->assertSame('1', (string) $rows[0]['ranking']);
        $this->assertSame('1', (string) $rows[0]['ranking_country']);
        $this->assertSame('200', (string) $rows[1]['account_id']);
        $this->assertSame('2', (string) $rows[1]['ranking']);
        $this->assertSame('1', (string) $rows[1]['ranking_country']);
    }

    public function testRecalculateRecoversFromOrphanedPreviousTable(): void
    {
        $database = $this->createMysqlDatabase();
        if ($database === null) {
            return;
        }

        $this->seedPlayers($database);
        $database->exec('CREATE TABLE player_ranking_old LIKE player_ranking');

        $updater = new PlayerRankingUpdater(
            $database,
            retryDelaySeconds: 1,
            maxRetryDelaySeconds: 1,
        );
        $updater->recalculate();

        $oldTableExists = (int) $database->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'player_ranking_old'"
        )->fetchColumn();

        $this->assertSame(0, $oldTableExists);
        $this->assertSame(2, (int) $database->query('SELECT COUNT(*) FROM player_ranking')->fetchColumn());
    }

    public function testRecalculateSkipsWhenAnotherRunHoldsLock(): void
    {
        $database = $this->createMysqlDatabase();
        if ($database === null) {
            return;
        }

        $this->seedPlayers($database);
        $this->assertTrue($this->acquireRankingLock());

        $logDatabase = new PDO('sqlite::memory:');
        $logDatabase->exec('CREATE TABLE log (id INTEGER PRIMARY KEY AUTOINCREMENT, message TEXT NOT NULL)');

        $updater = new PlayerRankingUpdater(
            $database,
            logger: new Psn100Logger($logDatabase),
            sleeper: static function (int $seconds): void {
                throw new RuntimeException('Should not sleep when lock is unavailable.');
            },
        );

        $updater->recalculate();

        $messages = $logDatabase->query('SELECT message FROM log ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('skipped because another run is in progress', $messages[0]);
    }

    private function createMysqlDatabase(): ?PDO
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $name = getenv('DB_NAME') ?: 'psn100';
        $user = getenv('DB_USER') ?: 'psn100';
        $password = getenv('DB_PASSWORD') ?: 'psn100';

        try {
            $database = new PDO(
                sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name),
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]
            );
        } catch (Throwable) {
            return null;
        }

        $this->database = $database;
        $this->createSchema($database);

        return $database;
    }

    private function createSchema(PDO $database): void
    {
        $database->exec(
            <<<SQL
            CREATE TABLE IF NOT EXISTS player (
                account_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                online_id VARCHAR(16) NOT NULL,
                country VARCHAR(2) NOT NULL,
                avatar_url VARCHAR(100) DEFAULT NULL,
                plus TINYINT(1) NOT NULL DEFAULT 0,
                about_me TEXT NOT NULL,
                last_updated_date DATETIME DEFAULT NULL,
                bronze MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                silver MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                gold MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                platinum MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                level SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
                points INT UNSIGNED NOT NULL DEFAULT 0,
                rarity_points INT UNSIGNED NOT NULL DEFAULT 0,
                rank_last_week MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                rarity_rank_last_week MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                rank_country_last_week MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                rarity_rank_country_last_week MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                common MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                uncommon MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                rare MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                epic MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                legendary MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                status TINYINT UNSIGNED NOT NULL DEFAULT 99,
                trophy_count_npwr MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                trophy_count_sony MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                in_game_rarity_points INT UNSIGNED NOT NULL DEFAULT 0,
                in_game_rarity_rank_last_week MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                in_game_rarity_rank_country_last_week MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                in_game_common MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                in_game_uncommon MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                in_game_rare MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                in_game_epic MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
                in_game_legendary MEDIUMINT UNSIGNED NOT NULL DEFAULT 0
            )
            SQL
        );

        $database->exec(
            <<<SQL
            CREATE TABLE IF NOT EXISTS player_ranking (
                account_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                ranking MEDIUMINT UNSIGNED NOT NULL,
                ranking_country MEDIUMINT UNSIGNED NOT NULL,
                rarity_ranking MEDIUMINT UNSIGNED NOT NULL,
                rarity_ranking_country MEDIUMINT UNSIGNED NOT NULL,
                in_game_rarity_ranking MEDIUMINT UNSIGNED NOT NULL,
                in_game_rarity_ranking_country MEDIUMINT UNSIGNED NOT NULL
            )
            SQL
        );

        $database->exec('DELETE FROM player_ranking');
        $database->exec('DELETE FROM player');
    }

    private function seedPlayers(PDO $database): void
    {
        $statement = $database->prepare(
            <<<SQL
            INSERT INTO player (
                account_id,
                online_id,
                country,
                plus,
                about_me,
                points,
                platinum,
                gold,
                silver,
                rarity_points,
                in_game_rarity_points,
                status
            ) VALUES (
                :account_id,
                :online_id,
                :country,
                0,
                '',
                :points,
                :platinum,
                :gold,
                :silver,
                :rarity_points,
                :in_game_rarity_points,
                :status
            )
            SQL
        );

        $players = [
            [
                'account_id' => 100,
                'online_id' => 'TopPlayer',
                'country' => 'US',
                'points' => 5000,
                'platinum' => 10,
                'gold' => 20,
                'silver' => 30,
                'rarity_points' => 900,
                'in_game_rarity_points' => 800,
                'status' => 0,
            ],
            [
                'account_id' => 200,
                'online_id' => 'SecondPlayer',
                'country' => 'US',
                'points' => 1000,
                'platinum' => 1,
                'gold' => 2,
                'silver' => 3,
                'rarity_points' => 100,
                'in_game_rarity_points' => 50,
                'status' => 0,
            ],
            [
                'account_id' => 300,
                'online_id' => 'InactivePlayer',
                'country' => 'US',
                'points' => 9999,
                'platinum' => 99,
                'gold' => 99,
                'silver' => 99,
                'rarity_points' => 9999,
                'in_game_rarity_points' => 9999,
                'status' => 1,
            ],
        ];

        foreach ($players as $player) {
            $statement->execute($player);
        }
    }

    private function acquireRankingLock(): bool
    {
        if (!$this->database instanceof PDO) {
            return false;
        }

        $statement = $this->database->prepare("SELECT GET_LOCK('psn100:player_ranking_recalc', 0)");
        $statement->execute();

        return (int) ($statement->fetchColumn() ?? 0) === 1;
    }

    private function releaseRankingLock(): void
    {
        if (!$this->database instanceof PDO) {
            return;
        }

        $statement = $this->database->prepare("SELECT RELEASE_LOCK('psn100:player_ranking_recalc')");
        $statement->execute();
    }
}
