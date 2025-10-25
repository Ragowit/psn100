<?php

declare(strict_types=1);

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../classes/Admin/PsnpPlusService.php';

$psnpPlusService = new PsnpPlusService($database);
$psnpPlusReport = null;
$errorMessage = null;

try {
    $psnpPlusReport = $psnpPlusService->buildReport();
} catch (RuntimeException $exception) {
    $errorMessage = $exception->getMessage();
}
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <title>Admin ~ PSNP+</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>

            <h1>PSNP+ changes</h1>

            <?php if ($errorMessage !== null) { ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlentities($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php } elseif ($psnpPlusReport !== null) { ?>
                <?php if ($psnpPlusReport->hasMissingGames()) { ?>
                    <div class="mb-3">
                        <?php foreach ($psnpPlusReport->getMissingGames() as $missingGame) { ?>
                            <p>
                                PSNProfiles ID <a href="<?= htmlentities($missingGame->getPsnprofilesUrl(), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?= htmlentities((string) $missingGame->getPsnprofilesId(), ENT_QUOTES, 'UTF-8'); ?></a> not in our database.
                            </p>
                        <?php } ?>
                    </div>
                <?php } ?>

                <?php foreach ($psnpPlusReport->getGameDifferences() as $difference) { ?>
                    <div class="mb-3">
                        <strong>
                            <a href="../game/<?= $difference->getGameId(); ?>" target="_blank" rel="noopener">
                                <?= htmlentities($difference->getGameName(), ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </strong><br>

                        <?php if ($difference->hasUnobtainable()) { ?>
                            <a href="unobtainable.php?status=1&amp;trophy=<?= $difference->getUnobtainableTrophyIdQuery(); ?>">Unobtainable</a>: <?= htmlentities($difference->getUnobtainableOrderList(), ENT_QUOTES, 'UTF-8'); ?><br>
                        <?php } ?>

                        <?php if ($difference->hasObtainable()) { ?>
                            <a href="unobtainable.php?status=0&amp;trophy=<?= $difference->getObtainableTrophyIdQuery(); ?>">Obtainable</a>: <?= htmlentities($difference->getObtainableOrderList(), ENT_QUOTES, 'UTF-8'); ?><br>
                        <?php } ?>
                    </div>
                <?php } ?>

                <h1>No longer in PSNP+ (all trophies fixed!)</h1>

                <?php foreach ($psnpPlusReport->getFixedGames() as $fixedGame) { ?>
                    <div class="mb-3">
                        <strong>
                            <a href="../game/<?= $fixedGame->getGameId(); ?>" target="_blank" rel="noopener">
                                <?= htmlentities($fixedGame->getGameName(), ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </strong><br>

                        <?php if ($fixedGame->hasTrophies()) { ?>
                            <a href="unobtainable.php?status=0&amp;trophy=<?= $fixedGame->getTrophyIdQuery(); ?>">Obtainable</a>: <?= htmlentities($fixedGame->getTrophyIdList(), ENT_QUOTES, 'UTF-8'); ?><br>
                        <?php } ?>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    </body>
</html>
