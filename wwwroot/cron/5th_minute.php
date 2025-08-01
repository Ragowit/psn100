<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("/home/psn100/public_html/init.php");

// Recalculate all the rankings
do {
    try {
        // 1. Create a new table if it doesn't exist
        $database->exec("CREATE TABLE IF NOT EXISTS player_ranking_new LIKE player_ranking");

        // 2. Empty the new table
        $database->exec("TRUNCATE TABLE player_ranking_new");

        // 3. Fill it with new ranked data
        $insertSQL = "
            INSERT INTO player_ranking_new (account_id, ranking, ranking_country, rarity_ranking, rarity_ranking_country)
            SELECT
                account_id,
                RANK() OVER (
                    ORDER BY points DESC, platinum DESC, gold DESC, silver DESC
                ) AS ranking,
                RANK() OVER (
                    PARTITION BY country
                    ORDER BY points DESC, platinum DESC, gold DESC, silver DESC
                ) AS ranking_country,
                RANK() OVER (
                    ORDER BY `rarity_points` DESC
                ) AS rarity_ranking,
                RANK() OVER (
                    PARTITION BY country
                    ORDER BY `rarity_points` DESC
                ) AS rarity_ranking_country
            FROM player
            WHERE `status` = 0
        ";
        $database->exec($insertSQL);

        // 4. Change table name
        $database->exec("
            RENAME TABLE player_ranking TO player_ranking_old,
                         player_ranking_new TO player_ranking
        ");

        // 5. Delete the old table
        $database->exec("DROP TABLE player_ranking_old");

        $deadlock = false;
    } catch (Exception $e) {
        sleep(3);
        $deadlock = true;
    }
} while ($deadlock);
