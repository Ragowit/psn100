<?php
declare(strict_types=1);

require_once '../init.php';
require_once '../classes/Admin/PossibleCheaterService.php';

$possibleCheaterService = new PossibleCheaterService($database);
$generalCheaters = $possibleCheaterService->getGeneralPossibleCheaters();
$sections = $possibleCheaterService->getSectionResults();
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <title>Admin ~ Possible Cheaters</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <?php foreach ($generalCheaters as $possibleCheater): ?>
                <a href="/game/<?= (int) $possibleCheater['game_id']; ?>-<?= htmlspecialchars($utility->slugify($possibleCheater['game_name']), ENT_QUOTES, 'UTF-8'); ?>/<?= rawurlencode($possibleCheater['player_name']); ?>">
                    <?= htmlspecialchars($possibleCheater['player_name'], ENT_QUOTES, 'UTF-8'); ?> (<?= $possibleCheater['account_id']; ?>)
                </a><br>
            <?php endforeach; ?>

            <?php foreach ($sections as $section): ?>
                <br>
                <?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8'); ?><br>
                <?php foreach ($section['entries'] as $entry): ?>
                    <a href="<?= htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?= htmlspecialchars($entry['online_id'], ENT_QUOTES, 'UTF-8'); ?> (<?= $entry['account_id']; ?>)
                    </a><br>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </body>
</html>
