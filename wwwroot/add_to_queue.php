<?php
require_once("init.php");

$player = trim($_REQUEST["q"]);
$ipAddress = $_SERVER["REMOTE_ADDR"];

$query = $database->prepare("SELECT
        COUNT(*)
    FROM
        player_queue
    WHERE
        ip_address = :ip_address
    ");
$query->bindParam(":ip_address", $ipAddress, PDO::PARAM_STR);
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
    $player = htmlentities($player, ENT_QUOTES, "UTF-8");
    ?>
    Player '<a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/player/<?= $player; ?>"><?= $player; ?></a>' is tagged as a cheater and won't be scanned. <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://github.com/Ragowit/psn100/issues?q=label%3Acheater+<?= $player; ?>+OR+<?= $accountId; ?>">Dispute</a>?
    <?php
} elseif ($count >= 10) {
    echo "You have already entered 10 players into the queue. Please wait a while.";
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
    $query->bindParam(":ip_address", $ipAddress, PDO::PARAM_STR);
    $query->execute();

    $player = htmlentities($player, ENT_QUOTES, "UTF-8");
    ?>
    <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/player/<?= $player; ?>"><?= $player; ?></a> is being added to the queue.
    <div class="spinner-border" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <?php
} else {
    echo "PSN name must contain between three and 16 characters, and can consist of letters, numbers, hyphens (-) and underscores (_).";
}
