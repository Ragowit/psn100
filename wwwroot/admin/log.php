<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once '../classes/StaticAsset.php';
require_once '../classes/Admin/LogEntry.php';
require_once '../classes/Admin/LogEntryFormatter.php';
require_once '../classes/Admin/LogService.php';
require_once '../classes/Admin/LogPageResult.php';
require_once '../classes/Admin/LogPage.php';

$formatter = new LogEntryFormatter($database, $utility);
$logService = new LogService($database, $formatter);
$logPage = new LogPage($logService);

$pageResult = $logPage->handle($_GET, $_POST, $_SERVER['REQUEST_METHOD'] ?? 'GET');

$pagination = '';

if ($pageResult->getTotalPages() > 1) {
    $pagination = $paginationRenderer->render(
        $pageResult->getCurrentPage(),
        $pageResult->getTotalPages(),
        static fn (int $page): array => ['page' => $page],
        'Log pagination'
    );
}
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="<?= htmlspecialchars(BootstrapAssets::stylesheetUrl(), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
        <title>Admin ~ Logs</title>
        <script src="<?= htmlspecialchars(StaticAsset::url('/js/localized-date-formatter.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
        <script src="<?= htmlspecialchars(StaticAsset::url('/js/admin-log-bulk-actions.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    </head>
    <body>
        <div class="container py-4">
            <div class="mb-3">
                <a href="/admin/">Back</a>
            </div>

            <?php if ($pageResult->getSuccessMessage() !== null) { ?>
                <div class="alert alert-success" role="alert">
                    <?= htmlspecialchars($pageResult->getSuccessMessage(), ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php } ?>

            <?php if ($pageResult->getErrorMessage() !== null) { ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($pageResult->getErrorMessage(), ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php } ?>

            <?php if ($pagination !== '') { ?>
                <?= $pagination; ?>
            <?php } ?>

            <?php if ($pageResult->getEntries() === []) { ?>
                <div class="alert alert-info">No log entries found.</div>
            <?php } else { ?>
                <form method="post" id="log-entries-form">
                    <?php AdminBootstrap::renderCsrfField(); ?>
                    <div class="d-flex justify-content-end align-items-center mb-2 gap-2">
                        <button type="submit" name="delete_selected" value="1" class="btn btn-sm btn-danger" id="delete-selected-button" disabled>
                            Delete selected
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle table-sm">
                            <thead>
                                <tr>
                                    <th scope="col" class="text-center" style="width: 3rem;">
                                        <input type="checkbox" class="form-check-input" id="select-all-log-entries">
                                    </th>
                                    <th scope="col" style="width: 6rem;">ID</th>
                                    <th scope="col" style="width: 14rem;">Time</th>
                                    <th scope="col">Message</th>
                                    <th scope="col" class="text-center" style="width: 6rem;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pageResult->getEntries() as $entry) { ?>
                                    <tr>
                                        <td class="text-center">
                                            <input
                                                type="checkbox"
                                                class="form-check-input js-log-checkbox"
                                                name="delete_ids[]"
                                                value="<?= htmlspecialchars((string) $entry->getId(), ENT_QUOTES, 'UTF-8'); ?>"
                                                aria-label="Select log entry #<?= htmlspecialchars((string) $entry->getId(), ENT_QUOTES, 'UTF-8'); ?>"
                                            >
                                        </td>
                                        <td class="text-nowrap">#<?= htmlspecialchars((string) $entry->getId(), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php $time = $entry->getTime(); ?>
                                            <time class="small text-body-secondary js-localized-datetime" datetime="<?= htmlspecialchars($time->format(DATE_ATOM), ENT_QUOTES, 'UTF-8'); ?>">
                                                <?= htmlspecialchars($time->format('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8'); ?>
                                            </time>
                                        </td>
                                        <td><?= $entry->getFormattedMessage(); ?></td>
                                        <td class="text-center">
                                            <button
                                                type="submit"
                                                name="delete_id"
                                                value="<?= htmlspecialchars((string) $entry->getId(), ENT_QUOTES, 'UTF-8'); ?>"
                                                class="btn btn-sm btn-danger js-log-delete-button"
                                            >
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            <?php } ?>

            <?php if ($pagination !== '') { ?>
                <?= $pagination; ?>
            <?php } ?>
        </div>
    </body>
</html>
