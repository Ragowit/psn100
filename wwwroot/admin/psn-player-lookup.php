<?php
declare(strict_types=1);

require_once '../init.php';
require_once '../vendor/autoload.php';
require_once '../classes/Admin/PsnPlayerLookupService.php';
require_once '../classes/Admin/PsnPlayerLookupRequestHandler.php';

$onlineId = isset($_GET['onlineId']) ? (string) $_GET['onlineId'] : '';
$lookupService = PsnPlayerLookupService::fromDatabase($database);
$handledRequest = PsnPlayerLookupRequestHandler::handle($lookupService, $onlineId);

$normalizedOnlineId = $handledRequest['normalizedOnlineId'];
$result = $handledRequest['result'];
$errorMessage = $handledRequest['errorMessage'];
$decodedNpId = $handledRequest['decodedNpId'];
$npCountry = $handledRequest['npCountry'];
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <title>Admin ~ PSN Player Lookup</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <h1 class="h3 mb-3">PSN Player Lookup</h1>
            <p class="text-body-secondary">Look up a player profile directly from Sony PSN.</p>
            <form method="get" class="mb-4" action="">
                <div class="mb-2">
                    <label for="onlineId" class="form-label">Online ID</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="onlineId" name="onlineId" value="<?= htmlentities($onlineId, ENT_QUOTES, 'UTF-8'); ?>" maxlength="16" placeholder="PSN online ID" autocomplete="off">
                        <button class="btn btn-primary" type="submit">Lookup</button>
                    </div>
                </div>
            </form>
            <?php if ($errorMessage !== null) { ?>
                <div class="alert alert-warning" role="alert">
                    <?= htmlentities($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php } elseif ($normalizedOnlineId !== '' && $result === null) { ?>
                <div class="alert alert-info" role="alert">
                    No profile data was returned for "<?= htmlentities($normalizedOnlineId, ENT_QUOTES, 'UTF-8'); ?>".
                </div>
            <?php } ?>
            <?php if ($decodedNpId !== null || $npCountry !== null) { ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <dl class="row mb-0">
                            <?php if ($decodedNpId !== null) { ?>
                                <dt class="col-sm-3">Decoded NP ID</dt>
                                <dd class="col-sm-9"><code><?= htmlentities($decodedNpId, ENT_QUOTES, 'UTF-8'); ?></code></dd>
                            <?php } ?>
                            <?php if ($npCountry !== null) { ?>
                                <dt class="col-sm-3">Country</dt>
                                <dd class="col-sm-9"><?= htmlentities($npCountry, ENT_QUOTES, 'UTF-8'); ?></dd>
                            <?php } ?>
                        </dl>
                    </div>
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
