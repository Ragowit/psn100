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
                <form method="post" id="log-entries-form">
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
                                                <?= htmlspecialchars($time->format('Y-m-d H:i:s T'), ENT_QUOTES, 'UTF-8'); ?>
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
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof Intl === 'object' && typeof Intl.DateTimeFormat === 'function') {
                    const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone ?? '';
                    const pad = (value) => value.toString().padStart(2, '0');

                    document.querySelectorAll('.js-localized-datetime').forEach((timeElement) => {
                        const isoString = timeElement.getAttribute('datetime');

                        if (!isoString) {
                            return;
                        }

                        const date = new Date(isoString);

                        if (Number.isNaN(date.getTime())) {
                            return;
                        }

                        const formattedDate = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
                        const formattedTime = `${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;

                        timeElement.textContent = `${formattedDate} ${formattedTime}${timeZone ? ` ${timeZone}` : ''}`;
                        timeElement.setAttribute('data-timezone', timeZone);
                    });
                }

                const form = document.getElementById('log-entries-form');

                if (!(form instanceof HTMLFormElement)) {
                    return;
                }

                const selectAllCheckbox = document.getElementById('select-all-log-entries');
                const deleteSelectedButton = document.getElementById('delete-selected-button');
                const logCheckboxes = Array.from(form.querySelectorAll('.js-log-checkbox'))
                    .filter((element) => element instanceof HTMLInputElement);
                let lastChangedCheckbox = null;

                const updateBulkDeleteState = () => {
                    if (!(deleteSelectedButton instanceof HTMLButtonElement)) {
                        return;
                    }

                    const selectedCount = logCheckboxes.filter((checkbox) => checkbox.checked).length;

                    deleteSelectedButton.disabled = selectedCount === 0;

                    if (selectAllCheckbox instanceof HTMLInputElement) {
                        if (logCheckboxes.length === 0) {
                            selectAllCheckbox.checked = false;
                            selectAllCheckbox.indeterminate = false;
                        } else if (selectedCount === 0) {
                            selectAllCheckbox.checked = false;
                            selectAllCheckbox.indeterminate = false;
                        } else if (selectedCount === logCheckboxes.length) {
                            selectAllCheckbox.checked = true;
                            selectAllCheckbox.indeterminate = false;
                        } else {
                            selectAllCheckbox.checked = false;
                            selectAllCheckbox.indeterminate = true;
                        }
                    }
                };

                if (selectAllCheckbox instanceof HTMLInputElement) {
                    selectAllCheckbox.addEventListener('change', () => {
                        logCheckboxes.forEach((checkbox) => {
                            checkbox.checked = selectAllCheckbox.checked;
                        });

                        updateBulkDeleteState();
                    });
                }

                logCheckboxes.forEach((checkbox) => {
                    checkbox.addEventListener('click', (event) => {
                        if (!(event instanceof MouseEvent)) {
                            return;
                        }

                        if (!event.shiftKey || lastChangedCheckbox === null || lastChangedCheckbox === checkbox) {
                            return;
                        }

                        const startIndex = logCheckboxes.indexOf(lastChangedCheckbox);
                        const endIndex = logCheckboxes.indexOf(checkbox);

                        if (startIndex === -1 || endIndex === -1) {
                            return;
                        }

                        const [fromIndex, toIndex] = startIndex < endIndex
                            ? [startIndex, endIndex]
                            : [endIndex, startIndex];
                        const shouldCheck = checkbox.checked;

                        for (let index = fromIndex; index <= toIndex; index += 1) {
                            logCheckboxes[index].checked = shouldCheck;
                        }

                        updateBulkDeleteState();
                    });

                    checkbox.addEventListener('change', () => {
                        lastChangedCheckbox = checkbox;
                        updateBulkDeleteState();
                    });
                });

                updateBulkDeleteState();

                if (deleteSelectedButton instanceof HTMLButtonElement) {
                    deleteSelectedButton.addEventListener('click', (event) => {
                        const hasSelection = logCheckboxes.some((checkbox) => checkbox.checked);

                        if (!hasSelection) {
                            event.preventDefault();

                            return;
                        }

                        if (!window.confirm('Are you sure you want to delete the selected log entries?')) {
                            event.preventDefault();
                        }
                    });
                }

                form.querySelectorAll('.js-log-delete-button').forEach((button) => {
                    button.addEventListener('click', (event) => {
                        if (!window.confirm('Are you sure you want to delete this log entry?')) {
                            event.preventDefault();
                        }
                    });
                });
            });
        </script>
    </body>
</html>
