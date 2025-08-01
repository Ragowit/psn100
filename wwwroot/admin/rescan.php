<?php
require_once("../vendor/autoload.php");
require_once("../init.php");

use Tustin\PlayStation\Client;

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
    $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
    $query->bindValue(":group_id", $groupId, PDO::PARAM_STR);
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
    $query->bindValue(":bronze", $trophyTypes["bronze"], PDO::PARAM_INT);
    $query->bindValue(":silver", $trophyTypes["silver"], PDO::PARAM_INT);
    $query->bindValue(":gold", $trophyTypes["gold"], PDO::PARAM_INT);
    $query->bindValue(":platinum", $trophyTypes["platinum"], PDO::PARAM_INT);
    $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
    $query->bindValue(":group_id", $groupId, PDO::PARAM_STR);
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
    $query->bindValue(":account_id", $accountId, PDO::PARAM_INT);
    $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
    $query->bindValue(":group_id", $groupId, PDO::PARAM_STR);
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

function RecalculateTrophyTitle($npCommunicationId, $lastUpdateDate, $newTrophies, $accountId, $merge) {
    $database = new Database();
    $titleHavePlatinum = false;

    $query = $database->prepare("SELECT Sum(bronze)   AS bronze,
               Sum(silver)   AS silver,
               Sum(gold)     AS gold,
               Sum(platinum) AS platinum
        FROM   trophy_group
        WHERE  np_communication_id = :np_communication_id ");
    $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
    $query->execute();
    $trophies = $query->fetch();
    $query = $database->prepare("UPDATE trophy_title
        SET    bronze = :bronze,
               silver = :silver,
               gold = :gold,
               platinum = :platinum
        WHERE  np_communication_id = :np_communication_id ");
    $query->bindValue(":bronze", $trophies["bronze"], PDO::PARAM_INT);
    $query->bindValue(":silver", $trophies["silver"], PDO::PARAM_INT);
    $query->bindValue(":gold", $trophies["gold"], PDO::PARAM_INT);
    $query->bindValue(":platinum", $trophies["platinum"], PDO::PARAM_INT);
    $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
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
        $select->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
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
            $query->bindValue(":account_id", $row["account_id"], PDO::PARAM_INT);
            $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
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
            $query->bindValue(":progress", $progress, PDO::PARAM_INT);
            $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
            $query->bindValue(":account_id", $row["account_id"], PDO::PARAM_INT);
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
    $query->bindValue(":account_id", $accountId, PDO::PARAM_INT);
    $query->bindValue(":np_communication_id", $npCommunicationId, PDO::PARAM_STR);
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

if (isset($_POST["game"])) {
    // Grab the np_communication_id
    $gameId = $_POST["game"];
    $query = $database->prepare("SELECT np_communication_id FROM trophy_title WHERE id = :id");
    $query->bindValue(":id", $gameId, PDO::PARAM_INT);
    $query->execute();
    $gameNpCommunicationId = $query->fetchColumn();

    if (str_starts_with($gameNpCommunicationId, "NPWR")) {
        // Login with a token
        $loggedIn = false;
        while (!$loggedIn) {
            $query = $database->prepare("SELECT
                    id,
                    npsso
                FROM
                    setting
                ORDER BY
                    id");
            $query->execute();
            $workers = $query->fetchAll();

            foreach ($workers as $worker) {
                try {
                    $client = new Client();
                    $npsso = $worker["npsso"];
                    $client->loginWithNpsso($npsso);

                    $loggedIn = true;
                } catch (TypeError $e) {
                    // Something odd, try next worker
                } catch (Exception $e) {
                    $message = "Can't login with worker ". $worker["id"];
                    $query = $database->prepare("INSERT INTO log(message)
                        VALUES(:message)");
                    $query->bindValue(":message", $message, PDO::PARAM_STR);
                    $query->execute();
                }

                if ($loggedIn) {
                    break 2;
                }
            }

            if (!$loggedIn) {
                // Wait 5 minutes to not hammer login
                sleep(60 * 5);
            }
        }

        // Grab the latest player (that's not private) with this game
        $query = $database->prepare("SELECT
                account_id
            FROM
                trophy_title_player ttp
            JOIN player p USING(account_id)
            WHERE
                ttp.np_communication_id = :np_communication_id AND p.status != 3
            ORDER BY
                ttp.last_updated_date
            DESC");
        $query->bindValue(":np_communication_id", $gameNpCommunicationId, PDO::PARAM_STR);
        $query->execute();
        while ($accountId = $query->fetchColumn()) {
            $user = $client->users()->find($accountId);

            try {
                $level = $user->trophySummary()->level();
                break; // Everything is ok!
            } catch (TypeError $e) {
                // Something odd, try next player
            } catch (Exception $e) {
                // Something odd, player probably private again, try next player
            }
        }

        foreach ($user->trophyTitles() as $trophyTitle) {
            if ($trophyTitle->npCommunicationId() != $gameNpCommunicationId) {
                continue;
            }

            // Update the title data
            // Get the title icon url we want to save
            $trophyTitleIconUrl = $trophyTitle->iconUrl();
            $trophyTitleIconFilename = md5_file($trophyTitleIconUrl) . strtolower(substr($trophyTitleIconUrl, strrpos($trophyTitleIconUrl, ".")));
            $platforms = "";
            foreach ($trophyTitle->platform() as $platform) {
                $platforms .= $platform->value .",";
            }
            $platforms = rtrim($platforms, ",");
            // Download the title icon if we don't have it
            if (!file_exists("/home/psn100/public_html/img/title/". $trophyTitleIconFilename)) {
                file_put_contents("/home/psn100/public_html/img/title/". $trophyTitleIconFilename, fopen($trophyTitleIconUrl, "r"));
            }

            $query = $database->prepare("UPDATE
                    trophy_title
                SET
                    detail = :detail,
                    icon_url = :icon_url,
                    platform = :platform
                WHERE
                    np_communication_id = :np_communication_id");
            $query->bindValue(":detail", $trophyTitle->detail(), PDO::PARAM_STR);
            $query->bindValue(":icon_url", $trophyTitleIconFilename, PDO::PARAM_STR);
            $query->bindValue(":platform", $platforms, PDO::PARAM_STR);
            $query->bindValue(":np_communication_id", $trophyTitle->npCommunicationId(), PDO::PARAM_STR);
            // Don't insert platinum/gold/silver/bronze here since our site recalculate this.
            $query->execute();

            // Update the group data
            foreach ($client->trophies($trophyTitle->npCommunicationId(), $trophyTitle->serviceName())->trophyGroups() as $trophyGroup) {
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
                $query->bindValue(":np_communication_id", $trophyTitle->npCommunicationId(), PDO::PARAM_STR);
                $query->bindValue(":group_id", $trophyGroup->id(), PDO::PARAM_STR);
                $query->bindValue(":name", $trophyGroup->name(), PDO::PARAM_STR);
                $query->bindValue(":detail", $trophyGroup->detail(), PDO::PARAM_STR);
                $query->bindValue(":icon_url", $trophyGroupIconFilename, PDO::PARAM_STR);
                // Don't insert platinum/gold/silver/bronze here since our site recalculate this.
                $query->execute();

                // Update trophy data within the current group
                foreach ($trophyGroup->trophies() as $trophy) {
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
                    $query->bindValue(":np_communication_id", $trophyTitle->npCommunicationId(), PDO::PARAM_STR);
                    $query->bindValue(":group_id", $trophyGroup->id(), PDO::PARAM_STR);
                    $query->bindValue(":order_id", $trophy->id(), PDO::PARAM_INT);
                    $query->bindValue(":hidden", $trophy->hidden(), PDO::PARAM_INT);
                    $trophyTypeEnumValue = $trophy->type()->value;
                    $query->bindValue(":type", $trophyTypeEnumValue, PDO::PARAM_STR);
                    $query->bindValue(":name", $trophy->name(), PDO::PARAM_STR);
                    $query->bindValue(":detail", $trophy->detail(), PDO::PARAM_STR);
                    $query->bindValue(":icon_url", $trophyIconFilename, PDO::PARAM_STR);
                    if ($trophy->progressTargetValue() === '') {
                        $progressTargetValue = null;
                    } else {
                        $progressTargetValue = $trophy->progressTargetValue();
                    }
                    $query->bindValue(":progress_target_value", $progressTargetValue, PDO::PARAM_INT);
                    if ($trophy->rewardName() === '') {
                        $rewardName = null;
                    } else {
                        $rewardName = $trophy->rewardName();
                    }
                    $query->bindValue(":reward_name", $rewardName, PDO::PARAM_STR);
                    $query->bindValue(":reward_image_url", $rewardImageFilename, PDO::PARAM_STR);
                    // Don't insert platinum/gold/silver/bronze here since our site recalculate this.
                    $query->execute();
                }

                // Recalculate trophies for trophy group and player
                RecalculateTrophyGroup($trophyTitle->npCommunicationId(), $trophyGroup->id(), $user->accountId());
            }

            // Recalculate trophies for trophy title and player
            RecalculateTrophyTitle($trophyTitle->npCommunicationId(), $trophyTitle->lastUpdatedDateTime(), true, $user->accountId(), false);

            // Game Merge stuff
            $query = $database->prepare("SELECT DISTINCT parent_np_communication_id, 
                    parent_group_id 
                FROM   trophy_merge 
                WHERE  child_np_communication_id = :child_np_communication_id ");
            $query->bindValue(":child_np_communication_id", $trophyTitle->npCommunicationId(), PDO::PARAM_STR);
            $query->execute();
            while ($row = $query->fetch()) {
                RecalculateTrophyGroup($row["parent_np_communication_id"], $row["parent_group_id"], $user->accountId());
                RecalculateTrophyTitle($row["parent_np_communication_id"], $trophyTitle->lastUpdatedDateTime(), false, $user->accountId(), true);
            }

            // Successfully went through title, groups and trophies. Set the version.
            $query = $database->prepare("UPDATE
                    trophy_title
                SET
                    set_version = :set_version
                WHERE
                    np_communication_id = :np_communication_id");
            $query->bindValue(":set_version", $trophyTitle->trophySetVersion(), PDO::PARAM_STR);
            $query->bindValue(":np_communication_id", $trophyTitle->npCommunicationId(), PDO::PARAM_STR);
            $query->execute();

            $query = $database->prepare("INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES ('GAME_RESCAN', :param_1)");
            $query->bindValue(":param_1", $gameId, PDO::PARAM_INT);
            $query->execute();

            $success = "<p>Game ". $gameId ." have been rescanned.</p>";

            // We are done
            break;
        }
    } else {
        $success = "Can only rescan original game entries.";
    }
}

?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <title>Admin ~ Rescan Game</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <form method="post" autocomplete="off">
                Game:<br>
                <input type="text" name="game"><br><br>
                <input type="submit" value="Submit">
            </form>

            <?php
            if (isset($success)) {
                echo $success;
            }
            ?>
        </div>
    </body>
</html>
