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

// Check if scanning
$query = $database->prepare("SELECT scanning FROM setting WHERE scanning = :online_id");
$query->bindParam(":online_id", $player, PDO::PARAM_STR);
$query->execute();
$scanning = $query->fetchColumn();

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

// Check status
$query = $database->prepare("SELECT account_id, `status` FROM player WHERE online_id = :online_id");
$query->bindParam(":online_id", $player, PDO::PARAM_STR);
$query->execute();
$playerData = $query->fetch();
$accountId = $playerData["account_id"] ?? "";
$status = $playerData["status"] ?? 0;

$player = htmlentities($player, ENT_QUOTES, "UTF-8");

if (!isset($player) || $player === "") {
    echo "PSN name can't be empty.";
} elseif ($count >= 10) {
    echo "You have already entered 10 players into the queue. Please wait a while.";
} elseif (!preg_match("/^[\w\-]{3,16}$/", $player)) {
    echo "PSN name must contain between three and 16 characters, and can consist of letters, numbers, hyphens (-) and underscores (_).";
} elseif ($status == 1) { // cheater
    ?>
    Player '<a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/player/<?= $player; ?>"><?= $player; ?></a>' is tagged as a cheater and won't be scanned. <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://github.com/Ragowit/psn100/issues?q=label%3Acheater+<?= $player; ?>+OR+<?= $accountId; ?>">Dispute</a>?
    <?php
} elseif ($scanning) {
    ?>
    <a class='link-underline link-underline-opacity-0 link-underline-opacity-100-hover' href="/player/<?= $player; ?>"><?= $player; ?></a> is currently being scanned.
    <div class="spinner-border" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <?php
} elseif ($position) {
    ?>
    <a class='link-underline link-underline-opacity-0 link-underline-opacity-100-hover' href="/player/<?= $player; ?>"><?= $player; ?></a> is in the update queue, currently in position <?= $position; ?>.
    <div class="spinner-border" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <?php
} else {
    ?>
    <a class='link-underline link-underline-opacity-0 link-underline-opacity-100-hover' href="/player/<?= $player; ?>"><?= $player; ?></a> has been updated!
    <?php
}
?>

