<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("/home/psn100/public_html/init.php");

// Store temp ranking
do {
    try {
        $query = $database->prepare("TRUNCATE TABLE `player_extra`");
        $query->execute();

        $query = $database->prepare("INSERT INTO player_extra(`account_id`, `rank`)
            SELECT `account_id`, RANK() OVER (ORDER BY `points` DESC, `platinum` DESC, `gold` DESC, `silver` DESC) `ranking` FROM player WHERE `status` = 0");
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
            JOIN (SELECT `account_id`, `rank` FROM player_extra) p USING (account_id)
            WHERE
                p.rank <= 50000 AND ttp.last_updated_date >= DATE(NOW()) - INTERVAL 7 DAY
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

// Recalculate game owners
do {
    try {
        $query = $database->prepare("WITH
            game AS(
            SELECT
                np_communication_id,
                COUNT(*) AS count
            FROM
                trophy_title_player ttp
            JOIN (SELECT `account_id`, `rank` FROM player_extra) p USING (account_id)
            WHERE
                p.rank <= 50000
            GROUP BY
                np_communication_id)
            UPDATE
                trophy_title tt,
                game g
            SET
                tt.owners = g.count
            WHERE
                tt.np_communication_id = g.np_communication_id");
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);

// Recalculate game owners completed
do {
    try {
        $query = $database->prepare("WITH
            game AS(
            SELECT
                np_communication_id,
                COUNT(*) AS count
            FROM
                trophy_title_player ttp
            JOIN (SELECT `account_id`, `rank` FROM player_extra) p USING (account_id)
            WHERE
                ttp.progress = 100 AND p.rank <= 50000
            GROUP BY
                np_communication_id)
            UPDATE
                trophy_title tt,
                game g
            SET
                tt.owners_completed = g.count
            WHERE
                tt.np_communication_id = g.np_communication_id");
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);

// Recalculate game difficulty
do {
    try {
        $query = $database->prepare("UPDATE
                trophy_title
            SET
                difficulty = IF(owners = 0, 0, (owners_completed / owners) * 100)");
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);
