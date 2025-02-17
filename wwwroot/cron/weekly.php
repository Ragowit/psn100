<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("/home/psn100/public_html/init.php");

// Set ranks last week
do {
    try {
        $query = $database->prepare("WITH
            ranking AS(
                SELECT
                    p.account_id,
                    RANK() OVER(ORDER BY p.points DESC, p.platinum DESC, p.gold DESC, p.silver DESC) ranking,
                    RANK() OVER(ORDER BY p.rarity_points DESC) rarity_ranking
                FROM
                    player p
                WHERE
                    p.status = 0)
            UPDATE
                player p,
                ranking r
            SET
                p.rank_last_week = r.ranking,
                p.rarity_rank_last_week = r.rarity_ranking
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
                    RANK() OVER(ORDER BY p.points DESC, p.platinum DESC, p.gold DESC, p.silver DESC) ranking,
                    RANK() OVER(ORDER BY p.rarity_points DESC) rarity_ranking
                FROM
                    player p
                WHERE
                    p.status = 0 AND p.country = :country)
            UPDATE
                player p,
                ranking r
            SET
                p.rank_country_last_week = r.ranking,
                p.rarity_rank_country_last_week = r.rarity_ranking
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
