<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("/home/psn100/public_html/init.php");

// Update ranks
do {
    try {
        $query = $database->prepare("WITH
                ranking AS(
                SELECT
                    p.account_id,
                    RANK() OVER(
                    ORDER BY
                        p.points
                    DESC
                        ,
                        p.platinum
                    DESC
                        ,
                        p.gold
                    DESC
                        ,
                        p.silver
                    DESC
                ) ranking
            FROM
                player p
            WHERE
                p.status = 0)
                UPDATE
                    player p,
                    ranking r
                SET
                    p.rank = r.ranking
                WHERE
                    p.account_id = r.account_id");
        $query->execute();
        
        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);

$countryQuery = $database->prepare("SELECT DISTINCT
        (country)
    FROM
        player
    ORDER BY NULL");
$countryQuery->execute();
while ($country = $countryQuery->fetch()) {
    do {
        try {
            $query = $database->prepare("WITH
                    ranking AS(
                    SELECT
                        p.account_id,
                        RANK() OVER(
                        ORDER BY
                            p.points
                        DESC
                            ,
                            p.platinum
                        DESC
                            ,
                            p.gold
                        DESC
                            ,
                            p.silver
                        DESC
                    ) ranking
                    FROM
                        player p
                    WHERE
                        p.status = 0 AND p.country = :country)
                UPDATE
                    player p,
                    ranking r
                SET
                    p.rank_country = r.ranking
                WHERE
                    p.account_id = r.account_id");
            $query->bindParam(":country", $country["country"], PDO::PARAM_STR);
            $query->execute();

            $deadlock = false;
        } catch (Exception $e) {
            sleep(3);
            $deadlock = true;
        }
    } while ($deadlock);
}

// Recalculate trophy rarity percent and rarity name.
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
                    COUNT(p.account_id) AS trophy_owners,
                    order_id
                FROM
                    trophy_earned te
                LEFT JOIN player p ON p.account_id = te.account_id AND p.status = 0 AND p.rank <= 50000
                JOIN trophy t USING(np_communication_id, order_id)
                WHERE
                    te.np_communication_id = :np_communication_id AND te.earned = 1
                GROUP BY te.order_id
                    ORDER BY NULL
            )
            UPDATE
                trophy t,
                rarity
            SET
                t.rarity_percent =(rarity.trophy_owners / 50000) * 100,
                t.rarity_name = CASE
                    WHEN (rarity.trophy_owners / 50000) * 100 > 20 THEN 'COMMON'
                    WHEN (rarity.trophy_owners / 50000) * 100 <= 20 AND (rarity.trophy_owners / 50000) * 100 > 2 THEN 'UNCOMMON'
                    WHEN (rarity.trophy_owners / 50000) * 100 <= 2 AND (rarity.trophy_owners / 50000) * 100 > 0.2 THEN 'RARE'
                    WHEN (rarity.trophy_owners / 50000) * 100 <= 0.2 AND (rarity.trophy_owners / 50000) * 100 > 0.02 THEN 'EPIC'
                    WHEN (rarity.trophy_owners / 50000) * 100 <= 0.02 THEN 'LEGENDARY'
                    ELSE 'NONE'
                END
            WHERE
                t.np_communication_id = :np_communication_id AND t.order_id = rarity.order_id");
        $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock || $game);

// Recalculate trophy rarity point (trophy)
do {
    try {
        $query = $database->prepare(
            "UPDATE
                trophy t
            JOIN trophy_title tt USING(np_communication_id)
            SET
                t.rarity_point = IF(
                    t.status = 0 AND tt.status = 0,
                    IF(
                        t.rarity_percent = 0,
                        99999,
                        FLOOR(
                            1 /(t.rarity_percent / 100) - 1
                        )
                    ),
                    0
                ),
                t.rarity_name = IF(t.status = 0 AND tt.status = 0, t.rarity_name, 'NONE')");
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);

// Recalculate trophy rarity point (trophy_title)
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
                trophy_title tt,
                rarity
            SET
                tt.rarity_points = rarity.points
            WHERE
                tt.np_communication_id = rarity.np_communication_id");
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);
