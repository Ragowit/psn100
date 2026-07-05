<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerRankingUpdater.php';
require_once __DIR__ . '/../wwwroot/classes/Psn100Logger.php';

final class PlayerRankingUpdaterIntegrationTest extends TestCase
{
    private const string INTEGRATION_TEST_DB_ENV = 'PSN100_INTEGRATION_TEST_DB';

    private const string DEFAULT_APP_DATABASE = 'psn100';

    private ?PDO $database = null;

    private ?PDO $lockHolderConnection = null;

    private ?PDO $adminConnection = null;

    private ?string $integrationDatabaseName = null;

    private bool $ownsIntegrationDatabase = false;

    protected function tearDown(): void
    {
        if ($this->lockHolderConnection instanceof PDO) {
            $this->releaseRankingLock($this->lockHolderConnection);
            $this->lockHolderConnection = null;
        }

        if ($this->database instanceof PDO) {
            $this->releaseRankingLock();
        }

        if ($this->ownsIntegrationDatabase && $this->integrationDatabaseName !== null && $this->adminConnection instanceof PDO) {
            $this->adminConnection->exec(
                sprintf('DROP DATABASE IF EXISTS `%s`', $this->escapeDatabaseIdentifier($this->integrationDatabaseName))
            );
        } elseif ($this->database instanceof PDO) {
            $this->database->exec('DROP TABLE IF EXISTS player_ranking_old');
            $this->database->exec('DROP TABLE IF EXISTS player_ranking_new');
            $this->database->exec('DROP TABLE IF EXISTS player_ranking');
            $this->database->exec('DROP TABLE IF EXISTS player');
        }

        $this->database = null;
        $this->adminConnection = null;
        $this->integrationDatabaseName = null;
        $this->ownsIntegrationDatabase = false;
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

        $lockHolder = $this->createAdditionalMysqlConnection();
        if ($lockHolder === null) {
            return;
        }

        $this->lockHolderConnection = $lockHolder;
        $this->assertTrue($this->acquireRankingLock($lockHolder));

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
        $this->assertSame(
            0,
            (int) $database->query('SELECT COUNT(*) FROM player_ranking')->fetchColumn()
        );
    }

    public function testIntegrationTestsSkipWithoutExplicitDisposableDatabase(): void
    {
        $originalValue = getenv(self::INTEGRATION_TEST_DB_ENV);
        $method = new ReflectionMethod($this, 'resolveIntegrationDatabaseConfiguration');
        $method->setAccessible(true);

        putenv(self::INTEGRATION_TEST_DB_ENV);
        try {
            $this->assertSame(null, $method->invoke($this));
        } finally {
            if ($originalValue === false) {
                putenv(self::INTEGRATION_TEST_DB_ENV);
            } else {
                putenv(self::INTEGRATION_TEST_DB_ENV . '=' . $originalValue);
            }
        }
    }

    public function testIntegrationTestsRejectDefaultApplicationDatabaseName(): void
    {
        $originalValue = getenv(self::INTEGRATION_TEST_DB_ENV);
        $method = new ReflectionMethod($this, 'resolveIntegrationDatabaseConfiguration');
        $method->setAccessible(true);

        putenv(self::INTEGRATION_TEST_DB_ENV . '=' . self::DEFAULT_APP_DATABASE);
        try {
            $this->assertSame(null, $method->invoke($this));
        } finally {
            if ($originalValue === false) {
                putenv(self::INTEGRATION_TEST_DB_ENV);
            } else {
                putenv(self::INTEGRATION_TEST_DB_ENV . '=' . $originalValue);
            }
        }
    }

