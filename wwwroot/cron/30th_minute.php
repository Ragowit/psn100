<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("/home/psn100/public_html/vendor/autoload.php");
require_once("/home/psn100/public_html/init.php");

use PlayStation\Client;

$maxTime = 1800; // 1800 seconds = 30 minutes

function RecalculateTrophyGroup($npCommunicationId, $groupId, $accountId) {
    $database = new Database();
    $titleHavePlatinum = false;

    $query = $database->prepare("SELECT type,
               Count(*) AS count
        FROM   trophy
        WHERE  np_communication_id = :np_communication_id
               AND group_id = :group_id
               AND status = 0
        GROUP  BY type ");
    $query->bindParam(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
    $query->bindParam(":group_id", $groupId, PDO::PARAM_STR);
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
    }
    $query = $database->prepare("UPDATE trophy_group
        SET    bronze = :bronze,
               silver = :silver,
               gold = :gold,
               platinum = :platinum
        WHERE  np_communication_id = :np_communication_id
               AND group_id = :group_id ");
    $query->bindParam(":bronze", $trophyTypes["bronze"], PDO::PARAM_INT);
    $query->bindParam(":silver", $trophyTypes["silver"], PDO::PARAM_INT);
    $query->bindParam(":gold", $trophyTypes["gold"], PDO::PARAM_INT);
    $query->bindParam(":platinum", $trophyTypes["platinum"], PDO::PARAM_INT);
    $query->bindParam(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
    $query->bindParam(":group_id", $groupId, PDO::PARAM_STR);
    $query->execute();

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
    $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
    $query->bindParam(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
    $query->bindParam(":group_id", $groupId, PDO::PARAM_STR);
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
        if ($progress == 100 && $trophyTypes["platinum"] == 0 && $titleHavePlatinum) {
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
    $query->bindParam(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
    $query->bindParam(":group_id", $groupId, PDO::PARAM_STR);
    $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
    $query->bindParam(":bronze", $trophyTypes["bronze"], PDO::PARAM_INT);
    $query->bindParam(":silver", $trophyTypes["silver"], PDO::PARAM_INT);
    $query->bindParam(":gold", $trophyTypes["gold"], PDO::PARAM_INT);
    $query->bindParam(":platinum", $trophyTypes["platinum"], PDO::PARAM_INT);
    $query->bindParam(":progress", $progress, PDO::PARAM_INT);
    $query->execute();
}

function RecalculateTrophyTitle($npCommunicationId, $lastUpdateDate, $newDLC, $accountId, $merge) {
    $database = new Database();
    $titleHavePlatinum = false;

    $query = $database->prepare("SELECT Sum(bronze)   AS bronze,
               Sum(silver)   AS silver,
               Sum(gold)     AS gold,
               Sum(platinum) AS platinum
        FROM   trophy_group
        WHERE  np_communication_id = :np_communication_id ");
    $query->bindParam(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
    $query->execute();
    $trophies = $query->fetch();
    $query = $database->prepare("UPDATE trophy_title
        SET    bronze = :bronze,
               silver = :silver,
               gold = :gold,
               platinum = :platinum
        WHERE  np_communication_id = :np_communication_id ");
    $query->bindParam(":bronze", $trophies["bronze"], PDO::PARAM_INT);
    $query->bindParam(":silver", $trophies["silver"], PDO::PARAM_INT);
    $query->bindParam(":gold", $trophies["gold"], PDO::PARAM_INT);
    $query->bindParam(":platinum", $trophies["platinum"], PDO::PARAM_INT);
    $query->bindParam(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
    $query->execute();

    if ($trophies["platinum"] == 1) {
        $titleHavePlatinum = true;
    }

    // Recalculate trophies for trophy title for the player(s)
    $maxScore = $trophies["bronze"]*15 + $trophies["silver"]*30 + $trophies["gold"]*90; // Platinum isn't counted for
    if ($newDLC === true) {
        $select = $database->prepare("SELECT account_id
            FROM   trophy_title_player
            WHERE  np_communication_id = :np_communication_id ");
        $select->bindParam(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
        $select->execute();
        while ($row = $select->fetch()) {
            if ($row["account_id"] == $accountId) {
                continue;
            }

            $query = $database->prepare("SELECT Sum(bronze)   AS bronze,
                       Sum(silver)   AS silver,
                       Sum(gold)     AS gold,
                       Sum(platinum) AS platinum
                FROM   trophy_group_player
                WHERE  account_id = :account_id
                       AND np_communication_id = :np_communication_id ");
            $query->bindParam(":account_id", $row["account_id"], PDO::PARAM_INT);
            $query->bindParam(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
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
            $query = $database->prepare("UPDATE trophy_title_player
                SET    progress = :progress
                WHERE  np_communication_id = :np_communication_id
                       AND account_id = :account_id ");
            $query->bindParam(":progress", $progress, PDO::PARAM_INT);
            $query->bindParam(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
            $query->bindParam(":account_id", $row["account_id"], PDO::PARAM_INT);
            $query->execute();
        }
    }

    $query = $database->prepare("SELECT Sum(bronze)   AS bronze,
               Sum(silver)   AS silver,
               Sum(gold)     AS gold,
               Sum(platinum) AS platinum
        FROM   trophy_group_player
        WHERE  account_id = :account_id
               AND np_communication_id = :np_communication_id ");
    $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
    $query->bindParam(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
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
    $dateTimeObject = DateTime::createFromFormat("Y-m-d\TH:i:s\Z", $lastUpdateDate);
    $dtAsTextForInsert = $dateTimeObject->format("Y-m-d H:i:s");

    if ($merge) {
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
                   last_updated_date = IF(last_updated_date > VALUES
                   (
                          last_updated_date
                   )
                   , VALUES
                   (
                          last_updated_date
                   )
                   , last_updated_date)");
    } else {
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
                   last_updated_date=VALUES
                   (
                          last_updated_date
                   )");
    }
    $query->bindParam(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
    $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
    $query->bindParam(":bronze", $trophyTypes["bronze"], PDO::PARAM_INT);
    $query->bindParam(":silver", $trophyTypes["silver"], PDO::PARAM_INT);
    $query->bindParam(":gold", $trophyTypes["gold"], PDO::PARAM_INT);
    $query->bindParam(":platinum", $trophyTypes["platinum"], PDO::PARAM_INT);
    $query->bindParam(":progress", $progress, PDO::PARAM_INT);
    $query->bindParam(":last_updated_date", $dtAsTextForInsert, PDO::PARAM_STR);
    $query->execute();
}

// Get current tokens
$query = $database->prepare("SELECT *
    FROM   setting ");
$query->execute();
$workers = $query->fetchAll();

$clients = array();

// Login with all the tokens
$database->beginTransaction();
foreach ($workers as $worker) {
    try {
        $client = new Client();
        $refreshToken = $worker["refresh_token"];
        $client->login($refreshToken);

        // Store new token
        $refreshToken = $client->refreshToken();
        $query = $database->prepare("UPDATE setting
            SET    refresh_token = :refresh_token
            WHERE  id = :id ");
        $query->bindParam(":refresh_token", $refreshToken, PDO::PARAM_STR);
        $query->bindParam(":id", $worker["id"], PDO::PARAM_INT);
        $query->execute();

        array_push($clients, $client);
    } catch (Exception $e) {
        $message = "Can't login with worker ". $worker["id"];
        $query = $database->prepare("INSERT INTO log
                        (message)
            VALUES      (:message) ");
        $query->bindParam(":message", $message, PDO::PARAM_STR);
        $query->execute();
    }
}
$database->commit();

if (count($clients) == 0) {
    $message = "No workers available.";
    $query = $database->prepare("INSERT INTO log
                    (message)
        VALUES      (:message) ");
    $query->bindParam(":message", $message, PDO::PARAM_STR);
    $query->execute();
    die();
}

while (true) {
    // Get our queue. Prioritize user requests, and then just old scanned players from our database.
    $query = $database->prepare("SELECT online_id,
               offset
        FROM   (SELECT 1 AS tier,
                       online_id,
                       request_time,
                       offset
                FROM   player_queue
                UNION ALL
                SELECT 2 AS tier,
                       online_id,
                       last_updated_date,
                       0 AS offset
                FROM   player
                WHERE  `rank` <= 100000) a
        ORDER  BY tier,
                  request_time,
                  online_id
        LIMIT  1 ");
    $query->execute();
    $player = $query->fetch();

    // Initialize the current player
    $users = array();
    try {
        foreach ($clients as $client) {
            $user = $client->user($player["online_id"]);
            array_push($users, $user);
        }
    } catch (Exception $e) {
        if (str_contains($e->getMessage(), "User not found")) {
            $query = $database->prepare("DELETE FROM player_queue
                WHERE  online_id = :online_id ");
            $query->bindParam(":online_id", $player["online_id"], PDO::PARAM_STR);
            $query->execute();

            $query = $database->prepare("SELECT account_id
                FROM   player
                WHERE  online_id = :online_id ");
            $query->bindParam(":online_id", $player["online_id"], PDO::PARAM_STR);
            $query->execute();
            $accountId = $query->fetchColumn();

            if (!$accountId) {
                $query = $database->prepare("UPDATE player
                    SET    level = 0,
                           progress = 0,
                           platinum = 0,
                           gold = 0,
                           silver = 0,
                           bronze = 0,
                           points = 0,
                           rarity_points = 0
                    WHERE  account_id = :account_id ");
                $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                $query->execute();

                $query = $database->prepare("DELETE FROM trophy_earned
                    WHERE  account_id = :account_id ");
                $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                $query->execute();

                $query = $database->prepare("DELETE FROM trophy_group_player
                    WHERE  account_id = :account_id ");
                $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                $query->execute();

                $query = $database->prepare("DELETE FROM trophy_title_player
                    WHERE  account_id = :account_id ");
                $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                $query->execute();
            }
        }

        continue;
    }

    // Get basic info of the current player
    if (microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"] > $maxTime)
    {
        die();
    }
    $client = 0;
    try {
        $info = $users[$client]->info();
    } catch (Exception $e) {
        if (str_contains($e->getMessage(), "User not found")) {
            $query = $database->prepare("DELETE FROM player_queue
                WHERE  online_id = :online_id ");
            $query->bindParam(":online_id", $player["online_id"], PDO::PARAM_STR);
            $query->execute();

            $query = $database->prepare("SELECT account_id
                FROM   player
                WHERE  online_id = :online_id ");
            $query->bindParam(":online_id", $player["online_id"], PDO::PARAM_STR);
            $query->execute();
            $accountId = $query->fetchColumn();

            if (!$accountId) {
                $query = $database->prepare("UPDATE player
                    SET    level = 0,
                           progress = 0,
                           platinum = 0,
                           gold = 0,
                           silver = 0,
                           bronze = 0,
                           points = 0,
                           rarity_points = 0
                    WHERE  account_id = :account_id ");
                $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                $query->execute();

                $query = $database->prepare("DELETE FROM trophy_earned
                    WHERE  account_id = :account_id ");
                $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                $query->execute();

                $query = $database->prepare("DELETE FROM trophy_group_player
                    WHERE  account_id = :account_id ");
                $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                $query->execute();

                $query = $database->prepare("DELETE FROM trophy_title_player
                    WHERE  account_id = :account_id ");
                $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                $query->execute();
            }
        } elseif (str_contains($e->getMessage(), "Internal server error")) {
            // Sony seems to have some kind of random error.
            $message = "Internal server error from Sony when scanning ". $player["online_id"] .".";
            $query = $database->prepare("INSERT INTO log
                            (message)
                VALUES      (:message) ");
            $query->bindParam(":message", $message, PDO::PARAM_STR);
            $query->execute();
            
            sleep(3);
        }

        continue;
    }
    $client++;
    if ($client >= count($clients)) {
        $client = 0;
    }

    if (property_exists($info, "currentOnlineId") && is_null($info->currentOnlineId) === false) {
        $query = $database->prepare("DELETE FROM player_queue
            WHERE  online_id = :new_online_id ");
        $query->bindParam(":new_online_id", $info->currentOnlineId, PDO::PARAM_STR);
        $query->execute();

        $query = $database->prepare("UPDATE player_queue
            SET    online_id = :new_online_id
            WHERE  online_id = :old_online_id ");
        $query->bindParam(":new_online_id", $info->currentOnlineId, PDO::PARAM_STR);
        $query->bindParam(":old_online_id", $info->onlineId, PDO::PARAM_STR);
        $query->execute();
        continue;
    }

    // Get the avatar url we want to save
    $avatarUrl = $info->avatarUrls[0]->avatarUrl;
    $avatarFilename = md5_file($avatarUrl) . strtolower(substr($avatarUrl, strrpos($avatarUrl, ".")));
    // Download the avatar if we don't have it
    if (!file_exists("/home/psn100/public_html/img/avatar/". $avatarFilename)) {
        file_put_contents("/home/psn100/public_html/img/avatar/". $avatarFilename, fopen($avatarUrl, 'r'));
    }

    // Plus is null or 1, we don't want null so this will make it 0 if so.
    $plus = (bool)$info->plus;

    // Add/update player into database
    $query = $database->prepare("INSERT INTO player
                    (
                                account_id,
                                online_id,
                                country,
                                avatar_url,
                                plus,
                                about_me
                    )
                    VALUES
                    (
                                :account_id,
                                :online_id,
                                :country,
                                :avatar_url,
                                :plus,
                                :about_me
                    )
        on duplicate KEY
        UPDATE online_id=VALUES
               (
                      online_id
               )
               ,
               avatar_url=VALUES
               (
                      avatar_url
               )
               ,
               plus=VALUES
               (
                      plus
               )
               ,
               about_me=VALUES
               (
                      about_me
               )");
    $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
    $query->bindParam(":online_id", $info->onlineId, PDO::PARAM_STR);
    $query->bindParam(":country", substr(base64_decode($info->npId), -2), PDO::PARAM_STR);
    $query->bindParam(":avatar_url", $avatarFilename, PDO::PARAM_STR);
    $query->bindParam(":plus", $plus, PDO::PARAM_BOOL);
    $query->bindParam(":about_me", $info->aboutMe, PDO::PARAM_STR);
    // Don't insert level/progress/platinum/gold/silver/bronze here since our site recalculate this.
    $query->execute();

    // The profiles currently known as "Platastical", "Platasium", "ShadowsGodly" are bugged and can't
    // fetch trophy titles. Not even on the official website. Ignore them.
    if ($info->accountId == 2985983827926904402 ||
        $info->accountId == 4835369520272949900 ||
        $info->accountId == 6549517298327131420) {
        // Recalculate trophy count, level & progress for the player
        $query = $database->prepare("SELECT Ifnull(Sum(ttp.bronze), 0)   AS bronze,
                   Ifnull(Sum(ttp.silver), 0)   AS silver,
                   Ifnull(Sum(ttp.gold), 0)     AS gold,
                   Ifnull(Sum(ttp.platinum), 0) AS platinum
            FROM   trophy_title_player ttp
                   JOIN trophy_title tt USING (np_communication_id)
            WHERE  tt.status = 0
                   AND ttp.account_id = :account_id ");
        $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
        $query->execute();
        $trophies = $query->fetch();
        $points = $trophies["bronze"]*15 + $trophies["silver"]*30 + $trophies["gold"]*90 + $trophies["platinum"]*300;
        if ($points <= 5940) {
            $level = floor($points / 60) + 1;
            $progress = floor(($points / 60 * 100) % 100);
        } elseif ($points <= 14940) {
            $level = floor(($points - 5940) / 90) + 100;
            $progress = floor((($points - 5940) / 90 * 100) % 100);
        } else {
            $stage = 1;
            $leftovers = $points - 14940;
            while ($leftovers > 45000 * $stage) {
                $leftovers -= 45000 * $stage;
                $stage++;
            }
            
            $level = floor($leftovers / (450 * $stage)) + (100 + 100 * $stage);
            $progress = floor($leftovers / (450 * $stage) * 100) % 100;
        }
        $query = $database->prepare("UPDATE player
            SET    bronze = :bronze,
                   silver = :silver,
                   gold = :gold,
                   platinum = :platinum,
                   level = :level,
                   progress = :progress,
                   points = :points
            WHERE  account_id = :account_id ");
        $query->bindParam(":bronze", $trophies["bronze"], PDO::PARAM_INT);
        $query->bindParam(":silver", $trophies["silver"], PDO::PARAM_INT);
        $query->bindParam(":gold", $trophies["gold"], PDO::PARAM_INT);
        $query->bindParam(":platinum", $trophies["platinum"], PDO::PARAM_INT);
        $query->bindParam(":level", $level, PDO::PARAM_INT);
        $query->bindParam(":progress", $progress, PDO::PARAM_INT);
        $query->bindParam(":points", $points, PDO::PARAM_INT);
        $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
        $query->execute();

        $query = $database->prepare("UPDATE player
            SET    last_updated_date = Now()
            WHERE  account_id = :account_id ");
        $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
        $query->execute();

        $query = $database->prepare("DELETE FROM player_queue
            WHERE  online_id = :online_id ");
        $query->bindParam(":online_id", $info->onlineId, PDO::PARAM_STR);
        $query->execute();
        continue;
    }

    if ($info->trophySummary->level === 0) {
        // Profile seem to be private, set status to 3 to hide all trophies.
        $query = $database->prepare("UPDATE player
                SET    status = 3,
                    `rank` = 0,
                    rank_last_week = 0,
                    rarity_rank = 0,
                    rarity_rank_last_week = 0,
                    rank_country = 0,
                    rank_country_last_week = 0,
                    rarity_rank_country = 0,
                    rarity_rank_country_last_week = 0
                WHERE  account_id = :account_id 
                    AND status != 1");
        $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
        $query->execute();
    } else {
        $offset = $player["offset"];

        $query = $database->prepare("SELECT last_updated_date
            FROM   player
            WHERE  account_id = :account_id ");
        $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
        $query->execute();
        $playerLastUpdatedDate = $query->fetchColumn();

        $query = $database->prepare("SELECT np_communication_id,
                   last_updated_date
            FROM   trophy_title_player
            WHERE  account_id = :account_id ");
        $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
        $query->execute();
        $gameLastUpdatedDate = $query->fetchAll(PDO::FETCH_KEY_PAIR);

        do {
            // Try and get the player games until we succeed (have only gotten HTTP 500 for ikemenzi from time to time, but you never know)
            $errorCount = 0;
            $fetchTrophyTitles = true;
            while ($fetchTrophyTitles) {
                if (microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"] > $maxTime)
                {
                    die();
                }

                try {
                    $trophyTitles = $users[$client]->trophyTitles($offset);

                    if ($trophyTitles->totalResults > 0) {
                        $fetchTrophyTitles = false;
                    } else {
                        $errorCount++;

                        // Sony is currently giving us strange results... Retry.
                        if ($errorCount == 1) {
                            $message = "Problems fetching trophy titles for ". $info->onlineId;
                            $query = $database->prepare("INSERT INTO log
                                            (message)
                                VALUES      (:message) ");
                            $query->bindParam(":message", $message, PDO::PARAM_STR);
                            $query->execute();
                        }

                        if ($errorCount > 100) {
                            // The user have probably hidden all their games, but is not set to private.
                            $message = "Removing ". $info->onlineId ." from the queue.";
                            $query = $database->prepare("INSERT INTO log
                                            (message)
                                VALUES      (:message) ");
                            $query->bindParam(":message", $message, PDO::PARAM_STR);
                            $query->execute();

                            $query = $database->prepare("DELETE FROM player_queue
                                WHERE  online_id = :online_id ");
                            $query->bindParam(":online_id", $info->onlineId, PDO::PARAM_STR);
                            $query->execute();

                            continue 3;
                        }

                        sleep(5);
                    }
                } catch (Exception $e) {
                    // Increase the request_time and continue with the next one in the queue.
                    $query = $database->prepare("UPDATE player_queue
                        SET    request_time = Date_add(request_time, INTERVAL 1 minute)
                        WHERE  online_id = :online_id ");
                    $query->bindParam(":online_id", $info->onlineId, PDO::PARAM_STR);
                    $query->execute();

                    continue 3;
                }

                $client++;
                if ($client >= count($clients)) {
                    $client = 0;
                }
            }

            $totalResults = $trophyTitles->totalResults;
            $skippedGames = 0;

            foreach ($trophyTitles->trophyTitles as $game) {
                $newDLC = false;
                $sonyLastUpdatedDate = date_create($game->comparedUser->lastUpdateDate);
                if (array_key_exists($game->npCommunicationId, $gameLastUpdatedDate) && $sonyLastUpdatedDate->format("Y-m-d H:i:s") === date_create($gameLastUpdatedDate[$game->npCommunicationId])->format("Y-m-d H:i:s")) {
                    $skippedGames++;

                    if ($playerLastUpdatedDate != null) { // New players have null as last updated date, and will thus continue with a full scan.
                        if ($skippedGames >= 128) {
                            // 128 skipped games (one full trophyTitles() fetch), we can assume we are done with this player.
                            break 2;
                        }

                        // Game seems scanned already, skip to next.
                        continue;
                    }
                }

                // Add trophy title (game) information into database
                // INSERT IGNORE  makes the autoincrement tick as well. We don't want that.
                $query = $database->prepare("SELECT Count(*)
                    FROM   trophy_title
                    WHERE  np_communication_id = :np_communication_id ");
                $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                $query->execute();
                $check = $query->fetchColumn();
                if ($check == 0) {
                    // Get the title icon url we want to save
                    $trophyTitleIconUrl = $game->trophyTitleIconUrl;
                    $trophyTitleIconFilename = md5_file($trophyTitleIconUrl) . strtolower(substr($trophyTitleIconUrl, strrpos($trophyTitleIconUrl, ".")));
                    // Download the title icon if we don't have it
                    if (!file_exists("/home/psn100/public_html/img/title/". $trophyTitleIconFilename)) {
                        file_put_contents("/home/psn100/public_html/img/title/". $trophyTitleIconFilename, fopen($trophyTitleIconUrl, "r"));
                    }

                    $query = $database->prepare("INSERT INTO trophy_title
                                    (np_communication_id,
                                     name,
                                     detail,
                                     icon_url,
                                     platform,
                                     message)
                        VALUES      (:np_communication_id,
                                     :name,
                                     :detail,
                                     :icon_url,
                                     :platform,
                                     '') ");
                    $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                    $query->bindParam(":name", $game->trophyTitleName, PDO::PARAM_STR);
                    $query->bindParam(":detail", $game->trophyTitleDetail, PDO::PARAM_STR);
                    $query->bindParam(":icon_url", $trophyTitleIconFilename, PDO::PARAM_STR);
                    $query->bindParam(":platform", $game->trophyTitlePlatfrom, PDO::PARAM_STR);
                    // Don't insert platinum/gold/silver/bronze here since our site recalculate this.
                    $query->execute();
                }

                // Get "groups" (game and DLCs)
                $trophyGroups = $users[$client]->trophyGroups($game->npCommunicationId)->trophyGroups;
                $client++;
                if ($client >= count($clients)) {
                    $client = 0;
                }

                foreach ($trophyGroups as $trophyGroup) {
                    // Add trophy group (game + dlcs) into database
                    // INSERT IGNORE  makes the autoincrement tick as well. We don't want that.
                    $query = $database->prepare("SELECT Count(*)
                        FROM   trophy_group
                        WHERE  np_communication_id = :np_communication_id
                               AND group_id = :group_id ");
                    $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                    $query->bindParam(":group_id", $trophyGroup->trophyGroupId, PDO::PARAM_STR);
                    $query->execute();
                    $check = $query->fetchColumn();
                    if ($check == 0) {
                        $trophyGroupIconUrl = $trophyGroup->trophyGroupIconUrl;
                        $trophyGroupIconFilename = md5_file($trophyGroupIconUrl) . strtolower(substr($trophyGroupIconUrl, strrpos($trophyGroupIconUrl, ".")));
                        // Download the group icon if we don't have it
                        if (!file_exists("/home/psn100/public_html/img/group/". $trophyGroupIconFilename)) {
                            file_put_contents("/home/psn100/public_html/img/group/". $trophyGroupIconFilename, fopen($trophyGroupIconUrl, "r"));
                        }

                        $query = $database->prepare("INSERT INTO trophy_group
                                        (np_communication_id,
                                         group_id,
                                         name,
                                         detail,
                                         icon_url)
                            VALUES      (:np_communication_id,
                                         :group_id,
                                         :name,
                                         :detail,
                                         :icon_url) ");
                        $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                        $query->bindParam(":group_id", $trophyGroup->trophyGroupId, PDO::PARAM_STR);
                        $query->bindParam(":name", $trophyGroup->trophyGroupName, PDO::PARAM_STR);
                        $query->bindParam(":detail", $trophyGroup->trophyGroupDetail, PDO::PARAM_STR);
                        $query->bindParam(":icon_url", $trophyGroupIconFilename, PDO::PARAM_STR);
                        // Don't insert platinum/gold/silver/bronze here since our site recalculate this.
                        $query->execute();

                        $newDLC = true;

                        $query = $database->prepare("SELECT status
                            FROM   trophy_title
                            WHERE  np_communication_id = :np_communication_id ");
                        $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                        $query->execute();
                        $status = $query->fetchColumn();
                        if ($status == 2) { // A "Merge Title" have gotten a DLC. Add a log about it so admin can check it out later and fix this.
                            $message = "DLC added. ". $game->npCommunicationId . ", ". $trophyGroup->trophyGroupId .", ". $trophyGroup->trophyGroupName;
                            $query = $database->prepare("INSERT INTO log
                                            (message)
                                VALUES      (:message) ");
                            $query->bindParam(":message", $message, PDO::PARAM_STR);
                            $query->execute();
                        }
                    }

                    if (microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"] > $maxTime)
                    {
                        die();
                    }
                    $result = $users[$client]->trophies($game->npCommunicationId, $trophyGroup->trophyGroupId);
                    $client++;
                    if ($client >= count($clients)) {
                        $client = 0;
                    }
                    foreach ($result as $trophies) {
                        foreach ($trophies as $trophy) {
                            // Add trophies into database
                            // INSERT IGNORE  makes the autoincrement tick as well. We don't want that.
                            $query = $database->prepare("SELECT Count(*)
                                FROM   trophy
                                WHERE  np_communication_id = :np_communication_id
                                       AND group_id = :group_id
                                       AND order_id = :order_id ");
                            $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                            $query->bindParam(":group_id", $trophyGroup->trophyGroupId, PDO::PARAM_STR);
                            $query->bindParam(":order_id", $trophy->trophyId, PDO::PARAM_INT);
                            $query->execute();
                            $check = $query->fetchColumn();
                            if ($check == 0) {
                                $trophyIconUrl = $trophy->trophyIconUrl;
                                $trophyIconFilename = md5_file($trophyIconUrl) . strtolower(substr($trophyIconUrl, strrpos($trophyIconUrl, ".")));
                                // Download the trophy icon if we don't have it
                                if (!file_exists("/home/psn100/public_html/img/trophy/". $trophyIconFilename)) {
                                    file_put_contents("/home/psn100/public_html/img/trophy/". $trophyIconFilename, fopen($trophyIconUrl, "r"));
                                }

                                $queryInsertTrophy = $database->prepare("INSERT INTO trophy
                                                (np_communication_id,
                                                 group_id,
                                                 order_id,
                                                 hidden,
                                                 type,
                                                 name,
                                                 detail,
                                                 icon_url,
                                                 rare,
                                                 earned_rate)
                                    VALUES      (:np_communication_id,
                                                 :group_id,
                                                 :order_id,
                                                 :hidden,
                                                 :type,
                                                 :name,
                                                 :detail,
                                                 :icon_url,
                                                 :rare,
                                                 :earned_rate) ");
                                $queryInsertTrophy->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                                $queryInsertTrophy->bindParam(":group_id", $trophyGroup->trophyGroupId, PDO::PARAM_STR);
                                $queryInsertTrophy->bindParam(":order_id", $trophy->trophyId, PDO::PARAM_INT);
                                $queryInsertTrophy->bindParam(":hidden", $trophy->trophyHidden, PDO::PARAM_INT);
                                $queryInsertTrophy->bindParam(":type", $trophy->trophyType, PDO::PARAM_STR);
                                $queryInsertTrophy->bindParam(":name", $trophy->trophyName, PDO::PARAM_STR);
                                $queryInsertTrophy->bindParam(":detail", $trophy->trophyDetail, PDO::PARAM_STR);
                                $queryInsertTrophy->bindParam(":icon_url", $trophyIconFilename, PDO::PARAM_STR);
                                $queryInsertTrophy->bindParam(":rare", $trophy->trophyRare, PDO::PARAM_INT);
                                $queryInsertTrophy->bindParam(":earned_rate", $trophy->trophyEarnedRate, PDO::PARAM_STR);
                                // Don't insert platinum/gold/silver/bronze here since our site recalculate this.
                                $queryInsertTrophy->execute();
                            }

                            // If the player have earned the trophy, add it into the database
                            if (property_exists($trophy, "comparedUser") && $trophy->comparedUser->earned == "1") {
                                $dateTimeObject = property_exists($trophy->comparedUser, "earnedDate") ? DateTime::createFromFormat("Y-m-d\TH:i:s\Z", $trophy->comparedUser->earnedDate) : false;
                                if ($dateTimeObject === false) {
                                    $dtAsTextForInsert = null;
                                } else {
                                    $dtAsTextForInsert = $dateTimeObject->format("Y-m-d H:i:s");
                                }

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
                                    UPDATE earned_date=VALUES
                                           (
                                                  earned_date
                                           )");
                                $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                                $query->bindParam(":group_id", $trophyGroup->trophyGroupId, PDO::PARAM_STR);
                                $query->bindParam(":order_id", $trophy->trophyId, PDO::PARAM_INT);
                                $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
                                $query->bindParam(":earned_date", $dtAsTextForInsert, PDO::PARAM_STR);
                                $query->execute();

                                // Check if "merge"-trophy
                                $query = $database->prepare("SELECT parent_np_communication_id,
                                           parent_group_id,
                                           parent_order_id
                                    FROM   trophy_merge
                                    WHERE  child_np_communication_id = :child_np_communication_id
                                           AND child_group_id = :child_group_id
                                           AND child_order_id = :child_order_id ");
                                $query->bindParam(":child_np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                                $query->bindParam(":child_group_id", $trophyGroup->trophyGroupId, PDO::PARAM_STR);
                                $query->bindParam(":child_order_id", $trophy->trophyId, PDO::PARAM_INT);
                                $query->execute();
                                $parent = $query->fetch();
                                if ($parent !== false) {
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
                                    $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
                                    $query->bindParam(":earned_date", $dtAsTextForInsert, PDO::PARAM_STR);
                                    $query->execute();
                                }
                            }
                        }
                    }

                    // Recalculate trophies for trophy group and player
                    RecalculateTrophyGroup($game->npCommunicationId, $trophyGroup->trophyGroupId, $info->accountId);
                }

                // Recalculate trophies for trophy title and player
                RecalculateTrophyTitle($game->npCommunicationId, $game->comparedUser->lastUpdateDate, $newDLC, $info->accountId, false);

                // Game Merge stuff
                $query = $database->prepare("SELECT DISTINCT parent_np_communication_id, 
                                    parent_group_id 
                    FROM   trophy_merge 
                    WHERE  child_np_communication_id = :child_np_communication_id ");
                $query->bindParam(":child_np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                $query->execute();
                while ($row = $query->fetch()) {
                    RecalculateTrophyGroup($row["parent_np_communication_id"], $row["parent_group_id"], $info->accountId);
                    RecalculateTrophyTitle($row["parent_np_communication_id"], $game->comparedUser->lastUpdateDate, false, $info->accountId, true);
                }
            }

            $offset += 128 - 8; // Subtract a little bit in-case the player have gotten new games while we are scanning

            $query = $database->prepare("INSERT INTO player_queue
                            (
                                        online_id,
                                        offset
                            )
                            VALUES
                            (
                                        :online_id,
                                        :offset
                            )
                on duplicate KEY
                UPDATE
                offset=VALUES
                       (
                              offset
                       )");
            $query->bindParam(":online_id", $player["online_id"], PDO::PARAM_STR);
            $query->bindParam(":offset", $offset, PDO::PARAM_INT);
            $query->execute();
        } while ($offset <= $totalResults);

        // Recalculate trophy count, level & progress for the player
        $query = $database->prepare("SELECT Ifnull(Sum(ttp.bronze), 0)   AS bronze,
                   Ifnull(Sum(ttp.silver), 0)   AS silver,
                   Ifnull(Sum(ttp.gold), 0)     AS gold,
                   Ifnull(Sum(ttp.platinum), 0) AS platinum
            FROM   trophy_title_player ttp
                   JOIN trophy_title tt USING (np_communication_id)
            WHERE  tt.status = 0
                   AND ttp.account_id = :account_id ");
        $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
        $query->execute();
        $trophies = $query->fetch();
        $points = $trophies["bronze"]*15 + $trophies["silver"]*30 + $trophies["gold"]*90 + $trophies["platinum"]*300;
        if ($points <= 5940) {
            $level = floor($points / 60) + 1;
            $progress = floor(($points / 60 * 100) % 100);
        } elseif ($points <= 14940) {
            $level = floor(($points - 5940) / 90) + 100;
            $progress = floor((($points - 5940) / 90 * 100) % 100);
        } else {
            $stage = 1;
            $leftovers = $points - 14940;
            while ($leftovers > 45000 * $stage) {
                $leftovers -= 45000 * $stage;
                $stage++;
            }
            
            $level = floor($leftovers / (450 * $stage)) + (100 + 100 * $stage);
            $progress = floor($leftovers / (450 * $stage) * 100) % 100;
        }
        $query = $database->prepare("UPDATE player
            SET    bronze = :bronze,
                   silver = :silver,
                   gold = :gold,
                   platinum = :platinum,
                   level = :level,
                   progress = :progress,
                   points = :points
            WHERE  account_id = :account_id ");
        $query->bindParam(":bronze", $trophies["bronze"], PDO::PARAM_INT);
        $query->bindParam(":silver", $trophies["silver"], PDO::PARAM_INT);
        $query->bindParam(":gold", $trophies["gold"], PDO::PARAM_INT);
        $query->bindParam(":platinum", $trophies["platinum"], PDO::PARAM_INT);
        $query->bindParam(":level", $level, PDO::PARAM_INT);
        $query->bindParam(":progress", $progress, PDO::PARAM_INT);
        $query->bindParam(":points", $points, PDO::PARAM_INT);
        $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
        $query->execute();

        // Set player as active if private
        $query = $database->prepare("UPDATE
                player p
            SET
                p.status = 0
            WHERE
                p.account_id = :account_id AND p.status = 3");
        $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
        $query->execute();

        // Check for hidden trophies
        $totalTrophies = $info->trophySummary->earnedTrophies->platinum + $info->trophySummary->earnedTrophies->gold + $info->trophySummary->earnedTrophies->silver + $info->trophySummary->earnedTrophies->bronze;
        $query = $database->prepare("SELECT COUNT(*) FROM trophy_earned WHERE np_communication_id LIKE 'NPWR%' AND account_id = :account_id");
        $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
        $query->execute();
        $ourTotalTrophies = $query->fetchColumn();
        if ($ourTotalTrophies < $totalTrophies) {
            $query = $database->prepare("UPDATE player
                SET    status = 2,
                    `rank` = 0,
                    rank_last_week = 0,
                    rarity_rank = 0,
                    rarity_rank_last_week = 0,
                    rank_country = 0,
                    rank_country_last_week = 0,
                    rarity_rank_country = 0,
                    rarity_rank_country_last_week = 0
                WHERE  account_id = :account_id 
                    AND status = 0");
            $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
            $query->execute();
        }
    }

    // Done with the user, update the date
    $query = $database->prepare("UPDATE player
        SET    last_updated_date = Now()
        WHERE  account_id = :account_id ");
    $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
    $query->execute();

    // Delete user from the queue
    $query = $database->prepare("DELETE FROM player_queue
        WHERE  online_id = :online_id ");
    $query->bindParam(":online_id", $info->onlineId, PDO::PARAM_STR);
    $query->execute();
}
