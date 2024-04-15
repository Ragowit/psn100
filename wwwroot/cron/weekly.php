<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("/home/psn100/public_html/init.php");

do {
    try {
        $query = $database->prepare("UPDATE player
            SET    rank_last_week = `rank`,
                rarity_rank_last_week = rarity_rank,
                rank_country_last_week = rank_country,
                rarity_rank_country_last_week = rarity_rank_country ");
        $query->execute();

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);
