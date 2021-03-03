<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("/home/psn100/public_html/init.php");

// Recalculate owners for each game, but only for those ranked 100k or higher
do {
    try {
        $query = $database->prepare("WITH
                game AS(
                SELECT
                    np_communication_id,
                    COUNT(*) AS owners
                FROM
                    trophy_title_player ttp
                JOIN player p USING(account_id)
                WHERE
                    p.status = 0 AND p.rank <= 100000
                GROUP BY
                    np_communication_id
                ORDER BY NULL
            )
            UPDATE
                trophy_title tt,
                game g
            SET
                tt.owners = g.owners
            WHERE
                tt.np_communication_id = g.np_communication_id");
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
        $query = $database->prepare("WITH
                game_players AS(
                SELECT
                    tt.np_communication_id,
                    COUNT(p.account_id) AS completed_players
                FROM
                    trophy_title tt
                LEFT JOIN trophy_title_player ttp ON
                    ttp.np_communication_id = tt.np_communication_id AND ttp.progress = 100
                LEFT JOIN player p ON
                    p.account_id = ttp.account_id AND p.status = 0 AND p.rank <= 100000
                GROUP BY
                    np_communication_id
                ORDER BY NULL
            )
            UPDATE
                trophy_title tt,
                game_players gp
            SET
                tt.difficulty = IF(
                    tt.owners = 0,
                    0,
                    (
                        gp.completed_players / tt.owners
                    ) * 100
                )
            WHERE
                tt.np_communication_id = gp.np_communication_id");
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);

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
                p.status = 0 AND p.rank <= 1000000 AND ttp.last_updated_date >= DATE(NOW()) - INTERVAL 7 DAY
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
