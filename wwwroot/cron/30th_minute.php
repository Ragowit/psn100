<?php
parse_str(implode('&', array_slice($argv, 1)), $_GET);

//ini_set("max_execution_time", "0");
//ini_set("mysql.connect_timeout", "0");
//set_time_limit(0);
require_once("/home/psn100/public_html/vendor/autoload.php");
require_once("/home/psn100/public_html/init.php");

use Tustin\PlayStation\Client;

//$maxTime = 1800; // 1800 seconds = 30 minutes

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
            t.np_communication_id = te.np_communication_id AND t.order_id = te.order_id AND t.status = 0
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
    $query = $database->prepare("INSERT INTO
            trophy_group_player (
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
            ) AS new ON DUPLICATE KEY
        UPDATE
            bronze = new.bronze,
            silver = new.silver,
            gold = new.gold,
            platinum = new.platinum,
            progress = new.progress
    ");
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
            ) AS new
            ON DUPLICATE KEY
            UPDATE
                bronze = new.bronze,
                silver = new.silver,
                gold = new.gold,
                platinum = new.platinum,
                progress = new.progress,
                last_updated_date = IF(trophy_title_player.last_updated_date > new.last_updated_date, trophy_title_player.last_updated_date, new.last_updated_date)");
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
            ) AS new
            ON DUPLICATE KEY
            UPDATE
                bronze = new.bronze,
                silver = new.silver,
                gold = new.gold,
                platinum = new.platinum,
                progress = new.progress,
                last_updated_date = new.last_updated_date");
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

