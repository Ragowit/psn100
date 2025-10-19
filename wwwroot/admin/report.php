<?php
declare(strict_types=1);

require_once '../init.php';
require_once '../classes/Admin/PlayerReportAdminService.php';
require_once '../classes/Admin/PlayerReportAdminPage.php';
require_once '../classes/Admin/AdminLayoutRenderer.php';

$playerReportAdminService = new PlayerReportAdminService($database);
$playerReportAdminPage = new PlayerReportAdminPage($playerReportAdminService);
$pageResult = $playerReportAdminPage->handle($_GET ?? []);

$reportedPlayers = $pageResult->getReportedPlayers();
$successMessage = $pageResult->getSuccessMessage();
$errorMessage = $pageResult->getErrorMessage();

$layoutRenderer = new AdminLayoutRenderer();

echo $layoutRenderer->render('Admin ~ Reported Players', static function () use ($reportedPlayers, $successMessage, $errorMessage): void {
    ?>
    <?php if ($successMessage !== null) { ?>
        <div class="alert alert-success" role="alert">
            <?= htmlentities($successMessage, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php } ?>
    <?php if ($errorMessage !== null) { ?>
        <div class="alert alert-warning" role="alert">
            <?= htmlentities($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php } ?>
    <?php foreach ($reportedPlayers as $reportedPlayer) { ?>
        <div class="mb-3">
            <a href="/player/<?= htmlentities($reportedPlayer->getOnlineId(), ENT_QUOTES, 'UTF-8'); ?>">
                <?= htmlentities($reportedPlayer->getOnlineId(), ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <div class="mt-2">
                <?= nl2br(htmlentities($reportedPlayer->getExplanation(), ENT_QUOTES, 'UTF-8')); ?>
            </div>
            <div class="mt-2">
                <a href="?delete=<?= $reportedPlayer->getReportId(); ?>">Delete</a>
            </div>
        </div>
        <hr>
    <?php } ?>
    <?php
});
