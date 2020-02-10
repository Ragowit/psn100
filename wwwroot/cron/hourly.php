<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("/home/psn100/public_html/init.php");

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

// Recalculate recent players
$select = $database->prepare("SELECT np_communication_id, COUNT(*) AS count
    FROM trophy_title_player ttp
    JOIN player p USING (account_id)
    WHERE p.status = 0 AND p.rank <= 1000000 AND ttp.last_updated_date >= DATE(NOW()) - INTERVAL 7 DAY
    GROUP BY np_communication_id");
$select->execute();
while ($row = $select->fetch()) {
    $update = $database->prepare("UPDATE trophy_title SET recent_players = :recent_players WHERE np_communication_id = :np_communication_id");
    $update->bindParam(":recent_players", $row["count"], PDO::PARAM_INT);
    $update->bindParam(":np_communication_id", $row["np_communication_id"], PDO::PARAM_STR);
    $update->execute();
}
