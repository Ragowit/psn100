<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("/home/psn100/public_html/init.php");

// Recalculate recent players, owners, completed and difficulty
do {
    try {
        $query = $database->prepare("WITH
            player_ranking AS(
            SELECT
                `account_id`,
                RANK() OVER (ORDER BY `points` DESC, `platinum` DESC, `gold` DESC, `silver` DESC) `rank`
            FROM
                player
            WHERE
                `status` = 0
            ),
            game AS(
            SELECT
                np_communication_id,
                COUNT(p1.account_id) AS owners,
                COUNT(p2.account_id) AS owners_completed,
                COUNT(p3.account_id) AS recent_players
            FROM
                trophy_title_player ttp
            LEFT JOIN player_ranking p1 ON p1.account_id = ttp.account_id AND p1.rank <= 10000
            LEFT JOIN player_ranking p2 ON p2.account_id = ttp.account_id AND p2.rank <= 10000 AND ttp.progress = 100
            LEFT JOIN player_ranking p3 ON p3.account_id = ttp.account_id AND p3.rank <= 10000 AND ttp.last_updated_date >= DATE(NOW()) - INTERVAL 7 DAY
            GROUP BY
                np_communication_id)
            UPDATE
                trophy_title tt,
                game g
            SET
                tt.owners = g.owners,
                tt.owners_completed = g.owners_completed,
                tt.recent_players = g.recent_players,
                tt.difficulty = IF(g.owners = 0, 0, (g.owners_completed / g.owners) * 100)
            WHERE
                tt.np_communication_id = g.np_communication_id");
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);
