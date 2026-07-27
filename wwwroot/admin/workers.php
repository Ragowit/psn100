<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once '../classes/StaticAsset.php';
require_once '../classes/Admin/AdminRequest.php';
require_once '../classes/Admin/WorkerAction.php';
require_once '../classes/Admin/WorkerCredentialMasker.php';
require_once '../classes/Admin/WorkerService.php';
require_once '../classes/Admin/WorkerPage.php';

$workerService = new WorkerService($database);
$request = AdminRequest::fromGlobals($_SERVER ?? [], $_POST ?? []);

$workerPage = new WorkerPage($workerService);
$pageResult = $workerPage->handle($_GET ?? [], $request);

$workers = $pageResult->getWorkers();
$successMessage = $pageResult->getSuccessMessage();
$errorMessage = $pageResult->getErrorMessage();

$idSortLink = $pageResult->getSortLink('id');
$scanStartSortLink = $pageResult->getSortLink('scan_start');

$idSortUrl = $idSortLink?->getUrl() ?? '?sort=id&direction=asc';
$scanStartSortUrl = $scanStartSortLink?->getUrl() ?? '?sort=scan_start&direction=asc';

$idSortIndicator = $idSortLink?->getIndicator() ?? '';
$scanStartSortIndicator = $scanStartSortLink?->getIndicator() ?? '';
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="csrf-token" content="<?= Html::escape(AdminBootstrap::getCsrfToken()); ?>">
        <link href="<?= Html::escape(BootstrapAssets::stylesheetUrl()); ?>" rel="stylesheet">
        <title>Admin ~ Workers</title>
        <script src="<?= Html::escape(StaticAsset::url('/js/localized-date-formatter.js')); ?>" defer></script>
        <script src="<?= Html::escape(StaticAsset::url('/js/admin-worker-credentials.js')); ?>" defer></script>
    </head>
    <body>
        <div class="container py-4">
            <div class="mb-3">
                <a href="/admin/">Back</a>
            </div>

            <div class="mb-4 d-flex justify-content-end">
                <form method="post" onsubmit="return confirm('Restart all workers?');">
                    <?php AdminBootstrap::renderCsrfField(); ?>
                    <input type="hidden" name="action" value="<?= Html::escape(WorkerAction::RestartAllWorkers->value); ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        Restart All Workers
                    </button>
                </form>
            </div>

            <?php if ($successMessage !== null) { ?>
                <div class="alert alert-success" role="alert">
                    <?= Html::escape($successMessage); ?>
                </div>
            <?php } ?>

            <?php if ($errorMessage !== null) { ?>
                <div class="alert alert-danger" role="alert">
                    <?= Html::escape($errorMessage); ?>
                </div>
            <?php } ?>

            <?php if ($workers === []) { ?>
                <div class="alert alert-info" role="alert">No workers were found.</div>
            <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead>
                            <tr>
                                <th scope="col" style="width: 4rem;">
                                    <a class="text-decoration-none text-reset" href="<?= Html::escape($idSortUrl); ?>">
                                        ID
                                        <?php if ($idSortIndicator !== '') { ?>
                                            <span class="ms-1"><?= $idSortIndicator |> trim(...) |> Html::escape(...); ?></span>
                                        <?php } ?>
                                    </a>
                                </th>
                                <th scope="col" style="width: 24rem;">Credentials</th>
                                <th scope="col" style="width: 16rem;">Scanning</th>
                                <th scope="col" style="width: 16rem;">
                                    <a class="text-decoration-none text-reset" href="<?= Html::escape($scanStartSortUrl); ?>">
                                        Scan Start
                                        <?php if ($scanStartSortIndicator !== '') { ?>
                                            <span class="ms-1"><?= $scanStartSortIndicator |> trim(...) |> Html::escape(...); ?></span>
                                        <?php } ?>
                                    </a>
                                </th>
                                <th scope="col" style="width: 20rem;">Scan Progress</th>
                                <th scope="col" style="width: 10rem;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($workers as $worker) { ?>
                                <?php
                                $scanStart = $worker->getScanStart();
                                $scanning = $worker->getScanning();
                                $scanningDisplay = Html::escape($scanning);
                                $scanningLink = $scanning !== '' ? '/player/' . rawurlencode($scanning) : null;
                                $scanProgress = $worker->getScanProgress();
                                ?>
                                <tr>
                                    <td class="text-nowrap">#<?= Html::escape((string) $worker->getId()); ?></td>
                                    <td>
                                        <div class="vstack gap-2">
                                            <form method="post" class="d-flex gap-2 align-items-center" autocomplete="off">
                                                <?php AdminBootstrap::renderCsrfField(); ?>
                                                <input type="hidden" name="action" value="<?= Html::escape(WorkerAction::UpdateRefreshToken->value); ?>">
                                                <input type="hidden" name="worker_id" value="<?= Html::escape((string) $worker->getId()); ?>">
                                                <label class="form-label small text-body-secondary mb-0 text-nowrap" for="refresh-token-<?= Html::escape((string) $worker->getId()); ?>">
                                                    Refresh Token
                                                </label>
                                                <div class="d-flex gap-2 flex-grow-1" data-worker-credential-field>
                                                    <input
                                                        id="refresh-token-<?= Html::escape((string) $worker->getId()); ?>"
                                                        type="password"
                                                        name="refresh_token"
                                                        class="form-control form-control-sm"
                                                        placeholder="<?= Html::escape(WorkerCredentialMasker::mask($worker->getRefreshToken())); ?>"
                                                        maxlength="36"
                                                        autocomplete="off"
                                                        data-worker-credential-input
                                                        data-worker-credential="refresh_token"
                                                        data-worker-id="<?= Html::escape((string) $worker->getId()); ?>"
                                                    >
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-secondary text-nowrap"
                                                        data-worker-credential-toggle
                                                        aria-pressed="false"
                                                    >
                                                        Reveal
                                                    </button>
                                                </div>
                                                <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                            </form>
                                            <form method="post" class="d-flex gap-2 align-items-center" autocomplete="off">
                                                <?php AdminBootstrap::renderCsrfField(); ?>
                                                <input type="hidden" name="action" value="<?= Html::escape(WorkerAction::UpdateNpsso->value); ?>">
                                                <input type="hidden" name="worker_id" value="<?= Html::escape((string) $worker->getId()); ?>">
                                                <label class="form-label small text-body-secondary mb-0 text-nowrap" for="npsso-<?= Html::escape((string) $worker->getId()); ?>">
                                                    NPSSO
                                                </label>
                                                <div class="d-flex gap-2 flex-grow-1" data-worker-credential-field>
                                                    <input
                                                        id="npsso-<?= Html::escape((string) $worker->getId()); ?>"
                                                        type="password"
                                                        name="npsso"
                                                        class="form-control form-control-sm"
                                                        placeholder="<?= Html::escape(WorkerCredentialMasker::mask($worker->getNpsso())); ?>"
                                                        maxlength="64"
                                                        autocomplete="off"
                                                        data-worker-credential-input
                                                        data-worker-credential="npsso"
                                                        data-worker-id="<?= Html::escape((string) $worker->getId()); ?>"
                                                    >
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-secondary text-nowrap"
                                                        data-worker-credential-toggle
                                                        aria-pressed="false"
                                                    >
                                                        Reveal
                                                    </button>
                                                </div>
                                                <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                            </form>
                                        </div>
                                    </td>
                                    <td class="text-nowrap">
                                        <?php if ($scanningLink !== null) { ?>
                                            <a href="<?= Html::escape($scanningLink); ?>">
                                                <?= $scanningDisplay; ?>
                                            </a>
                                        <?php } else { ?>
                                            <span class="text-body-secondary">Idle</span>
                                        <?php } ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <time
                                            class="js-localized-datetime"
                                            datetime="<?= Html::escape($scanStart->format(DATE_ATOM)); ?>"
                                        >
                                            <?= Html::escape($scanStart->format('Y-m-d H:i:s')); ?>
                                        </time>
                                    </td>
                                    <td>
                                        <?php if ($scanning === '') { ?>
                                            <span class="text-body-secondary">—</span>
                                        <?php } elseif ($scanProgress === null) { ?>
                                            <span class="text-body-secondary">Not reported</span>
                                        <?php } else { ?>
                                            <div class="small">
                                                <?php $title = $scanProgress->getTitle(); ?>
                                                <?php if ($title !== null) { ?>
                                                    <div>
                                                        <strong>Title:</strong>
                                                        <?= Html::escape($title); ?>
                                                    </div>
                                                <?php } ?>
                                                <?php
                                                $progressSummary = $scanProgress->getProgressSummary();
                                                $percentage = $scanProgress->getPercentage();
                                                ?>
                                                <?php if ($progressSummary !== null) { ?>
                                                    <div>
                                                        <strong>Progress:</strong>
                                                        <?= Html::escape($progressSummary); ?>
                                                        <?php if ($percentage !== null) { ?>
                                                            (<?= Html::escape(number_format($percentage, 1)); ?>%)
                                                        <?php } ?>
                                                    </div>
                                                <?php } ?>
                                                <?php $npCommunicationId = $scanProgress->getNpCommunicationId(); ?>
                                                <?php if ($npCommunicationId !== null) { ?>
                                                    <div>
                                                        <strong>NP Communication ID:</strong>
                                                        <?= Html::escape($npCommunicationId); ?>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <form
                                            method="post"
                                            onsubmit="return confirm('Restart worker #<?= Html::escape((string) $worker->getId()); ?>?');"
                                        >
                                            <?php AdminBootstrap::renderCsrfField(); ?>
                                            <input type="hidden" name="action" value="<?= Html::escape(WorkerAction::RestartWorker->value); ?>">
                                            <input type="hidden" name="worker_id" value="<?= Html::escape((string) $worker->getId()); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning">
                                                Restart
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
    </body>
</html>
