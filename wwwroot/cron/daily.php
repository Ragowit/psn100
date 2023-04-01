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
                    COUNT(*) AS trophy_owners,
                    order_id
                FROM
                    trophy_earned te
                JOIN player p USING(account_id)
                JOIN trophy t USING(np_communication_id, order_id)
                WHERE
                    p.status = 0 AND p.rank <= 50000 AND te.np_communication_id = :np_communication_id AND te.earned = 1
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

// Recalculate trophy rarity point
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

// Recalculate rarity for each game for each player.
do {
    try {
        $query = $database->prepare("UPDATE
                trophy_title_player ttp
            SET
                ttp.temp_rarity_points = 0,
                ttp.temp_common = 0,
                ttp.temp_uncommon = 0,
                ttp.temp_rare = 0,
                ttp.temp_epic = 0,
                ttp.temp_legendary = 0");
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);

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
                    trophy_earned.account_id,
                    SUM(trophy.rarity_point) AS points,
                    SUM(trophy.rarity_name = 'COMMON') common,
                    SUM(trophy.rarity_name = 'UNCOMMON') uncommon,
                    SUM(trophy.rarity_name = 'RARE') rare,
                    SUM(trophy.rarity_name = 'EPIC') epic,
                    SUM(trophy.rarity_name = 'LEGENDARY') legendary
                FROM
                    trophy_earned
                JOIN trophy USING 
                    (np_communication_id, order_id)
                WHERE
                    trophy_earned.np_communication_id = :np_communication_id AND trophy_earned.earned = 1
                GROUP BY
                    trophy_earned.account_id
                ORDER BY NULL
            )
            UPDATE
                trophy_title_player ttp,
                rarity
            SET
                ttp.temp_rarity_points = ttp.temp_rarity_points + rarity.points,
                ttp.temp_common = ttp.temp_common + rarity.common,
                ttp.temp_uncommon = ttp.temp_uncommon + rarity.uncommon,
                ttp.temp_rare = ttp.temp_rare + rarity.rare,
                ttp.temp_epic = ttp.temp_epic + rarity.epic,
                ttp.temp_legendary = ttp.temp_legendary + rarity.legendary
            WHERE
                ttp.account_id = rarity.account_id AND ttp.np_communication_id = :np_communication_id");
        $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock || $game);

do {
    try {
        $query = $database->prepare("UPDATE
                trophy_title_player ttp
            SET
                ttp.rarity_points = ttp.temp_rarity_points,
                ttp.common = ttp.temp_common,
                ttp.uncommon = ttp.temp_uncommon,
                ttp.rare = ttp.temp_rare,
                ttp.epic = ttp.temp_epic,
                ttp.legendary = ttp.temp_legendary");
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);
