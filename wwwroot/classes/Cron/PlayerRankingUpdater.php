<?php

declare(strict_types=1);

require_once __DIR__ . '/../Psn100Logger.php';

class PlayerRankingUpdater
{
    private const string TEMPORARY_TABLE = 'player_ranking_new';
    private const string PRIMARY_TABLE = 'player_ranking';
    private const string PREVIOUS_TABLE = 'player_ranking_old';
    private const string LOCK_NAME = 'psn100:player_ranking_recalc';
    private const int DEFAULT_RETRY_DELAY_SECONDS = 3;
    private const int DEFAULT_MAX_RETRY_DELAY_SECONDS = 60;
    private const string INSERT_TEMPLATE = <<<'SQL'
INSERT INTO %s (
    account_id,
    ranking,
    ranking_country,
    rarity_ranking,
    rarity_ranking_country,
    in_game_rarity_ranking,
    in_game_rarity_ranking_country
)
SELECT
    account_id,
    RANK() OVER (
        ORDER BY points DESC, platinum DESC, gold DESC, silver DESC
    ) AS ranking,
    RANK() OVER (
        PARTITION BY country
        ORDER BY points DESC, platinum DESC, gold DESC, silver DESC
    ) AS ranking_country,
    RANK() OVER (
        ORDER BY `rarity_points` DESC
    ) AS rarity_ranking,
    RANK() OVER (
        PARTITION BY country
        ORDER BY `rarity_points` DESC
    ) AS rarity_ranking_country,
    RANK() OVER (
        ORDER BY `in_game_rarity_points` DESC
    ) AS in_game_rarity_ranking,
    RANK() OVER (
        PARTITION BY country
        ORDER BY `in_game_rarity_points` DESC
    ) AS in_game_rarity_ranking_country
FROM player
WHERE `status` = 0
SQL;

    private PDO $database;

    private int $retryDelaySeconds;

    private int $maxRetryDelaySeconds;

    private ?Psn100Logger $logger;

    /** @var callable(int): void */
    private $sleeper;

    public function __construct(
        PDO $database,
        int $retryDelaySeconds = self::DEFAULT_RETRY_DELAY_SECONDS,
        int $maxRetryDelaySeconds = self::DEFAULT_MAX_RETRY_DELAY_SECONDS,
        ?Psn100Logger $logger = null,
        ?callable $sleeper = null,
    ) {
        $this->database = $database;
        $this->retryDelaySeconds = $retryDelaySeconds;
        $this->maxRetryDelaySeconds = $maxRetryDelaySeconds;
        $this->logger = $logger;
        $this->sleeper = $sleeper ?? static function (int $seconds): void {
            sleep($seconds);
        };
    }

    public function recalculate(): void
    {
        if (!$this->acquireLock()) {
            $this->log('Player ranking recalculation skipped because another run is in progress.');

            return;
        }

        try {
            $this->executeWithRetry(
                function (): void {
                    $this->cleanupOrphanedTables();
                    $this->createTemporaryTable();
                    $this->clearTemporaryTable();
                    $this->populateTemporaryTable();
                },
                'build'
            );

            $this->executeWithRetry(
                function (): void {
                    $this->cleanupOrphanedTables();
                    $this->swapRankingTables();
                },
                'swap'
            );

            $this->executeWithRetry(
                function (): void {
                    $this->dropPreviousRankingTable();
                },
                'cleanup'
            );
        } finally {
            $this->releaseLock();
        }
    }

    private function executeWithRetry(callable $operation, string $phase): void
    {
        $attempt = 0;

        while (true) {
            try {
                $operation();

                return;
            } catch (Throwable $exception) {
                $attempt++;
                $delay = $this->calculateRetryDelay($attempt);
                $this->log(sprintf(
                    'Player ranking %s failed (attempt %d): %s. Retrying in %d seconds.',
                    $phase,
                    $attempt,
                    $exception->getMessage(),
                    $delay
                ));
                ($this->sleeper)($delay);
            }
        }
    }

    private function calculateRetryDelay(int $attempt): int
    {
        $exponent = min($attempt - 1, 10);

        return min(
            $this->maxRetryDelaySeconds,
            $this->retryDelaySeconds * (2 ** $exponent)
        );
    }

    private function cleanupOrphanedTables(): void
    {
        $this->database->exec(sprintf('DROP TABLE IF EXISTS %s', self::PREVIOUS_TABLE));
    }

    private function createTemporaryTable(): void
    {
        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS %s LIKE %s',
            self::TEMPORARY_TABLE,
            self::PRIMARY_TABLE
        );

        $this->database->exec($sql);
    }

    private function clearTemporaryTable(): void
    {
        $sql = sprintf('TRUNCATE TABLE %s', self::TEMPORARY_TABLE);
        $this->database->exec($sql);
    }

    private function populateTemporaryTable(): void
    {
        $sql = sprintf(self::INSERT_TEMPLATE, self::TEMPORARY_TABLE);
        $this->database->exec($sql);
    }

    private function swapRankingTables(): void
    {
        $renameSql = sprintf(
            'RENAME TABLE %s TO %s, %s TO %s',
            self::PRIMARY_TABLE,
            self::PREVIOUS_TABLE,
            self::TEMPORARY_TABLE,
            self::PRIMARY_TABLE
        );

        $this->database->exec($renameSql);
    }

    private function dropPreviousRankingTable(): void
    {
        $dropSql = sprintf('DROP TABLE IF EXISTS %s', self::PREVIOUS_TABLE);
        $this->database->exec($dropSql);
    }

    private function acquireLock(): bool
    {
        if (!$this->supportsNamedLocks()) {
            return true;
        }

        $lockStatement = $this->database->prepare('SELECT GET_LOCK(:lock_name, 0)');
        $lockStatement->bindValue(':lock_name', self::LOCK_NAME, PDO::PARAM_STR);
        $lockStatement->execute();

        return (int) ($lockStatement->fetchColumn() ?? 0) === 1;
    }

    private function releaseLock(): void
    {
        if (!$this->supportsNamedLocks()) {
            return;
        }

        $releaseStatement = $this->database->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $releaseStatement->bindValue(':lock_name', self::LOCK_NAME, PDO::PARAM_STR);
        $releaseStatement->execute();
    }

    private function supportsNamedLocks(): bool
    {
        return $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }

    private function log(string $message): void
    {
        if ($this->logger !== null) {
            $this->logger->log($message);

            return;
        }

        error_log($message);
    }
}
