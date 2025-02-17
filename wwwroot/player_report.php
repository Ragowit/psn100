<?php
if (!isset($accountId)) {
    header("Location: /player/", true, 303);
    die();
}

if (!empty($_GET["explanation"])) {
    $ipAddress = $_SERVER["REMOTE_ADDR"];

    $query = $database->prepare("SELECT * FROM player_report WHERE account_id = :account_id AND ip_address = :ip_address");
    $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
    $query->bindParam(":ip_address", $ipAddress, PDO::PARAM_STR);
    $query->execute();
    $reported = $query->fetch();

    $query = $database->prepare("SELECT COUNT(*) FROM player_report WHERE ip_address = :ip_address");
    $query->bindParam(":ip_address", $ipAddress, PDO::PARAM_STR);
    $query->execute();
    $count = $query->fetchColumn();

    if ($reported) {
        $result = "You've already reported this player.";
    } elseif ($count >= 10) {
        $result = "You've already 10 players reported waiting to be processed. Please try again later.";
    } else {
        $explanation = $_GET["explanation"];

        $query = $database->prepare("INSERT INTO player_report (account_id, ip_address, explanation) VALUES (:account_id, :ip_address, :explanation)");
        $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
        $query->bindParam(":ip_address", $ipAddress, PDO::PARAM_STR);
        $query->bindParam(":explanation", $explanation, PDO::PARAM_STR);
        $query->execute();

        $result = "Player reported successfully.";
    }
}

$title = $player["online_id"] . "'s Report ~ PSN 100%";
require_once("header.php");
?>

<main class="container">
    <?php
    require_once("player_header.php");
    ?>

    <div class="p-3">
        <div class="row">
            <div class="col-12 col-lg-3">
                <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/player/<?= $player["online_id"]; ?>/report">Report Player</a>
            </div>

            <div class="col-12 col-lg-6 mb-3 text-center">
                <div class="btn-group">
                    <a class="btn btn-outline-primary" href="/player/<?= $player["online_id"]; ?>">Games</a>
                    <a class="btn btn-outline-primary" href="/player/<?= $player["online_id"]; ?>/log">Log</a>
                    <a class="btn btn-outline-primary" href="/player/<?= $player["online_id"]; ?>/advisor">Trophy Advisor</a>
                    <a class="btn btn-outline-primary" href="/game?sort=completion&player=<?= $player["online_id"]; ?>">Game Advisor</a>
                    <a class="btn btn-outline-primary" href="/player/<?= $player["online_id"]; ?>/random">Random Games</a>
                </div>
            </div>

            <div class="col-12 col-lg-3 mb-3">
            </div>
        </div>
    </div>

    <div class="row">
        <?php
        if (isset($result)) {
            if (str_contains($result, "success")) {
                $alertClass = "success";
            } else {
                $alertClass = "warning";
            }
            ?>
            <div class="col-12 mb-3">
                <div class="alert alert-<?= $alertClass; ?>" role="alert">
                    <?= $result; ?>
                </div>
            </div>
            <?php
        }
        ?>

        <div class="col-12 mb-3">
            <div class="bg-body-tertiary p-3 rounded">
                <form>
                    <div class="mb-3">
                        <label for="explanation" class="form-label">What's wrong?</label>
                        <textarea class="form-control" id="explanation" name="explanation" maxlength="256" rows="7" aria-describedby="explanationHelp"><?= htmlentities($_GET["explanation"] ?? ""); ?></textarea>
                        <div id="explanationHelp" class="form-text">Or use <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://github.com/Ragowit/psn100/issues">issues</a> to include pictures and get a reply when it's done (requires GitHub login).</div>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </form>
            </div>
        </div>
    </div>
</main>

<?php
require_once("footer.php");
?>
