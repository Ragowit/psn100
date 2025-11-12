<?php

declare(strict_types=1);

require_once '../init.php';
require_once '../classes/Admin/AdminRequest.php';
require_once '../classes/Admin/WorkerService.php';

$workerService = new WorkerService($database);
$request = AdminRequest::fromGlobals($_SERVER ?? [], $_POST ?? []);

$successMessage = null;
$errorMessage = null;

$sortField = 'scan_start';
$sortDirection = 'asc';

$sortParam = $_GET['sort'] ?? null;

if (is_string($sortParam)) {
    $normalizedSort = strtolower(trim($sortParam));

    if ($normalizedSort === 'id') {
        $sortField = 'id';
    }
}

$directionParam = $_GET['direction'] ?? null;

if (is_string($directionParam)) {
    $normalizedDirection = strtolower(trim($directionParam));

    if (in_array($normalizedDirection, ['asc', 'desc'], true)) {
        $sortDirection = $normalizedDirection;
    }
}

if ($request->isPost()) {
    $workerId = $request->getPostPositiveInt('worker_id');
    $npsso = $request->getPostString('npsso');

    if ($workerId === null) {
        $errorMessage = 'Invalid worker selected.';
    } elseif ($npsso === '') {
        $errorMessage = 'The NPSSO value cannot be empty.';
    } elseif (strlen($npsso) > 64) {
        $errorMessage = 'The NPSSO value must be 64 characters or fewer.';
    } else {
        try {
            $updated = $workerService->updateWorkerNpsso($workerId, $npsso);

            if ($updated) {
                $successMessage = 'Worker NPSSO updated successfully.';
            } else {
                $errorMessage = 'Unable to update NPSSO. Please verify the worker still exists.';
            }
        } catch (Throwable $exception) {
            $errorMessage = 'An unexpected error occurred while updating the NPSSO value.';
        }
    }
}

$workers = $workerService->fetchWorkers($sortField, strtoupper($sortDirection));

$idSortNextDirection = $sortField === 'id' && $sortDirection === 'asc' ? 'desc' : 'asc';
$scanStartNextDirection = $sortField === 'scan_start' && $sortDirection === 'asc' ? 'desc' : 'asc';

$idSortUrl = '?' . http_build_query(['sort' => 'id', 'direction' => $idSortNextDirection]);
$scanStartSortUrl = '?' . http_build_query(['sort' => 'scan_start', 'direction' => $scanStartNextDirection]);

$idSortIndicator = '';
$scanStartSortIndicator = '';

if ($sortField === 'id') {
    $idSortIndicator = $sortDirection === 'asc' ? ' ▲' : ' ▼';
}

