<?php

declare(strict_types=1);

require_once '../init.php';
require_once '../vendor/autoload.php';
require_once '../classes/Admin/PsnGameLookupService.php';
require_once '../classes/Admin/PsnGameLookupRequestHandler.php';

$gameId = isset($_GET['gameId']) ? (string) $_GET['gameId'] : '';
$lookupService = PsnGameLookupService::fromDatabase($database);
$handledRequest = PsnGameLookupRequestHandler::handle($lookupService, $gameId);

$normalizedGameId = $handledRequest->getNormalizedGameId();
$result = $handledRequest->getResult();
$errorMessage = $handledRequest->getErrorMessage();
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <title>Admin ~ PSN Game Lookup</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <h1 class="h3 mb-3">PSN Game Lookup</h1>
            <p class="text-body-secondary">Look up trophy data directly from Sony PSN using a PSN100 game ID.</p>
            <form method="get" class="mb-4" action="">
                <div class="mb-2">
                    <label for="gameId" class="form-label">PSN100 Game ID</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="gameId" name="gameId" value="<?= htmlentities($gameId, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. 12345" autocomplete="off" inputmode="numeric">
                        <button class="btn btn-primary" type="submit">Lookup</button>
                    </div>
                </div>
            </form>
            <?php if ($errorMessage !== null) { ?>
                <div class="alert alert-warning" role="alert">
                    <?= htmlentities($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php } elseif ($normalizedGameId !== '' && $result === null) { ?>
                <div class="alert alert-info" role="alert">
                    No trophy data was returned for game ID "<?= htmlentities($normalizedGameId, ENT_QUOTES, 'UTF-8'); ?>".
                </div>
            <?php } ?>
            <?php if (is_array($result)) { ?>
                <div class="card">
                    <div class="card-body">
                        <pre class="mb-0 text-white-50"><?php
                            $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                            echo htmlentities($json === false ? 'Unable to encode response.' : $json, ENT_QUOTES, 'UTF-8');
                        ?></pre>
                    </div>
                </div>
            <?php } ?>
        </div>
    </body>
</html>
