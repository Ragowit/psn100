<?php
declare(strict_types=1);

require_once '../init.php';
require_once '../classes/Admin/PossibleCheaterPage.php';
require_once '../classes/Admin/PossibleCheaterService.php';

$possibleCheaterService = new PossibleCheaterService($database);
$possibleCheaterPage = new PossibleCheaterPage($possibleCheaterService);
$possibleCheaterReport = $possibleCheaterPage->getReport();
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <title>Admin ~ Possible Cheaters</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <?php foreach ($possibleCheaterReport->getGeneralCheaters() as $possibleCheater): ?>
                <a href="<?= htmlspecialchars($possibleCheater->getProfileUrl($utility), ENT_QUOTES, 'UTF-8'); ?>">
                    <?= htmlspecialchars($possibleCheater->getPlayerName(), ENT_QUOTES, 'UTF-8'); ?> (<?= $possibleCheater->getAccountId(); ?>)
                </a><br>
            <?php endforeach; ?>

            <?php foreach ($possibleCheaterReport->getSections() as $section): ?>
                <br>
                <?= htmlspecialchars($section->getTitle(), ENT_QUOTES, 'UTF-8'); ?><br>
                <?php foreach ($section->getEntries() as $entry): ?>
                    <a href="<?= htmlspecialchars($entry->getUrl(), ENT_QUOTES, 'UTF-8'); ?>">
                        <?= htmlspecialchars($entry->getOnlineId(), ENT_QUOTES, 'UTF-8'); ?> (<?= $entry->getAccountId(); ?>)
                    </a><br>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </body>
</html>
