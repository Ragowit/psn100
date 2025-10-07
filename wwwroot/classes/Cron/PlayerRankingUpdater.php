<?php

declare(strict_types=1);

require_once __DIR__ . '/RetryableOperationExecutor.php';

class PlayerRankingUpdater
{
    private const TEMPORARY_TABLE = 'player_ranking_new';
    private const PRIMARY_TABLE = 'player_ranking';
    private const PREVIOUS_TABLE = 'player_ranking_old';
    private const INSERT_TEMPLATE = <<<'SQL'
INSERT INTO %s (account_id, ranking, ranking_country, rarity_ranking, rarity_ranking_country)
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
    ) AS rarity_ranking_country
FROM player
WHERE `status` = 0
SQL;

    private PDO $database;

    private RetryableOperationExecutor $operationExecutor;

    public function __construct(
        PDO $database,
        int $retryDelaySeconds = 3,
        ?RetryableOperationExecutor $operationExecutor = null
    ) {
        $this->database = $database;
        $this->operationExecutor = $operationExecutor ?? new RetryableOperationExecutor(
            $retryDelaySeconds,
            [Throwable::class]
        );
    }

    public function recalculate(): void
    {
        $this->operationExecutor->execute(function (): void {
            $this->createTemporaryTable();
            $this->clearTemporaryTable();
            $this->populateTemporaryTable();
            $this->replaceRankingTable();
        });
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

    private function replaceRankingTable(): void
    {
        $renameSql = sprintf(
            'RENAME TABLE %s TO %s, %s TO %s',
            self::PRIMARY_TABLE,
            self::PREVIOUS_TABLE,
            self::TEMPORARY_TABLE,
            self::PRIMARY_TABLE
        );

        $this->database->exec($renameSql);

        $dropSql = sprintf('DROP TABLE %s', self::PREVIOUS_TABLE);
        $this->database->exec($dropSql);
    }
}
