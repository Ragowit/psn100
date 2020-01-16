<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
// The lines above doesn't seem to do anything on my webhost. 504 after about 5 minutes anyway.
require_once("../init.php");

// Recalculate trophy rarity & rarity point. SLOW!
$query = $database->prepare("SELECT np_communication_id, owners FROM trophy_title");
$query->execute();
$gameOwners = $query->fetchAll(PDO::FETCH_KEY_PAIR);
$query = $database->prepare("SELECT te.np_communication_id, te.group_id, te.order_id, COUNT(*) AS count
    FROM trophy_earned te
    JOIN player p USING (account_id)
    WHERE p.status = 0 AND p.rank <= 100000
    GROUP BY np_communication_id, group_id, order_id");
$query->execute();
while ($trophyOwners = $query->fetch()) {
    $rarityPercent = $trophyOwners["count"] / $gameOwners[$trophyOwners["np_communication_id"]] * 100;
    $rarityPoint = floor(1 / (max($rarityPercent, 0.01) / 100) - 1);

    $update = $database->prepare("UPDATE trophy SET rarity_percent = :rarity_percent, rarity_point = :rarity_point WHERE np_communication_id = :np_communication_id AND group_id = :group_id AND order_id = :order_id");
    $update->bindParam(":rarity_percent", $rarityPercent, PDO::PARAM_STR);
    $update->bindParam(":rarity_point", $rarityPoint, PDO::PARAM_INT);
    $update->bindParam(":np_communication_id", $trophyOwners["np_communication_id"], PDO::PARAM_STR);
    $update->bindParam(":group_id", $trophyOwners["group_id"], PDO::PARAM_STR);
    $update->bindParam(":order_id", $trophyOwners["order_id"], PDO::PARAM_INT);
    $update->execute();
}

// Recalculate rarity points for each game for each players. SLOW!
$query = $database->prepare("UPDATE trophy_title_player ttp, (
    SELECT account_id, np_communication_id, SUM(t.rarity_point) AS points FROM trophy t
    JOIN trophy_earned USING (np_communication_id, group_id, order_id)
    JOIN trophy_title tt USING (np_communication_id)
    WHERE t.status = 0 AND tt.status = 0
    GROUP BY account_id, np_communication_id) tsum
    SET ttp.rarity_points = tsum.points
    WHERE ttp.account_id = tsum.account_id AND ttp.np_communication_id = tsum.np_communication_id");
$query->execute();

// Recalculate rarity points for each player.
$query = $database->prepare("UPDATE player p, (SELECT account_id, SUM(rarity_points) AS rarity_points FROM trophy_title_player GROUP BY account_id) ttp SET p.rarity_points = ttp.rarity_points WHERE p.account_id = ttp.account_id");
$query->execute();

// Recalculate rarity levels for players. THIS ONE IS SLOW! TODO: How to speed it up?
$query = $database->prepare("UPDATE player p, (
    SELECT te.account_id,
    SUM(t.rarity_percent > 50) common,
    SUM(t.rarity_percent <= 50 AND t.rarity_percent > 20) uncommon,
    SUM(t.rarity_percent <= 20 AND t.rarity_percent > 5) rare,
    SUM(t.rarity_percent <= 5 AND t.rarity_percent > 1) epic,
    SUM(t.rarity_percent <= 1) legendary FROM trophy t
    JOIN trophy_earned te USING (np_communication_id, group_id, order_id)
    JOIN trophy_title tt USING (np_communication_id)
    WHERE t.status = 0 AND tt.status = 0 GROUP BY te.account_id
    ) x SET p.common = x.common, p.uncommon = x.uncommon, p.rare = x.rare, p.epic = x.epic, p.legendary = x.legendary WHERE p.account_id = x.account_id");
$query->execute();

// Update rarity ranks
$query = $database->prepare("SELECT account_id, rarity_points, country FROM player WHERE status = 0 ORDER BY rarity_points DESC, legendary DESC, epic DESC, rare DESC, uncommon DESC, common DESC");
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

    $queryRank = $database->prepare("UPDATE player SET rarity_rank = :rarity_rank, rarity_rank_country = :rarity_rank_country WHERE account_id = :account_id");
    $queryRank->bindParam(":rarity_rank", $rank, PDO::PARAM_INT);
    $queryRank->bindParam(":rarity_rank_country", $countryRanks[$player["country"]][1], PDO::PARAM_INT);
    $queryRank->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
    $queryRank->execute();
}
