<?php
if (!isset($accountId)) {
    header("Location: /player/", true, 303);
    die();
}

require_once __DIR__ . '/classes/PlayerReportHandler.php';
require_once __DIR__ . '/classes/PlayerReportService.php';
require_once __DIR__ . '/classes/PlayerSummary.php';
require_once __DIR__ . '/classes/PlayerSummaryService.php';

$playerReportService = new PlayerReportService($database);
$playerReportHandler = new PlayerReportHandler($playerReportService);
$playerSummaryService = new PlayerSummaryService($database);
$playerSummary = $playerSummaryService->getSummary((int) $accountId);

$queryParameters = $_GET ?? [];
$explanation = $playerReportHandler->getExplanation($queryParameters);
$reportResult = $playerReportHandler->handleReportRequest((int) $accountId, $explanation, $_SERVER ?? []);

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
                <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover text-danger" href="/player/<?= $player["online_id"]; ?>/report">Report Player</a>
            </div>

            <div class="col-12 col-lg-6 mb-3 text-center">
                <div class="btn-group">
                    <a class="btn btn-outline-primary" href="/player/<?= $player["online_id"]; ?>">Games</a>
                    <a class="btn btn-outline-primary" href="/player/<?= $player["online_id"]; ?>/log">Log</a>
                    <a class="btn btn-outline-primary" href="/player/<?= $player["online_id"]; ?>/advisor">Trophy Advisor</a>
                    <a class="btn btn-outline-primary" href="/game?sort=completion&filter=true&player=<?= $player["online_id"]; ?>">Game Advisor</a>
                    <a class="btn btn-outline-primary" href="/player/<?= $player["online_id"]; ?>/random">Random Games</a>
                </div>
            </div>

            <div class="col-12 col-lg-3 mb-3">
            </div>
        </div>
    </div>

    <div class="row">
        <?php
        if ($reportResult->hasMessage()) {
            $alertClass = $reportResult->isSuccess() ? 'success' : 'warning';
            ?>
            <div class="col-12 mb-3">
                <div class="alert alert-<?= $alertClass; ?>" role="alert">
                    <?= $reportResult->getMessage(); ?>
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
                        <ul>
                            <li>Only report issues with trophies. If the player have done something outside of PlayStation trophies, it's not going to be handled.</li>
                            <li>Include the game and trophy name and why it's wrong.</li>
                            <li>"This player is banned on X and/or Y!" isn't going to help, we need specific details on what trophy and why it's wrong.</li>
                        </ul>
                        <textarea class="form-control" id="explanation" name="explanation" maxlength="256" rows="7" aria-describedby="explanationHelp"><?= htmlentities($explanation, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <div id="explanationHelp" class="form-text">Or use <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://github.com/Ragowit/psn100/issues">issues</a> to include images and get feedback on your report (requires GitHub login).</div>
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
