<?php
require_once("init.php");

$player = trim($_REQUEST["q"]);

$query = $database->prepare("SELECT
        COUNT(*)
    FROM
        player_queue
    WHERE
        ip_address = :ip_address
    ");
$query->bindParam(":ip_address", $_SERVER["REMOTE_ADDR"], PDO::PARAM_STR);
$query->execute();
$count = $query->fetchColumn();

// Check cheater status
$query = $database->prepare("SELECT
        account_id
    FROM
        player
    WHERE
        online_id = :online_id AND status = 1
    ");
$query->bindParam(":online_id", $player, PDO::PARAM_STR);
$query->execute();
$accountId = $query->fetchColumn();

if (!isset($player) || $player === "") {
    echo "PSN name can't be empty.";
} elseif ($accountId !== false) {
    ?>
    Player '<a href="/player/<?= $player; ?>"><?= $player; ?></a>' is tagged as a cheater and won't be scanned. <a href="https://github.com/Ragowit/psn100/issues?q=label%3Acheater+<?= $player; ?>+OR+<?= $accountId; ?>">Dispute</a>?
    <?php
} elseif ($count >= 50) {
    echo "You have already entered 50 players into the queue. Please wait a while.";
} elseif (preg_match("/^[\w\-]{3,16}$/", $player)) {
    // Insert player into the queue
    // $query = $database->prepare("INSERT IGNORE INTO player_queue (online_id, ip_address)
    //     VALUES
    //         (:online_id, :ip_address)
    //     ");
    // Currently our initial backlog is huge, so use this for a while.
    $query = $database->prepare("INSERT INTO
            player_queue (online_id, ip_address)
        VALUES
            (:online_id, :ip_address) ON DUPLICATE KEY
        UPDATE
            ip_address = IF(
                request_time >= '2030-01-01 00:00:00',
                :ip_address,
                ip_address
            ),
            request_time = IF(
                request_time >= '2030-01-01 00:00:00',
                NOW(),
                request_time
            )
        ");
    $query->bindParam(":online_id", $player, PDO::PARAM_STR);
    $query->bindParam(":ip_address", $_SERVER["REMOTE_ADDR"], PDO::PARAM_STR);
    $query->execute();

    // Check position
    $query = $database->prepare("WITH temp AS(
            SELECT
                request_time,
                online_id,
                ROW_NUMBER() OVER(
                    ORDER BY
                        request_time
                ) AS 'rownum'
            FROM
                player_queue
        )
        SELECT
            rownum
        FROM
            temp
        WHERE
            online_id = :online_id
        ");
    $query->bindParam(":online_id", $player, PDO::PARAM_STR);
    $query->execute();
    $position = $query->fetchColumn();

    $player = htmlentities($player, ENT_QUOTES, "UTF-8");
    echo "<a href=\"/player/". $player ."\">". $player ."</a> is in the update queue, currently in position ". $position;
} else {
    echo "PSN name must contain between three and 16 characters, and can consist of letters, numbers, hyphens (-) and underscores (_).";
}
