<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
// The lines above doesn't seem to do anything on my webhost. 504 after about 5 minutes anyway.
require_once("../init.php");

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
