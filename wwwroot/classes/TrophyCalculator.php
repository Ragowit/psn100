<?php

declare(strict_types=1);

final class TrophyCalculator
{
    private const int BRONZE_SCORE = 15;
    private const int SILVER_SCORE = 30;
    private const int GOLD_SCORE = 90;

    public function __construct(
        private readonly PDO $database
    ) {
    }

    private function clampProgress(int $progress): int
    {
        return max(0, min(100, $progress));
    }

    /**
     * @param array<string, int|string|null> $trophyTypes
     * @return array{bronze: int, silver: int, gold: int, platinum: int}
     */
    private function normalizeTrophyTypes(array $trophyTypes): array
    {
        return [
            'bronze' => (int) ($trophyTypes['bronze'] ?? 0),
            'silver' => (int) ($trophyTypes['silver'] ?? 0),
            'gold' => (int) ($trophyTypes['gold'] ?? 0),
            'platinum' => (int) ($trophyTypes['platinum'] ?? 0),
        ];
    }

    /**
     * @param array{bronze: int, silver: int, gold: int, platinum: int} $trophyTypes
     */
    private function calculateScore(array $trophyTypes): int
    {
        return $trophyTypes['bronze'] * self::BRONZE_SCORE
            + $trophyTypes['silver'] * self::SILVER_SCORE
            + $trophyTypes['gold'] * self::GOLD_SCORE;
    }

    /**
     * @param array{bronze: int, silver: int, gold: int, platinum: int} $trophyTypes
     */
    private function calculateProgress(array $trophyTypes, int $maxScore, bool $titleHavePlatinum): int
    {
        if ($maxScore === 0) {
            return 100;
        }

        $userScore = $this->calculateScore($trophyTypes);
        $progress = (int) floor(($userScore / $maxScore) * 100);

        if ($userScore !== 0 && $progress === 0) {
            $progress = 1;
        }

        if ($progress === 100 && $trophyTypes['platinum'] === 0 && $titleHavePlatinum) {
            $progress = 99;
        }

        return $this->clampProgress($progress);
    }

    public function recalculateTrophyGroup(string $npCommunicationId, string $groupId, int $accountId): void
    {
        $query = $this->database->prepare(
            'SELECT t.type, COUNT(*) AS count
            FROM trophy t
            JOIN trophy_meta tm ON tm.trophy_id = t.id
            WHERE t.np_communication_id = :np_communication_id
                AND t.group_id = :group_id
                AND tm.status = 0
            GROUP BY t.type'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':group_id', $groupId, PDO::PARAM_STR);
        $query->execute();
        /** @var array<string, int|string|null> $trophyTypes */
        $trophyTypes = $query->fetchAll(PDO::FETCH_KEY_PAIR);
        $trophyTypes = $this->normalizeTrophyTypes($trophyTypes);

        $titleHavePlatinum = $trophyTypes['platinum'] > 0;

        $query = $this->database->prepare(
            'UPDATE trophy_group
            SET bronze = :bronze,
                silver = :silver,
                gold = :gold,
                platinum = :platinum
            WHERE np_communication_id = :np_communication_id
                AND group_id = :group_id'
        );
        $query->bindValue(':bronze', $trophyTypes['bronze'], PDO::PARAM_INT);
        $query->bindValue(':silver', $trophyTypes['silver'], PDO::PARAM_INT);
        $query->bindValue(':gold', $trophyTypes['gold'], PDO::PARAM_INT);
        $query->bindValue(':platinum', $trophyTypes['platinum'], PDO::PARAM_INT);
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':group_id', $groupId, PDO::PARAM_STR);
        $query->execute();

        $maxScore = $this->calculateScore($trophyTypes);

        $query = $this->database->prepare(
            'SELECT t.type, COUNT(t.type) AS count
            FROM trophy_earned te
            LEFT JOIN trophy t ON t.np_communication_id = te.np_communication_id
                AND t.order_id = te.order_id
            LEFT JOIN trophy_meta tm ON tm.trophy_id = t.id
            WHERE account_id = :account_id
                AND te.np_communication_id = :np_communication_id
                AND te.group_id = :group_id
                AND te.earned = 1
                AND tm.status = 0
            GROUP BY t.type'
        );
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':group_id', $groupId, PDO::PARAM_STR);
        $query->execute();
        /** @var array<string, int|string|null> $earnedTrophyTypes */
        $earnedTrophyTypes = $query->fetchAll(PDO::FETCH_KEY_PAIR);
        $earnedTrophyTypes = $this->normalizeTrophyTypes($earnedTrophyTypes);

        $progress = $this->calculateProgress($earnedTrophyTypes, $maxScore, $titleHavePlatinum);

