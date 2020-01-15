<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
// The lines above doesn't seem to do anything on my webhost. 504 after about 5 minutes anyway.
require_once("../init.php");

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
