<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("/home/psn100/public_html/init.php");

// Recalculate recent players
do {
    try {
        $query = $database->prepare("WITH
            recent AS(
            SELECT
                np_communication_id,
                COUNT(*) AS recent_players
            FROM
                trophy_title_player ttp
            JOIN player p USING(account_id)
            WHERE
                p.status = 0 AND p.rank <= 50000 AND ttp.last_updated_date >= DATE(NOW()) - INTERVAL 7 DAY
            GROUP BY
                np_communication_id)
            UPDATE
                trophy_title tt,
                recent r
            SET
                tt.recent_players = r.recent_players
            WHERE
                tt.np_communication_id = r.np_communication_id");
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);