        $query = $this->database->prepare(
            'INSERT INTO trophy_group_player (
                np_communication_id,
                group_id,
                account_id,
                bronze,
                silver,
                gold,
                platinum,
                progress
            )
            VALUES (
                :np_communication_id,
                :group_id,
                :account_id,
                :bronze,
                :silver,
                :gold,
                :platinum,
                :progress
            ) AS new
            ON DUPLICATE KEY UPDATE
                bronze = new.bronze,
                silver = new.silver,
                gold = new.gold,
                platinum = new.platinum,
                progress = new.progress'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':group_id', $groupId, PDO::PARAM_STR);
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->bindValue(':bronze', $earnedTrophyTypes['bronze'], PDO::PARAM_INT);
        $query->bindValue(':silver', $earnedTrophyTypes['silver'], PDO::PARAM_INT);
        $query->bindValue(':gold', $earnedTrophyTypes['gold'], PDO::PARAM_INT);
        $query->bindValue(':platinum', $earnedTrophyTypes['platinum'], PDO::PARAM_INT);
        $query->bindValue(':progress', $progress, PDO::PARAM_INT);
        $query->execute();
    }

    public function recalculateTrophyTitle(string $npCommunicationId, string $lastUpdateDate, bool $newTrophies, int $accountId, bool $merge): void
    {
        $query = $this->database->prepare(
            'SELECT SUM(bronze) AS bronze,
                SUM(silver) AS silver,
                SUM(gold) AS gold,
                SUM(platinum) AS platinum
            FROM trophy_group
            WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();
        /** @var array<string, int|string|null>|false $trophiesRow */
        $trophiesRow = $query->fetch(PDO::FETCH_ASSOC);
        $trophies = $this->normalizeTrophyTypes(is_array($trophiesRow) ? $trophiesRow : []);

        $query = $this->database->prepare(
            'UPDATE trophy_title
            SET bronze = :bronze,
                silver = :silver,
                gold = :gold,
                platinum = :platinum
            WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':bronze', $trophies['bronze'], PDO::PARAM_INT);
        $query->bindValue(':silver', $trophies['silver'], PDO::PARAM_INT);
        $query->bindValue(':gold', $trophies['gold'], PDO::PARAM_INT);
        $query->bindValue(':platinum', $trophies['platinum'], PDO::PARAM_INT);
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $titleHavePlatinum = $trophies['platinum'] === 1;
        $maxScore = $this->calculateScore($trophies);

        if ($newTrophies) {
            $select = $this->database->prepare(
                'SELECT account_id,
                    bronze,
                    silver,
                    gold,
                    platinum
                FROM trophy_title_player
                WHERE np_communication_id = :np_communication_id
                    AND account_id != :account_id'
            );
            $select->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
            $select->bindValue(':account_id', $accountId, PDO::PARAM_INT);
            $select->execute();

            $updateProgress = $this->database->prepare(
                'UPDATE trophy_title_player
                SET progress = :progress
                WHERE np_communication_id = :np_communication_id
                    AND account_id = :account_id'
            );

            while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
                if (!is_array($row)) {
                    continue;
                }

                $rowTrophyTypes = $this->normalizeTrophyTypes($row);
                $progress = $this->calculateProgress($rowTrophyTypes, $maxScore, $titleHavePlatinum);

                $updateProgress->bindValue(':progress', $progress, PDO::PARAM_INT);
                $updateProgress->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
                $updateProgress->bindValue(':account_id', (int) $row['account_id'], PDO::PARAM_INT);
                $updateProgress->execute();
            }
        }

        $query = $this->database->prepare(
            'SELECT SUM(bronze) AS bronze,
                SUM(silver) AS silver,
                SUM(gold) AS gold,
                SUM(platinum) AS platinum
            FROM trophy_group_player
            WHERE account_id = :account_id
                AND np_communication_id = :np_communication_id'
        );
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();
        /** @var array<string, int|string|null>|false $playerTrophiesRow */
        $playerTrophiesRow = $query->fetch(PDO::FETCH_ASSOC);
        $playerTrophies = $this->normalizeTrophyTypes(is_array($playerTrophiesRow) ? $playerTrophiesRow : []);

        $progress = $this->calculateProgress($playerTrophies, $maxScore, $titleHavePlatinum);

        $dateTimeObject = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i:s\\Z', $lastUpdateDate);

        if ($dateTimeObject === false) {
            throw new InvalidArgumentException(sprintf('Invalid trophy title update date format: %s', $lastUpdateDate));
        }

        $dtAsTextForInsert = $dateTimeObject->format('Y-m-d H:i:s');

        if ($merge) {
            $query = $this->database->prepare(
                'INSERT INTO trophy_title_player (
                    np_communication_id,
                    account_id,
                    bronze,
                    silver,
                    gold,
                    platinum,
                    progress,
                    last_updated_date
                )
                VALUES (
                    :np_communication_id,
                    :account_id,
                    :bronze,
                    :silver,
                    :gold,
                    :platinum,
                    :progress,
                    :last_updated_date
                ) AS new
                ON DUPLICATE KEY UPDATE
                    bronze = new.bronze,
                    silver = new.silver,
                    gold = new.gold,
                    platinum = new.platinum,
                    progress = new.progress,
                    last_updated_date = GREATEST(trophy_title_player.last_updated_date, new.last_updated_date)'
            );
        } else {
            $query = $this->database->prepare(
                'INSERT INTO trophy_title_player (
                    np_communication_id,
                    account_id,
                    bronze,
                    silver,
                    gold,
                    platinum,
                    progress,
                    last_updated_date
                )
                VALUES (
                    :np_communication_id,
                    :account_id,
                    :bronze,
                    :silver,
                    :gold,
                    :platinum,
                    :progress,
                    :last_updated_date
                ) AS new
                ON DUPLICATE KEY UPDATE
                    bronze = new.bronze,
                    silver = new.silver,
                    gold = new.gold,
                    platinum = new.platinum,
                    progress = new.progress,
                    last_updated_date = new.last_updated_date'
            );
        }

        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->bindValue(':bronze', $playerTrophies['bronze'], PDO::PARAM_INT);
        $query->bindValue(':silver', $playerTrophies['silver'], PDO::PARAM_INT);
        $query->bindValue(':gold', $playerTrophies['gold'], PDO::PARAM_INT);
        $query->bindValue(':platinum', $playerTrophies['platinum'], PDO::PARAM_INT);
        $query->bindValue(':progress', $progress, PDO::PARAM_INT);
        $query->bindValue(':last_updated_date', $dtAsTextForInsert, PDO::PARAM_STR);
        $query->execute();
    }
}
