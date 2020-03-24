<?php
require_once("../init.php");

if (ctype_digit(strval($_POST["parent"])) && ctype_digit(strval($_POST["child"]))) {
    $childId = $_POST["child"];
    $parentId = $_POST["parent"];

    // Grab the trophy data from child, and merge them with parent
    $database->beginTransaction();
    $query = $database->prepare("INSERT IGNORE INTO trophy_merge (child_np_communication_id, child_group_id, child_order_id, parent_np_communication_id, parent_group_id, parent_order_id)
        SELECT child.np_communication_id, child.group_id, child.order_id, parent.np_communication_id, parent.group_id, parent.order_id FROM trophy child
        INNER JOIN trophy parent USING (group_id, order_id)
        WHERE child.np_communication_id = (SELECT np_communication_id FROM trophy_title WHERE id = :child_game_id) AND parent.np_communication_id = (SELECT np_communication_id FROM trophy_title WHERE id = :parent_game_id)");
    $query->bindParam(":child_game_id", $childId, PDO::PARAM_INT);
    $query->bindParam(":parent_game_id", $parentId, PDO::PARAM_INT);
    $query->execute();
    $database->commit();

    // Set child game as merged (status = 2)
    $database->beginTransaction();
    $query = $database->prepare("UPDATE trophy_title SET status = 2 WHERE id = :game_id");
    $query->bindParam(":game_id", $childId, PDO::PARAM_INT);
    $query->execute();
    $database->commit();

    // Go through all players with child trophies
    $selectQuery = $database->prepare("SELECT * FROM trophy_earned
        WHERE np_communication_id = (SELECT np_communication_id FROM trophy_title WHERE id = :game_id)");
    $selectQuery->bindParam(":game_id", $childId, PDO::PARAM_INT);
    $selectQuery->execute();
    while ($child = $selectQuery->fetch()) {
        $query = $database->prepare("SELECT parent_np_communication_id, parent_group_id, parent_order_id FROM trophy_merge
            WHERE child_np_communication_id = :child_np_communication_id AND child_group_id = :child_group_id AND child_order_id = :child_order_id");
        $query->bindParam(":child_np_communication_id", $child["np_communication_id"], PDO::PARAM_STR);
        $query->bindParam(":child_group_id", $child["group_id"], PDO::PARAM_STR);
        $query->bindParam(":child_order_id", $child["order_id"], PDO::PARAM_INT);
        $query->execute();
        $parent = $query->fetch();

        $query = $database->prepare("INSERT INTO trophy_earned (np_communication_id, group_id, order_id, account_id, earned_date)
            VALUES (:np_communication_id, :group_id, :order_id, :account_id, :earned_date)
            ON DUPLICATE KEY UPDATE earned_date = IF(earned_date < VALUES(earned_date), earned_date, VALUES(earned_date))");
        $query->bindParam(":np_communication_id", $parent["parent_np_communication_id"], PDO::PARAM_STR);
        $query->bindParam(":group_id", $parent["parent_group_id"], PDO::PARAM_STR);
        $query->bindParam(":order_id", $parent["parent_order_id"], PDO::PARAM_INT);
        $query->bindParam(":account_id", $child["account_id"], PDO::PARAM_INT);
        $query->bindParam(":earned_date", $child["earned_date"], PDO::PARAM_STR);
        $query->execute();

        // trophy_group_player
        $query = $database->prepare("SELECT type, COUNT(*) AS count FROM trophy
            WHERE np_communication_id = :np_communication_id AND group_id = :group_id AND status = 0
            GROUP BY type");
        $query->bindParam(":np_communication_id", $parent["parent_np_communication_id"], PDO::PARAM_STR);
        $query->bindParam(":group_id", $parent["parent_group_id"], PDO::PARAM_STR);
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

        // Recalculate trophies for trophy group for the player
        $maxScore = $trophyTypes["bronze"]*15 + $trophyTypes["silver"]*30 + $trophyTypes["gold"]*90; // Platinum isn't counted for
        $query = $database->prepare("SELECT type, COUNT(type) AS count FROM trophy_earned te
            LEFT JOIN trophy t ON t.np_communication_id = te.np_communication_id AND t.group_id = te.group_id AND t.order_id = te.order_id AND t.status = 0
            WHERE account_id = :account_id AND te.np_communication_id = :np_communication_id AND te.group_id = :group_id
            GROUP BY type");
        $query->bindParam(":account_id", $child["account_id"], PDO::PARAM_INT);
        $query->bindParam(":np_communication_id", $parent["parent_np_communication_id"], PDO::PARAM_STR);
        $query->bindParam(":group_id", $parent["parent_group_id"], PDO::PARAM_STR);
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
        $query = $database->prepare("INSERT INTO trophy_group_player (np_communication_id, group_id, account_id, bronze, silver, gold, platinum, progress)
            VALUES (:np_communication_id, :group_id, :account_id, :bronze, :silver, :gold, :platinum, :progress)
            ON DUPLICATE KEY UPDATE bronze=VALUES(bronze), silver=VALUES(silver), gold=VALUES(gold), platinum=VALUES(platinum), progress=VALUES(progress)");
        $query->bindParam(":np_communication_id", $parent["parent_np_communication_id"], PDO::PARAM_STR);
        $query->bindParam(":group_id", $parent["parent_group_id"], PDO::PARAM_STR);
        $query->bindParam(":account_id", $child["account_id"], PDO::PARAM_INT);
        $query->bindParam(":bronze", $trophyTypes["bronze"], PDO::PARAM_INT);
        $query->bindParam(":silver", $trophyTypes["silver"], PDO::PARAM_INT);
        $query->bindParam(":gold", $trophyTypes["gold"], PDO::PARAM_INT);
        $query->bindParam(":platinum", $trophyTypes["platinum"], PDO::PARAM_INT);
        $query->bindParam(":progress", $progress, PDO::PARAM_INT);
        $query->execute();
        
        // trophy_title_player
        $query = $database->prepare("SELECT SUM(bronze) AS bronze, SUM(silver) AS silver, SUM(gold) AS gold, SUM(platinum) AS platinum FROM trophy_group
            WHERE np_communication_id = :np_communication_id");
        $query->bindParam(":np_communication_id", $parent["parent_np_communication_id"], PDO::PARAM_STR);
        $query->execute();
        $trophies = $query->fetch();

        // Recalculate trophies for trophy title for the player(s)
        $maxScore = $trophies["bronze"]*15 + $trophies["silver"]*30 + $trophies["gold"]*90; // Platinum isn't counted for
        
        $query = $database->prepare("SELECT SUM(bronze) AS bronze, SUM(silver) AS silver, SUM(gold) AS gold, SUM(platinum) AS platinum
            FROM trophy_group_player
            WHERE account_id = :account_id AND np_communication_id = :np_communication_id");
        $query->bindParam(":account_id", $child["account_id"], PDO::PARAM_INT);
        $query->bindParam(":np_communication_id", $parent["parent_np_communication_id"], PDO::PARAM_STR);
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
        
        $query = $database->prepare("SELECT last_updated_date FROM trophy_title_player
            WHERE np_communication_id = :np_communication_id AND account_id = :account_id");
        $query->bindParam(":np_communication_id", $child["np_communication_id"], PDO::PARAM_STR);
        $query->bindParam(":account_id", $child["account_id"], PDO::PARAM_STR);
        $query->execute();
        $dtAsTextForInsert = $query->fetchColumn();

        $query = $database->prepare("INSERT INTO trophy_title_player (np_communication_id, account_id, bronze, silver, gold, platinum, progress, last_updated_date)
            VALUES (:np_communication_id, :account_id, :bronze, :silver, :gold, :platinum, :progress, :last_updated_date)
            ON DUPLICATE KEY
            UPDATE bronze=VALUES(bronze), silver=VALUES(silver), gold=VALUES(gold), platinum=VALUES(platinum), progress=VALUES(progress),
                last_updated_date = IF(last_updated_date < VALUES(last_updated_date), VALUES(last_updated_date), last_updated_date)");
        $query->bindParam(":np_communication_id", $parent["parent_np_communication_id"], PDO::PARAM_STR);
        $query->bindParam(":account_id", $child["account_id"], PDO::PARAM_INT);
        $query->bindParam(":bronze", $trophyTypes["bronze"], PDO::PARAM_INT);
        $query->bindParam(":silver", $trophyTypes["silver"], PDO::PARAM_INT);
        $query->bindParam(":gold", $trophyTypes["gold"], PDO::PARAM_INT);
        $query->bindParam(":platinum", $trophyTypes["platinum"], PDO::PARAM_INT);
        $query->bindParam(":progress", $progress, PDO::PARAM_INT);
        $query->bindParam(":last_updated_date", $dtAsTextForInsert, PDO::PARAM_STR);
        $query->execute();
    }

    // Add all affected players to the queue to recalculate trophy count, level and level progress
    $players = $database->prepare("SELECT online_id FROM player p WHERE EXISTS (SELECT 1 FROM trophy_title_player ttp WHERE ttp.np_communication_id = (SELECT np_communication_id FROM trophy_title WHERE id = :game_id) AND ttp.account_id = p.account_id)");
    $players->bindParam(":game_id", $childId, PDO::PARAM_INT);
    $players->execute();
    while ($player = $players->fetch()) {
        $query = $database->prepare("INSERT INTO player_queue (online_id) VALUES (:online_id) ON DUPLICATE KEY UPDATE request_time=NOW()");
        $query->bindParam(":online_id", $player["online_id"], PDO::PARAM_STR);
        $query->execute();
    }

    $message = "The games have been merged.";
} elseif (ctype_digit(strval($_POST["child"]))) {
    // Clone the game. This will be the master game for the others.
    $childId = $_POST["child"];

    $query = $database->prepare("SELECT np_communication_id FROM trophy_title WHERE id = :id");
    $query->bindParam(":id", $childId, PDO::PARAM_INT);
    $query->execute();
    $childNpCommunicationId = $query->fetchColumn();

    $query = $database->prepare("SELECT Auto_increment FROM information_schema.tables WHERE table_name = 'trophy_title'");
    $query->execute();
    $gameId = $query->fetchColumn();
    $cloneNpCommunicationId = "MERGE_". str_pad($gameId, 6, '0', STR_PAD_LEFT);

    $query = $database->prepare("INSERT INTO trophy_title (np_communication_id, name, detail, icon_url, platform, bronze, silver, gold, platinum, message)
        SELECT :np_communication_id, name, detail, icon_url, platform, bronze, silver, gold, platinum, message FROM trophy_title WHERE id = :id");
    $query->bindParam(":np_communication_id", $cloneNpCommunicationId, PDO::PARAM_STR);
    $query->bindParam(":id", $childId, PDO::PARAM_INT);
    $query->execute();

    $query = $database->prepare("INSERT INTO trophy_group (np_communication_id, group_id, name, detail, icon_url, bronze, silver, gold, platinum)
        SELECT :np_communication_id, group_id, name, detail, icon_url, bronze, silver, gold, platinum FROM trophy_group WHERE np_communication_id = :child_np_communication_id");
    $query->bindParam(":np_communication_id", $cloneNpCommunicationId, PDO::PARAM_STR);
    $query->bindParam(":child_np_communication_id", $childNpCommunicationId, PDO::PARAM_STR);
    $query->execute();

    $query = $database->prepare("INSERT INTO trophy (np_communication_id, group_id, order_id, hidden, type, name, detail, icon_url, rare, earned_rate, status)
        SELECT :np_communication_id, group_id, order_id, hidden, type, name, detail, icon_url, 0, 0, status FROM trophy WHERE np_communication_id = :child_np_communication_id");
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
        <form method="post">
            Child ID:<br>
            <input type="number" name="child"><br>
            Parent ID:<br>
            <input type="number" name="parent"><br><br>
            <input type="submit" value="Submit">
        </form>

        <?= $message; ?>
    </body>
</html>
