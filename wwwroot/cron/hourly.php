<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
// The lines above doesn't seem to do anything on my webhost. 504 after about 5 minutes anyway.
require_once("../init.php");

// Update ranks
$query = $database->prepare("SELECT account_id, points, country FROM player WHERE status = 0 ORDER BY points DESC, platinum DESC, gold DESC, silver DESC, bronze DESC");
$query->execute();
$rank = 1;
$row_count = 0;
$points = 0;
$countryRanks = array();
while ($player = $query->fetch()) {
    if (!isset($countryRanks[$player["country"]])) {
        $countryRanks[$player["country"]] = array($player["points"], 1, 0); // Points, Current Country Rank, Current Country Row
    }

    $row_count++;
    $countryRanks[$player["country"]][2] = $countryRanks[$player["country"]][2] + 1;

    // Only change rank if the points differs from the previous player
    if ($player["points"] !== $points) {
        $rank = $row_count;
    }
    if ($player["points"] !== $countryRanks[$player["country"]][0]) {
        $countryRanks[$player["country"]][0] = $player["points"];
        $countryRanks[$player["country"]][1] = $countryRanks[$player["country"]][2];
    }

    $points = $player["points"];

    $database->beginTransaction();
    $queryRank = $database->prepare("UPDATE player SET rank = :rank, rank_country = :rank_country WHERE account_id = :account_id");
    $queryRank->bindParam(":rank", $rank, PDO::PARAM_INT);
    $queryRank->bindParam(":rank_country", $countryRanks[$player["country"]][1], PDO::PARAM_INT);
    $queryRank->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
    $queryRank->execute();
    $database->commit();
}

// Recalculate owners for each game, but only for those ranked 100k or higher
$database->beginTransaction();
$query = $database->prepare("UPDATE trophy_title tt SET tt.owners = (SELECT COUNT(*) FROM trophy_title_player ttp JOIN player p USING (account_id) WHERE ttp.np_communication_id = tt.np_communication_id AND p.status = 0 AND p.rank <= 100000)");
$query->execute();
$database->commit();

// Update game difficulty
$database->beginTransaction();
$query = $database->prepare("UPDATE trophy_title tt SET tt.difficulty = ((SELECT COUNT(*) FROM trophy_title_player ttp JOIN player p USING (account_id) WHERE p.status = 0 AND ttp.progress = 100 AND ttp.np_communication_id = tt.np_communication_id) / tt.owners) * 100");
$query->execute();
$database->commit();

// Recalculate trophy rarity. THIS ONE IS SLOW! TODO: How to speed it up?
$database->beginTransaction();
$query = $database->prepare("UPDATE trophy t SET t.rarity_percent = (SELECT COUNT(*) FROM trophy_earned te JOIN player p USING (account_id) WHERE te.np_communication_id = t.np_communication_id AND te.group_id = t.group_id AND te.order_id = t.order_id AND p.status = 0 AND p.rank <= 100000)/(SELECT owners FROM trophy_title tt WHERE tt.np_communication_id = t.np_communication_id) * 100");
$query->execute();
$database->commit();

// Recalculate trophy rarity point
$database->beginTransaction();
$query = $database->prepare("UPDATE trophy SET rarity_point = FLOOR(1 / (GREATEST(rarity_percent, 0.01) / 100) - 1)");
$query->execute();
$database->commit();

// Recalculate rarity points and rarity levels for players. THIS ONE IS SLOW! TODO: How to speed it up?
$query = $database->prepare("SELECT te.account_id, SUM(t.rarity_point) AS rarity_points, SUM(t.rarity_percent > 50) common, SUM(t.rarity_percent <= 50 AND t.rarity_percent > 20) uncommon, SUM(t.rarity_percent <= 20 AND t.rarity_percent > 5) rare, SUM(t.rarity_percent <= 5 AND t.rarity_percent > 1) epic, SUM(t.rarity_percent <= 1) legendary FROM trophy t JOIN trophy_earned te USING (np_communication_id, group_id, order_id) JOIN trophy_title tt USING (np_communication_id) WHERE t.status = 0 AND tt.status = 0 GROUP BY te.account_id");
$query->execute();
while ($player = $query->fetch()) {
    $database->beginTransaction();
    $query2 = $database->prepare("UPDATE player p SET p.rarity_points = :rarity_points, p.common = :common, p.uncommon = :uncommon, p.rare = :rare, p.epic = :epic, p.legendary = :legendary WHERE p.account_id = :account_id");
    $query2->bindParam(":rarity_points", $player["rarity_points"], PDO::PARAM_INT);
    $query2->bindParam(":common", $player["common"], PDO::PARAM_INT);
    $query2->bindParam(":uncommon", $player["uncommon"], PDO::PARAM_INT);
    $query2->bindParam(":rare", $player["rare"], PDO::PARAM_INT);
    $query2->bindParam(":epic", $player["epic"], PDO::PARAM_INT);
    $query2->bindParam(":legendary", $player["legendary"], PDO::PARAM_INT);
    $query2->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
    $query2->execute();
    $database->commit();
}

// Update rarity ranks
$query = $database->prepare("SELECT account_id, rarity_points, country FROM player WHERE status = 0 ORDER BY rarity_points DESC, platinum DESC, gold DESC, silver DESC, bronze DESC");
$query->execute();
$rank = 1;
$row_count = 0;
$points = 0;
$countryRanks = array();
while ($player = $query->fetch()) {
    if (!isset($countryRanks[$player["country"]])) {
        $countryRanks[$player["country"]] = array($player["rarity_points"], 1, 0); // Points, Current Country Rank, Current Country Row
    }

    $row_count++;
    $countryRanks[$player["country"]][2] = $countryRanks[$player["country"]][2] + 1;

    // Only change rank if the points differs from the previous player
    if ($player["rarity_points"] !== $points) {
        $rank = $row_count;
    }
    if ($player["rarity_points"] !== $countryRanks[$player["country"]][0]) {
        $countryRanks[$player["country"]][0] = $player["rarity_points"];
        $countryRanks[$player["country"]][1] = $countryRanks[$player["country"]][2];
    }

    $points = $player["rarity_points"];

    $database->beginTransaction();
    $queryRank = $database->prepare("UPDATE player SET rarity_rank = :rarity_rank, rarity_rank_country = :rarity_rank_country WHERE account_id = :account_id");
    $queryRank->bindParam(":rarity_rank", $rank, PDO::PARAM_INT);
    $queryRank->bindParam(":rarity_rank_country", $countryRanks[$player["country"]][1], PDO::PARAM_INT);
    $queryRank->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
    $queryRank->execute();
    $database->commit();
}
