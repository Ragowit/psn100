<?php
if (!isset($accountId)) {
    header("Location: /player/", true, 303);
    die();
}

$query = $database->prepare("SELECT * FROM player WHERE account_id = :account_id");
$query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
$query->execute();
$player = $query->fetch();

$title = $player["online_id"] . "'s Timeline ~ PSN 100%";
require_once("player_header.php");
?>
        <div class="row">
            <div class="col text-center">
                <h5><a href="/player/<?= $player["online_id"]; ?>">Games</a></h5>
            </div>
            <div class="col text-center">
                <h5><a href="/player/<?= $player["online_id"]; ?>/log">Log</a></h5>
            </div>
            <div class="col text-center">
                <h5><a href="/player/<?= $player["online_id"]; ?>/advisor">Trophy Advisor</a></h5>
            </div>
            <div class="col text-center">
                <h5><a href="/game?sort=completion&player=<?= $player["online_id"]; ?>">Game Advisor</a></h5>
            </div>
            <div class="col text-center">
                <h5><a href="/player/<?= $player["online_id"]; ?>/random">Random Games</a></h5>
            </div>
        </div>

        <div class="row">
            <div class="col">
                This page is currently disabled.
            </div>
        </div>
    </div>
</main>
<?php
require_once("footer.php");
?>
