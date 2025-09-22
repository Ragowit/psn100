<?php
declare(strict_types=1);

require_once '../init.php';
require_once '../classes/Admin/GameDetail.php';
require_once '../classes/Admin/GameDetailService.php';

$gameDetailService = new GameDetailService($database);
$gameDetail = null;
$success = null;
$error = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $gameId = filter_input(INPUT_POST, 'game', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        if ($gameId === null || $gameId === false) {
            $error = '<p>Invalid game ID provided.</p>';
        } else {
            $regionInput = trim((string) ($_POST['region'] ?? ''));
            $psnprofilesInput = trim((string) ($_POST['psnprofiles_id'] ?? ''));

            $gameDetail = $gameDetailService->updateGameDetail(
                new GameDetail(
                    (int) $gameId,
                    null,
                    (string) ($_POST['name'] ?? ''),
                    (string) ($_POST['icon_url'] ?? ''),
                    (string) ($_POST['platform'] ?? ''),
                    (string) ($_POST['message'] ?? ''),
                    (string) ($_POST['set_version'] ?? ''),
                    $regionInput === '' ? null : $regionInput,
                    $psnprofilesInput === '' ? null : $psnprofilesInput
                )
            );

            $success = sprintf('<p>Game ID %d is updated.</p>', $gameDetail->getId());
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $gameId = filter_input(INPUT_GET, 'game', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        if ($gameId !== null && $gameId !== false) {
            $gameDetail = $gameDetailService->getGameDetail((int) $gameId);

            if ($gameDetail === null) {
                $error = '<p>Unable to find the requested game.</p>';
            }
        }
    }
} catch (Throwable $exception) {
    $error = '<p>' . htmlentities($exception->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
}

?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <title>Admin ~ Game Details</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <form method="get" autocomplete="off">
                Game ID:<br>
                <input type="number" name="game"><br>
                <input type="submit" value="Fetch">
            </form>

            <?php if ($gameDetail !== null) { ?>
                <form method="post" autocomplete="off">
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
