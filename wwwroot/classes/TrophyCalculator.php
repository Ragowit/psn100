<?php

declare(strict_types=1);

class TrophyCalculator
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    private function clampProgress(int $progress): int
    {
        if ($progress < 0) {
            return 0;
        }

        if ($progress > 100) {
            return 100;
        }

        return $progress;
    }

    public function recalculateTrophyGroup(string $npCommunicationId, string $groupId, int $accountId): void
    {
        $titleHavePlatinum = false;

        $query = $this->database->prepare(
            "SELECT t.type, COUNT(*) AS count
            FROM trophy t
            JOIN trophy_meta tm ON tm.trophy_id = t.id
            WHERE t.np_communication_id = :np_communication_id
                AND t.group_id = :group_id
                AND tm.status = 0
            GROUP BY t.type"
        );
        $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(":group_id", $groupId, PDO::PARAM_STR);
        $query->execute();
        $trophyTypes = $query->fetchAll(PDO::FETCH_KEY_PAIR);

        $trophyTypes["bronze"] = $trophyTypes["bronze"] ?? 0;
        $trophyTypes["silver"] = $trophyTypes["silver"] ?? 0;
        $trophyTypes["gold"] = $trophyTypes["gold"] ?? 0;
        $trophyTypes["platinum"] = $trophyTypes["platinum"] ?? 0;

        if ($trophyTypes["platinum"] > 0) {
            $titleHavePlatinum = true;
        }

        $query = $this->database->prepare(
            "UPDATE trophy_group
            SET bronze = :bronze,
                silver = :silver,
                gold = :gold,
                platinum = :platinum
            WHERE np_communication_id = :np_communication_id
                AND group_id = :group_id"
        );
        $query->bindValue(":bronze", $trophyTypes["bronze"], PDO::PARAM_INT);
        $query->bindValue(":silver", $trophyTypes["silver"], PDO::PARAM_INT);
        $query->bindValue(":gold", $trophyTypes["gold"], PDO::PARAM_INT);
        $query->bindValue(":platinum", $trophyTypes["platinum"], PDO::PARAM_INT);
        $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(":group_id", $groupId, PDO::PARAM_STR);
        $query->execute();

        $maxScore = $trophyTypes["bronze"] * 15 + $trophyTypes["silver"] * 30 + $trophyTypes["gold"] * 90;

        $query = $this->database->prepare(
            "SELECT t.type, COUNT(t.type) AS count
            FROM trophy_earned te
            LEFT JOIN trophy t ON t.np_communication_id = te.np_communication_id
                AND t.order_id = te.order_id
            LEFT JOIN trophy_meta tm ON tm.trophy_id = t.id
            WHERE account_id = :account_id
                AND te.np_communication_id = :np_communication_id
                AND te.group_id = :group_id
                AND te.earned = 1
                AND tm.status = 0
            GROUP BY t.type"
        );
        $query->bindValue(":account_id", $accountId, PDO::PARAM_INT);
        $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(":group_id", $groupId, PDO::PARAM_STR);
        $query->execute();
        $trophyTypes = $query->fetchAll(PDO::FETCH_KEY_PAIR);

        $trophyTypes["bronze"] = $trophyTypes["bronze"] ?? 0;
        $trophyTypes["silver"] = $trophyTypes["silver"] ?? 0;
        $trophyTypes["gold"] = $trophyTypes["gold"] ?? 0;
        $trophyTypes["platinum"] = $trophyTypes["platinum"] ?? 0;

        $userScore = $trophyTypes["bronze"] * 15 + $trophyTypes["silver"] * 30 + $trophyTypes["gold"] * 90;

        if ($maxScore === 0) {
            $progress = 100;
        } else {
            $progress = (int) floor($userScore / $maxScore * 100);

            if ($userScore !== 0 && $progress === 0) {
                $progress = 1;
            }

            if ($progress === 100 && $trophyTypes["platinum"] === 0 && $titleHavePlatinum) {
                $progress = 99;
            }
        }

        $progress = $this->clampProgress($progress);

        $query = $this->database->prepare(
            "INSERT INTO trophy_group_player (
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
                progress = new.progress"
        );
        $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(":group_id", $groupId, PDO::PARAM_STR);
        $query->bindValue(":account_id", $accountId, PDO::PARAM_INT);
        $query->bindValue(":bronze", $trophyTypes["bronze"], PDO::PARAM_INT);
        $query->bindValue(":silver", $trophyTypes["silver"], PDO::PARAM_INT);
        $query->bindValue(":gold", $trophyTypes["gold"], PDO::PARAM_INT);
        $query->bindValue(":platinum", $trophyTypes["platinum"], PDO::PARAM_INT);
        $query->bindValue(":progress", $progress, PDO::PARAM_INT);
        $query->execute();
    }

    public function recalculateTrophyTitle(string $npCommunicationId, string $lastUpdateDate, bool $newTrophies, int $accountId, bool $merge): void
    {
        $titleHavePlatinum = false;

        $query = $this->database->prepare(
            "SELECT SUM(bronze) AS bronze,
                SUM(silver) AS silver,
                SUM(gold) AS gold,
                SUM(platinum) AS platinum
            FROM trophy_group
            WHERE np_communication_id = :np_communication_id"
        );
        $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
        $query->execute();
        $trophies = $query->fetch();

        $query = $this->database->prepare(
            "UPDATE trophy_title
            SET bronze = :bronze,
                silver = :silver,
                gold = :gold,
                platinum = :platinum
            WHERE np_communication_id = :np_communication_id"
        );
        $query->bindValue(":bronze", $trophies["bronze"], PDO::PARAM_INT);
        $query->bindValue(":silver", $trophies["silver"], PDO::PARAM_INT);
        $query->bindValue(":gold", $trophies["gold"], PDO::PARAM_INT);
        $query->bindValue(":platinum", $trophies["platinum"], PDO::PARAM_INT);
        $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        if ((int) $trophies["platinum"] === 1) {
            $titleHavePlatinum = true;
        }

        $maxScore = $trophies["bronze"] * 15 + $trophies["silver"] * 30 + $trophies["gold"] * 90;

        if ($newTrophies) {
            $select = $this->database->prepare(
                "SELECT account_id
                FROM trophy_title_player
                WHERE np_communication_id = :np_communication_id"
            );
            $select->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
            $select->execute();

            while ($row = $select->fetch()) {
                if ((int) $row["account_id"] === $accountId) {
                    continue;
                }

                $query = $this->database->prepare(
                    "SELECT SUM(bronze) AS bronze,
                        SUM(silver) AS silver,
                        SUM(gold) AS gold,
                        SUM(platinum) AS platinum
                    FROM trophy_group_player
                    WHERE account_id = :account_id
                        AND np_communication_id = :np_communication_id"
                );
                $query->bindValue(":account_id", $row["account_id"], PDO::PARAM_INT);
                $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
                $query->execute();
                $trophyTypes = $query->fetch();

                $userScore = $trophyTypes["bronze"] * 15 + $trophyTypes["silver"] * 30 + $trophyTypes["gold"] * 90;

                if ($maxScore === 0) {
                    $progress = 100;
                } else {
                    $progress = (int) floor($userScore / $maxScore * 100);

                    if ($userScore !== 0 && $progress === 0) {
                        $progress = 1;
                    }

                    if ($progress === 100 && $trophyTypes["platinum"] == 0 && $titleHavePlatinum) {
                        $progress = 99;
                    }
                }

                $progress = $this->clampProgress($progress);

                $query = $this->database->prepare(
                    "UPDATE trophy_title_player
                    SET progress = :progress
                    WHERE np_communication_id = :np_communication_id
                        AND account_id = :account_id"
                );
                $query->bindValue(":progress", $progress, PDO::PARAM_INT);
                $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
                $query->bindValue(":account_id", $row["account_id"], PDO::PARAM_INT);
                $query->execute();
            }
        }

        $query = $this->database->prepare(
            "SELECT SUM(bronze) AS bronze,
                SUM(silver) AS silver,
                SUM(gold) AS gold,
                SUM(platinum) AS platinum
            FROM trophy_group_player
            WHERE account_id = :account_id
                AND np_communication_id = :np_communication_id"
        );
        $query->bindValue(":account_id", $accountId, PDO::PARAM_INT);
        $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
        $query->execute();
        $trophyTypes = $query->fetch();

        $userScore = $trophyTypes["bronze"] * 15 + $trophyTypes["silver"] * 30 + $trophyTypes["gold"] * 90;

        if ($maxScore === 0) {
            $progress = 100;
        } else {
            $progress = (int) floor($userScore / $maxScore * 100);

            if ($userScore !== 0 && $progress === 0) {
                $progress = 1;
            }

            if ($progress === 100 && $trophyTypes["platinum"] == 0 && $titleHavePlatinum) {
                $progress = 99;
            }
        }

        $progress = $this->clampProgress($progress);

        $dateTimeObject = DateTime::createFromFormat("Y-m-d\\TH:i:s\\Z", $lastUpdateDate);
        $dtAsTextForInsert = $dateTimeObject->format("Y-m-d H:i:s");

        if ($merge) {
            $query = $this->database->prepare(
                "INSERT INTO trophy_title_player (
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
                    last_updated_date = IF(
                        trophy_title_player.last_updated_date > new.last_updated_date,
                        trophy_title_player.last_updated_date,
                        new.last_updated_date
                    )"
            );
        } else {
            $query = $this->database->prepare(
                "INSERT INTO trophy_title_player (
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
                    last_updated_date = new.last_updated_date"
            );
        }

        $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(":account_id", $accountId, PDO::PARAM_INT);
        $query->bindValue(":bronze", $trophyTypes["bronze"], PDO::PARAM_INT);
        $query->bindValue(":silver", $trophyTypes["silver"], PDO::PARAM_INT);
        $query->bindValue(":gold", $trophyTypes["gold"], PDO::PARAM_INT);
        $query->bindValue(":platinum", $trophyTypes["platinum"], PDO::PARAM_INT);
        $query->bindValue(":progress", $progress, PDO::PARAM_INT);
        $query->bindValue(":last_updated_date", $dtAsTextForInsert, PDO::PARAM_STR);
        $query->execute();
    }
}
