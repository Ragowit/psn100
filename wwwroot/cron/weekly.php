<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("/home/psn100/public_html/init.php");

// Set ranks last week
do {
    try {
        $query = $database->prepare("
            UPDATE player p
            JOIN player_ranking r ON p.account_id = r.account_id
            SET
                p.rank_last_week = r.ranking,
                p.rarity_rank_last_week = r.rarity_ranking,
                p.rank_country_last_week = r.ranking_country,
                p.rarity_rank_country_last_week = r.rarity_ranking_country
            WHERE p.status = 0
        ");
        $query->execute();
        
        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);

// Reset last week ranks for those not on the leaderboard
$query = $database->prepare("
    UPDATE
        player p
    SET
        p.rank_last_week = 0,
        p.rank_country_last_week = 0,
        p.rarity_rank_last_week = 0,
        p.rarity_rank_country_last_week = 0
    WHERE
        p.status != 0
");
$query->execute();
