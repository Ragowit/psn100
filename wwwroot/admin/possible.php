<?php
declare(strict_types=1);

require_once '../init.php';
require_once '../classes/Admin/PossibleCheaterPage.php';
require_once '../classes/Admin/PossibleCheaterService.php';
require_once '../classes/Admin/AdminLayoutRenderer.php';

$possibleCheaterService = new PossibleCheaterService($database);
$possibleCheaterPage = new PossibleCheaterPage($possibleCheaterService);
$possibleCheaterReport = $possibleCheaterPage->getReport();

$layoutRenderer = new AdminLayoutRenderer();

echo $layoutRenderer->render('Admin ~ Possible Cheaters', static function () use ($possibleCheaterReport, $utility): void {
    ?>
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
    <?php
});
