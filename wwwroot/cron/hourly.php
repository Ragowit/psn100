<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("/home/psn100/public_html/init.php");

// Recalculate recent players, owners, completed and difficulty
do {
    try {
        $query = $database->prepare("
            WITH game AS (
                SELECT
                    ttp.np_communication_id,
                    COUNT(*) AS owners,
                    COUNT(CASE WHEN ttp.progress = 100 THEN 1 END) AS owners_completed,
                    COUNT(CASE WHEN ttp.last_updated_date >= CURDATE() - INTERVAL 7 DAY THEN 1 END) AS recent_players
                FROM
                    trophy_title_player ttp
                    JOIN player_ranking pr ON pr.account_id = ttp.account_id AND pr.ranking <= 10000
                GROUP BY
                    ttp.np_communication_id
            )
            UPDATE trophy_title tt
            JOIN game g ON tt.np_communication_id = g.np_communication_id
            SET
                tt.owners = g.owners,
                tt.owners_completed = g.owners_completed,
                tt.recent_players = g.recent_players,
                tt.difficulty = IF(g.owners = 0, 0, (g.owners_completed / g.owners) * 100)
        ");
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);