if ($sortField === 'scan_start') {
    $scanStartSortIndicator = $sortDirection === 'asc' ? ' ▲' : ' ▼';
}
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
            rel="stylesheet"
            integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
            crossorigin="anonymous"
        >
        <title>Admin ~ Workers</title>
    </head>
    <body>
        <div class="container py-4">
            <div class="mb-3">
                <a href="/admin/">Back</a>
            </div>

            <?php if ($successMessage !== null) { ?>
                <div class="alert alert-success" role="alert">
                    <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php } ?>

            <?php if ($errorMessage !== null) { ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
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
                                    <a class="text-decoration-none text-reset" href="<?= htmlspecialchars($idSortUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                        ID
                                        <?php if ($idSortIndicator !== '') { ?>
                                            <span class="ms-1"><?= htmlspecialchars(trim($idSortIndicator), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php } ?>
                                    </a>
                                </th>
                                <th scope="col" style="width: 18rem;">NPSSO</th>
                                <th scope="col" style="width: 16rem;">Scanning</th>
                                <th scope="col" style="width: 16rem;">
                                    <a class="text-decoration-none text-reset" href="<?= htmlspecialchars($scanStartSortUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                        Scan Start
                                        <?php if ($scanStartSortIndicator !== '') { ?>
                                            <span class="ms-1"><?= htmlspecialchars(trim($scanStartSortIndicator), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php } ?>
                                    </a>
                                </th>
                                <th scope="col" style="width: 20rem;">Scan Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($workers as $worker) { ?>
                                <?php
                                $scanStart = $worker->getScanStart();
                                $scanning = $worker->getScanning();
                                $scanningDisplay = htmlspecialchars($scanning, ENT_QUOTES, 'UTF-8');
                                $scanningLink = $scanning !== '' ? '/player/' . rawurlencode($scanning) : null;
                                $scanProgress = $worker->getScanProgress();
                                ?>
                                <tr>
                                    <td class="text-nowrap">#<?= htmlspecialchars((string) $worker->getId(), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <form method="post" class="d-flex gap-2 align-items-center" autocomplete="off">
                                            <input type="hidden" name="worker_id" value="<?= htmlspecialchars((string) $worker->getId(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input
                                                type="text"
                                                name="npsso"
                                                class="form-control form-control-sm"
                                                value="<?= htmlspecialchars($worker->getNpsso(), ENT_QUOTES, 'UTF-8'); ?>"
                                                maxlength="64"
                                            >
                                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                        </form>
                                    </td>
                                    <td class="text-nowrap">
                                        <?php if ($scanningLink !== null) { ?>
                                            <a href="<?= htmlspecialchars($scanningLink, ENT_QUOTES, 'UTF-8'); ?>">
                                                <?= $scanningDisplay; ?>
                                            </a>
                                        <?php } else { ?>
                                            <span class="text-body-secondary">Idle</span>
                                        <?php } ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <time
                                            class="js-localized-datetime"
                                            datetime="<?= htmlspecialchars($scanStart->format(DATE_ATOM), ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            <?= htmlspecialchars($scanStart->format('Y-m-d H:i:s T'), ENT_QUOTES, 'UTF-8'); ?>
                                        </time>
                                    </td>
                                    <td>
                                        <?php if ($scanning === '') { ?>
                                            <span class="text-body-secondary">—</span>
                                        <?php } elseif ($scanProgress === null) { ?>
                                            <span class="text-body-secondary">Not reported</span>
                                        <?php } else { ?>
                                            <div class="small">
                                                <?php if (array_key_exists('title', $scanProgress)) { ?>
                                                    <div>
                                                        <strong>Title:</strong>
                                                        <?= htmlspecialchars((string) $scanProgress['title'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </div>
                                                <?php } ?>
                                                <?php
                                                $current = $scanProgress['current'] ?? null;
                                                $total = $scanProgress['total'] ?? null;
                                                $progressSummary = null;
                                                $percentage = null;

                                                if (is_int($current) && is_int($total) && $total > 0) {
                                                    $progressSummary = sprintf('%d / %d', $current, $total);
                                                    $percentage = round(($current / $total) * 100, 1);
                                                } elseif (is_int($current) && $total === null) {
                                                    $progressSummary = (string) $current;
                                                } elseif (is_int($total) && $current === null) {
                                                    $progressSummary = '0 / ' . $total;
                                                }
                                                ?>
                                                <?php if ($progressSummary !== null) { ?>
                                                    <div>
                                                        <strong>Progress:</strong>
                                                        <?= htmlspecialchars($progressSummary, ENT_QUOTES, 'UTF-8'); ?>
                                                        <?php if ($percentage !== null) { ?>
                                                            (<?= htmlspecialchars(number_format($percentage, 1), ENT_QUOTES, 'UTF-8'); ?>%)
                                                        <?php } ?>
                                                    </div>
                                                <?php } ?>
                                                <?php if (array_key_exists('npCommunicationId', $scanProgress)) { ?>
                                                    <div>
                                                        <strong>NP Communication ID:</strong>
                                                        <?= htmlspecialchars((string) $scanProgress['npCommunicationId'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof Intl !== 'object' || typeof Intl.DateTimeFormat !== 'function') {
                    return;
                }

                const pad = (value) => value.toString().padStart(2, '0');
                document.querySelectorAll('.js-localized-datetime').forEach((element) => {
                    if (!(element instanceof HTMLElement)) {
                        return;
                    }

                    const isoString = element.getAttribute('datetime');

                    if (!isoString) {
                        return;
                    }

                    const date = new Date(isoString);

                    if (Number.isNaN(date.getTime())) {
                        return;
                    }

                    const formattedDate = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
                    const formattedTime = `${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
                    element.textContent = `${formattedDate} ${formattedTime}`;
                    element.removeAttribute('data-timezone');
                });
            });
        </script>
    </body>
</html>
