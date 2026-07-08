<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once '../classes/StaticAsset.php';
require_once '../classes/Admin/PlayerReportAdminService.php';
require_once '../classes/Admin/PlayerReportAdminPage.php';

$playerReportAdminService = new PlayerReportAdminService($database);
$playerReportAdminPage = new PlayerReportAdminPage($playerReportAdminService);
$pageResult = $playerReportAdminPage->handle($_GET ?? [], $_POST ?? [], $_SERVER['REQUEST_METHOD'] ?? 'GET');

$reportedPlayers = $pageResult->getReportedPlayers();
$successMessage = $pageResult->getSuccessMessage();
$errorMessage = $pageResult->getErrorMessage();
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="<?= htmlspecialchars(BootstrapAssets::stylesheetUrl(), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
        <title>Admin ~ Reported Players</title>
        <script src="<?= htmlspecialchars(StaticAsset::url('/js/admin-report-delete.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <?php if ($successMessage !== null) { ?>
                <div class="alert alert-success" role="alert">
                    <?= Html::escape($successMessage); ?>
                </div>
            <?php } ?>
            <?php if ($errorMessage !== null) { ?>
                <div class="alert alert-warning" role="alert">
                    <?= Html::escape($errorMessage); ?>
                </div>
            <?php } ?>
            <?php foreach ($reportedPlayers as $reportedPlayer) { ?>
                <div class="mb-3">
                    <a href="/player/<?= Html::escape($reportedPlayer->getOnlineId()); ?>">
                        <?= Html::escape($reportedPlayer->getOnlineId()); ?>
                    </a>
                    <div class="mt-2">
                        <?= nl2br(Html::escape($reportedPlayer->getExplanation())); ?>
                    </div>
                    <div class="mt-2">
                        <form method="post" class="d-inline js-report-delete-form">
                            <?php AdminBootstrap::renderCsrfField(); ?>
                            <input type="hidden" name="delete_id" value="<?= (int) $reportedPlayer->getReportId(); ?>">
                            <button type="submit" class="btn btn-link p-0">Delete</button>
                        </form>
                    </div>
                </div>
                <hr>
            <?php } ?>
        </div>
    </body>
</html>
