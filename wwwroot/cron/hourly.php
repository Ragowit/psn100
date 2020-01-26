<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("/home/psn100/public_html/init.php");

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

    $queryRank = $database->prepare("UPDATE player SET rank = :rank, rank_country = :rank_country WHERE account_id = :account_id");
    $queryRank->bindParam(":rank", $rank, PDO::PARAM_INT);
    $queryRank->bindParam(":rank_country", $countryRanks[$player["country"]][1], PDO::PARAM_INT);
    $queryRank->bindParam(":account_id", $player["account_id"], PDO::PARAM_INT);
    $queryRank->execute();
}

// Recalculate owners for each game, but only for those ranked 100k or higher
$query = $database->prepare("UPDATE trophy_title tt SET tt.owners = (
    SELECT COUNT(*) FROM trophy_title_player ttp
    JOIN player p USING (account_id)
    WHERE ttp.np_communication_id = tt.np_communication_id AND p.status = 0 AND p.rank <= 100000)");
$query->execute();

// Update game difficulty
$query = $database->prepare("UPDATE trophy_title tt SET tt.difficulty = CASE WHEN tt.owners = 0 THEN 0 ELSE ((
    SELECT COUNT(*) FROM trophy_title_player ttp
    JOIN player p USING (account_id)
    WHERE p.status = 0 AND p.rank <= 100000 AND ttp.progress = 100 AND ttp.np_communication_id = tt.np_communication_id
    ) / tt.owners
) * 100 END");
$query->execute();

// Recalculate trophy rarity. THIS ONE IS SLOW! TODO: How to speed it up?
$query = $database->prepare("UPDATE trophy t SET t.rarity_percent = CASE
    WHEN (SELECT owners FROM trophy_title tt WHERE tt.np_communication_id = t.np_communication_id) = 0 THEN 0
    WHEN (SELECT owners FROM trophy_title tt WHERE tt.np_communication_id = t.np_communication_id) <= (
        SELECT COUNT(*) FROM trophy_earned te
        JOIN player p USING (account_id)
        WHERE te.np_communication_id = t.np_communication_id AND te.group_id = t.group_id AND te.order_id = t.order_id AND p.status = 0 AND p.rank <= 100000
        ) THEN 100
    ELSE
        ((SELECT COUNT(*) FROM trophy_earned te
        JOIN player p USING (account_id)
        WHERE te.np_communication_id = t.np_communication_id AND te.group_id = t.group_id AND te.order_id = t.order_id AND p.status = 0 AND p.rank <= 100000
        ) / (
            SELECT owners FROM trophy_title tt WHERE tt.np_communication_id = t.np_communication_id
        ) * 100)
    END");
$query->execute();

// Recalculate trophy rarity point
$query = $database->prepare("UPDATE trophy SET rarity_point = FLOOR(1 / (GREATEST(rarity_percent, 0.01) / 100) - 1)");
$query->execute();

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

// Recalculate recent players
$query = $database->prepare("UPDATE trophy_title tt, (
    SELECT np_communication_id, COUNT(*) AS count FROM trophy_title_player ttp
    JOIN player p USING (account_id)
    JOIN trophy_title tt USING (np_communication_id)
    WHERE p.status = 0 AND p.rank <= 1000000 AND ttp.last_updated_date >= DATE(NOW()) - INTERVAL 7 DAY
    GROUP BY np_communication_id) x
    SET tt.recent_players = x.count
    WHERE tt.np_communication_id = x.np_communication_id");
$query->execute();
