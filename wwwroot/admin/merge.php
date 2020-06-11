<?php
ini_set("max_execution_time", "0");
ini_set("max_input_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("../init.php");

if (ctype_digit(strval($_POST["trophyparent"])) && isset($_POST["trophychild"])) {
    $trophyChildId = $_POST["trophychild"];
    $trophyParentId = $_POST["trophyparent"];
    $children = explode(",", $trophyChildId);

    // Sanity checks
    foreach ($children as $childId) {
        $query = $database->prepare("SELECT np_communication_id
            FROM   trophy
            WHERE  id = :id ");
        $query->bindParam(":id", $childId, PDO::PARAM_INT);
        $query->execute();
        $childNpCommunicationId = $query->fetchColumn();
        if (substr($childNpCommunicationId, 0, 5) === "MERGE") {
            echo "Child can't be a merge title.";
            die();
        }
    }
    $query = $database->prepare("SELECT np_communication_id
        FROM   trophy
        WHERE  id = :id ");
    $query->bindParam(":id", $trophyParentId, PDO::PARAM_INT);
    $query->execute();
    $parentNpCommunicationId = $query->fetchColumn();
    if (substr($parentNpCommunicationId, 0, 5) !== "MERGE") {
        echo "Parent must be a merge title.";
        die();
    }

    foreach ($children as $childId) {
        // Grab the trophy data from child, and merge them with parent
        $database->beginTransaction();
        $query = $database->prepare("INSERT IGNORE
            into   trophy_merge
                   (
                          child_np_communication_id,
                          child_group_id,
                          child_order_id,
                          parent_np_communication_id,
                          parent_group_id,
                          parent_order_id
                   )
            SELECT child.np_communication_id,
                   child.group_id,
                   child.order_id,
                   parent.np_communication_id,
                   parent.group_id,
                   parent.order_id
            FROM   trophy child,
                   trophy parent
            WHERE  child.id = :child_trophy_id
            AND    parent.id = :parent_trophy_id");
        $query->bindParam(":child_trophy_id", $childId, PDO::PARAM_INT);
        $query->bindParam(":parent_trophy_id", $trophyParentId, PDO::PARAM_INT);
        $query->execute();
        $database->commit();

        // Set child game as merged (status = 2)
        $database->beginTransaction();
        $query = $database->prepare("UPDATE trophy_title
            SET    status = 2
            WHERE  np_communication_id = (SELECT np_communication_id
                                          FROM   trophy
                                          WHERE  id = :child_trophy_id) ");
        $query->bindParam(":child_trophy_id", $childId, PDO::PARAM_INT);
        $query->execute();
        $database->commit();

        // Go through all players from child game
        $players = $database->prepare("SELECT account_id,
                   last_updated_date
            FROM   trophy_title_player
            WHERE  np_communication_id = (SELECT np_communication_id
                                          FROM   trophy
                                          WHERE  id = :child_trophy_id) ");
        $players->bindParam(":child_trophy_id", $childId, PDO::PARAM_INT);
        $players->execute();
        while ($player = $players->fetch()) {
            // Copy the trophy
            $childTrophies = $database->prepare("SELECT np_communication_id,
                       group_id,
                       order_id,
                       earned_date
                FROM   trophy_earned
                WHERE  np_communication_id = (SELECT np_communication_id
                                              FROM   trophy
                                              WHERE  id = :child_trophy_id)
                       AND group_id = (SELECT group_id
                                       FROM   trophy
                                       WHERE  id = :child_trophy_id)
                       AND order_id = (SELECT order_id
                                       FROM   trophy
                                       WHERE  id = :child_trophy_id)
                       AND account_id = :account_id ");
            $childTrophies->bindParam(":child_trophy_id", $childId, PDO::PARAM_INT);
            $childTrophies->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
            $childTrophies->execute();
            while ($child = $childTrophies->fetch()) {
                $query = $database->prepare("SELECT parent_np_communication_id,
                           parent_group_id,
                           parent_order_id
                    FROM   trophy_merge
                    WHERE  child_np_communication_id = :child_np_communication_id
                           AND child_group_id = :child_group_id
                           AND child_order_id = :child_order_id ");
                $query->bindParam(":child_np_communication_id", $child["np_communication_id"], PDO::PARAM_STR);
                $query->bindParam(":child_group_id", $child["group_id"], PDO::PARAM_STR);
                $query->bindParam(":child_order_id", $child["order_id"], PDO::PARAM_INT);
                $query->execute();
                $parent = $query->fetch();

                $query = $database->prepare("INSERT INTO trophy_earned
                                (
                                            np_communication_id,
                                            group_id,
                                            order_id,
                                            account_id,
                                            earned_date
                                )
                                VALUES
                                (
                                            :np_communication_id,
                                            :group_id,
                                            :order_id,
                                            :account_id,
                                            :earned_date
                                )
                    on duplicate KEY
                    UPDATE earned_date = IF(earned_date > VALUES
                           (
                                  earned_date
                           )
                           , earned_date, VALUES
                           (
                                  earned_date
                           )
                           )");
                $query->bindParam(":np_communication_id", $parent["parent_np_communication_id"], PDO::PARAM_STR);
                $query->bindParam(":group_id", $parent["parent_group_id"], PDO::PARAM_STR);
                $query->bindParam(":order_id", $parent["parent_order_id"], PDO::PARAM_INT);
                $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
                $query->bindParam(":earned_date", $child["earned_date"], PDO::PARAM_STR);
                $query->execute();
            }

            // trophy_group_player
            $groups = $database->prepare("SELECT DISTINCT parent_np_communication_id,
                                parent_group_id
                FROM   trophy_merge
                WHERE  child_np_communication_id = (SELECT np_communication_id
                                                    FROM   trophy
                                                    WHERE  id = :child_trophy_id) ");
            $groups->bindParam(":child_trophy_id", $childId, PDO::PARAM_INT);
            $groups->execute();
            $titleHavePlatinum = false;
            while ($group = $groups->fetch()) {
                $groupHavePlatinum = false;

                $query = $database->prepare("SELECT type,
                           Count(*) AS count
                    FROM   trophy
                    WHERE  np_communication_id = :np_communication_id
                           AND group_id = :group_id
                           AND status = 0
                    GROUP  BY type ");
                $query->bindParam(":np_communication_id", $group["parent_np_communication_id"], PDO::PARAM_STR);
                $query->bindParam(":group_id", $group["parent_group_id"], PDO::PARAM_STR);
                $query->execute();
                $trophyTypes = $query->fetchAll(PDO::FETCH_KEY_PAIR);
                if (!isset($trophyTypes["bronze"])) {
                    $trophyTypes["bronze"] = 0;
                }
                if (!isset($trophyTypes["silver"])) {
                    $trophyTypes["silver"] = 0;
                }
                if (!isset($trophyTypes["gold"])) {
                    $trophyTypes["gold"] = 0;
                }
                if (!isset($trophyTypes["platinum"])) {
                    $trophyTypes["platinum"] = 0;
                } else {
                    $titleHavePlatinum = true;
                    $groupHavePlatinum = true;
                }

                // Recalculate trophies for trophy group for the player
                $maxScore = $trophyTypes["bronze"]*15 + $trophyTypes["silver"]*30 + $trophyTypes["gold"]*90; // Platinum isn't counted for
                $query = $database->prepare("SELECT type,
                           Count(type) AS count
                    FROM   trophy_earned te
                           LEFT JOIN trophy t
                                  ON t.np_communication_id = te.np_communication_id
                                     AND t.group_id = te.group_id
                                     AND t.order_id = te.order_id
                                     AND t.status = 0
                    WHERE  account_id = :account_id
                           AND te.np_communication_id = :np_communication_id
                           AND te.group_id = :group_id
                    GROUP  BY type ");
                $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
                $query->bindParam(":np_communication_id", $group["parent_np_communication_id"], PDO::PARAM_STR);
                $query->bindParam(":group_id", $group["parent_group_id"], PDO::PARAM_STR);
                $query->execute();
                $trophyTypes = $query->fetchAll(PDO::FETCH_KEY_PAIR);
                if (!isset($trophyTypes["bronze"])) {
                    $trophyTypes["bronze"] = 0;
                }
                if (!isset($trophyTypes["silver"])) {
                    $trophyTypes["silver"] = 0;
                }
                if (!isset($trophyTypes["gold"])) {
                    $trophyTypes["gold"] = 0;
                }
                if (!isset($trophyTypes["platinum"])) {
                    $trophyTypes["platinum"] = 0;
                }
                $userScore = $trophyTypes["bronze"]*15 + $trophyTypes["silver"]*30 + $trophyTypes["gold"]*90; // Platinum isn't counted for
                if ($maxScore == 0) {
                    $progress = 100;
                } else {
                    $progress = floor($userScore/$maxScore*100);
                    if ($userScore != 0 && $progress == 0) {
                        $progress = 1;
                    }
                    if ($progress == 100 && $trophyTypes["platinum"] == 0 && $groupHavePlatinum) {
                        $progress = 99;
                    }
                }
                $query = $database->prepare("INSERT INTO trophy_group_player
                                (
                                            np_communication_id,
                                            group_id,
                                            account_id,
                                            bronze,
                                            silver,
                                            gold,
                                            platinum,
                                            progress
                                )
                                VALUES
                                (
                                            :np_communication_id,
                                            :group_id,
                                            :account_id,
                                            :bronze,
                                            :silver,
                                            :gold,
                                            :platinum,
                                            :progress
                                )
                    on duplicate KEY
                    UPDATE bronze=VALUES
                           (
                                  bronze
                           )
                           ,
                           silver=VALUES
                           (
                                  silver
                           )
                           ,
                           gold=VALUES
                           (
                                  gold
                           )
                           ,
                           platinum=VALUES
                           (
                                  platinum
                           )
                           ,
                           progress=VALUES
                           (
                                  progress
                           )");
                $query->bindParam(":np_communication_id", $group["parent_np_communication_id"], PDO::PARAM_STR);
                $query->bindParam(":group_id", $group["parent_group_id"], PDO::PARAM_STR);
                $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
                $query->bindParam(":bronze", $trophyTypes["bronze"], PDO::PARAM_INT);
                $query->bindParam(":silver", $trophyTypes["silver"], PDO::PARAM_INT);
                $query->bindParam(":gold", $trophyTypes["gold"], PDO::PARAM_INT);
                $query->bindParam(":platinum", $trophyTypes["platinum"], PDO::PARAM_INT);
                $query->bindParam(":progress", $progress, PDO::PARAM_INT);
                $query->execute();
            }

            // trophy_title_player
            $titles = $database->prepare("SELECT DISTINCT parent_np_communication_id
                FROM   trophy_merge
                WHERE  child_np_communication_id = (SELECT np_communication_id
                                                    FROM   trophy
                                                    WHERE  id = :child_trophy_id) ");
            $titles->bindParam(":child_trophy_id", $childId, PDO::PARAM_INT);
            $titles->execute();
            while ($title = $titles->fetch()) {
                $query = $database->prepare("SELECT Sum(bronze)   AS bronze,
                           Sum(silver)   AS silver,
                           Sum(gold)     AS gold,
                           Sum(platinum) AS platinum
                    FROM   trophy_group
                    WHERE  np_communication_id = :np_communication_id ");
                $query->bindParam(":np_communication_id", $title["parent_np_communication_id"], PDO::PARAM_STR);
                $query->execute();
                $trophies = $query->fetch();

                // Recalculate trophies for trophy title for the player(s)
                $maxScore = $trophies["bronze"]*15 + $trophies["silver"]*30 + $trophies["gold"]*90; // Platinum isn't counted for

                $query = $database->prepare("SELECT Sum(bronze)   AS bronze,
                           Sum(silver)   AS silver,
                           Sum(gold)     AS gold,
                           Sum(platinum) AS platinum
                    FROM   trophy_group_player
                    WHERE  account_id = :account_id
                           AND np_communication_id = :np_communication_id ");
                $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
                $query->bindParam(":np_communication_id", $title["parent_np_communication_id"], PDO::PARAM_STR);
                $query->execute();
                $trophyTypes = $query->fetch();
                $userScore = $trophyTypes["bronze"]*15 + $trophyTypes["silver"]*30 + $trophyTypes["gold"]*90; // Platinum isn't counted for
                if ($maxScore == 0) {
                    $progress = 100;
                } else {
                    $progress = floor($userScore/$maxScore*100);
                    if ($userScore != 0 && $progress == 0) {
                        $progress = 1;
                    }
                    if ($progress == 100 && $trophyTypes["platinum"] == 0 && $titleHavePlatinum) {
                        $progress = 99;
                    }
                }

                $query = $database->prepare("INSERT INTO trophy_title_player
                                (
                                            np_communication_id,
                                            account_id,
                                            bronze,
                                            silver,
                                            gold,
                                            platinum,
                                            progress,
                                            last_updated_date
                                )
                                VALUES
                                (
                                            :np_communication_id,
                                            :account_id,
                                            :bronze,
                                            :silver,
                                            :gold,
                                            :platinum,
                                            :progress,
                                            :last_updated_date
                                )
                    on duplicate KEY
                    UPDATE bronze=VALUES
                           (
                                  bronze
                           )
                           ,
                           silver=VALUES
                           (
                                  silver
                           )
                           ,
                           gold=VALUES
                           (
                                  gold
                           )
                           ,
                           platinum=VALUES
                           (
                                  platinum
                           )
                           ,
                           progress=VALUES
                           (
                                  progress
                           )
                           ,
                           last_updated_date = IF(last_updated_date < VALUES
                           (
                                  last_updated_date
                           )
                           , VALUES
                           (
                                  last_updated_date
                           )
                           , last_updated_date)");
                $query->bindParam(":np_communication_id", $title["parent_np_communication_id"], PDO::PARAM_STR);
                $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
                $query->bindParam(":bronze", $trophyTypes["bronze"], PDO::PARAM_INT);
                $query->bindParam(":silver", $trophyTypes["silver"], PDO::PARAM_INT);
                $query->bindParam(":gold", $trophyTypes["gold"], PDO::PARAM_INT);
                $query->bindParam(":platinum", $trophyTypes["platinum"], PDO::PARAM_INT);
                $query->bindParam(":progress", $progress, PDO::PARAM_INT);
                $query->bindParam(":last_updated_date", $player["last_updated_date"], PDO::PARAM_STR);
                $query->execute();
            }
        }

        // Add all affected players to the queue to recalculate trophy count, level and level progress
        $players = $database->prepare("SELECT online_id
            FROM   player p
            WHERE  EXISTS (SELECT 1
                           FROM   trophy_title_player
                                  JOIN trophy_earned USING (np_communication_id, account_id)
                           WHERE  np_communication_id = (SELECT np_communication_id
                                                         FROM   trophy
                                                         WHERE  id = :child_trophy_id)
                                  AND group_id = (SELECT group_id
                                                  FROM   trophy
                                                  WHERE  id = :child_trophy_id)
                                  AND order_id = (SELECT order_id
                                                  FROM   trophy
                                                  WHERE  id = :child_trophy_id)
                                  AND account_id = p.account_id) ");
        $players->bindParam(":child_trophy_id", $childId, PDO::PARAM_INT);
        $players->execute();
        while ($player = $players->fetch()) {
            $query = $database->prepare("INSERT INTO player_queue
                            (
                                        online_id
                            )
                            VALUES
                            (
                                        :online_id
                            )
                on duplicate KEY
                UPDATE request_time=now()");
            $query->bindParam(":online_id", $player["online_id"], PDO::PARAM_STR);
            $query->execute();
        }
    }

    $message = "The trophies have been merged.";
} elseif (ctype_digit(strval($_POST["parent"])) && ctype_digit(strval($_POST["child"]))) {
    $childId = $_POST["child"];
    $parentId = $_POST["parent"];
    $method = $_POST["method"];
    $message = "";

    // Sanity checks
    $query = $database->prepare("SELECT np_communication_id
        FROM   trophy_title
        WHERE  id = :id ");
    $query->bindParam(":id", $childId, PDO::PARAM_INT);
    $query->execute();
    $childNpCommunicationId = $query->fetchColumn();
    if (substr($childNpCommunicationId, 0, 5) === "MERGE") {
        echo "Child can't be a merge title.";
        die();
    }
    $query = $database->prepare("SELECT np_communication_id
        FROM   trophy_title
        WHERE  id = :id ");
    $query->bindParam(":id", $parentId, PDO::PARAM_INT);
    $query->execute();
    $parentNpCommunicationId = $query->fetchColumn();
    if (substr($parentNpCommunicationId, 0, 5) !== "MERGE") {
        echo "Parent must be a merge title.";
        die();
    }

    // Grab the trophy data from child, and merge them with parent
    $database->beginTransaction();
    if ($method == "name") {
        $childTrophies = $database->prepare("SELECT np_communication_id,
                   group_id,
                   order_id,
                   name
            FROM   trophy
            WHERE  np_communication_id = (SELECT np_communication_id
                                          FROM   trophy_title
                                          WHERE  id = :child_game_id) ");
        $childTrophies->bindParam(":child_game_id", $childId, PDO::PARAM_INT);
        $childTrophies->execute();
        while ($childTrophy = $childTrophies->fetch()) {
            $parentTrophies = $database->prepare("SELECT np_communication_id,
                       group_id,
                       order_id
                FROM   trophy
                WHERE  np_communication_id = (SELECT np_communication_id
                                              FROM   trophy_title
                                              WHERE  id = :parent_game_id)
                       AND name = :name ");
            $parentTrophies->bindParam(":parent_game_id", $parentId, PDO::PARAM_INT);
            $parentTrophies->bindParam(":name", $childTrophy["name"], PDO::PARAM_STR);
            $parentTrophies->execute();

            if ($parentTrophy = $parentTrophies->fetch()) {
                $query = $database->prepare("INSERT IGNORE
                    into   trophy_merge
                           (
                                  child_np_communication_id,
                                  child_group_id,
                                  child_order_id,
                                  parent_np_communication_id,
                                  parent_group_id,
                                  parent_order_id
                           )
                           VALUES
                           (
                                  :child_np_communication_id,
                                  :child_group_id,
                                  :child_order_id,
                                  :parent_np_communication_id,
                                  :parent_group_id,
                                  :parent_order_id
                           )");
                $query->bindParam(":child_np_communication_id", $childTrophy["np_communication_id"], PDO::PARAM_STR);
                $query->bindParam(":child_group_id", $childTrophy["group_id"], PDO::PARAM_STR);
                $query->bindParam(":child_order_id", $childTrophy["order_id"], PDO::PARAM_INT);
                $query->bindParam(":parent_np_communication_id", $parentTrophy["np_communication_id"], PDO::PARAM_STR);
                $query->bindParam(":parent_group_id", $parentTrophy["group_id"], PDO::PARAM_STR);
                $query->bindParam(":parent_order_id", $parentTrophy["order_id"], PDO::PARAM_INT);
                $query->execute();
            } else {
                $message .= $childTrophy["name"] ." couldn't be merged.<br>";
            }
        }
    } elseif ($method == "order") {
        $query = $database->prepare("INSERT IGNORE
            into   trophy_merge
                   (
                          child_np_communication_id,
                          child_group_id,
                          child_order_id,
                          parent_np_communication_id,
                          parent_group_id,
                          parent_order_id
                   )
            SELECT     child.np_communication_id,
                       child.group_id,
                       child.order_id,
                       parent.np_communication_id,
                       parent.group_id,
                       parent.order_id
            FROM       trophy child
            INNER JOIN trophy parent
            USING      (group_id, order_id)
            WHERE      child.np_communication_id =
                       (
                              SELECT np_communication_id
                              FROM   trophy_title
                              WHERE  id = :child_game_id)
            AND        parent.np_communication_id =
                       (
                              SELECT np_communication_id
                              FROM   trophy_title
                              WHERE  id = :parent_game_id)");
        $query->bindParam(":child_game_id", $childId, PDO::PARAM_INT);
        $query->bindParam(":parent_game_id", $parentId, PDO::PARAM_INT);
        $query->execute();
    } else {
        echo "Wrong input";
        die();
    }
    $database->commit();

    // Set child game as merged (status = 2)
    $database->beginTransaction();
    $query = $database->prepare("UPDATE trophy_title
        SET    status = 2
        WHERE  id = :game_id ");
    $query->bindParam(":game_id", $childId, PDO::PARAM_INT);
    $query->execute();
    $database->commit();

    // Go through all players with child trophies
    $players = $database->prepare("SELECT account_id,
               last_updated_date
        FROM   trophy_title_player
        WHERE  np_communication_id = (SELECT np_communication_id
                                      FROM   trophy_title
                                      WHERE  id = :game_id) ");
    $players->bindParam(":game_id", $childId, PDO::PARAM_INT);
    $players->execute();
    while ($player = $players->fetch()) {
        // Copy the trophies
        $childTrophies = $database->prepare("SELECT np_communication_id,
                   group_id,
                   order_id,
                   earned_date
            FROM   trophy_earned
            WHERE  np_communication_id = (SELECT np_communication_id
                                          FROM   trophy_title
                                          WHERE  id = :game_id)
                   AND account_id = :account_id ");
        $childTrophies->bindParam(":game_id", $childId, PDO::PARAM_INT);
        $childTrophies->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
        $childTrophies->execute();
        while ($child = $childTrophies->fetch()) {
            $query = $database->prepare("SELECT parent_np_communication_id,
                       parent_group_id,
                       parent_order_id
                FROM   trophy_merge
                WHERE  child_np_communication_id = :child_np_communication_id
                       AND child_group_id = :child_group_id
                       AND child_order_id = :child_order_id ");
            $query->bindParam(":child_np_communication_id", $child["np_communication_id"], PDO::PARAM_STR);
            $query->bindParam(":child_group_id", $child["group_id"], PDO::PARAM_STR);
            $query->bindParam(":child_order_id", $child["order_id"], PDO::PARAM_INT);
            $query->execute();

            if ($parent = $query->fetch()) {
                $query = $database->prepare("INSERT INTO trophy_earned
                                (
                                            np_communication_id,
                                            group_id,
                                            order_id,
                                            account_id,
                                            earned_date
                                )
                                VALUES
                                (
                                            :np_communication_id,
                                            :group_id,
                                            :order_id,
                                            :account_id,
                                            :earned_date
                                )
                    on duplicate KEY
                    UPDATE earned_date = IF(earned_date > VALUES
                           (
                                  earned_date
                           )
                           , earned_date, VALUES
                           (
                                  earned_date
                           )
                           )");
                $query->bindParam(":np_communication_id", $parent["parent_np_communication_id"], PDO::PARAM_STR);
                $query->bindParam(":group_id", $parent["parent_group_id"], PDO::PARAM_STR);
                $query->bindParam(":order_id", $parent["parent_order_id"], PDO::PARAM_INT);
                $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
                $query->bindParam(":earned_date", $child["earned_date"], PDO::PARAM_STR);
                $query->execute();
            }
        }

        // trophy_group_player
        $groups = $database->prepare("SELECT DISTINCT parent_np_communication_id,
                            parent_group_id
            FROM   trophy_merge
            WHERE  child_np_communication_id = (SELECT np_communication_id
                                                FROM   trophy_title
                                                WHERE  id = :game_id) ");
        $groups->bindParam(":game_id", $childId, PDO::PARAM_INT);
        $groups->execute();
        $titleHavePlatinum = false;
        while ($group = $groups->fetch()) {
            $groupHavePlatinum = false;
            $query = $database->prepare("SELECT type,
                       Count(*) AS count
                FROM   trophy
                WHERE  np_communication_id = :np_communication_id
                       AND group_id = :group_id
                       AND status = 0
                GROUP  BY type ");
            $query->bindParam(":np_communication_id", $group["parent_np_communication_id"], PDO::PARAM_STR);
            $query->bindParam(":group_id", $group["parent_group_id"], PDO::PARAM_STR);
            $query->execute();
            $trophyTypes = $query->fetchAll(PDO::FETCH_KEY_PAIR);
            if (!isset($trophyTypes["bronze"])) {
                $trophyTypes["bronze"] = 0;
            }
            if (!isset($trophyTypes["silver"])) {
                $trophyTypes["silver"] = 0;
            }
            if (!isset($trophyTypes["gold"])) {
                $trophyTypes["gold"] = 0;
            }
            if (!isset($trophyTypes["platinum"])) {
                $trophyTypes["platinum"] = 0;
            } else {
                $titleHavePlatinum = true;
                $groupHavePlatinum = true;
            }

            // Recalculate trophies for trophy group for the player
            $maxScore = $trophyTypes["bronze"]*15 + $trophyTypes["silver"]*30 + $trophyTypes["gold"]*90; // Platinum isn't counted for
            $query = $database->prepare("SELECT type,
                       Count(type) AS count
                FROM   trophy_earned te
                       LEFT JOIN trophy t
                              ON t.np_communication_id = te.np_communication_id
                                 AND t.group_id = te.group_id
                                 AND t.order_id = te.order_id
                                 AND t.status = 0
                WHERE  account_id = :account_id
                       AND te.np_communication_id = :np_communication_id
                       AND te.group_id = :group_id
                GROUP  BY type ");
            $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
            $query->bindParam(":np_communication_id", $group["parent_np_communication_id"], PDO::PARAM_STR);
            $query->bindParam(":group_id", $group["parent_group_id"], PDO::PARAM_STR);
            $query->execute();
            $trophyTypes = $query->fetchAll(PDO::FETCH_KEY_PAIR);
            if (!isset($trophyTypes["bronze"])) {
                $trophyTypes["bronze"] = 0;
            }
            if (!isset($trophyTypes["silver"])) {
                $trophyTypes["silver"] = 0;
            }
            if (!isset($trophyTypes["gold"])) {
                $trophyTypes["gold"] = 0;
            }
            if (!isset($trophyTypes["platinum"])) {
                $trophyTypes["platinum"] = 0;
            }
            $userScore = $trophyTypes["bronze"]*15 + $trophyTypes["silver"]*30 + $trophyTypes["gold"]*90; // Platinum isn't counted for
            if ($maxScore == 0) {
                $progress = 100;
            } else {
                $progress = floor($userScore/$maxScore*100);
                if ($userScore != 0 && $progress == 0) {
                    $progress = 1;
                }
                if ($progress == 100 && $trophyTypes["platinum"] == 0 && $groupHavePlatinum) {
                    $progress = 99;
                }
            }
            $query = $database->prepare("INSERT INTO trophy_group_player
                            (
                                        np_communication_id,
                                        group_id,
                                        account_id,
                                        bronze,
                                        silver,
                                        gold,
                                        platinum,
                                        progress
                            )
                            VALUES
                            (
                                        :np_communication_id,
                                        :group_id,
                                        :account_id,
                                        :bronze,
                                        :silver,
                                        :gold,
                                        :platinum,
                                        :progress
                            )
                on duplicate KEY
                UPDATE bronze=VALUES
                       (
                              bronze
                       )
                       ,
                       silver=VALUES
                       (
                              silver
                       )
                       ,
                       gold=VALUES
                       (
                              gold
                       )
                       ,
                       platinum=VALUES
                       (
                              platinum
                       )
                       ,
                       progress=VALUES
                       (
                              progress
                       )");
            $query->bindParam(":np_communication_id", $group["parent_np_communication_id"], PDO::PARAM_STR);
            $query->bindParam(":group_id", $group["parent_group_id"], PDO::PARAM_STR);
            $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
            $query->bindParam(":bronze", $trophyTypes["bronze"], PDO::PARAM_INT);
            $query->bindParam(":silver", $trophyTypes["silver"], PDO::PARAM_INT);
            $query->bindParam(":gold", $trophyTypes["gold"], PDO::PARAM_INT);
            $query->bindParam(":platinum", $trophyTypes["platinum"], PDO::PARAM_INT);
            $query->bindParam(":progress", $progress, PDO::PARAM_INT);
            $query->execute();
        }

        // trophy_title_player
        $titles = $database->prepare("SELECT DISTINCT parent_np_communication_id
            FROM   trophy_merge
            WHERE  child_np_communication_id = (SELECT np_communication_id
                                                FROM   trophy_title
                                                WHERE  id = :game_id) ");
        $titles->bindParam(":game_id", $childId, PDO::PARAM_INT);
        $titles->execute();
        while ($title = $titles->fetch()) {
            $query = $database->prepare("SELECT Sum(bronze)   AS bronze,
                       Sum(silver)   AS silver,
                       Sum(gold)     AS gold,
                       Sum(platinum) AS platinum
                FROM   trophy_group
                WHERE  np_communication_id = :np_communication_id ");
            $query->bindParam(":np_communication_id", $title["parent_np_communication_id"], PDO::PARAM_STR);
            $query->execute();
            $trophies = $query->fetch();

            // Recalculate trophies for trophy title for the player(s)
            $maxScore = $trophies["bronze"]*15 + $trophies["silver"]*30 + $trophies["gold"]*90; // Platinum isn't counted for

            $query = $database->prepare("SELECT Sum(bronze)   AS bronze,
                       Sum(silver)   AS silver,
                       Sum(gold)     AS gold,
                       Sum(platinum) AS platinum
                FROM   trophy_group_player
                WHERE  account_id = :account_id
                       AND np_communication_id = :np_communication_id ");
            $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
            $query->bindParam(":np_communication_id", $title["parent_np_communication_id"], PDO::PARAM_STR);
            $query->execute();
            $trophyTypes = $query->fetch();
            $userScore = $trophyTypes["bronze"]*15 + $trophyTypes["silver"]*30 + $trophyTypes["gold"]*90; // Platinum isn't counted for
            if ($maxScore == 0) {
                $progress = 100;
            } else {
                $progress = floor($userScore/$maxScore*100);
                if ($userScore != 0 && $progress == 0) {
                    $progress = 1;
                }
                if ($progress == 100 && $trophyTypes["platinum"] == 0 && $titleHavePlatinum) {
                    $progress = 99;
                }
            }

            $query = $database->prepare("INSERT INTO trophy_title_player
                            (
                                        np_communication_id,
                                        account_id,
                                        bronze,
                                        silver,
                                        gold,
                                        platinum,
                                        progress,
                                        last_updated_date
                            )
                            VALUES
                            (
                                        :np_communication_id,
                                        :account_id,
                                        :bronze,
                                        :silver,
                                        :gold,
                                        :platinum,
                                        :progress,
                                        :last_updated_date
                            )
                on duplicate KEY
                UPDATE bronze=VALUES
                       (
                              bronze
                       )
                       ,
                       silver=VALUES
                       (
                              silver
                       )
                       ,
                       gold=VALUES
                       (
                              gold
                       )
                       ,
                       platinum=VALUES
                       (
                              platinum
                       )
                       ,
                       progress=VALUES
                       (
                              progress
                       )
                       ,
                       last_updated_date = IF(last_updated_date < VALUES
                       (
                              last_updated_date
                       )
                       , VALUES
                       (
                              last_updated_date
                       )
                       , last_updated_date)");
            $query->bindParam(":np_communication_id", $title["parent_np_communication_id"], PDO::PARAM_STR);
            $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
            $query->bindParam(":bronze", $trophyTypes["bronze"], PDO::PARAM_INT);
            $query->bindParam(":silver", $trophyTypes["silver"], PDO::PARAM_INT);
            $query->bindParam(":gold", $trophyTypes["gold"], PDO::PARAM_INT);
            $query->bindParam(":platinum", $trophyTypes["platinum"], PDO::PARAM_INT);
            $query->bindParam(":progress", $progress, PDO::PARAM_INT);
            $query->bindParam(":last_updated_date", $player["last_updated_date"], PDO::PARAM_STR);
            $query->execute();
        }
    }

    // Add all affected players to the queue to recalculate trophy count, level and level progress
    $players = $database->prepare("SELECT online_id
        FROM   player p
        WHERE  EXISTS (SELECT 1
                       FROM   trophy_title_player ttp
                       WHERE  ttp.np_communication_id = (SELECT np_communication_id
                                                         FROM   trophy_title
                                                         WHERE  id = :game_id)
                              AND ttp.account_id = p.account_id) ");
    $players->bindParam(":game_id", $childId, PDO::PARAM_INT);
    $players->execute();
    while ($player = $players->fetch()) {
        $query = $database->prepare("INSERT INTO player_queue
                        (
                                    online_id
                        )
                        VALUES
                        (
                                    :online_id
                        )
            on duplicate KEY
            UPDATE request_time=now()");
        $query->bindParam(":online_id", $player["online_id"], PDO::PARAM_STR);
        $query->execute();
    }

    $message .= "The games have been merged.";
} elseif (ctype_digit(strval($_POST["child"]))) {
    // Clone the game. This will be the master game for the others.
    $childId = $_POST["child"];

    // Sanity checks
    $query = $database->prepare("SELECT np_communication_id
        FROM   trophy_title
        WHERE  id = :id ");
    $query->bindParam(":id", $childId, PDO::PARAM_INT);
    $query->execute();
    $childNpCommunicationId = $query->fetchColumn();
    if (substr($childNpCommunicationId, 0, 5) === "MERGE") {
        echo "Can't clone an already cloned game.";
        die();
    }

    $query = $database->prepare("SELECT np_communication_id
        FROM   trophy_title
        WHERE  id = :id ");
    $query->bindParam(":id", $childId, PDO::PARAM_INT);
    $query->execute();
    $childNpCommunicationId = $query->fetchColumn();

    $query = $database->prepare("SELECT auto_increment
        FROM   information_schema.tables
        WHERE  table_name = 'trophy_title' ");
    $query->execute();
    $gameId = $query->fetchColumn();
    $cloneNpCommunicationId = "MERGE_". str_pad($gameId, 6, '0', STR_PAD_LEFT);

    $query = $database->prepare("INSERT INTO trophy_title
                    (np_communication_id,
                     name,
                     detail,
                     icon_url,
                     platform,
                     bronze,
                     silver,
                     gold,
                     platinum,
                     message)
        SELECT :np_communication_id,
               name,
               detail,
               icon_url,
               platform,
               bronze,
               silver,
               gold,
               platinum,
               message
        FROM   trophy_title
        WHERE  id = :id ");
    $query->bindParam(":np_communication_id", $cloneNpCommunicationId, PDO::PARAM_STR);
    $query->bindParam(":id", $childId, PDO::PARAM_INT);
    $query->execute();

    $query = $database->prepare("INSERT INTO trophy_group
                    (np_communication_id,
                     group_id,
                     name,
                     detail,
                     icon_url,
                     bronze,
                     silver,
                     gold,
                     platinum)
        SELECT :np_communication_id,
               group_id,
               name,
               detail,
               icon_url,
               bronze,
               silver,
               gold,
               platinum
        FROM   trophy_group
        WHERE  np_communication_id = :child_np_communication_id ");
    $query->bindParam(":np_communication_id", $cloneNpCommunicationId, PDO::PARAM_STR);
    $query->bindParam(":child_np_communication_id", $childNpCommunicationId, PDO::PARAM_STR);
    $query->execute();

    $query = $database->prepare("INSERT INTO trophy
                    (np_communication_id,
                     group_id,
                     order_id,
                     hidden,
                     type,
                     name,
                     detail,
                     icon_url,
                     rare,
                     earned_rate,
                     status)
        SELECT :np_communication_id,
               group_id,
               order_id,
               hidden,
               type,
               name,
               detail,
               icon_url,
               0,
               0,
               status
        FROM   trophy
        WHERE  np_communication_id = :child_np_communication_id ");
    $query->bindParam(":np_communication_id", $cloneNpCommunicationId, PDO::PARAM_STR);
    $query->bindParam(":child_np_communication_id", $childNpCommunicationId, PDO::PARAM_STR);
    $query->execute();

    $message = "The game have been cloned.";
}
?>
<!doctype html>
<html lang="en">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Admin ~ Merge Games</title>
    </head>
    <body>
        <a href="/admin/">Back</a><br><br>
        <form method="post" autocomplete="off">
            Game Child ID:<br>
            <input type="number" name="child"><br>
            Game Parent ID:<br>
            <input type="number" name="parent"><br>
            Method:<br>
            <select name="method">
                <option value="name">Name</option>
                <option value="order">Order</option>
            </select><br><br>
            Trophy Child ID:<br>
            <input type="text" name="trophychild"><br>
            Trophy Parent ID:<br>
            <input type="number" name="trophyparent"><br><br>
            <input type="submit" value="Submit">
        </form>

        <?= $message; ?>
    </body>
</html>
