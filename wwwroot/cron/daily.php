<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("/home/psn100/public_html/init.php");

$deadlock = false;

// Recalculate trophy rarity percent, point and name.
$query = $database->prepare("SELECT np_communication_id FROM trophy_title ORDER BY id DESC");
$query->execute();
$games = $query->fetchAll();
foreach ($games as $game) {
    do {
        try {
            $query = $database->prepare("
                WITH rarity AS (
                    SELECT
                        t.order_id,
                        COUNT(p.account_id) AS trophy_owners,
                        (COUNT(p.account_id) / 10000.0) * 100 AS rarity_percent
                    FROM trophy t
                    LEFT JOIN trophy_earned te
                        ON te.np_communication_id = t.np_communication_id AND te.order_id = t.order_id AND te.earned = 1
                    LEFT JOIN player_ranking p
                        ON p.account_id = te.account_id AND p.ranking <= 10000
                    WHERE t.np_communication_id = :np_communication_id
                    GROUP BY order_id
                    ORDER BY NULL
                )
                UPDATE trophy t
                JOIN rarity r USING(order_id)
                JOIN trophy_title tt USING(np_communication_id)
                SET
                    t.rarity_percent = r.rarity_percent,
                    t.rarity_point = IF(
                        t.status = 0 AND tt.status = 0,
                        IF(r.rarity_percent = 0, 99999, FLOOR(1 / (r.rarity_percent / 100) - 1)),
                        0
                    ),
                    t.rarity_name = CASE
                        WHEN t.status != 0 OR tt.status != 0 THEN 'NONE'
                        WHEN r.rarity_percent > 10 THEN 'COMMON'
                        WHEN r.rarity_percent > 2 THEN 'UNCOMMON'
                        WHEN r.rarity_percent > 0.2 THEN 'RARE'
                        WHEN r.rarity_percent > 0.02 THEN 'EPIC'
                        ELSE 'LEGENDARY'
                    END
                WHERE t.np_communication_id = :np_communication_id
            ");
            $query->bindValue(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
            $query->execute();

            $deadlock = false;
        } catch (Exception $e) {
            sleep(3);
            $deadlock = true;
        }
    } while ($deadlock);
}

// Recalculate trophy title rarity points.
do {
    try {
        $query = $database->prepare("
            WITH rarity AS (
                SELECT
                    np_communication_id,
                    IFNULL(SUM(rarity_point), 0) AS rarity_sum
                FROM trophy
                WHERE `status` = 0
                GROUP BY np_communication_id
            )
            UPDATE trophy_title tt
            JOIN rarity r USING(np_communication_id)
            SET tt.rarity_points = r.rarity_sum
        ");
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);
