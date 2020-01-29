<?php
require_once("init.php");

$player = $_REQUEST["q"];

if (!isset($player) || trim($player) === "") {
    echo "PSN name can't be empty.";
} else {
    // Insert player into the queue
    //$query = $database->prepare("INSERT IGNORE INTO player_queue (online_id) VALUES (:online_id)");
    $query = $database->prepare("INSERT INTO player_queue (online_id) VALUES (:online_id) ON DUPLICATE KEY UPDATE request_time=NOW()"); // Currently our initial backlog is huge, so use this for a while.
    $query->bindParam(":online_id", $player, PDO::PARAM_STR);
    $query->execute();

    // Check position
    $query = $database->prepare("SELECT id FROM (SELECT *, @rownum:=@rownum + 1 AS id FROM player_queue, (SELECT @rownum:=0) r ORDER BY request_time) d WHERE d.online_id = :online_id");
    $query->bindParam(":online_id", $player, PDO::PARAM_STR);
    $query->execute();
    $position = $query->fetchColumn();

    $player = htmlentities($player, ENT_QUOTES, "UTF-8");
    echo "<a href=\"/player/". $player ."\">". $player ."</a> is in the update queue, currently in position ". $position;
}
