<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("/home/psn100/public_html/vendor/autoload.php");
require_once("/home/psn100/public_html/init.php");

use Tustin\PlayStation\Client;

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
            COUNT(type) AS count
        FROM
            trophy_earned te
        LEFT JOIN trophy t ON
            t.np_communication_id = te.np_communication_id AND t.group_id = te.group_id AND t.order_id = te.order_id AND t.status = 0
        WHERE
            account_id = :account_id AND te.np_communication_id = :np_communication_id AND te.group_id = :group_id AND te.earned = 1
        GROUP BY type ");
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

function RecalculateTrophyTitle($npCommunicationId, $lastUpdateDate, $newTrophies, $accountId, $merge) {
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
    if ($newTrophies === true) {
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
        $query = $database->prepare("INSERT INTO trophy_title_player(
                np_communication_id,
                account_id,
                bronze,
                silver,
                gold,
                platinum,
                progress,
                last_updated_date
            )
            VALUES(
                :np_communication_id,
                :account_id,
                :bronze,
                :silver,
                :gold,
                :platinum,
                :progress,
                :last_updated_date
            )
            ON DUPLICATE KEY
            UPDATE
                bronze =
            VALUES(bronze), silver =
            VALUES(silver), gold =
            VALUES(gold), platinum =
            VALUES(platinum), progress =
            VALUES(progress), last_updated_date = IF(
                last_updated_date >
            VALUES(last_updated_date),
            last_updated_date,
            VALUES(last_updated_date)
            )");
    } else {
        $query = $database->prepare("INSERT INTO trophy_title_player(
                np_communication_id,
                account_id,
                bronze,
                silver,
                gold,
                platinum,
                progress,
                last_updated_date
            )
            VALUES(
                :np_communication_id,
                :account_id,
                :bronze,
                :silver,
                :gold,
                :platinum,
                :progress,
                :last_updated_date
            )
            ON DUPLICATE KEY
            UPDATE
                bronze =
            VALUES(bronze), silver =
            VALUES(silver), gold =
            VALUES(gold), platinum =
            VALUES(platinum), progress =
            VALUES(progress), last_updated_date =
            VALUES(last_updated_date)");
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
$query = $database->prepare("SELECT
        id,
        npsso
    FROM
        setting");
$query->execute();
$workers = $query->fetchAll();

// Login with a token
$loggedIn = false;
while (!$loggedIn) {
    foreach ($workers as $worker) {
        try {
            $client = new Client();
            $npsso = $worker["npsso"];
            $client->loginWithNpsso($npsso);

            $loggedIn = true;
            break;
        } catch (Exception $e) {
            $message = "Can't login with worker ". $worker["id"];
            $query = $database->prepare("INSERT INTO log(message)
                VALUES(:message)");
            $query->bindParam(":message", $message, PDO::PARAM_STR);
            $query->execute();
        }
    }

    if (!$loggedIn) {
        // Wait 5 minutes to not hammer login
        sleep(60 * 5);
    }
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
    try {
        // Search the user
        unset($user);
        $userFound = false;
        $userCounter = 0;
        foreach ($client->users()->search($player["online_id"]) as $userSearchResult) {
            if (strtolower($userSearchResult->onlineId()) == strtolower($player["online_id"])) {
                $user = $userSearchResult;
                $userFound = true;
                break;
            }

            // Limit to the first 50 search results
            $userCounter++;
            if ($userCounter >= 50) {
                break;
            }
        }

        if (!$userFound) {
            // User not found, set as private and remove from queue.
            $query = $database->prepare("UPDATE
                    player
                SET
                    `status` = 3,
                    `rank` = 0,
                    rank_last_week = 0,
                    rarity_rank = 0,
                    rarity_rank_last_week = 0,
                    rank_country = 0,
                    rank_country_last_week = 0,
                    rarity_rank_country = 0,
                    rarity_rank_country_last_week = 0,
                    last_updated_date = NOW()
                WHERE
                    online_id = :online_id AND `status` != 1");
            $query->bindParam(":online_id", $player["online_id"], PDO::PARAM_STR);
            $query->execute();

            $query = $database->prepare("DELETE
                FROM
                    player_queue
                WHERE
                    online_id = :online_id");
            $query->bindParam(":online_id", $player["online_id"], PDO::PARAM_STR);
            $query->execute();

            continue;
        }
        
        if (isset($user) && $user->token() === "token_invalidated:deleted") {
            // This user have been deleted from Sony
            $message = "Sony have deleted ". $player["online_id"] .".";
            $query = $database->prepare("INSERT INTO log(message)
                VALUES(:message)");
            $query->bindParam(":message", $message, PDO::PARAM_STR);
            $query->execute();

            $query = $database->prepare("DELETE
                FROM
                    trophy_earned
                WHERE
                    account_id =(
                    SELECT
                        account_id
                    FROM
                        player
                    WHERE
                        online_id = :online_id
                )");
            $query->bindParam(":online_id", $player["online_id"], PDO::PARAM_STR);
            $query->execute();

            $query = $database->prepare("DELETE
                FROM
                    trophy_group_player
                WHERE
                    account_id =(
                    SELECT
                        account_id
                    FROM
                        player
                    WHERE
                        online_id = :online_id
                )");
            $query->bindParam(":online_id", $player["online_id"], PDO::PARAM_STR);
            $query->execute();

            $query = $database->prepare("DELETE
                FROM
                    trophy_title_player
                WHERE
                    account_id =(
                    SELECT
                        account_id
                    FROM
                        player
                    WHERE
                        online_id = :online_id
                )");
            $query->bindParam(":online_id", $player["online_id"], PDO::PARAM_STR);
            $query->execute();

            $query = $database->prepare("DELETE
                FROM
                    player
                WHERE
                    online_id = :online_id");
            $query->bindParam(":online_id", $player["online_id"], PDO::PARAM_STR);
            $query->execute();

            $query = $database->prepare("DELETE
                FROM
                    player_queue
                WHERE
                    online_id = :online_id");
            $query->bindParam(":online_id", $player["online_id"], PDO::PARAM_STR);
            $query->execute();

            continue;
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
    if (microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"] > $maxTime) {
        die();
    }

    // TODO: Find out about currentOnlineId with the new API
    // if (is_null($user->currentOnlineId()) === false) {
    //     $query = $database->prepare("DELETE FROM player_queue
    //         WHERE  online_id = :new_online_id ");
    //     $query->bindParam(":new_online_id", $user->currentOnlineId(), PDO::PARAM_STR);
    //     $query->execute();

    //     $query = $database->prepare("UPDATE player_queue
    //         SET    online_id = :new_online_id
    //         WHERE  online_id = :old_online_id ");
    //     $query->bindParam(":new_online_id", $user->currentOnlineId(), PDO::PARAM_STR);
    //     $query->bindParam(":old_online_id", $user->onlineId(), PDO::PARAM_STR);
    //     $query->execute();
    //     continue;
    // }

    // Get the avatar url we want to save
    $avatarUrls = $user->avatarUrls();
    for ($i = 0; $i < 4; $i++) { 
        switch ($i) {
            case 0:
                $size = "xl";
                break;
            case 1:
                $size = "l";
                break;
            case 2:
                $size = "m";
                break;
            case 3:
                $size = "s";
                break;
        }
        $avatarUrl = $avatarUrls[$size];

        // Check SQL
        $query = $database->prepare("SELECT
                md5_hash,
                extension
            FROM
                psn100_avatars
            WHERE
                avatar_url = :avatar_url");
        $query->bindParam(":avatar_url", $avatarUrl, PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch();

        if (!$result) { // We doesn't seem to have this avatar
            $md5Hash = md5_file($avatarUrl);
            if (!$md5Hash) {
                // File not found. Try next.
                continue;
            }

            $extension = strtolower(pathinfo($avatarUrl, PATHINFO_EXTENSION));

            $avatarFilename = $md5Hash .".". $extension;
            if (!file_exists("/home/psn100/public_html/img/avatar/". $avatarFilename)) {
                file_put_contents("/home/psn100/public_html/img/avatar/". $avatarFilename, fopen($avatarUrl, 'r'));
            }

            // SQL Insert
            $query = $database->prepare("INSERT INTO psn100_avatars(
                    size,
                    avatar_url,
                    md5_hash,
                    extension
                )
                VALUES(
                    :size,
                    :avatar_url,
                    :md5_hash,
                    :extension
                )");
            $query->bindParam(":size", $size, PDO::PARAM_STR);
            $query->bindParam(":avatar_url", $avatarUrl, PDO::PARAM_STR);
            $query->bindParam(":md5_hash", $md5Hash, PDO::PARAM_STR);
            $query->bindParam(":extension", $extension, PDO::PARAM_STR);
            $query->execute();
        } else {
            $avatarFilename = $result["md5_hash"] .".". $result["extension"];
        }

        // We are done, no need to check other images.
        break;
    }

    // Plus is null or 1, we don't want null so this will make it 0 if so.
    $plus = (bool)$user->hasPlus();

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
    $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
    $query->bindParam(":online_id", $user->onlineId(), PDO::PARAM_STR);
    $query->bindParam(":country", strtolower($user->country()), PDO::PARAM_STR);
    $query->bindParam(":avatar_url", $avatarFilename, PDO::PARAM_STR);
    $query->bindParam(":plus", $plus, PDO::PARAM_BOOL);
    $query->bindParam(":about_me", $user->aboutMe(), PDO::PARAM_STR);
    // Don't insert level/progress/platinum/gold/silver/bronze here since our site recalculate this.
    $query->execute();

    try {
        $level = 0;
        $level = $user->trophySummary()->level();
    } catch (Exception $e) {
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
        $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
        $query->execute();
    }

    if ($level !== 0) {
        $offset = $player["offset"];

        $query = $database->prepare("SELECT last_updated_date
            FROM   player
            WHERE  account_id = :account_id ");
        $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
        $query->execute();
        $playerLastUpdatedDate = $query->fetchColumn();

        $query = $database->prepare("SELECT np_communication_id,
                last_updated_date
            FROM   trophy_title_player
            WHERE  account_id = :account_id ");
        $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
        $query->execute();
        $gameLastUpdatedDate = $query->fetchAll(PDO::FETCH_KEY_PAIR);

        do {
            // TODO: Fix total result with the new API
            //$totalResults = $trophyTitles->totalResults;
            $totalResults = 0;
            $skippedGames = 0;
            
            foreach ($user->trophyTitles() as $trophyTitle) {
                $newTrophies = false;
                $sonyLastUpdatedDate = date_create($trophyTitle->lastUpdatedDateTime());
                if (array_key_exists($trophyTitle->npCommunicationId(), $gameLastUpdatedDate) && $sonyLastUpdatedDate->format("Y-m-d H:i:s") === date_create($gameLastUpdatedDate[$trophyTitle->npCommunicationId()])->format("Y-m-d H:i:s")) {
                    $skippedGames++;

                    if ($playerLastUpdatedDate != null) { // New players have null as last updated date, and will thus continue with a full scan.
                        if ($skippedGames >= 128) {
                            // 128 skipped games, we can assume we are done with this player.
                            break 2;
                        }
                    }

                    // Game seems scanned already, skip to next.
                    continue;
                }

                // Add trophy title (game) information into database
                $query = $database->prepare("SELECT set_version
                    FROM   trophy_title
                    WHERE  np_communication_id = :np_communication_id ");
                $query->bindParam(":np_communication_id", $trophyTitle->npCommunicationId(), PDO::PARAM_STR);
                $query->execute();
                $setVersion = $query->fetchColumn();
                if (!$setVersion || $setVersion != $trophyTitle->trophySetVersion()) {
                    // Get the title icon url we want to save
                    $trophyTitleIconUrl = $trophyTitle->iconUrl();
                    $trophyTitleIconFilename = md5_file($trophyTitleIconUrl) . strtolower(substr($trophyTitleIconUrl, strrpos($trophyTitleIconUrl, ".")));
                    // Download the title icon if we don't have it
                    if (!file_exists("/home/psn100/public_html/img/title/". $trophyTitleIconFilename)) {
                        file_put_contents("/home/psn100/public_html/img/title/". $trophyTitleIconFilename, fopen($trophyTitleIconUrl, "r"));
                    }

                    $query = $database->prepare("INSERT INTO trophy_title(
                            np_communication_id,
                            name,
                            detail,
                            icon_url,
                            platform,
                            message,
                            set_version
                        )
                        VALUES(
                            :np_communication_id,
                            :name,
                            :detail,
                            :icon_url,
                            :platform,
                            '',
                            :set_version
                        )
                        ON DUPLICATE KEY
                        UPDATE
                            icon_url =
                        VALUES(icon_url)");
                    $query->bindParam(":np_communication_id", $trophyTitle->npCommunicationId(), PDO::PARAM_STR);
                    $query->bindParam(":name", $trophyTitle->name(), PDO::PARAM_STR);
                    $query->bindParam(":detail", $trophyTitle->detail(), PDO::PARAM_STR);
                    $query->bindParam(":icon_url", $trophyTitleIconFilename, PDO::PARAM_STR);
                    $query->bindParam(":platform", implode(",", $trophyTitle->platform()), PDO::PARAM_STR);
                    $query->bindParam(":set_version", $trophyTitle->trophySetVersion(), PDO::PARAM_STR);
                    // Don't insert platinum/gold/silver/bronze here since our site recalculate this.
                    $query->execute();
                }

                // Get "groups" (game and DLCs)
                foreach ($client->trophies($trophyTitle->npCommunicationId(), $trophyTitle->serviceName())->trophyGroups() as $trophyGroup) {
                    // Add trophy group (game + dlcs) into database
                    $query = $database->prepare("SELECT Count(*)
                        FROM   trophy_group
                        WHERE  np_communication_id = :np_communication_id
                            AND group_id = :group_id ");
                    $query->bindParam(":np_communication_id", $trophyTitle->npCommunicationId(), PDO::PARAM_STR);
                    $query->bindParam(":group_id", $trophyGroup->id(), PDO::PARAM_STR);
                    $query->execute();
                    $check = $query->fetchColumn();
                    if ($check == 0 || $setVersion != $trophyTitle->trophySetVersion()) {
                        $trophyGroupIconUrl = $trophyGroup->iconUrl();
                        $trophyGroupIconFilename = md5_file($trophyGroupIconUrl) . strtolower(substr($trophyGroupIconUrl, strrpos($trophyGroupIconUrl, ".")));
                        // Download the group icon if we don't have it
                        if (!file_exists("/home/psn100/public_html/img/group/". $trophyGroupIconFilename)) {
                            file_put_contents("/home/psn100/public_html/img/group/". $trophyGroupIconFilename, fopen($trophyGroupIconUrl, "r"));
                        }

                        $query = $database->prepare("INSERT INTO trophy_group(
                                np_communication_id,
                                group_id,
                                name,
                                detail,
                                icon_url
                            )
                            VALUES(
                                :np_communication_id,
                                :group_id,
                                :name,
                                :detail,
                                :icon_url
                            )
                            ON DUPLICATE KEY
                            UPDATE
                                detail =
                            VALUES(detail), icon_url =
                            VALUES(icon_url)");
                        $query->bindParam(":np_communication_id", $trophyTitle->npCommunicationId(), PDO::PARAM_STR);
                        $query->bindParam(":group_id", $trophyGroup->id(), PDO::PARAM_STR);
                        $query->bindParam(":name", $trophyGroup->name(), PDO::PARAM_STR);
                        $query->bindParam(":detail", $trophyGroup->detail(), PDO::PARAM_STR);
                        $query->bindParam(":icon_url", $trophyGroupIconFilename, PDO::PARAM_STR);
                        // Don't insert platinum/gold/silver/bronze here since our site recalculate this.
                        $query->execute();

                        // Add trophies into database
                        foreach ($trophyGroup->trophies() as $trophy) {
                            $query = $database->prepare("SELECT Count(*)
                                FROM   trophy
                                WHERE  np_communication_id = :np_communication_id
                                    AND group_id = :group_id
                                    AND order_id = :order_id ");
                            $query->bindParam(":np_communication_id", $trophyTitle->npCommunicationId(), PDO::PARAM_STR);
                            $query->bindParam(":group_id", $trophyGroup->id(), PDO::PARAM_STR);
                            $query->bindParam(":order_id", $trophy->id(), PDO::PARAM_INT);
                            $query->execute();
                            $check = $query->fetchColumn();
                            if ($check == 0 || $setVersion != $trophyTitle->trophySetVersion()) {
                                $trophyIconUrl = $trophy->iconUrl();
                                $trophyIconFilename = md5_file($trophyIconUrl) . strtolower(substr($trophyIconUrl, strrpos($trophyIconUrl, ".")));
                                // Download the trophy icon if we don't have it
                                if (!file_exists("/home/psn100/public_html/img/trophy/". $trophyIconFilename)) {
                                    file_put_contents("/home/psn100/public_html/img/trophy/". $trophyIconFilename, fopen($trophyIconUrl, "r"));
                                }

                                $rewardImageUrl = $trophy->rewardImageUrl();
                                if ($rewardImageUrl === '') {
                                    $rewardImageFilename = null;
                                } else {
                                    $rewardImageFilename = md5_file($rewardImageUrl) . strtolower(substr($rewardImageUrl, strrpos($rewardImageUrl, ".")));
                                    // Download the reward image if we don't have it
                                    if (!file_exists("/home/psn100/public_html/img/reward/". $rewardImageFilename)) {
                                        file_put_contents("/home/psn100/public_html/img/reward/". $rewardImageFilename, fopen($rewardImageUrl, "r"));
                                    }
                                }

                                $query = $database->prepare("INSERT INTO trophy(
                                        np_communication_id,
                                        group_id,
                                        order_id,
                                        hidden,
                                        type,
                                        name,
                                        detail,
                                        icon_url,
                                        progress_target_value,
                                        reward_name,
                                        reward_image_url
                                    )
                                    VALUES(
                                        :np_communication_id,
                                        :group_id,
                                        :order_id,
                                        :hidden,
                                        :type,
                                        :name,
                                        :detail,
                                        :icon_url,
                                        :progress_target_value,
                                        :reward_name,
                                        :reward_image_url
                                    )
                                    ON DUPLICATE KEY
                                    UPDATE
                                        hidden =
                                    VALUES(hidden), type =
                                    VALUES(type), name =
                                    VALUES(name), detail =
                                    VALUES(detail), icon_url =
                                    VALUES(icon_url), progress_target_value =
                                    VALUES(progress_target_value), reward_name =
                                    VALUES(reward_name), reward_image_url =
                                    VALUES(reward_image_url)");
                                $query->bindParam(":np_communication_id", $trophyTitle->npCommunicationId(), PDO::PARAM_STR);
                                $query->bindParam(":group_id", $trophyGroup->id(), PDO::PARAM_STR);
                                $query->bindParam(":order_id", $trophy->id(), PDO::PARAM_INT);
                                $query->bindParam(":hidden", $trophy->hidden(), PDO::PARAM_INT);
                                $query->bindParam(":type", $trophy->type(), PDO::PARAM_STR);
                                $query->bindParam(":name", $trophy->name(), PDO::PARAM_STR);
                                $query->bindParam(":detail", $trophy->detail(), PDO::PARAM_STR);
                                $query->bindParam(":icon_url", $trophyIconFilename, PDO::PARAM_STR);
                                if ($trophy->progressTargetValue() === '') {
                                    $progressTargetValue = null;
                                } else {
                                    $progressTargetValue = $trophy->progressTargetValue();
                                }
                                $query->bindParam(":progress_target_value", $progressTargetValue, PDO::PARAM_INT);
                                if ($trophy->rewardName() === '') {
                                    $rewardName = null;
                                } else {
                                    $rewardName = $trophy->rewardName();
                                }
                                $query->bindParam(":reward_name", $rewardName, PDO::PARAM_STR);
                                $query->bindParam(":reward_image_url", $rewardImageFilename, PDO::PARAM_STR);
                                // Don't insert platinum/gold/silver/bronze here since our site recalculate this.
                                $query->execute();
                                
                                $newTrophies = true;
                            }
                        }

                        if ($newTrophies) {
                            $query = $database->prepare("SELECT status
                                FROM   trophy_title
                                WHERE  np_communication_id = :np_communication_id ");
                            $query->bindParam(":np_communication_id", $trophyTitle->npCommunicationId(), PDO::PARAM_STR);
                            $query->execute();
                            $status = $query->fetchColumn();
                            if ($status == 2) { // A "Merge Title" have gotten new trophies. Add a log about it so admin can check it out later and fix this.
                                $message = "New trophies added for ". $trophyTitle->name() .". ". $trophyTitle->npCommunicationId() . ", ". $trophyGroup->id() .", ". $trophyGroup->name();
                                $query = $database->prepare("INSERT INTO log
                                                (message)
                                    VALUES      (:message) ");
                                $query->bindParam(":message", $message, PDO::PARAM_STR);
                                $query->execute();
                            } else {
                                $message = "SET VERSION for ". $trophyTitle->name() .". ". $trophyTitle->npCommunicationId() . ", ". $trophyGroup->id() .", ". $trophyGroup->name();
                                $query = $database->prepare("INSERT INTO log
                                                (message)
                                    VALUES      (:message) ");
                                $query->bindParam(":message", $message, PDO::PARAM_STR);
                                $query->execute();
                            }
                        }
                    }
                }

                // Successfully went through title, groups and trophies. Set the version.
                if (!$setVersion || $setVersion != $trophyTitle->trophySetVersion()) {
                    $query = $database->prepare("UPDATE
                            trophy_title
                        SET
                            set_version = :set_version
                        WHERE
                            np_communication_id = :np_communication_id");
                    $query->bindParam(":set_version", $trophyTitle->trophySetVersion(), PDO::PARAM_STR);
                    $query->bindParam(":np_communication_id", $trophyTitle->npCommunicationId(), PDO::PARAM_STR);
                    $query->execute();
                }

                if (microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"] > $maxTime) {
                    die();
                }

                // Fetch user trophies
                foreach ($trophyTitle->trophyGroups() as $trophyGroup) {
                    foreach ($trophyGroup->trophies() as $trophy) {
                        if ($trophy->earned() || ($trophy->progress() != '' && intval($trophy->progress()) > 0)) {
                            if ($trophy->earnedDateTime() === '') {
                                $dtAsTextForInsert = null;
                            } else {
                                $dateTimeObject = DateTime::createFromFormat("Y-m-d\TH:i:s\Z", $trophy->earnedDateTime());
                                $dtAsTextForInsert = $dateTimeObject->format("Y-m-d H:i:s");
                            }

                            $query = $database->prepare("INSERT INTO trophy_earned(
                                    np_communication_id,
                                    group_id,
                                    order_id,
                                    account_id,
                                    earned_date,
                                    progress,
                                    earned
                                )
                                VALUES(
                                    :np_communication_id,
                                    :group_id,
                                    :order_id,
                                    :account_id,
                                    :earned_date,
                                    :progress,
                                    :earned
                                )
                                ON DUPLICATE KEY
                                UPDATE
                                    earned_date = VALUES(earned_date),
                                    progress = VALUES(progress),
                                    earned = VALUES(earned)");
                            $query->bindParam(":np_communication_id", $trophyTitle->npCommunicationId(), PDO::PARAM_STR);
                            $query->bindParam(":group_id", $trophyGroup->id(), PDO::PARAM_STR);
                            $query->bindParam(":order_id", $trophy->id(), PDO::PARAM_INT);
                            $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
                            $query->bindParam(":earned_date", $dtAsTextForInsert, PDO::PARAM_STR);
                            if ($trophy->progress() === '') {
                                $progress = null;
                            } else {
                                $progress = intval($trophy->progress());
                            }
                            $query->bindParam(":progress", $progress, PDO::PARAM_INT);
                            $query->bindParam(":earned", $trophy->earned(), PDO::PARAM_INT);
                            $query->execute();

                            // Check if "merge"-trophy
                            $query = $database->prepare("SELECT parent_np_communication_id,
                                        parent_group_id,
                                        parent_order_id
                                FROM   trophy_merge
                                WHERE  child_np_communication_id = :child_np_communication_id
                                        AND child_group_id = :child_group_id
                                        AND child_order_id = :child_order_id ");
                            $query->bindParam(":child_np_communication_id", $trophyTitle->npCommunicationId(), PDO::PARAM_STR);
                            $query->bindParam(":child_group_id", $trophyGroup->id(), PDO::PARAM_STR);
                            $query->bindParam(":child_order_id", $trophy->id(), PDO::PARAM_INT);
                            $query->execute();
                            $parent = $query->fetch();
                            if ($parent !== false) {
                                $query = $database->prepare("INSERT INTO trophy_earned(
                                        np_communication_id,
                                        group_id,
                                        order_id,
                                        account_id,
                                        earned_date,
                                        progress,
                                        earned
                                    )
                                    VALUES(
                                        :np_communication_id,
                                        :group_id,
                                        :order_id,
                                        :account_id,
                                        :earned_date,
                                        :progress,
                                        :earned
                                    )
                                    ON DUPLICATE KEY
                                    UPDATE
                                        earned_date = IF(
                                            earned_date >
                                        VALUES(earned_date),
                                        earned_date,
                                    VALUES(earned_date)
                                        ), progress = IF(
                                            progress IS NULL,
                                        VALUES(progress),
                                        IF(
                                        VALUES(progress) IS NULL,
                                        progress,
                                        IF(
                                            progress >
                                        VALUES(progress),
                                        progress,
                                    VALUES(progress)
                                        )
                                    )
                                        ), earned = IF(
                                            earned = 1,
                                            earned,
                                        VALUES(earned)
                                        )");
                                $query->bindParam(":np_communication_id", $parent["parent_np_communication_id"], PDO::PARAM_STR);
                                $query->bindParam(":group_id", $parent["parent_group_id"], PDO::PARAM_STR);
                                $query->bindParam(":order_id", $parent["parent_order_id"], PDO::PARAM_INT);
                                $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
                                $query->bindParam(":earned_date", $dtAsTextForInsert, PDO::PARAM_STR);
                                $query->bindParam(":progress", $progress, PDO::PARAM_INT);
                                $query->bindParam(":earned", $trophy->earned(), PDO::PARAM_INT);
                                $query->execute();
                            }
                        }
                    }

                    // Recalculate trophies for trophy group and player
                    RecalculateTrophyGroup($trophyTitle->npCommunicationId(), $trophyGroup->id(), $user->accountId());
                }

                // Recalculate trophies for trophy title and player
                RecalculateTrophyTitle($trophyTitle->npCommunicationId(), $trophyTitle->lastUpdatedDateTime(), $newTrophies, $user->accountId(), false);

                // Game Merge stuff
                $query = $database->prepare("SELECT DISTINCT parent_np_communication_id, 
                                    parent_group_id 
                    FROM   trophy_merge 
                    WHERE  child_np_communication_id = :child_np_communication_id ");
                $query->bindParam(":child_np_communication_id", $trophyTitle->npCommunicationId(), PDO::PARAM_STR);
                $query->execute();
                while ($row = $query->fetch()) {
                    RecalculateTrophyGroup($row["parent_np_communication_id"], $row["parent_group_id"], $user->accountId());
                    RecalculateTrophyTitle($row["parent_np_communication_id"], $trophyTitle->lastUpdatedDateTime(), false, $user->accountId(), true);
                }
            }

            // TODO: Fix offset with the new API
            $offset += 128 - 8; // Subtract a little bit in-case the player have gotten new games while we are scanning

            // $query = $database->prepare("INSERT INTO player_queue
            //                 (
            //                             online_id,
            //                             offset
            //                 )
            //                 VALUES
            //                 (
            //                             :online_id,
            //                             :offset
            //                 )
            //     on duplicate KEY
            //     UPDATE
            //     offset=VALUES
            //            (
            //                   offset
            //            )");
            // $query->bindParam(":online_id", $player["online_id"], PDO::PARAM_STR);
            // $query->bindParam(":offset", $offset, PDO::PARAM_INT);
            // $query->execute();
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
        $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
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
        $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
        $query->execute();

        // Set player as clean if not a cheater
        $query = $database->prepare("UPDATE
                player p
            SET
                p.status = 0
            WHERE
                p.account_id = :account_id AND p.status != 1");
        $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
        $query->execute();

        // Check for hidden trophies
        $totalTrophies = $user->trophySummary()->platinum() + $user->trophySummary()->gold() + $user->trophySummary()->silver() + $user->trophySummary()->bronze();
        $query = $database->prepare("SELECT COUNT(*) FROM trophy_earned WHERE np_communication_id LIKE 'NPWR%' AND earned = 1 AND account_id = :account_id");
        $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
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
            $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
            $query->execute();
        }
    }

    // Done with the user, update the date
    $query = $database->prepare("UPDATE player
        SET    last_updated_date = Now()
        WHERE  account_id = :account_id ");
    $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
    $query->execute();

    // Delete user from the queue
    $query = $database->prepare("DELETE FROM player_queue
        WHERE  online_id = :online_id ");
    $query->bindParam(":online_id", $user->onlineId(), PDO::PARAM_STR);
    $query->execute();
}
