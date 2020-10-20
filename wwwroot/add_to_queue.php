<?php
require_once("init.php");

$player = trim($_REQUEST["q"]);

if (!isset($player) || $player === "") {
    echo "PSN name can't be empty.";
} elseif (preg_match("/^[\w\-]{3,16}$/", $player)) {
    // Insert player into the queue
    //$query = $database->prepare("INSERT IGNORE INTO player_queue (online_id) VALUES (:online_id)");
    $query = $database->prepare("INSERT INTO player_queue (online_id) VALUES (:online_id) ON DUPLICATE KEY UPDATE request_time=IF(request_time >= '2030-01-01 00:00:00', NOW(), request_time)"); // Currently our initial backlog is huge, so use this for a while.
    $query->bindParam(":online_id", $player, PDO::PARAM_STR);
    $query->execute();

    // Check position
    $query = $database->prepare("WITH
            temp AS(
            SELECT
                request_time,
                online_id,
                ROW_NUMBER() OVER(
            ORDER BY
                request_time
            ) AS 'rownum'
        FROM
            player_queue)
            SELECT
                rownum
            FROM
                temp
            WHERE
                online_id = :online_id");
    $query->bindParam(":online_id", $player, PDO::PARAM_STR);
    $query->execute();
    $position = $query->fetchColumn();

    $player = htmlentities($player, ENT_QUOTES, "UTF-8");
    echo "<a href=\"/player/". $player ."\">". $player ."</a> is in the update queue, currently in position ". $position;
} else {
    echo "PSN name must contain between three and 16 characters, and can consist of letters, numbers, hyphens (-) and underscores (_).";
}
