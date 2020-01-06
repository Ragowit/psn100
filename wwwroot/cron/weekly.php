<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
// The lines above doesn't seem to do anything on my webhost. 504 after about 5 minutes anyway.
require_once("../init.php");

$database->beginTransaction();
$query = $database->prepare("UPDATE player SET rank_last_week = rank, rarity_rank_last_week = rarity_rank, rank_country_last_week = rank_country, rarity_rank_country_last_week = rarity_rank_country");
$query->execute();
$database->commit();
