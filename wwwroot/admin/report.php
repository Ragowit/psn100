<?php
declare(strict_types=1);

require_once '../init.php';
require_once '../classes/Admin/PlayerReportAdminService.php';
require_once '../classes/Admin/PlayerReportAdminPage.php';

$playerReportAdminService = new PlayerReportAdminService($database);
$playerReportAdminPage = new PlayerReportAdminPage($playerReportAdminService);
$pageResult = $playerReportAdminPage->handle($_GET ?? []);

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
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <title>Admin ~ Reported Players</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
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
        </div>
    </body>
</html>
