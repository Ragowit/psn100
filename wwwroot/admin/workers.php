<?php

declare(strict_types=1);

require_once '../init.php';
require_once '../classes/Admin/AdminRequest.php';
require_once '../classes/Admin/WorkerService.php';

$workerService = new WorkerService($database);
$request = AdminRequest::fromGlobals($_SERVER ?? [], $_POST ?? []);

$successMessage = null;
$errorMessage = null;

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

$workers = $workerService->fetchWorkers();
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
                                <th scope="col" style="width: 4rem;">ID</th>
                                <th scope="col" style="width: 18rem;">NPSSO</th>
                                <th scope="col" style="width: 16rem;">Scanning</th>
                                <th scope="col" style="width: 16rem;">Scan Start</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($workers as $worker) { ?>
                                <?php
                                $scanStart = $worker->getScanStart();
                                $scanning = $worker->getScanning();
                                $scanningDisplay = htmlspecialchars($scanning, ENT_QUOTES, 'UTF-8');
                                $scanningLink = $scanning !== '' ? '/player/' . rawurlencode($scanning) : null;
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
                const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone ?? '';

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
                    const timeZoneSuffix = timeZone !== '' ? ` ${timeZone}` : '';

                    element.textContent = `${formattedDate} ${formattedTime}${timeZoneSuffix}`;
                    element.setAttribute('data-timezone', timeZone);
                });
            });
        </script>
    </body>
</html>
