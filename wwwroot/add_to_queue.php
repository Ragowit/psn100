<?php
require_once("init.php");

$player = trim($_REQUEST["q"]);

if (!isset($player) || $player === "") {
    echo "PSN name can't be empty.";
} elseif ($player == "ikemenzi") {
    echo "ikemenzi is unfortunately currently bugged and can't be updated. Not even on the official website. <a href=\"https://my.playstation.com/profile/ikemenzi/trophies\">https://my.playstation.com/profile/ikemenzi/trophies</a>";
} elseif (preg_match("/^[\w\-]{3,16}$/", $player)) {
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
} else {
    echo "PSN name must contain between three and 16 characters, and can consist of letters, numbers, hyphens (-) and underscores (_).";
}
