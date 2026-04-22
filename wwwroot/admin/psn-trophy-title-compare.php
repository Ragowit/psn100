<?php

declare(strict_types=1);

require_once '../init.php';
require_once '../vendor/autoload.php';
require_once '../classes/Admin/PsnTrophyTitleComparisonException.php';
require_once '../classes/Admin/PsnTrophyTitleComparisonRequestHandler.php';
require_once '../classes/Admin/PsnTrophyTitleComparisonRequestResult.php';
require_once '../classes/Admin/PsnTrophyTitleComparisonService.php';

$accountId = isset($_GET['accountId']) ? (string) $_GET['accountId'] : '';
$service = PsnTrophyTitleComparisonService::fromDatabase($database);
$handledRequest = PsnTrophyTitleComparisonRequestHandler::handle($service, $accountId);

$normalizedAccountId = $handledRequest->getNormalizedAccountId();
$result = $handledRequest->getResult();
$errorMessage = $handledRequest->getErrorMessage();

/**
 * @return mixed
 */
function normalizeForJsonPayload(mixed $value): mixed
{
    if (is_null($value) || is_scalar($value)) {
        return $value;
    }

    if (is_array($value)) {
        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = normalizeForJsonPayload($item);
        }

        return $normalized;
    }

    if ($value instanceof JsonSerializable) {
        return normalizeForJsonPayload($value->jsonSerialize());
    }

    if (is_object($value)) {
        if (method_exists($value, 'toArray')) {
            /** @var mixed $toArrayValue */
            $toArrayValue = $value->toArray();
            return normalizeForJsonPayload($toArrayValue);
        }

        $normalized = ['__class' => get_class($value)];
        $reflection = new ReflectionObject($value);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $methodName = $method->getName();
            if (str_starts_with($methodName, '__')) {
                continue;
            }

            try {
                $methodValue = $method->invoke($value);
            } catch (Throwable) {
                continue;
            }

            if (!is_scalar($methodValue) && !is_array($methodValue) && !is_null($methodValue)) {
                continue;
            }

            $normalized[$methodName] = normalizeForJsonPayload($methodValue);
        }

        return $normalized;
    }

    return (string) $value;
}
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
            <p class="text-body-secondary">Fetch all trophy titles for an account ID using direct endpoint paging and compare it against tustin/psn-php.</p>
            <form method="get" class="mb-4" action="">
                <div class="mb-2">
                    <label for="accountId" class="form-label">Account ID</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="accountId" name="accountId" value="<?= htmlentities($accountId, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. 1234567890123456789" autocomplete="off" inputmode="numeric">
                        <button class="btn btn-primary" type="submit">Fetch</button>
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
                                <h2 class="h5">Direct endpoint</h2>
                                <dl class="row mb-0">
                                    <dt class="col-sm-5">Titles fetched</dt>
                                    <dd class="col-sm-7"><?= htmlentities((string) ($result['direct']['count'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></dd>
                                    <dt class="col-sm-5">Pages fetched</dt>
                                    <dd class="col-sm-7"><?= htmlentities((string) ($result['direct']['pagesFetched'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></dd>
                                    <dt class="col-sm-5">Total item count</dt>
                                    <dd class="col-sm-7"><?= htmlentities((string) ($result['direct']['totalItemCount'] ?? 'n/a'), ENT_QUOTES, 'UTF-8'); ?></dd>
                                    <dt class="col-sm-5">Duration (ms)</dt>
                                    <dd class="col-sm-7"><?= htmlentities((string) ($result['direct']['durationMs'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h2 class="h5">tustin/psn-php</h2>
                                <dl class="row mb-0">
                                    <dt class="col-sm-5">Titles fetched</dt>
                                    <dd class="col-sm-7"><?= htmlentities((string) ($result['tustin']['count'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></dd>
                                    <dt class="col-sm-5">Duration (ms)</dt>
                                    <dd class="col-sm-7"><?= htmlentities((string) ($result['tustin']['durationMs'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></dd>
                                    <dt class="col-sm-5">Count match</dt>
                                    <dd class="col-sm-7"><?= htmlentities(($result['countsMatch'] ?? false) ? 'Yes' : 'No', ENT_QUOTES, 'UTF-8'); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="h5">Direct endpoint payload</h2>
                        <pre class="mb-0 text-white-50"><?php
                            $json = json_encode($result['direct']['titles'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                            echo htmlentities($json === false ? 'Unable to encode response.' : $json, ENT_QUOTES, 'UTF-8');
                        ?></pre>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h2 class="h5">tustin/psn-php payload</h2>
                        <pre class="mb-0 text-white-50"><?php
                            $json = json_encode(normalizeForJsonPayload($result['tustin']['titles'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                            echo htmlentities($json === false ? 'Unable to encode response.' : $json, ENT_QUOTES, 'UTF-8');
                        ?></pre>
                    </div>
                </div>
            <?php } ?>
        </div>
    </body>
</html>
