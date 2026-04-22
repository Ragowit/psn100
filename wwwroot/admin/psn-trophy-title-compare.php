<?php

declare(strict_types=1);

require_once '../init.php';
require_once '../vendor/autoload.php';
require_once '../classes/Admin/PsnTrophyTitleComparisonException.php';
require_once '../classes/Admin/PsnTrophyTitleComparisonRequestHandler.php';
require_once '../classes/Admin/PsnTrophyTitleComparisonRequestResult.php';
require_once '../classes/Admin/PsnTrophyTitleComparisonService.php';

$accountId = isset($_GET['accountId']) ? (string) $_GET['accountId'] : '';
$source = isset($_GET['source']) ? (string) $_GET['source'] : PsnTrophyTitleComparisonService::SOURCE_DIRECT;
$service = PsnTrophyTitleComparisonService::fromDatabase($database);
$handledRequest = PsnTrophyTitleComparisonRequestHandler::handle($service, $accountId, $source);

$normalizedAccountId = $handledRequest->getNormalizedAccountId();
$normalizedSource = $handledRequest->getNormalizedSource();
$result = $handledRequest->getResult();
$errorMessage = $handledRequest->getErrorMessage();
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <title>Admin ~ Trophy Title Compare</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <h1 class="h3 mb-3">PSN Trophy Title Compare</h1>
            <p class="text-body-secondary">Fetch all trophy titles for an account ID using a selectable source.</p>
            <form method="get" class="mb-4" action="">
                <div class="mb-2">
                    <label for="accountId" class="form-label">Account ID</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="accountId" name="accountId" value="<?= htmlentities($accountId, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. 1234567890123456789" autocomplete="off" inputmode="numeric">
                        <button class="btn btn-primary" type="submit">Fetch</button>
                    </div>
                </div>
                <div class="mb-2">
                    <span class="form-label d-block">Source</span>
                    <div class="btn-group" role="group" aria-label="Source toggle">
                        <input type="radio" class="btn-check" name="source" id="source-direct" value="direct" autocomplete="off" <?= $normalizedSource === PsnTrophyTitleComparisonService::SOURCE_DIRECT ? 'checked' : ''; ?>>
                        <label class="btn btn-outline-secondary" for="source-direct">Direct endpoint</label>

                        <input type="radio" class="btn-check" name="source" id="source-tustin" value="tustin" autocomplete="off" <?= $normalizedSource === PsnTrophyTitleComparisonService::SOURCE_TUSTIN ? 'checked' : ''; ?>>
                        <label class="btn btn-outline-secondary" for="source-tustin">tustin/psn-php</label>
                    </div>
                </div>
            </form>
            <?php if ($errorMessage !== null) { ?>
                <div class="alert alert-warning" role="alert">
                    <?= htmlentities($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php } elseif ($normalizedAccountId !== '' && $result === null) { ?>
                <div class="alert alert-info" role="alert">
                    No data was returned for account ID "<?= htmlentities($normalizedAccountId, ENT_QUOTES, 'UTF-8'); ?>".
                </div>
            <?php } ?>
            <?php if (is_array($result)) { ?>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h2 class="h5"><?= $normalizedSource === PsnTrophyTitleComparisonService::SOURCE_DIRECT ? 'Direct endpoint' : 'tustin/psn-php'; ?></h2>
                                <dl class="row mb-0">
                                    <dt class="col-sm-5">Titles fetched</dt>
                                    <dd class="col-sm-7"><?= htmlentities((string) ($result['result']['count'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></dd>
                                    <?php if ($normalizedSource === PsnTrophyTitleComparisonService::SOURCE_DIRECT) { ?>
                                        <dt class="col-sm-5">Pages fetched</dt>
                                        <dd class="col-sm-7"><?= htmlentities((string) ($result['result']['pagesFetched'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></dd>
                                        <dt class="col-sm-5">Total item count</dt>
                                        <dd class="col-sm-7"><?= htmlentities((string) ($result['result']['totalItemCount'] ?? 'n/a'), ENT_QUOTES, 'UTF-8'); ?></dd>
                                    <?php } ?>
                                    <dt class="col-sm-5">Duration (ms)</dt>
                                    <dd class="col-sm-7"><?= htmlentities((string) ($result['result']['durationMs'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-secondary" role="alert">
                    Full payload rendering is intentionally disabled on this page to avoid response-size timeouts.
                    This lookup still fetches <strong>all titles</strong> from the selected source.
                </div>
            <?php } ?>
        </div>
    </body>
</html>
