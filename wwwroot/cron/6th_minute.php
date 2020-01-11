<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
// The lines above doesn't seem to do anything on my webhost. 504 after about 5 minutes anyway.
require_once("../vendor/autoload.php");
require_once("../init.php");

use PlayStation\Client;

// Get current tokens
$query = $database->prepare("SELECT * FROM setting");
$query->execute();
$workers = $query->fetchAll();

$clients = array();

// Login with all the tokens
$database->beginTransaction();
foreach ($workers as $worker) {
    $client = new Client();
    $refreshToken = $worker["refresh_token"];
    $client->login($refreshToken);

    // Store new token
    $refreshToken = $client->refreshToken();
    $query = $database->prepare("UPDATE setting SET refresh_token = :refresh_token WHERE id = :id");
    $query->bindParam(":refresh_token", $refreshToken, PDO::PARAM_STR);
    $query->bindParam(":id", $worker["id"], PDO::PARAM_INT);
    $query->execute();

    array_push($clients, $client);
}
$database->commit();

// Get our queue. Prioritize user requests, and then just old scanned players from our database.
$queueQuery = $database->prepare("SELECT * FROM (SELECT 1 AS tier, online_id, request_time FROM player_queue UNION ALL SELECT 2 AS tier, online_id, last_updated_date FROM player WHERE rank <= 100000) a ORDER BY tier, request_time, online_id");
$queueQuery->execute();
while ($tempPlayer = $queueQuery->fetch()) {
    $player = $tempPlayer["online_id"];

    // Initialize the current player
    $users = array();
    try {
        foreach ($clients as $client) {
            $user = $client->user($player);
            array_push($users, $user);
        }
    } catch (Exception $e) {
        // User doesn't exist, remove from the queue.
        $query = $database->prepare("DELETE FROM player_queue WHERE online_id = :online_id");
        $query->bindParam(":online_id", $player, PDO::PARAM_STR);
        $query->execute();

        if (strpos($e->getMessage(), "User not found") !== false) {
            $query = $database->prepare("SELECT account_id FROM player WHERE online_id = :online_id");
            $query->bindParam(":online_id", $player, PDO::PARAM_STR);
            $query->execute();
            $accountId = $query->fetchColumn();

            if (!$accountId) {
                $query = $database->prepare("DELETE FROM trophy_earned WHERE account_id = :account_id");
                $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
                $query->execute();

                $query = $database->prepare("DELETE FROM trophy_group_player WHERE account_id = :account_id");
                $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
                $query->execute();

                $query = $database->prepare("DELETE FROM trophy_title_player WHERE account_id = :account_id");
                $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
                $query->execute();
                $database->commit();
            }
        }

        continue;
    }

    // Get basic info of the current player
    $client = 0;
    try {
        $info = $users[$client]->info();
    } catch (Exception $e) {
        // User doesn't exist, remove from the queue.
        $query = $database->prepare("DELETE FROM player_queue WHERE online_id = :online_id");
        $query->bindParam(":online_id", $player, PDO::PARAM_STR);
        $query->execute();

        if (strpos($e->getMessage(), "User not found") !== false) {
            $query = $database->prepare("SELECT account_id FROM player WHERE online_id = :online_id");
            $query->bindParam(":online_id", $player, PDO::PARAM_STR);
            $query->execute();
            $accountId = $query->fetchColumn();

            if (!$accountId) {
                $query = $database->prepare("DELETE FROM trophy_earned WHERE account_id = :account_id");
                $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
                $query->execute();

                $query = $database->prepare("DELETE FROM trophy_group_player WHERE account_id = :account_id");
                $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
                $query->execute();

                $query = $database->prepare("DELETE FROM trophy_title_player WHERE account_id = :account_id");
                $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
                $query->execute();
                $database->commit();
            }
        }

        continue;
    }
    $client++;
    if ($client >= count($clients)) {
        $client = 0;
    }

    // Get the avatar url we want to save
    $avatarUrl = $info->avatarUrls[0]->avatarUrl;
    $avatarFilename = substr($avatarUrl, strrpos($avatarUrl, "/") + 1);
    // Download the avatar if we don't have it
    if (!file_exists("../img/avatar/". $avatarFilename)) {
        file_put_contents("../img/avatar/". $avatarFilename, fopen($avatarUrl, 'r'));
    }

    // Plus is null or 1, we don't want null so this will make it 0 if so.
    $plus = (bool)$info->plus;

    // Add/update player into database
    $database->beginTransaction();
    $query = $database->prepare("INSERT INTO player (account_id, online_id, country, avatar_url, plus, about_me) VALUES (:account_id, :online_id, :country, :avatar_url, :plus, :about_me) ON DUPLICATE KEY UPDATE online_id=VALUES(online_id), avatar_url=VALUES(avatar_url), plus=VALUES(plus), about_me=VALUES(about_me)");
    $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
    $query->bindParam(":online_id", $info->onlineId, PDO::PARAM_STR);
    $query->bindParam(":country", substr(base64_decode($info->npId), -2), PDO::PARAM_STR);
    $query->bindParam(":avatar_url", $avatarFilename, PDO::PARAM_STR);
    $query->bindParam(":plus", $plus, PDO::PARAM_BOOL);
    $query->bindParam(":about_me", $info->aboutMe, PDO::PARAM_STR);
    // Don't insert level/progress/platinum/gold/silver/bronze here since our site recalculate this.
    $query->execute();
    $database->commit();

    if ($info->trophySummary->level === 1 && $info->trophySummary->progress === 0) {
        // Profile most likely set to private, remove all trophy data we have for this player
        $database->beginTransaction();
        $query = $database->prepare("UPDATE player SET level = 1, progress = 0, platinum = 0, gold = 0, silver = 0, bronze = 0 WHERE account_id = :account_id");
        $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
        $query->execute();

        $query = $database->prepare("DELETE FROM trophy_earned WHERE account_id = :account_id");
        $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
        $query->execute();

        $query = $database->prepare("DELETE FROM trophy_group_player WHERE account_id = :account_id");
        $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
        $query->execute();

        $query = $database->prepare("DELETE FROM trophy_title_player WHERE account_id = :account_id");
        $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
        $query->execute();
        $database->commit();
    } else {
        $offset = 0;
        $totalResults = 0;
        $skippedGames = 0;

        $query = $database->prepare("SELECT last_updated_date FROM player WHERE account_id = :account_id");
        $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
        $query->execute();
        $playerLastUpdatedDate = $query->fetchColumn();

        $query = $database->prepare("SELECT np_communication_id, last_updated_date FROM trophy_title_player WHERE account_id = :account_id");
        $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
        $query->execute();
        $gameLastUpdatedDate = $query->fetchAll(PDO::FETCH_KEY_PAIR);

        while ($offset <= $totalResults) {
            // Try and get the player games until we succeed (have only gotten HTTP 500 for ikemenzi from time to time, but you never know)
            $fetchTrophyTitles = true;
            while ($fetchTrophyTitles) {
                try {
                    $trophyTitles = $users[$client]->trophyTitles($offset);
                    $fetchTrophyTitles = false;
                } catch (Exception $e) {
                    // Increase the request_time and continue with the next one in the queue.
                    $database->beginTransaction();
                    $query = $database->prepare("UPDATE player_queue SET request_time = DATE_ADD(request_time, INTERVAL 1 MINUTE) WHERE online_id = :online_id");
                    $query->bindParam(":online_id", $info->onlineId, PDO::PARAM_STR);
                    $query->execute();
                    $database->commit();

                    continue 3;
                }

                $client++;
                if ($client >= count($clients)) {
                    $client = 0;
                }
            }

            $totalResults = $trophyTitles->totalResults;

            foreach ($trophyTitles->trophyTitles as $game) {
                $sonyLastUpdatedDate = date_create($game->comparedUser->lastUpdateDate);
                if ($sonyLastUpdatedDate->format("Y-m-d H:i:s") === date_create($gameLastUpdatedDate[$game->npCommunicationId])->format("Y-m-d H:i:s")) {
                    $skippedGames++;

                    if ($skippedGames >= 248 && $playerLastUpdatedDate != "0000-00-00 00:00:00") { // New players have "0000-00-00 00:00:00", and will thus continue with a full scan.
                        // 248 skipped games (a little bit less then two trophyTitles() fetches), we can assume we are done with this player.
                        break 2;
                    }

                    // Game seems scanned already, skip to next.
                    continue;
                }

                // Get the title icon url we want to save
                $trophyTitleIconUrl = $game->trophyTitleIconUrl;
                $trophyTitleIconFilename = substr($trophyTitleIconUrl, strrpos($trophyTitleIconUrl, "/") + 1);
                // Download the title icon if we don't have it
                if (!file_exists("../img/title/". $trophyTitleIconFilename)) {
                    file_put_contents("../img/title/". $trophyTitleIconFilename, fopen($trophyTitleIconUrl, "r"));
                }

                // Add trophy title (game) information into database
                $database->beginTransaction();
                // I know there is a INSERT INTO ... ON DUPLICATE KEY UPDATE, however it makes the autoincrement tick as well. I don't want that.
                $query = $database->prepare("SELECT COUNT(*) FROM trophy_title WHERE np_communication_id = :np_communication_id");
                $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                $query->execute();
                $check = $query->fetchColumn();
                if ($check == 0) {
                    $query = $database->prepare("INSERT INTO trophy_title (np_communication_id, name, detail, icon_url, platform) VALUES (:np_communication_id, :name, :detail, :icon_url, :platform)");
                } else {
                    $query = $database->prepare("UPDATE trophy_title SET name = :name, detail = :detail, icon_url = :icon_url, platform = :platform WHERE np_communication_id = :np_communication_id");
                }
                $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                $query->bindParam(":name", $game->trophyTitleName, PDO::PARAM_STR);
                $query->bindParam(":detail", $game->trophyTitleDetail, PDO::PARAM_STR);
                $query->bindParam(":icon_url", $trophyTitleIconFilename, PDO::PARAM_STR);
                $query->bindParam(":platform", $game->trophyTitlePlatfrom, PDO::PARAM_STR);
                // Don't insert platinum/gold/silver/bronze here since our site recalculate this.
                $query->execute();
                $database->commit();

                // Get "groups" (game and DLCs)
                $trophyGroups = $users[$client]->trophyGroups($game->npCommunicationId)->trophyGroups;
                $client++;
                if ($client >= count($clients)) {
                    $client = 0;
                }

                foreach ($trophyGroups as $trophyGroup) {
                    $trophyGroupIconUrl = $trophyGroup->trophyGroupIconUrl;
                    $trophyGroupIconFilename = substr($trophyGroupIconUrl, strrpos($trophyGroupIconUrl, "/") + 1);
                    // Download the group icon if we don't have it
                    if (!file_exists("../img/group/". $trophyGroupIconFilename)) {
                        file_put_contents("../img/group/". $trophyGroupIconFilename, fopen($trophyGroupIconUrl, "r"));
                    }

                    // Add trophy group (game + dlcs) into database
                    $database->beginTransaction();
                    // I know there is a INSERT INTO ... ON DUPLICATE KEY UPDATE, however it makes the autoincrement tick as well. I don't want that.
                    $query = $database->prepare("SELECT COUNT(*) FROM trophy_group WHERE np_communication_id = :np_communication_id AND group_id = :group_id");
                    $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                    $query->bindParam(":group_id", $trophyGroup->trophyGroupId, PDO::PARAM_STR);
                    $query->execute();
                    $check = $query->fetchColumn();
                    if ($check == 0) {
                        $query = $database->prepare("INSERT INTO trophy_group (np_communication_id, group_id, name, detail, icon_url) VALUES (:np_communication_id, :group_id, :name, :detail, :icon_url)");
                    } else {
                        $query = $database->prepare("UPDATE trophy_group SET name = :name, detail = :detail, icon_url = :icon_url WHERE np_communication_id = :np_communication_id AND group_id = :group_id");
                    }
                    $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                    $query->bindParam(":group_id", $trophyGroup->trophyGroupId, PDO::PARAM_STR);
                    $query->bindParam(":name", $trophyGroup->trophyGroupName, PDO::PARAM_STR);
                    $query->bindParam(":detail", $trophyGroup->trophyGroupDetail, PDO::PARAM_STR);
                    $query->bindParam(":icon_url", $trophyGroupIconFilename, PDO::PARAM_STR);
                    // Don't insert platinum/gold/silver/bronze here since our site recalculate this.
                    $query->execute();
                    $database->commit();

                    $result = $users[$client]->trophies($game->npCommunicationId, $trophyGroup->trophyGroupId);
                    $client++;
                    if ($client >= count($clients)) {
                        $client = 0;
                    }
                    foreach ($result as $trophies) {
                        $queryInsertTrophyEarned = $database->prepare("INSERT IGNORE INTO trophy_earned (np_communication_id, group_id, order_id, account_id, earned_date) VALUES (:np_communication_id, :group_id, :order_id, :account_id, :earned_date)");

                        foreach ($trophies as $trophy) {
                            $trophyIconUrl = $trophy->trophyIconUrl;
                            $trophyIconFilename = substr($trophyIconUrl, strrpos($trophyIconUrl, "/") + 1);
                            // Download the trophy icon if we don't have it
                            if (!file_exists("../img/trophy/". $trophyIconFilename)) {
                                file_put_contents("../img/trophy/". $trophyIconFilename, fopen($trophyIconUrl, "r"));
                            }

                            // Add trophies into database
                            $database->beginTransaction();
                            // I know there is a INSERT INTO ... ON DUPLICATE KEY UPDATE, however it makes the autoincrement tick as well. I don't want that.
                            $query = $database->prepare("SELECT COUNT(*) FROM trophy WHERE np_communication_id = :np_communication_id AND group_id = :group_id AND order_id = :order_id");
                            $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                            $query->bindParam(":group_id", $trophyGroup->trophyGroupId, PDO::PARAM_STR);
                            $query->bindParam(":order_id", $trophy->trophyId, PDO::PARAM_INT);
                            $query->execute();
                            $check = $query->fetchColumn();
                            if ($check == 0) {
                                $queryInsertTrophy = $database->prepare("INSERT INTO trophy (np_communication_id, group_id, order_id, hidden, type, name, detail, icon_url, rare, earned_rate) VALUES (:np_communication_id, :group_id, :order_id, :hidden, :type, :name, :detail, :icon_url, :rare, :earned_rate)");
                            } else {
                                $queryInsertTrophy = $database->prepare("UPDATE trophy SET hidden = :hidden, type = :type, name = :name, detail = :detail, icon_url = :icon_url, rare = :rare, earned_rate = :earned_rate WHERE np_communication_id = :np_communication_id AND group_id = :group_id AND order_id = :order_id");
                            }
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
                            $database->commit();

                            // If the player have earned the trophy, add it into the database
                            if ($trophy->comparedUser->earned == "1") {
                                $database->beginTransaction();
                                $queryInsertTrophyEarned->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                                $queryInsertTrophyEarned->bindParam(":group_id", $trophyGroup->trophyGroupId, PDO::PARAM_STR);
                                $queryInsertTrophyEarned->bindParam(":order_id", $trophy->trophyId, PDO::PARAM_INT);
                                $queryInsertTrophyEarned->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
                                $queryInsertTrophyEarned->bindParam(":earned_date", $trophy->comparedUser->earnedDate, PDO::PARAM_STR);
                                $queryInsertTrophyEarned->execute();
                                $database->commit();
                            }
                        }
                    }

                    // Recalculate trophies for trophy group
                    $query = $database->prepare("SELECT type, COUNT(*) AS count FROM trophy WHERE np_communication_id = :np_communication_id AND group_id = :group_id AND status = 0 GROUP BY type");
                    $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                    $query->bindParam(":group_id", $trophyGroup->trophyGroupId, PDO::PARAM_STR);
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
                    $database->beginTransaction();
                    $query = $database->prepare("UPDATE trophy_group SET bronze = :bronze, silver = :silver, gold = :gold, platinum = :platinum WHERE np_communication_id = :np_communication_id AND group_id = :group_id");
                    $query->bindParam(":bronze", $trophyTypes["bronze"], PDO::PARAM_INT);
                    $query->bindParam(":silver", $trophyTypes["silver"], PDO::PARAM_INT);
                    $query->bindParam(":gold", $trophyTypes["gold"], PDO::PARAM_INT);
                    $query->bindParam(":platinum", $trophyTypes["platinum"], PDO::PARAM_INT);
                    $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                    $query->bindParam(":group_id", $trophyGroup->trophyGroupId, PDO::PARAM_STR);
                    $query->execute();
                    $database->commit();

                    // Recalculate trophies for trophy group for the player
                    $maxScore = $trophyTypes["bronze"]*15 + $trophyTypes["silver"]*30 + $trophyTypes["gold"]*90; // Platinum isn't counted for
                    $query = $database->prepare("SELECT type, COUNT(type) AS count FROM trophy_earned te LEFT JOIN trophy t ON t.np_communication_id = te.np_communication_id AND t.group_id = te.group_id AND t.order_id = te.order_id AND t.status = 0 WHERE account_id = :account_id AND te.np_communication_id = :np_communication_id AND te.group_id = :group_id GROUP BY type");
                    $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
                    $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                    $query->bindParam(":group_id", $trophyGroup->trophyGroupId, PDO::PARAM_STR);
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
                    }
                    $database->beginTransaction();
                    $query = $database->prepare("INSERT INTO trophy_group_player (np_communication_id, group_id, account_id, bronze, silver, gold, platinum, progress) VALUES (:np_communication_id, :group_id, :account_id, :bronze, :silver, :gold, :platinum, :progress) ON DUPLICATE KEY UPDATE bronze=VALUES(bronze), silver=VALUES(silver), gold=VALUES(gold), platinum=VALUES(platinum), progress=VALUES(progress)");
                    $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                    $query->bindParam(":group_id", $trophyGroup->trophyGroupId, PDO::PARAM_STR);
                    $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
                    $query->bindParam(":bronze", $trophyTypes["bronze"], PDO::PARAM_INT);
                    $query->bindParam(":silver", $trophyTypes["silver"], PDO::PARAM_INT);
                    $query->bindParam(":gold", $trophyTypes["gold"], PDO::PARAM_INT);
                    $query->bindParam(":platinum", $trophyTypes["platinum"], PDO::PARAM_INT);
                    $query->bindParam(":progress", $progress, PDO::PARAM_INT);
                    $query->execute();
                    $database->commit();
                }

                // Recalculate trophies for trophy title
                $query = $database->prepare("SELECT SUM(bronze) AS bronze, SUM(silver) AS silver, SUM(gold) AS gold, SUM(platinum) AS platinum FROM trophy_group WHERE np_communication_id = :np_communication_id");
                $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                $query->execute();
                $trophies = $query->fetch();
                $database->beginTransaction();
                $query = $database->prepare("UPDATE trophy_title SET bronze = :bronze, silver = :silver, gold = :gold, platinum = :platinum WHERE np_communication_id = :np_communication_id");
                $query->bindParam(":bronze", $trophies["bronze"], PDO::PARAM_INT);
                $query->bindParam(":silver", $trophies["silver"], PDO::PARAM_INT);
                $query->bindParam(":gold", $trophies["gold"], PDO::PARAM_INT);
                $query->bindParam(":platinum", $trophies["platinum"], PDO::PARAM_INT);
                $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                $query->execute();
                $database->commit();

                // Recalculate trophies for trophy title for the player
                $maxScore = $trophies["bronze"]*15 + $trophies["silver"]*30 + $trophies["gold"]*90; // Platinum isn't counted for
                $query = $database->prepare("SELECT SUM(bronze) AS bronze, SUM(silver) AS silver, SUM(gold) AS gold, SUM(platinum) AS platinum FROM trophy_group_player tgp WHERE account_id = :account_id AND tgp.np_communication_id = :np_communication_id");
                $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
                $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
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
                }
                $database->beginTransaction();
                $query = $database->prepare("INSERT INTO trophy_title_player (np_communication_id, account_id, bronze, silver, gold, platinum, progress, last_updated_date) VALUES (:np_communication_id, :account_id, :bronze, :silver, :gold, :platinum, :progress, :last_updated_date) ON DUPLICATE KEY UPDATE bronze=VALUES(bronze), silver=VALUES(silver), gold=VALUES(gold), platinum=VALUES(platinum), progress=VALUES(progress), last_updated_date=VALUES(last_updated_date)");
                $query->bindParam(":np_communication_id", $game->npCommunicationId, PDO::PARAM_STR);
                $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
                $query->bindParam(":bronze", $trophyTypes["bronze"], PDO::PARAM_INT);
                $query->bindParam(":silver", $trophyTypes["silver"], PDO::PARAM_INT);
                $query->bindParam(":gold", $trophyTypes["gold"], PDO::PARAM_INT);
                $query->bindParam(":platinum", $trophyTypes["platinum"], PDO::PARAM_INT);
                $query->bindParam(":progress", $progress, PDO::PARAM_INT);
                $query->bindParam(":last_updated_date", $game->comparedUser->lastUpdateDate, PDO::PARAM_STR);
                $query->execute();
                $database->commit();
            }

            $offset += 128 - 8; // Subtract a little bit in-case the player have gotten new games while we are scanning
        }

        // Recalculate trophy count, level & progress for the player
        $query = $database->prepare("SELECT SUM(ttp.bronze) AS bronze, SUM(ttp.silver) AS silver, SUM(ttp.gold) AS gold, SUM(ttp.platinum) AS platinum FROM trophy_title_player ttp JOIN trophy_title tt USING (np_communication_id) WHERE tt.status = 0 AND ttp.account_id = :account_id");
        $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
        $query->execute();
        $trophies = $query->fetch();
        $points = $trophies["bronze"]*15 + $trophies["silver"]*30 + $trophies["gold"]*90 + $trophies["platinum"]*180;
        if ($points < 200) {
            $level = 1;
            $progress = floor($points / 200 * 100);
        } elseif ($points < 600) {
            $level = 2;
            $progress = floor(($points - 200) / 400 * 100);
        } elseif ($points < 1200) {
            $level = 3;
            $progress = floor(($points - 600) / 600 * 100);
        } elseif ($points < 2400) {
            $level = 4;
            $progress = floor(($points - 1200) / 1200 * 100);
        } elseif ($points < 4000) {
            $level = 5;
            $progress = floor(($points - 2400) / 1600 * 100);
        } elseif ($points < 16000) {
            $level = 6 + floor(($points - 4000) / 2000);
            $progress = floor(($points - 4000 - ($level - 6) * 2000) / 2000 * 100);
        } elseif ($points < 128000) {
            $level = 12 + floor(($points - 16000) / 8000);
            $progress = floor(($points - 16000 - ($level - 12) * 8000) / 8000 * 100);
        } else {
            $level = 26 + floor(($points - 128000) / 10000);
            $progress = floor(($points - 128000 - ($level - 26) * 10000) / 10000 * 100);
        }
        $database->beginTransaction();
        $query = $database->prepare("UPDATE player SET bronze = :bronze, silver = :silver, gold = :gold, platinum = :platinum, level = :level, progress = :progress, points = :points WHERE account_id = :account_id");
        $query->bindParam(":bronze", $trophies["bronze"], PDO::PARAM_INT);
        $query->bindParam(":silver", $trophies["silver"], PDO::PARAM_INT);
        $query->bindParam(":gold", $trophies["gold"], PDO::PARAM_INT);
        $query->bindParam(":platinum", $trophies["platinum"], PDO::PARAM_INT);
        $query->bindParam(":level", $level, PDO::PARAM_INT);
        $query->bindParam(":progress", $progress, PDO::PARAM_INT);
        $query->bindParam(":points", $points, PDO::PARAM_INT);
        $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
        $query->execute();
        $database->commit();
    }

    // Done with the user, update the date
    $database->beginTransaction();
    $query = $database->prepare("UPDATE player SET last_updated_date = NOW() WHERE account_id = :account_id");
    $query->bindParam(":account_id", $info->accountId, PDO::PARAM_INT);
    $query->execute();
    $database->commit();

    // Delete user from the queue
    $database->beginTransaction();
    $query = $database->prepare("DELETE FROM player_queue WHERE online_id = :online_id");
    $query->bindParam(":online_id", $info->onlineId, PDO::PARAM_STR);
    $query->execute();
    $database->commit();
}