    private function createMysqlDatabase(): ?PDO
    {
        if ($this->database instanceof PDO) {
            return $this->database;
        }

        $configuration = $this->resolveIntegrationDatabaseConfiguration();
        if ($configuration === null) {
            return null;
        }

        try {
            $this->adminConnection = new PDO(
                sprintf('mysql:host=%s;charset=utf8mb4', $configuration['host']),
                $configuration['user'],
                $configuration['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]
            );
        } catch (Throwable) {
            return null;
        }

        if ($configuration['ephemeral']) {
            $this->integrationDatabaseName = sprintf('psn100_it_%s', bin2hex(random_bytes(4)));
            $this->ownsIntegrationDatabase = true;

            try {
                $this->adminConnection->exec(
                    sprintf(
                        'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci',
                        $this->escapeDatabaseIdentifier($this->integrationDatabaseName)
                    )
                );
            } catch (Throwable) {
                return null;
            }
        } else {
            $this->integrationDatabaseName = $configuration['database'];
            $this->ownsIntegrationDatabase = false;
        }

        try {
            $this->database = $this->connectToIntegrationDatabase(
                $configuration['host'],
                $this->integrationDatabaseName,
                $configuration['user'],
                $configuration['password']
            );
        } catch (Throwable) {
            return null;
        }

        $this->createSchema($this->database);

        return $this->database;
    }

    /**
     * @return array{host: string, user: string, password: string, database: ?string, ephemeral: bool}|null
     */
    private function resolveIntegrationDatabaseConfiguration(): ?array
    {
        $configuredValue = getenv(self::INTEGRATION_TEST_DB_ENV);
        if (!is_string($configuredValue)) {
            return null;
        }

        $configuredValue = trim($configuredValue);
        if ($configuredValue === '' || $configuredValue === '0') {
            return null;
        }

        $ephemeral = in_array(strtolower($configuredValue), ['1', 'true', 'yes', 'auto'], true);
        $databaseName = $ephemeral ? null : $configuredValue;

        if ($databaseName === self::DEFAULT_APP_DATABASE) {
            return null;
        }

        return [
            'host' => $this->readEnvironmentString('DB_HOST') ?? '127.0.0.1',
            'user' => $this->readEnvironmentString('DB_USER') ?? 'psn100',
            'password' => $this->readEnvironmentString('DB_PASSWORD') ?? 'psn100',
            'database' => $databaseName,
            'ephemeral' => $ephemeral,
        ];
    }

    private function readEnvironmentString(string $name): ?string
    {
        $value = getenv($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function createAdditionalMysqlConnection(): ?PDO
    {
        if ($this->integrationDatabaseName === null) {
            return null;
        }

        $configuration = $this->resolveIntegrationDatabaseConfiguration();
        if ($configuration === null) {
            return null;
        }

        try {
            return $this->connectToIntegrationDatabase(
                $configuration['host'],
                $this->integrationDatabaseName,
                $configuration['user'],
                $configuration['password']
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function connectToIntegrationDatabase(
        string $host,
        string $databaseName,
        string $user,
        string $password,
    ): PDO {
        return new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $databaseName),
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]
        );
    }

    private function escapeDatabaseIdentifier(string $databaseName): string
    {
        return str_replace('`', '``', $databaseName);
    }

    private function createSchema(PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS player_ranking_old');
        $database->exec('DROP TABLE IF EXISTS player_ranking_new');
        $database->exec('DROP TABLE IF EXISTS player_ranking');
        $database->exec('DROP TABLE IF EXISTS player');

        $database->exec(
            <<<SQL
            CREATE TABLE player (
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
            CREATE TABLE player_ranking (
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

    private function acquireRankingLock(?PDO $connection = null): bool
    {
        $connection ??= $this->database;

        if (!$connection instanceof PDO) {
            return false;
        }

        $statement = $connection->prepare("SELECT GET_LOCK('psn100:player_ranking_recalc', 0)");
        $statement->execute();

        return (int) ($statement->fetchColumn() ?? 0) === 1;
    }

    private function releaseRankingLock(?PDO $connection = null): void
    {
        $connection ??= $this->database;

        if (!$connection instanceof PDO) {
            return;
        }

        $statement = $connection->prepare("SELECT RELEASE_LOCK('psn100:player_ranking_recalc')");
        $statement->execute();
    }
}
