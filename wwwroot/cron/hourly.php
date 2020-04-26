<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("/home/psn100/public_html/init.php");

// Recalculate owners for each game, but only for those ranked 100k or higher
do {
    try {
        $query = $database->prepare("UPDATE trophy_title tt
            SET    tt.owners = (SELECT Count(*)
                                FROM   trophy_title_player ttp
                                       JOIN player p USING(account_id)
                                WHERE  ttp.np_communication_id = tt.np_communication_id
                                       AND p.status = 0
                                       AND p.rank <= 100000) ");
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);

// Update game difficulty
do {
    try {
        $query = $database->prepare("UPDATE trophy_title tt
            SET    tt.difficulty = CASE
                                     WHEN tt.owners = 0 THEN 0
                                     ELSE( (SELECT Count(*)
                                            FROM   trophy_title_player ttp
                                                   JOIN player p USING(account_id)
                                            WHERE  p.status = 0
                                                   AND p.rank <= 100000
                                                   AND ttp.progress = 100
                                                   AND ttp.np_communication_id =
                                                       tt.np_communication_id) /
                                           tt.owners ) * 100
                                   end ");
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);

// Recalculate recent players
$select = $database->prepare("SELECT np_communication_id,
           Count(*) AS recent_players
    FROM   trophy_title_player ttp
           JOIN player p USING(account_id)
    WHERE  p.status = 0
           AND p.rank <= 1000000
           AND ttp.last_updated_date >= Date(Now()) - INTERVAL 7 day
    GROUP  BY np_communication_id ");
$select->execute();
while ($row = $select->fetch()) {
    $update = $database->prepare("UPDATE trophy_title 
        SET    recent_players = :recent_players
        WHERE  np_communication_id = :np_communication_id ");
    $update->bindParam(":recent_players", $row["recent_players"], PDO::PARAM_INT);
    $update->bindParam(":np_communication_id", $row["np_communication_id"], PDO::PARAM_STR);
    $update->execute();
}
