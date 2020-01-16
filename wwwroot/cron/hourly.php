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
$query = $database->prepare("UPDATE trophy_title tt SET tt.difficulty = ((
    SELECT COUNT(*) FROM trophy_title_player ttp
    JOIN player p USING (account_id)
    WHERE p.status = 0 AND p.rank <= 100000 AND ttp.progress = 100 AND ttp.np_communication_id = tt.np_communication_id
    ) / tt.owners
    ) * 100");
$query->execute();