function Psn100Log($message) {
    $database = new Database();

    $query = $database->prepare("INSERT INTO log
                    (message)
        VALUES      (:message) ");
    $query->bindParam(":message", $message, PDO::PARAM_STR);
    $query->execute();
}

$recheck = "";

while (true) {
    // Login with a token
    $loggedIn = false;
    while (!$loggedIn) {
        $query = $database->prepare("SELECT
                id,
                npsso,
                scanning
            FROM
                setting
            WHERE
                id = :id");
        $query->bindParam(":id", $_GET["worker"], PDO::PARAM_INT);
        $query->execute();
        $worker = $query->fetch();

        try {
            $client = new Client();
            $npsso = $worker["npsso"];
            $client->loginWithNpsso($npsso);

            $loggedIn = true;
        } catch (Exception $e) {
            Psn100Log("Can't login with worker ". $worker["id"]);

            // Something went wrong, 'release' the current scanning profile so other workers can pick it up.
            $query = $database->prepare("UPDATE setting SET scanning = :id WHERE id = :id");
            $query->bindParam(":id", $worker["id"], PDO::PARAM_INT);
            $query->execute();
        }

        if (!$loggedIn) {
            // Wait 5 minutes to not hammer login
            sleep(60 * 5);
        }
    }

    // Get our queue.
    // #1 - Users added from the front page, ordered by time entered
    // #2 - Top 100 players who haven't been updated within a day, ordered by the oldest one.
    // #3 - Top 1000 players or +/- 250 players who are about to drop out of top 50k who haven't been updated within a week, ordered by the oldest one.
    // #4 - Top 10000 players who haven't been updated within a month, ordered by the oldest one.
    // #5 - Top 50000 players who haven't been updated within six months, ordered by the oldest one.
    // #6 - Users added by Ragowit when site was created to populate the site, ordered by name (will be removed once done)
    // #7 - Oldest scanned player who is not tagged as a cheater
    $query = $database->prepare("SELECT
            online_id,
            account_id
        FROM
            (
                SELECT
                    1 AS tier,
                    pq.online_id,
                    pq.request_time,
                    p.account_id
                FROM
                    player_queue pq
                    LEFT JOIN player p ON p.online_id = pq.online_id
                WHERE
                    pq.request_time < '2030-12-25 00:00:00'
                UNION ALL
                SELECT
                    2 AS tier,
                    online_id,
                    last_updated_date,
                    account_id
                FROM
                    player
                WHERE
                    (
                        `rank` <= 100
                        OR rarity_rank <= 100
                    )
                    AND last_updated_date < NOW() - INTERVAL 1 DAY
                    AND `status` = 0
                UNION ALL
                SELECT
                    3 AS tier,
                    online_id,
                    last_updated_date,
                    account_id
                FROM
                    player
                WHERE
                    (
                        `rank` <= 1000
                        OR rarity_rank <= 1000
                        OR (
                            `rank` >= 49750
                            AND `rank` <= 50250
                        )
                        OR (
                            `rarity_rank` >= 49750
                            AND `rarity_rank` <= 50250
                        )
                    )
                    AND last_updated_date < NOW() - INTERVAL 7 DAY
                    AND `status` = 0
                UNION ALL
                SELECT
                    4 AS tier,
                    online_id,
                    last_updated_date,
                    account_id
                FROM
                    player
                WHERE
                    (
                        `rank` <= 10000
                        OR rarity_rank <= 10000
                    )
                    AND last_updated_date < NOW() - INTERVAL 1 MONTH
                    AND `status` = 0
                UNION ALL
                SELECT
                    5 AS tier,
                    online_id,
                    last_updated_date,
                    account_id
                FROM
                    player
                WHERE
                    (
                        `rank` <= 50000
                        OR rarity_rank <= 50000
                    )
                    AND last_updated_date < NOW() - INTERVAL 6 MONTH
                    AND `status` = 0
                UNION ALL
                SELECT
                    6 AS tier,
                    pq.online_id,
                    pq.request_time,
                    p.account_id
                FROM
                    player_queue pq
                    LEFT JOIN player p ON p.online_id = pq.online_id
                WHERE
                    pq.request_time >= '2030-12-25 00:00:00'
                UNION ALL
                SELECT
                    7 AS tier,
                    online_id,
                    last_updated_date,
                    account_id
                FROM
                    player
                WHERE
                    `status` != 1
            ) a
        WHERE NOT EXISTS (SELECT * FROM setting WHERE scanning = a.online_id AND id != :worker_id)
        ORDER BY
            tier,
            request_time,
            online_id
        LIMIT
            1
    ");
    $query->bindParam(":worker_id", $worker["id"], PDO::PARAM_INT);
    $query->execute();
    $player = $query->fetch();

    $query = $database->prepare("UPDATE setting SET scanning = :scanning WHERE id = :worker_id");
    $query->bindParam(":scanning", $player["online_id"], PDO::PARAM_STR);
    $query->bindParam(":worker_id", $worker["id"], PDO::PARAM_INT);
    $query->execute();

    if ($recheck == $player["online_id"]) {
        $recheck = "";
    } else {
        $recheck = $player["online_id"];
    }

    // Initialize the current player
    try {
        if (is_numeric($player["account_id"])) {
            $user = $client->users()->find($player["account_id"]);
            // ->find() doesn't have country information, but we should have it in our database from the ->search() when user was new to us.
            $query = $database->prepare("SELECT
                    country
                FROM
                    player
                WHERE
                    account_id = :account_id
            ");
            $query->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
            $query->execute();
            $country = $query->fetchColumn();
        } else {
            // Search the user
            unset($user);
            $userFound = false;
            $userCounter = 0;

            foreach ($client->users()->search($player["online_id"]) as $userSearchResult) {
                if (strtolower($userSearchResult->onlineId()) == strtolower($player["online_id"])) {
                    $user = $userSearchResult;
                    $userFound = true;
                    $country = $user->country();
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
                        last_updated_date = NOW()
                    WHERE
                        online_id = :online_id
                        AND `status` != 1
                    ");
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
        }

        // To test for exception.
        $user->aboutMe();
    } catch (Exception $e) {
        // $e->getMessage() == "User not found", and another "Resource not found" error
        $query = $database->prepare("DELETE FROM player_queue
            WHERE  online_id = :online_id ");
        $query->bindParam(":online_id", $player["online_id"], PDO::PARAM_STR);
        $query->execute();

        if (get_class($e) == "Tustin\Haste\Exception\NotFoundHttpException") {
            $query = $database->prepare("SELECT account_id
                FROM   player
                WHERE  online_id = :online_id ");
            $query->bindParam(":online_id", $player["online_id"], PDO::PARAM_STR);
            $query->execute();
            $accountId = $query->fetchColumn();

            if ($accountId) {
                // Doesn't seem to exist on Sonys end anymore. Set to status = 5 and let an admin delete the player from our system later.
                Psn100Log("Sony issues with ". $player["online_id"] ." (". $accountId .").");
    
                $query = $database->prepare("UPDATE player
                    SET `status` = 5, last_updated_date = NOW()
                    WHERE  account_id = :account_id ");
                $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                $query->execute();
            }
        }

        continue;
    }

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
    $query = $database->prepare("INSERT INTO
            player (
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
            ) AS new ON DUPLICATE KEY
        UPDATE
            online_id = new.online_id,
            avatar_url = new.avatar_url,
            plus = new.plus,
            about_me = new.about_me
    ");
    $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
    $query->bindParam(":online_id", $user->onlineId(), PDO::PARAM_STR);
    $query->bindParam(":country", strtolower($country), PDO::PARAM_STR);
    $query->bindParam(":avatar_url", $avatarFilename, PDO::PARAM_STR);
    $query->bindParam(":plus", $plus, PDO::PARAM_BOOL);
    $query->bindParam(":about_me", $user->aboutMe(), PDO::PARAM_STR);
    // Don't insert level/progress/platinum/gold/silver/bronze here since our site recalculate this.
    $query->execute();

    try {
        $level = 0;
        $level = $user->trophySummary();
    } catch (Exception $e) {
        // Wait 5 minutes to not hammer Sony
        sleep(60 * 5);

        // Something is odd with PSN, break out and try again later.
        break;
    }

    $privateUser = false;
    try {
        $level = 0;
        $level = $user->trophySummary()->level();
    } catch (Exception $e) {
        // Profile seem to be private, set status to 3 to hide all trophies.
        $query = $database->prepare("UPDATE
                player
            SET
                status = 3,
                last_updated_date = NOW()
            WHERE
                account_id = :account_id
                AND status != 1
        ");
        $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
        $query->execute();

        // Delete user from the queue
        $query = $database->prepare("DELETE FROM player_queue
            WHERE  online_id = :online_id ");
        $query->bindParam(":online_id", $user->onlineId(), PDO::PARAM_STR);
        $query->execute();

        $privateUser = true;
    }

    if (!$privateUser) {
        $totalTrophiesStart = $user->trophySummary()->platinum() + $user->trophySummary()->gold() + $user->trophySummary()->silver() + $user->trophySummary()->bronze();

        if ($level !== 0) {
            $query = $database->prepare("SELECT p.last_updated_date, p.status
                FROM   player p
                WHERE  p.account_id = :account_id ");
            $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
            $query->execute();
            $playerData = $query->fetch();

            $query = $database->prepare("SELECT np_communication_id,
                    last_updated_date
                FROM   trophy_title_player
                WHERE  account_id = :account_id ");
            $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
            $query->execute();
            $gameLastUpdatedDate = $query->fetchAll(PDO::FETCH_KEY_PAIR);

            // Look through each and every game
            foreach ($user->trophyTitles() as $trophyTitle) {
                $newTrophies = false;
                $sonyLastUpdatedDate = date_create($trophyTitle->lastUpdatedDateTime());
                // Check if the current game last updated date is the same as in our database
                if (array_key_exists($trophyTitle->npCommunicationId(), $gameLastUpdatedDate) && $sonyLastUpdatedDate->format("Y-m-d H:i:s") === date_create($gameLastUpdatedDate[$trophyTitle->npCommunicationId()])->format("Y-m-d H:i:s")) {
                    // Check if the current player is not new and doesn't have the hidden game status (these players will continue on with scanning, in order to find if they are unhidden)
                    if ($playerData["last_updated_date"] != null && $playerData["status"] != 2) {
                        // Check if we have passed player's last update date, we can assume we are done if so and break out of the scan loop.
                        if (date_create($gameLastUpdatedDate[$trophyTitle->npCommunicationId()])->format("Y-m-d H:i:s") < date_create($playerData["last_updated_date"])->format("Y-m-d H:i:s")) {
                            break;
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
                            ''
                        ) AS new
                        ON DUPLICATE KEY
                        UPDATE
                            icon_url = new.icon_url");
                    $query->bindParam(":np_communication_id", $trophyTitle->npCommunicationId(), PDO::PARAM_STR);
                    $query->bindParam(":name", $trophyTitle->name(), PDO::PARAM_STR);
                    $query->bindParam(":detail", $trophyTitle->detail(), PDO::PARAM_STR);
                    $query->bindParam(":icon_url", $trophyTitleIconFilename, PDO::PARAM_STR);
                    $platforms = "";
                    foreach ($trophyTitle->platform() as $platform) {
                        $platforms .= $platform->value .",";
                    }
                    $platforms = rtrim($platforms, ",");
                    $query->bindParam(":platform", $platforms, PDO::PARAM_STR);
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
                            ) AS new
                            ON DUPLICATE KEY
                            UPDATE
                                detail = new.detail,
                                icon_url = new.icon_url");
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
                                    ) AS new
                                    ON DUPLICATE KEY
                                    UPDATE
                                        hidden = new.hidden,
                                        type = new.type,
                                        name = new.name,
                                        detail = new.detail,
                                        icon_url = new.icon_url,
                                        progress_target_value = new.progress_target_value,
                                        reward_name = new.reward_name,
                                        reward_image_url = new.reward_image_url");
                                $query->bindParam(":np_communication_id", $trophyTitle->npCommunicationId(), PDO::PARAM_STR);
                                $query->bindParam(":group_id", $trophyGroup->id(), PDO::PARAM_STR);
                                $query->bindParam(":order_id", $trophy->id(), PDO::PARAM_INT);
                                $query->bindParam(":hidden", $trophy->hidden(), PDO::PARAM_INT);
                                $trophyTypeEnumValue = $trophy->type()->value;
                                $query->bindParam(":type", $trophyTypeEnumValue, PDO::PARAM_STR);
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
                                Psn100Log("New trophies added for ". $trophyTitle->name() .". ". $trophyTitle->npCommunicationId() . ", ". $trophyGroup->id() .", ". $trophyGroup->name());
                            } else {
                                Psn100Log("SET VERSION for ". $trophyTitle->name() .". ". $trophyTitle->npCommunicationId() . ", ". $trophyGroup->id() .", ". $trophyGroup->name());
                            }

                            
                        }
                    }
                }

                if ($newTrophies) {
                    $query = $database->prepare("SELECT id
                        FROM   trophy_title
                        WHERE  np_communication_id = :np_communication_id ");
                    $query->bindParam(":np_communication_id", $trophyTitle->npCommunicationId(), PDO::PARAM_STR);
                    $query->execute();
                    $id = $query->fetchColumn();

                    $query = $database->prepare("INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES ('GAME_VERSION', :param_1)");
                    $query->bindParam(":param_1", $id, PDO::PARAM_INT);
                    $query->execute();
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

                // Fetch user trophies
                foreach ($trophyTitle->trophyGroups() as $trophyGroup) {
                    foreach ($trophyGroup->trophies() as $trophy) {
                        $trophyEarned = $trophy->earned();
                        $progress = (clone $trophy)->progress();
                        if ($trophyEarned || ($progress != '' && intval($progress) > 0)) {
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
                                ) AS new
                                ON DUPLICATE KEY
                                UPDATE
                                    earned_date = IF(trophy_earned.earned = 0, new.earned_date, trophy_earned.earned_date),
                                    progress = new.progress,
                                    earned = new.earned");
                            $query->bindParam(":np_communication_id", $trophyTitle->npCommunicationId(), PDO::PARAM_STR);
                            $query->bindParam(":group_id", $trophyGroup->id(), PDO::PARAM_STR);
                            $query->bindParam(":order_id", $trophy->id(), PDO::PARAM_INT);
                            $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
                            $query->bindParam(":earned_date", $dtAsTextForInsert, PDO::PARAM_STR);
                            if ($progress === '') {
                                $progress = null;
                            } else {
                                $progress = intval($progress);
                            }
                            $query->bindParam(":progress", $progress, PDO::PARAM_INT);
                            $query->bindParam(":earned", $trophyEarned, PDO::PARAM_INT);
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
                                    ) AS new
                                    ON DUPLICATE KEY
                                    UPDATE
                                        earned_date = IF(trophy_earned.earned_date < new.earned_date, trophy_earned.earned_date, new.earned_date),
                                        progress = IF(trophy_earned.progress IS NULL, new.progress,
                                            IF(new.progress IS NULL, trophy_earned.progress,
                                                IF(trophy_earned.progress > new.progress, trophy_earned.progress, new.progress)
                                            )
                                        ),
                                        earned = IF(trophy_earned.earned = 1, trophy_earned.earned, new.earned)");
                                $query->bindParam(":np_communication_id", $parent["parent_np_communication_id"], PDO::PARAM_STR);
                                $query->bindParam(":group_id", $parent["parent_group_id"], PDO::PARAM_STR);
                                $query->bindParam(":order_id", $parent["parent_order_id"], PDO::PARAM_INT);
                                $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
                                $query->bindParam(":earned_date", $dtAsTextForInsert, PDO::PARAM_STR);
                                $query->bindParam(":progress", $progress, PDO::PARAM_INT);
                                $query->bindParam(":earned", $trophyEarned, PDO::PARAM_INT);
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

            // Set player status if not a cheater
            $playerStatus = 0;

            // Check for hidden trophies
            $query = $database->prepare("SELECT
                    trophy_count_npwr
                FROM
                    player
                WHERE
                    account_id = :account_id
                ");
            $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
            $query->execute();
            $ourTotalTrophies = $query->fetchColumn();

            if ($ourTotalTrophies < $totalTrophiesStart) {
                if (!empty($recheck)) { // If the user is about to be hidden, do one more scan from the beginning just to be sure.
                    continue;
                }

                $playerStatus = 2; // Hidden trophies
            }

            // Check for inactive
            $query = $database->prepare("SELECT
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
            $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
            $query->execute();
            $withinAYear = $query->fetchColumn();
            if ($withinAYear == 0) {
                $playerStatus = 4;
            }

            $query = $database->prepare("UPDATE
                    player p
                SET
                    p.status = :status
                WHERE
                    p.account_id = :account_id
                    AND p.status != 1
                ");
            $query->bindParam(":status", $playerStatus, PDO::PARAM_INT);
            $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
            $query->execute();
        }

        $query = $database->prepare("SELECT
                p.status
            FROM
                player p
            WHERE
                p.account_id = :account_id");
        $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
        $query->execute();
        $playerStatus = $query->fetchColumn();

        $totalTrophiesEnd = $user->trophySummary()->platinum() + $user->trophySummary()->gold() + $user->trophySummary()->silver() + $user->trophySummary()->bronze();
        if ($totalTrophiesStart == $totalTrophiesEnd) { // The scan of the player is only done if the trophy count is the same at the start and at the end.
            if ($playerStatus == 0) {
                // Update ranks
                $query = $database->prepare("WITH
                        ranking AS(
                        SELECT
                            p.account_id,
                            RANK() OVER(
                            ORDER BY
                                p.points
                            DESC
                                ,
                                p.platinum
                            DESC
                                ,
                                p.gold
                            DESC
                                ,
                                p.silver
                            DESC
                        ) ranking
                    FROM
                        player p
                    WHERE
                        p.status = 0)
                        UPDATE
                            player p,
                            ranking r
                        SET
                            p.rank = r.ranking
                        WHERE
                            p.account_id = r.account_id");
                $query->execute();

                // Update country ranks
                $query = $database->prepare("WITH
                        ranking AS(
                        SELECT
                            p.account_id,
                            RANK() OVER(
                            ORDER BY
                                p.points
                            DESC
                                ,
                                p.platinum
                            DESC
                                ,
                                p.gold
                            DESC
                                ,
                                p.silver
                            DESC
                        ) ranking
                        FROM
                            player p
                        WHERE
                            p.status = 0 AND p.country = :country)
                    UPDATE
                        player p,
                        ranking r
                    SET
                        p.rank_country = r.ranking
                    WHERE
                        p.account_id = r.account_id");
                $query->bindParam(":country", strtolower($country), PDO::PARAM_STR);
                $query->execute();

                // Update user rarity points for each game
                $query = $database->prepare("WITH
                        rarity AS(
                        SELECT
                            trophy_earned.np_communication_id,
                            SUM(trophy.rarity_point) AS points,
                            SUM(trophy.rarity_name = 'COMMON') common,
                            SUM(trophy.rarity_name = 'UNCOMMON') uncommon,
                            SUM(trophy.rarity_name = 'RARE') rare,
                            SUM(trophy.rarity_name = 'EPIC') epic,
                            SUM(trophy.rarity_name = 'LEGENDARY') legendary
                        FROM
                            trophy_earned
                        JOIN trophy USING(
                                np_communication_id,
                                order_id
                            )
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
                        ttp.common = rarity.common,
                        ttp.uncommon = rarity.uncommon,
                        ttp.rare = rarity.rare,
                        ttp.epic = rarity.epic,
                        ttp.legendary = rarity.legendary
                    WHERE
                        ttp.account_id = :account_id AND ttp.np_communication_id = rarity.np_communication_id");
                $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
                $query->execute();

                // Update user total rarity points
                $query = $database->prepare("WITH
                        rarity AS(
                        SELECT
                            IFNULL(SUM(rarity_points), 0) AS rarity_points,
                            IFNULL(SUM(common), 0) AS common,
                            IFNULL(SUM(uncommon), 0) AS uncommon,
                            IFNULL(SUM(rare), 0) AS rare,
                            IFNULL(SUM(epic), 0) AS epic,
                            IFNULL(SUM(legendary), 0) AS legendary
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
                        p.legendary = rarity.legendary
                    WHERE
                        p.account_id = :account_id AND p.status = 0");
                $query->bindParam(":account_id", $user->accountId(), PDO::PARAM_INT);
                $query->execute();

                // Update rarity ranks
                $query = $database->prepare("WITH
                        ranking AS(
                        SELECT
                            p.account_id,
                            RANK() OVER(
                        ORDER BY
                            p.rarity_points
                        DESC
                        ) ranking
                    FROM
                        player p
                    WHERE
                        p.status = 0)
                        UPDATE
                            player p,
                            ranking r
                        SET
                            p.rarity_rank = r.ranking
                        WHERE
                            p.account_id = r.account_id");
                $query->execute();

                // Update country rarity ranks
                $query = $database->prepare("WITH
                        ranking AS(
                        SELECT
                            p.account_id,
                            RANK() OVER(
                        ORDER BY
                            p.rarity_points
                        DESC
                        ) ranking
                    FROM
                        player p
                    WHERE
                        p.status = 0 AND p.country = :country)
                        UPDATE
                            player p,
                            ranking r
                        SET
                            p.rarity_rank_country = r.ranking
                        WHERE
                            p.account_id = r.account_id");
                $query->bindParam(":country", strtolower($country), PDO::PARAM_STR);
                $query->execute();
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
    }
}
