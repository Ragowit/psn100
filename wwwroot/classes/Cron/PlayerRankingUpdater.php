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
    private const \Closure DEFAULT_SLEEPER = sleep(...);
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

    public function __construct(
        private PDO $database,
        private int $retryDelaySeconds = self::DEFAULT_RETRY_DELAY_SECONDS,
        private int $maxRetryDelaySeconds = self::DEFAULT_MAX_RETRY_DELAY_SECONDS,
        private ?Psn100Logger $logger = null,
        private \Closure $sleeper = self::DEFAULT_SLEEPER,
    ) {
    }

    public function recalculate(): void
    {
        if (!$this->waitForLock()) {
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
                    $this->swapRankingTablesIfNeeded();
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

    private function swapRankingTablesIfNeeded(): void
    {
        if ($this->isRankingSwapAlreadyComplete()) {
            return;
        }

        $this->cleanupOrphanedTables();
        $this->swapRankingTables();
    }

    private function isRankingSwapAlreadyComplete(): bool
    {
        $hasPrimary = $this->tableExists(self::PRIMARY_TABLE);
        $hasTemporary = $this->tableExists(self::TEMPORARY_TABLE);
        $hasPrevious = $this->tableExists(self::PREVIOUS_TABLE);

        if (!$hasTemporary && $hasPrevious) {
            return true;
        }

        if (!$hasTemporary && !$hasPrevious && $hasPrimary) {
            return true;
        }

        return false;
    }

    private function tableExists(string $tableName): bool
    {
        $statement = $this->database->prepare(
            <<<'SQL'
            SELECT
                COUNT(*)
            FROM
                information_schema.tables
            WHERE
                table_schema = DATABASE()
                AND table_name = :table_name
            SQL
        );
        $statement->bindValue(':table_name', $tableName, PDO::PARAM_STR);
        $statement->execute();

        return (int) ($statement->fetchColumn() ?? 0) > 0;
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

    private function waitForLock(): bool
    {
        $attempt = 0;

        while (true) {
            try {
                return $this->acquireLock();
            } catch (Throwable $exception) {
                $attempt++;
                $delay = $this->calculateRetryDelay($attempt);
                $this->log(sprintf(
                    'Player ranking lock acquisition failed (attempt %d): %s. Retrying in %d seconds.',
                    $attempt,
                    $exception->getMessage(),
                    $delay
                ));
                ($this->sleeper)($delay);
            }
        }
    }

    private function acquireLock(): bool
    {
        if (!$this->supportsNamedLocks()) {
            return true;
        }

        $lockStatement = $this->database->prepare('SELECT GET_LOCK(:lock_name, 0)');
        $lockStatement->bindValue(':lock_name', self::LOCK_NAME, PDO::PARAM_STR);
        $lockStatement->execute();

        $result = $lockStatement->fetchColumn();
        if ($result === null || $result === false) {
            throw new RuntimeException('Unable to acquire player ranking recalculation lock.');
        }

        if ((string) $result === '1') {
            return true;
        }

        if ((string) $result === '0') {
            return false;
        }

        throw new RuntimeException(sprintf(
            'Unexpected GET_LOCK result for player ranking recalculation: %s',
            var_export($result, true)
        ));
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
            try {
                $this->logger->log($message);

                return;
            } catch (Throwable) {
                // Keep retrying even when the database logger is unavailable.
            }
        }

        error_log($message);
    }
}
