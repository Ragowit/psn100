<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("/home/psn100/public_html/init.php");

// Recalculate trophy rarity percent, point and name.
$gameQuery = $database->prepare("SELECT np_communication_id FROM trophy_title");
$gameQuery->execute();
do {
    if (!$deadlock) {
        $game = $gameQuery->fetch();

        if (!$game) {
            break;
        }
    }
    
    try {
        $query = $database->prepare("WITH
                rarity AS(
                SELECT
                    t.order_id,
                    COUNT(p.account_id) AS trophy_owners
                FROM trophy t
                    LEFT JOIN trophy_earned te ON te.np_communication_id = t.np_communication_id AND te.order_id = t.order_id AND te.earned = 1
                    LEFT JOIN player_extra p ON p.account_id = te.account_id AND p.rank <= 50000
                WHERE
                    t.np_communication_id = :np_communication_id
                GROUP BY order_id
                    ORDER BY NULL
            )
            UPDATE
                trophy t
            JOIN rarity r USING(order_id)
            JOIN trophy_title tt USING(np_communication_id)
            SET
                t.rarity_percent =(r.trophy_owners / 50000) * 100,
                t.rarity_point = IF(
                    t.status = 0 AND tt.status = 0,
                    IF(
                        (r.trophy_owners / 50000) * 100 = 0,
                        99999,
                        FLOOR(
                            1 /(r.trophy_owners / 50000) - 1
                        )
                    ),
                    0
                ),
                t.rarity_name = CASE
                    WHEN (t.status != 0 OR tt.status != 0) THEN 'NONE'
                    WHEN (r.trophy_owners / 50000) * 100 > 20 THEN 'COMMON'
                    WHEN (r.trophy_owners / 50000) * 100 <= 20 AND (r.trophy_owners / 50000) * 100 > 2 THEN 'UNCOMMON'
                    WHEN (r.trophy_owners / 50000) * 100 <= 2 AND (r.trophy_owners / 50000) * 100 > 0.2 THEN 'RARE'
                    WHEN (r.trophy_owners / 50000) * 100 <= 0.2 AND (r.trophy_owners / 50000) * 100 > 0.02 THEN 'EPIC'
                    WHEN (r.trophy_owners / 50000) * 100 <= 0.02 THEN 'LEGENDARY'
                    ELSE 'NONE'
                END
            WHERE
                t.np_communication_id = :np_communication_id");
        $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock || $game);

// Recalculate trophy title rarity points.
do {
    try {
        $query = $database->prepare(
            "WITH
                rarity AS(
                    SELECT np_communication_id, IFNULL(SUM(rarity_point), 0) AS points FROM trophy WHERE `status` = 0
                GROUP BY np_communication_id
                    ORDER BY NULL
            )
            UPDATE
                trophy_title tt
            JOIN rarity r USING(np_communication_id)
            SET
                tt.rarity_points = r.points
            WHERE
                tt.np_communication_id = r.np_communication_id");
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);
