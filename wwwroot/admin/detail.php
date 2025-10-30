<?php
declare(strict_types=1);

require_once '../init.php';
require_once '../classes/Admin/GameDetail.php';
require_once '../classes/Admin/GameDetailService.php';
require_once '../classes/Admin/GameDetailPage.php';
require_once '../classes/Admin/GameDetailPageResult.php';
require_once '../classes/GameStatusService.php';

$gameDetailService = new GameDetailService($database);
$gameStatusService = new GameStatusService($database);
$gameDetailPage = new GameDetailPage($gameDetailService, $gameStatusService);
$statusOptions = $gameDetailPage->getStatusOptions();
$pageResult = $gameDetailPage->handle($_SERVER ?? [], $_GET ?? [], $_POST ?? []);

$gameDetail = $pageResult->getGameDetail();
$success = $pageResult->getSuccessMessage();
$error = $pageResult->getErrorMessage();

$requestedGameId = isset($_GET['game']) ? (string) $_GET['game'] : '';
$requestedNpCommunicationId = isset($_GET['np_communication_id']) ? (string) $_GET['np_communication_id'] : '';

?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <title>Admin ~ Game Details</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <form method="get" autocomplete="off">
                Game ID:<br>
                <input type="number" name="game" value="<?= htmlentities($requestedGameId, ENT_QUOTES, 'UTF-8'); ?>"><br>
                NP Communication ID:<br>
                <input type="text" name="np_communication_id" style="width: 300px;" value="<?= htmlentities($requestedNpCommunicationId, ENT_QUOTES, 'UTF-8'); ?>"><br>
                <small>Examples: NPWR10853_00, MERGE_048500</small><br>
                <input type="submit" value="Fetch">
            </form>

            <?php if ($gameDetail !== null) { ?>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="action" value="update-detail">
                    <input type="hidden" name="game" value="<?= $gameDetail->getId(); ?>"><br>
                    Name:<br>
                    <input type="text" name="name" style="width: 859px;" value="<?= htmlentities($gameDetail->getName(), ENT_QUOTES, 'UTF-8'); ?>"><br>
                    Icon URL:<br>
                    <input type="text" name="icon_url" style="width: 859px;" value="<?= htmlentities($gameDetail->getIconUrl(), ENT_QUOTES, 'UTF-8'); ?>"><br>
                    Platform:<br>
                    <input type="text" name="platform" style="width: 859px;" value="<?= htmlentities($gameDetail->getPlatform(), ENT_QUOTES, 'UTF-8'); ?>"><br>
                    Set Version:<br>
                    <input type="text" name="set_version" style="width: 859px;" value="<?= htmlentities($gameDetail->getSetVersion(), ENT_QUOTES, 'UTF-8'); ?>"><br>
                    Region:<br>
                    <input type="text" name="region" style="width: 859px;" value="<?= htmlentities((string) ($gameDetail->getRegion() ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><br>
                    NP Communication ID:<br>
                    <input type="text" name="np_communication_id" style="width: 859px;" value="<?= htmlentities((string) ($gameDetail->getNpCommunicationId() ?? ''), ENT_QUOTES, 'UTF-8'); ?>" readonly><br>
                    PSNProfiles ID:<br>
                    <input type="text" name="psnprofiles_id" style="width: 859px;" value="<?= htmlentities((string) ($gameDetail->getPsnprofilesId() ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><br>
                    Message:<br>
                    <textarea name="message" rows="6" cols="120"><?= $gameDetail->getMessage(); ?></textarea><br><br>
                    <input type="submit" value="Submit">
                </form>

                <hr>

                <h2>Game Status</h2>
                <form method="post" autocomplete="off" class="mt-3">
                    <input type="hidden" name="action" value="update-status">
                    <input type="hidden" name="game" value="<?= $gameDetail->getId(); ?>">
                    <label for="status">Status:</label><br>
                    <select id="status" name="status" class="form-select" style="max-width: 300px;">
                        <?php foreach ($statusOptions as $value => $label) { ?>
                            <?php $selected = $gameDetail->getStatus() === $value ? 'selected' : ''; ?>
                            <option value="<?= htmlentities((string) $value, ENT_QUOTES, 'UTF-8'); ?>" <?= $selected; ?>>
                                <?= htmlentities($label, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php } ?>
                    </select><br><br>
                    <input type="submit" value="Update Status">
                </form>

                <p>
                    Standard messages:<br>
                    <?= htmlentities("This game is delisted (<a href=\"https://github.com/Ragowit/psn100/issues/\">source</a>). No trophies will be accounted for on any leaderboard."); ?><br>
                    <?= htmlentities("This game is obsolete, no trophies will be accounted for on any leaderboard. Please play <a href=\"/game/\"></a> instead."); ?><br>
                </p>
            <?php } ?>

            <?php
            if ($error !== null) {
                echo $error;
            }

            if ($success !== null) {
                echo $success;
            }
            ?>
        </div>
    </body>
</html>
