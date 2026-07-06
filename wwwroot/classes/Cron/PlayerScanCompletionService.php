<?php

declare(strict_types=1);

/**
 * Recalculates player trophy totals, PSN level/progress, status, and rarity rollups
 * after a scan completes.
 *
 * Encapsulates post-scan aggregation that was previously embedded in ThirtyMinuteCronJob.
 */
final class PlayerScanCompletionService
{
    public function __construct(private readonly PDO $database)
    {
    }

    /**
     * @return array{level: int, progress: int}
     */
    public function calculateLevelAndProgress(int $points): array
    {
        if ($points <= 5940) {
            return [
                'level' => (int) floor($points / 60) + 1,
                'progress' => (int) (floor($points / 60 * 100) % 100),
            ];
        }

        if ($points <= 14940) {
            return [
                'level' => (int) floor(($points - 5940) / 90) + 100,
                'progress' => (int) (floor(($points - 5940) / 90 * 100) % 100),
            ];
        }

        $stage = 1;
        $leftovers = $points - 14940;
        while ($leftovers > 45000 * $stage) {
            $leftovers -= 45000 * $stage;
            $stage++;
        }

        return [
            'level' => (int) floor($leftovers / (450 * $stage)) + (100 + 100 * $stage),
            'progress' => (int) (floor($leftovers / (450 * $stage) * 100) % 100),
        ];
    }

