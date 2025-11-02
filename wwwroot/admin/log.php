<?php

declare(strict_types=1);

require_once '../init.php';
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
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <title>Admin ~ Logs</title>
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
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle table-sm">
                        <thead>
                            <tr>
                                <th scope="col" style="width: 6rem;">ID</th>
                                <th scope="col" style="width: 14rem;">Time</th>
                                <th scope="col">Message</th>
                                <th scope="col" class="text-center" style="width: 6rem;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pageResult->getEntries() as $entry) { ?>
                                <tr>
                                    <td class="text-nowrap">#<?= htmlspecialchars((string) $entry->getId(), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php $time = $entry->getTime(); ?>
                                        <time class="small text-body-secondary" datetime="<?= htmlspecialchars($time->format(DATE_ATOM), ENT_QUOTES, 'UTF-8'); ?>">
                                            <?= htmlspecialchars($time->format('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8'); ?> UTC
                                        </time>
                                    </td>
                                    <td><?= $entry->getFormattedMessage(); ?></td>
                                    <td class="text-center">
                                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this log entry?');">
                                            <input type="hidden" name="delete_id" value="<?= htmlspecialchars((string) $entry->getId(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>

            <?php if ($pagination !== '') { ?>
                <?= $pagination; ?>
            <?php } ?>
        </div>
    </body>
</html>
