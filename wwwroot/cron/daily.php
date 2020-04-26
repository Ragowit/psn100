<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("/home/psn100/public_html/init.php");

// Update ranks
$query = $database->prepare("SELECT account_id,
           points,
           country,
           level
    FROM   player
    WHERE  status = 0
    ORDER  BY points DESC,
              platinum DESC,
              gold DESC,
              silver DESC,
              bronze DESC,
              level DESC ");
$query->execute();
$rank = 1;
$rowCount = 0;
$previousPlayerPoints = -999;
$countryRanks = array();
while ($player = $query->fetch()) {
    $playerPoints = $player["points"];
    if ($player["level"] == 0) {
        $playerPoints = -1;
    }

    if (!isset($countryRanks[$player["country"]])) {
        $countryRanks[$player["country"]] = array($playerPoints, 1, 0); // Points, Current Country Rank, Current Country Row
    }

    $rowCount++;
    $countryRanks[$player["country"]][2] = $countryRanks[$player["country"]][2] + 1;

    // Only change rank if the points differs from the previous player
    if ($playerPoints !== $previousPlayerPoints) {
        $rank = $rowCount;
    }
    if ($playerPoints !== $countryRanks[$player["country"]][0]) {
        $countryRanks[$player["country"]][0] = $playerPoints;
        $countryRanks[$player["country"]][1] = $countryRanks[$player["country"]][2];
    }

    $previousPlayerPoints = $playerPoints;

    $queryRank = $database->prepare("UPDATE player
        SET    rank = :rank,
               rank_country = :rank_country
        WHERE  account_id = :account_id ");
    $queryRank->bindParam(":rank", $rank, PDO::PARAM_INT);
    $queryRank->bindParam(":rank_country", $countryRanks[$player["country"]][1], PDO::PARAM_INT);
    $queryRank->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
    $queryRank->execute();
}

// Recalculate trophy rarity.
do {
    try {
        $query = $database->prepare("UPDATE trophy t
            SET    t.rarity_percent = CASE
                                        WHEN (SELECT owners
                                              FROM   trophy_title tt
                                              WHERE  tt.np_communication_id =
                                             t.np_communication_id) = 0 THEN 0
                                        WHEN (SELECT owners
                                              FROM   trophy_title tt
                                              WHERE  tt.np_communication_id =
                                             t.np_communication_id) <=
                                                    (SELECT Count(*)
                                                     FROM
                                                    trophy_earned te
                                                    JOIN player p USING (account_id)
                                                    WHERE
                                                    te.np_communication_id =
                                                    t.np_communication_id
                                                    AND te.group_id = t.group_id
                                                    AND te.order_id = t.order_id
                                                    AND p.status = 0
                                                    AND p.rank <= 100000) THEN 100
                                        ELSE ( (SELECT Count(*)
                                                FROM   trophy_earned te
                                                       JOIN player p USING (account_id)
                                                WHERE  te.np_communication_id =
                                                       t.np_communication_id
                                                       AND te.group_id = t.group_id
                                                       AND te.order_id = t.order_id
                                                       AND p.status = 0
                                                       AND p.rank <= 100000) / (SELECT
                                               owners
                                                                                FROM
                                               trophy_title tt
                                                                                WHERE
                                                        tt.np_communication_id =
                                                        t.np_communication_id) * 100 )
                                      end ");
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);

// Recalculate trophy rarity point
do {
    try {
        $query = $database->prepare("UPDATE trophy t
                   JOIN trophy_title tt USING (np_communication_id)
            SET    t.rarity_point = IF(t.status = 1
                                        OR tt.status != 0, 0, Floor(1 / (
                                                              Greatest(t.rarity_percent,
                                                              0.01) /
                                                              100 )
                                                                    - 1)) ");
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);

// Recalculate rarity points and rarity levels for each players.
$query = $database->prepare("SELECT account_id FROM player WHERE status = 0");
$query->execute();
while ($row = $query->fetch()) {
    // Rarity points for each game
    do {
        try {
            $queryUpdate = $database->prepare("UPDATE trophy_title_player ttp
                       INNER JOIN(SELECT account_id,
                                         np_communication_id,
                                         Sum(rarity_point) AS points
                                  FROM   trophy_earned
                                         JOIN trophy USING( np_communication_id, group_id,
                                                            order_id )
                                  WHERE  account_id = :account_id
                                  GROUP  BY np_communication_id) x USING(
                       account_id, np_communication_id )
                SET    ttp.rarity_points = x.points ");
            $queryUpdate->bindParam(":account_id", $row["account_id"], PDO::PARAM_INT);
            $queryUpdate->execute();

            $deadlock = false;
        } catch (Exception $e) {
            sleep(3);
            $deadlock = true;
        }
    } while ($deadlock);

    // Total rarity points
    do {
        try {
            $queryUpdate = $database->prepare("UPDATE player p
                       INNER JOIN(SELECT account_id,
                                         Sum(rarity_points) AS rarity_points
                                  FROM   trophy_title_player
                                  WHERE  account_id = :account_id
                                  GROUP  BY account_id) ttp USING(account_id)
                SET    p.rarity_points = ttp.rarity_points ");
            $queryUpdate->bindParam(":account_id", $row["account_id"], PDO::PARAM_INT);
            $queryUpdate->execute();

            $deadlock = false;
        } catch (Exception $e) {
            sleep(3);
            $deadlock = true;
        }
    } while ($deadlock);

    // Rarity levels
    do {
        try {
            $queryUpdate = $database->prepare("UPDATE player p
                       INNER JOIN (SELECT te.account_id,
                                          Sum(t.rarity_percent > 50)     common,
                                          Sum(t.rarity_percent <= 50
                                              AND t.rarity_percent > 20) uncommon,
                                          Sum(t.rarity_percent <= 20
                                              AND t.rarity_percent > 5)  rare,
                                          Sum(t.rarity_percent <= 5
                                              AND t.rarity_percent > 1)  epic,
                                          Sum(t.rarity_percent <= 1)     legendary
                                   FROM   trophy_earned te
                                          JOIN trophy t USING( np_communication_id, group_id,
                                                               order_id
                                          )
                                          JOIN trophy_title tt USING(np_communication_id)
                                   WHERE  te.account_id = :account_id
                                          AND t.status = 0
                                          AND tt.status = 0
                                   GROUP  BY te.account_id) x USING(account_id)
                SET    p.common = x.common,
                       p.uncommon = x.uncommon,
                       p.rare = x.rare,
                       p.epic = x.epic,
                       p.legendary = x.legendary ");
            $queryUpdate->bindParam(":account_id", $row["account_id"], PDO::PARAM_INT);
            $queryUpdate->execute();

            $deadlock = false;
        } catch (Exception $e) {
            sleep(3);
            $deadlock = true;
        }
    } while ($deadlock);
}

// Update rarity ranks
$query = $database->prepare("SELECT account_id,
           rarity_points,
           country,
           level
    FROM   player
    WHERE  status = 0
    ORDER  BY rarity_points DESC,
              legendary DESC,
              epic DESC,
              rare DESC,
              uncommon DESC,
              common DESC,
              level DESC ");
$query->execute();
$rank = 1;
$rowCount = 0;
$previousPlayerRarityPoints = -999;
$countryRanks = array();
while ($player = $query->fetch()) {
    $playerRarityPoints = $player["rarity_points"];
    if ($player["level"] == 0) {
        $playerRarityPoints = -1;
    }

    if (!isset($countryRanks[$player["country"]])) {
        $countryRanks[$player["country"]] = array($playerRarityPoints, 1, 0); // Points, Current Country Rank, Current Country Row
    }

    $rowCount++;
    $countryRanks[$player["country"]][2] = $countryRanks[$player["country"]][2] + 1;

    // Only change rank if the points differs from the previous player
    if ($playerRarityPoints !== $previousPlayerRarityPoints) {
        $rank = $rowCount;
    }
    if ($playerRarityPoints !== $countryRanks[$player["country"]][0]) {
        $countryRanks[$player["country"]][0] = $playerRarityPoints;
        $countryRanks[$player["country"]][1] = $countryRanks[$player["country"]][2];
    }

    $previousPlayerRarityPoints = $playerRarityPoints;

    $queryRank = $database->prepare("UPDATE player
        SET    rarity_rank = :rarity_rank,
               rarity_rank_country = :rarity_rank_country
        WHERE  account_id = :account_id ");
    $queryRank->bindParam(":rarity_rank", $rank, PDO::PARAM_INT);
    $queryRank->bindParam(":rarity_rank_country", $countryRanks[$player["country"]][1], PDO::PARAM_INT);
    $queryRank->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
    $queryRank->execute();
}
