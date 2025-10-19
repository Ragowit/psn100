<?php

declare(strict_types=1);

require_once '../init.php';
require_once '../classes/Admin/GameDetail.php';
require_once '../classes/Admin/GameDetailService.php';
require_once '../classes/Admin/GameDetailPage.php';
require_once '../classes/Admin/GameDetailPageResult.php';
require_once '../classes/Admin/AdminLayoutRenderer.php';

$gameDetailService = new GameDetailService($database);
$gameDetailPage = new GameDetailPage($gameDetailService);
$pageResult = $gameDetailPage->handle($_SERVER ?? [], $_GET ?? [], $_POST ?? []);

$gameDetail = $pageResult->getGameDetail();
$success = $pageResult->getSuccessMessage();
$error = $pageResult->getErrorMessage();

$layoutRenderer = new AdminLayoutRenderer();

echo $layoutRenderer->render('Admin ~ Game Details', static function () use ($gameDetail, $success, $error): void {
    ?>
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
            <?= htmlentities("This game is delisted (<a href=\"https://github.com/Ragowit/psn100/issues/\">source</a>).No trophies will be accounted for on any leaderboard."); ?><br>
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
    <?php
});