    public function recalculatePlayerTrophyStatsAndStatus(
        int $accountId,
        int $totalTrophiesSony,
        string $recheck,
    ): PlayerScanCompletionResult {
        $query = $this->database->prepare("SELECT Ifnull(Sum(ttp.bronze), 0)   AS bronze,
                Ifnull(Sum(ttp.silver), 0)   AS silver,
                Ifnull(Sum(ttp.gold), 0)     AS gold,
                Ifnull(Sum(ttp.platinum), 0) AS platinum
            FROM   trophy_title_player ttp
                JOIN trophy_title tt USING (np_communication_id)
                JOIN trophy_title_meta ttm USING (np_communication_id)
            WHERE  ttm.status = 0
                AND ttp.account_id = :account_id ");
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();
        $trophies = $query->fetch();
        $points = $trophies['bronze'] * 15
            + $trophies['silver'] * 30
            + $trophies['gold'] * 90
            + $trophies['platinum'] * 300;
        $levelAndProgress = $this->calculateLevelAndProgress($points);

        $query = $this->database->prepare("UPDATE player
            SET    bronze = :bronze,
                silver = :silver,
                gold = :gold,
                platinum = :platinum,
                level = :level,
                progress = :progress,
                points = :points
            WHERE  account_id = :account_id ");
        $query->bindValue(':bronze', $trophies['bronze'], PDO::PARAM_INT);
        $query->bindValue(':silver', $trophies['silver'], PDO::PARAM_INT);
        $query->bindValue(':gold', $trophies['gold'], PDO::PARAM_INT);
        $query->bindValue(':platinum', $trophies['platinum'], PDO::PARAM_INT);
        $query->bindValue(':level', $levelAndProgress['level'], PDO::PARAM_INT);
        $query->bindValue(':progress', $levelAndProgress['progress'], PDO::PARAM_INT);
        $query->bindValue(':points', $points, PDO::PARAM_INT);
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();

        $playerStatus = 0;

        $query = $this->database->prepare('SELECT trophy_count_npwr FROM player WHERE account_id = :account_id');
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();
        $ourTotalTrophies = $query->fetchColumn();

        if ($ourTotalTrophies > $totalTrophiesSony) {
            $query = $this->database->prepare("UPDATE `player` SET trophy_count_npwr = (SELECT COUNT(*) FROM `trophy_earned` WHERE account_id = :account_id AND earned = 1 AND np_communication_id LIKE 'N%') WHERE account_id = :account_id");
            $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
            $query->execute();

            $query = $this->database->prepare('SELECT trophy_count_npwr FROM player WHERE account_id = :account_id');
            $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
            $query->execute();
            $ourTotalTrophies = $query->fetchColumn();

            if ($recheck !== '') {
                return PlayerScanCompletionResult::continueScan();
            }
        }

        if ($ourTotalTrophies < $totalTrophiesSony && $recheck !== '') {
            return PlayerScanCompletionResult::continueScan();
        }

        $query = $this->database->prepare("SELECT
                IF(
                MAX(last_updated_date) >= DATE(NOW()) - INTERVAL 1 YEAR,
                TRUE,
                FALSE
                ) AS within_a_year
            FROM
                `trophy_title_player`
            WHERE
                account_id = :account_id
            ");
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();
        $withinAYear = $query->fetchColumn();
        if ($withinAYear == 0) {
            $playerStatus = 4;
        }

        $query = $this->database->prepare("UPDATE
                player p
            SET
                p.status = :status,
                p.trophy_count_sony = :trophy_count_sony
            WHERE
                p.account_id = :account_id
                AND p.status != 1
            ");
        $query->bindValue(':status', $playerStatus, PDO::PARAM_INT);
        $query->bindValue(':trophy_count_sony', $totalTrophiesSony, PDO::PARAM_INT);
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();

        return PlayerScanCompletionResult::completed();
    }

    public function updateRarityPointsForActivePlayer(int $accountId): void
    {
        $query = $this->database->prepare('SELECT
                p.status
            FROM
                player p
            WHERE
                p.account_id = :account_id');
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();
        $playerStatus = $query->fetchColumn();

        if ($playerStatus != 0) {
            return;
        }

        $this->executeWithDeadlockRetry(function () use ($accountId): void {
            $query = $this->database->prepare("WITH
                    rarity AS(
                    SELECT
                        trophy_earned.np_communication_id,
                        SUM(tm.rarity_point) AS points,
                        SUM(tm.in_game_rarity_point) AS in_game_points,
                        SUM(tm.rarity_name = 'COMMON') common,
                        SUM(tm.rarity_name = 'UNCOMMON') uncommon,
                        SUM(tm.rarity_name = 'RARE') rare,
                        SUM(tm.rarity_name = 'EPIC') epic,
                        SUM(tm.rarity_name = 'LEGENDARY') legendary,
                        SUM(tm.in_game_rarity_name = 'COMMON') in_game_common,
                        SUM(tm.in_game_rarity_name = 'UNCOMMON') in_game_uncommon,
                        SUM(tm.in_game_rarity_name = 'RARE') in_game_rare,
                        SUM(tm.in_game_rarity_name = 'EPIC') in_game_epic,
                        SUM(tm.in_game_rarity_name = 'LEGENDARY') in_game_legendary
                    FROM
                        trophy_earned
                    JOIN trophy t ON t.np_communication_id = trophy_earned.np_communication_id
                        AND t.order_id = trophy_earned.order_id
                    JOIN trophy_meta tm ON tm.trophy_id = t.id
                    WHERE
                        trophy_earned.account_id = :account_id AND trophy_earned.earned = 1
                    GROUP BY
                        trophy_earned.np_communication_id
                    ORDER BY NULL
                )
                UPDATE
                    trophy_title_player ttp,
                    rarity
                SET
                    ttp.rarity_points = rarity.points,
                    ttp.in_game_rarity_points = rarity.in_game_points,
                    ttp.common = rarity.common,
                    ttp.uncommon = rarity.uncommon,
                    ttp.rare = rarity.rare,
                    ttp.epic = rarity.epic,
                    ttp.legendary = rarity.legendary,
                    ttp.in_game_common = rarity.in_game_common,
                    ttp.in_game_uncommon = rarity.in_game_uncommon,
                    ttp.in_game_rare = rarity.in_game_rare,
                    ttp.in_game_epic = rarity.in_game_epic,
                    ttp.in_game_legendary = rarity.in_game_legendary
                WHERE
                    ttp.account_id = :account_id AND ttp.np_communication_id = rarity.np_communication_id");
            $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
            $query->execute();

            $query = $this->database->prepare("WITH
                    rarity AS(
                    SELECT
                        IFNULL(SUM(rarity_points), 0) AS rarity_points,
                        IFNULL(SUM(common), 0) AS common,
                        IFNULL(SUM(uncommon), 0) AS uncommon,
                        IFNULL(SUM(rare), 0) AS rare,
                        IFNULL(SUM(epic), 0) AS epic,
                        IFNULL(SUM(legendary), 0) AS legendary,
                        IFNULL(SUM(in_game_rarity_points), 0) AS in_game_rarity_points,
                        IFNULL(SUM(in_game_common), 0) AS in_game_common,
                        IFNULL(SUM(in_game_uncommon), 0) AS in_game_uncommon,
                        IFNULL(SUM(in_game_rare), 0) AS in_game_rare,
                        IFNULL(SUM(in_game_epic), 0) AS in_game_epic,
                        IFNULL(SUM(in_game_legendary), 0) AS in_game_legendary
                    FROM
                        trophy_title_player
                    WHERE
                        account_id = :account_id
                    ORDER BY NULL
                )
                UPDATE
                    player p,
                    rarity
                SET
                    p.rarity_points = rarity.rarity_points,
                    p.common = rarity.common,
                    p.uncommon = rarity.uncommon,
                    p.rare = rarity.rare,
                    p.epic = rarity.epic,
                    p.legendary = rarity.legendary,
                    p.in_game_rarity_points = rarity.in_game_rarity_points,
                    p.in_game_common = rarity.in_game_common,
                    p.in_game_uncommon = rarity.in_game_uncommon,
                    p.in_game_rare = rarity.in_game_rare,
                    p.in_game_epic = rarity.in_game_epic,
                    p.in_game_legendary = rarity.in_game_legendary
                WHERE
                    p.account_id = :account_id AND p.status = 0");
            $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
            $query->execute();
        });
    }

    /**
     * @param callable(): void $operation
     */
    private function executeWithDeadlockRetry(callable $operation, int $maxAttempts = 3): void
    {
        $attempt = 0;

        while (true) {
            try {
                $operation();
                return;
            } catch (PDOException $exception) {
                if (!$this->isDeadlockException($exception) || $attempt >= $maxAttempts) {
                    throw $exception;
                }

                $attempt++;
                usleep(200000);
            }
        }
    }

    private function isDeadlockException(PDOException $exception): bool
    {
        return $exception->getCode() === '40001'
            || (($exception->errorInfo[1] ?? null) === 1213);
    }
}
