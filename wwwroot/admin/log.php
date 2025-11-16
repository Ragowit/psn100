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
            class LocalizedDateTimeFormatter {
                constructor(selector = '.js-localized-datetime') {
                    this.selector = selector;
                    this.timeZone = '';
                }

                initialize() {
                    if (!this.isSupported()) {
                        return;
                    }

                    this.timeZone = this.resolveTimeZone();
                    const elements = Array.from(document.querySelectorAll(this.selector));

                    elements.forEach((element) => this.formatElement(element));
                }

                isSupported() {
                    return typeof Intl === 'object' && typeof Intl.DateTimeFormat === 'function';
                }

                resolveTimeZone() {
                    try {
                        return Intl.DateTimeFormat().resolvedOptions().timeZone ?? '';
                    } catch (error) {
                        return '';
                    }
                }

                formatElement(element) {
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

                    const formattedDate = `${date.getFullYear()}-${this.pad(date.getMonth() + 1)}-${this.pad(date.getDate())}`;
                    const formattedTime = `${this.pad(date.getHours())}:${this.pad(date.getMinutes())}:${this.pad(date.getSeconds())}`;
                    const suffix = this.timeZone !== '' ? ` ${this.timeZone}` : '';

                    element.textContent = `${formattedDate} ${formattedTime}${suffix}`;
                    element.setAttribute('data-timezone', this.timeZone);
                }

                pad(value) {
                    return value.toString().padStart(2, '0');
                }
            }

            class LogEntriesBulkActionManager {
                constructor({
                    formId = 'log-entries-form',
                    selectAllCheckboxId = 'select-all-log-entries',
                    deleteButtonId = 'delete-selected-button',
                    checkboxSelector = '.js-log-checkbox',
                    deleteButtonSelector = '.js-log-delete-button',
                } = {}) {
                    this.formId = formId;
                    this.selectAllCheckboxId = selectAllCheckboxId;
                    this.deleteButtonId = deleteButtonId;
                    this.checkboxSelector = checkboxSelector;
                    this.deleteButtonSelector = deleteButtonSelector;
                    this.form = null;
                    this.selectAllCheckbox = null;
                    this.deleteSelectedButton = null;
                    this.logCheckboxes = [];
                    this.lastChangedCheckbox = null;
                }

                initialize() {
                    this.form = document.getElementById(this.formId);

                    if (!(this.form instanceof HTMLFormElement)) {
                        return;
                    }

                    this.selectAllCheckbox = this.resolveCheckbox(this.selectAllCheckboxId);
                    this.deleteSelectedButton = this.resolveButton(this.deleteButtonId);
                    this.logCheckboxes = this.resolveLogCheckboxes();

                    this.bindEvents();
                    this.updateBulkDeleteState();
                    this.bindSingleDeleteButtons();
                }

                resolveCheckbox(id) {
                    const element = document.getElementById(id);

                    return element instanceof HTMLInputElement ? element : null;
                }

                resolveButton(id) {
                    const element = document.getElementById(id);

                    return element instanceof HTMLButtonElement ? element : null;
                }

                resolveLogCheckboxes() {
                    if (!(this.form instanceof HTMLFormElement)) {
                        return [];
                    }

                    return Array.from(this.form.querySelectorAll(this.checkboxSelector))
                        .filter((element) => element instanceof HTMLInputElement);
                }

                bindEvents() {
                    if (this.selectAllCheckbox) {
                        this.selectAllCheckbox.addEventListener('change', () => this.handleSelectAllChange());
                    }

                    this.logCheckboxes.forEach((checkbox) => {
                        checkbox.addEventListener('click', (event) => this.handleCheckboxClick(event, checkbox));
                        checkbox.addEventListener('change', () => this.handleCheckboxChange(checkbox));
                    });

                    if (this.deleteSelectedButton) {
                        this.deleteSelectedButton.addEventListener('click', (event) => this.handleBulkDeleteClick(event));
                    }
                }

                handleSelectAllChange() {
                    const shouldCheck = this.selectAllCheckbox?.checked ?? false;

                    this.logCheckboxes.forEach((checkbox) => {
                        checkbox.checked = shouldCheck;
                    });

                    this.updateBulkDeleteState();
                }

                handleCheckboxClick(event, checkbox) {
                    if (!(event instanceof MouseEvent) || !event.shiftKey) {
                        return;
                    }

                    if (this.lastChangedCheckbox === null || this.lastChangedCheckbox === checkbox) {
                        return;
                    }

                    const startIndex = this.logCheckboxes.indexOf(this.lastChangedCheckbox);
                    const endIndex = this.logCheckboxes.indexOf(checkbox);

                    if (startIndex === -1 || endIndex === -1) {
                        return;
                    }

                    const [fromIndex, toIndex] = startIndex < endIndex
                        ? [startIndex, endIndex]
                        : [endIndex, startIndex];
                    const shouldCheck = checkbox.checked;

                    for (let index = fromIndex; index <= toIndex; index += 1) {
                        this.logCheckboxes[index].checked = shouldCheck;
                    }

                    this.updateBulkDeleteState();
                }

                handleCheckboxChange(checkbox) {
                    this.lastChangedCheckbox = checkbox;
                    this.updateBulkDeleteState();
                }

                handleBulkDeleteClick(event) {
                    const hasSelection = this.logCheckboxes.some((checkbox) => checkbox.checked);

                    if (!hasSelection) {
                        event.preventDefault();

                        return;
                    }

                    if (!window.confirm('Are you sure you want to delete the selected log entries?')) {
                        event.preventDefault();
                    }
                }

                bindSingleDeleteButtons() {
                    if (!(this.form instanceof HTMLFormElement)) {
                        return;
                    }

                    const buttons = Array.from(this.form.querySelectorAll(this.deleteButtonSelector))
                        .filter((element) => element instanceof HTMLElement);

                    buttons.forEach((button) => {
                        button.addEventListener('click', (event) => {
                            if (!window.confirm('Are you sure you want to delete this log entry?')) {
                                event.preventDefault();
                            }
                        });
                    });
                }

                updateBulkDeleteState() {
                    const selectedCount = this.logCheckboxes.filter((checkbox) => checkbox.checked).length;

                    if (this.deleteSelectedButton) {
                        this.deleteSelectedButton.disabled = selectedCount === 0;
                    }

                    if (!this.selectAllCheckbox) {
                        return;
                    }

                    if (this.logCheckboxes.length === 0) {
                        this.selectAllCheckbox.checked = false;
                        this.selectAllCheckbox.indeterminate = false;

                        return;
                    }

                    if (selectedCount === 0) {
                        this.selectAllCheckbox.checked = false;
                        this.selectAllCheckbox.indeterminate = false;

                        return;
                    }

                    if (selectedCount === this.logCheckboxes.length) {
                        this.selectAllCheckbox.checked = true;
                        this.selectAllCheckbox.indeterminate = false;

                        return;
                    }

                    this.selectAllCheckbox.checked = false;
                    this.selectAllCheckbox.indeterminate = true;
                }
            }

            document.addEventListener('DOMContentLoaded', () => {
                const dateFormatter = new LocalizedDateTimeFormatter('.js-localized-datetime');
                dateFormatter.initialize();

                const bulkActionManager = new LogEntriesBulkActionManager();
                bulkActionManager.initialize();
            });
        </script>
    </body>
</html>
