<?php
require_once("init.php");

$player = trim($_REQUEST["q"]);

// Check if scanning
$query = $database->prepare("SELECT
        scanning
    FROM
        setting
    WHERE
        scanning = :online_id
");
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

$player = htmlentities($player, ENT_QUOTES, "UTF-8");

if ($scanning) {
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

