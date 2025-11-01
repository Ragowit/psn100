<?php
declare(strict_types=1);

require_once __DIR__ . '/../classes/ExecutionEnvironmentConfigurator.php';

ExecutionEnvironmentConfigurator::create()
    ->addIniSetting('max_execution_time', '0')
    ->addIniSetting('max_input_time', '0')
    ->addIniSetting('mysql.connect_timeout', '0')
    ->enableUnlimitedExecution()
    ->configure();

require_once("../init.php");
require_once("../classes/TrophyMergeService.php");
require_once("../classes/Admin/TrophyMergeRequestHandler.php");

$mergeService = new TrophyMergeService($database);
$requestHandler = new TrophyMergeRequestHandler($mergeService);
$message = $requestHandler->handle($_POST ?? []);
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <title>Admin ~ Merge Games</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <form id="merge-form" method="post" autocomplete="off" class="row g-3">
                <div class="row">
                    <div class="col-12 col-md-6 col-xl-4">
                        <label class="form-label" for="merge-parent">Game Parent ID</label>
                        <input type="number" class="form-control" id="merge-parent" name="parent" inputmode="numeric">
                    </div>
                    <div class="col-12 col-md-6 col-xl-4">
                        <label class="form-label" for="merge-child">Game Child ID</label>
                        <input type="number" class="form-control" id="merge-child" name="child" inputmode="numeric">
                    </div>
                    <div class="col-12 col-md-6 col-xl-4">
                        <label class="form-label" for="merge-method">Method</label>
                        <select class="form-select" id="merge-method" name="method">
                            <option value="order">Order</option>
                            <option value="name">Name</option>
                            <option value="icon">Icon</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 col-md-6 col-xl-4">
                        <label class="form-label" for="merge-trophy-parent">Trophy Parent ID</label>
                        <input type="number" class="form-control" id="merge-trophy-parent" name="trophyparent" inputmode="numeric">
                    </div>
                    <div class="col-12 col-md-6 col-xl-4">
                        <label class="form-label" for="merge-trophy-child">Trophy Child ID</label>
                        <input type="text" class="form-control" id="merge-trophy-child" name="trophychild">
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 col-md-6 col-xl-4">
                        <label class="form-label" for="merge-clone">Clone Game ID</label>
                        <input type="number" class="form-control" id="merge-clone" name="clone" inputmode="numeric">
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 align-self-end">
                        <button type="submit" class="btn btn-primary" id="merge-submit">Submit</button>
                    </div>
                </div>
            </form>

            <div id="merge-progress-wrapper" class="mt-4 d-none">
                <div class="progress">
                    <div id="merge-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">0%</div>
                </div>
                <p id="merge-progress-message" class="text-body-secondary small mt-2">Preparing game merge…</p>
            </div>

            <div id="merge-result" class="mt-3">
                <?= $message; ?>
            </div>
        </div>
        <script>
            class GameMergeFormController {
                constructor({
                    formId,
                    childInputId,
                    parentInputId,
                    methodSelectId,
                    trophyChildInputId,
                    trophyParentInputId,
                    cloneInputId,
                    submitButtonId,
                    progressWrapperId,
                    progressBarId,
                    progressMessageId,
                    resultContainerId,
                }) {
                    this.form = document.getElementById(formId);
                    this.childInput = document.getElementById(childInputId);
                    this.parentInput = document.getElementById(parentInputId);
                    this.methodSelect = document.getElementById(methodSelectId);
                    this.trophyChildInput = document.getElementById(trophyChildInputId);
                    this.trophyParentInput = document.getElementById(trophyParentInputId);
                    this.cloneInput = document.getElementById(cloneInputId);
                    this.submitButton = document.getElementById(submitButtonId);
                    this.progressWrapper = document.getElementById(progressWrapperId);
                    this.progressBar = document.getElementById(progressBarId);
                    this.progressMessage = document.getElementById(progressMessageId);
                    this.resultContainer = document.getElementById(resultContainerId);
                }

                initialize() {
                    if (!this.hasRequiredElements()) {
                        return;
                    }

                    this.form.addEventListener('submit', (event) => this.handleSubmit(event));
                }

                hasRequiredElements() {
                    return [
                        this.form,
                        this.childInput,
                        this.parentInput,
                        this.methodSelect,
                        this.progressWrapper,
                        this.progressBar,
                        this.progressMessage,
                        this.resultContainer,
                        this.cloneInput,
                    ].every((element) => element instanceof HTMLElement);
                }

                handleSubmit(event) {
                    if (!this.shouldHandleGameMerge()) {
                        return;
                    }

                    event.preventDefault();
                    this.clearResult();
                    this.resetProgress();
                    this.showProgress();
                    this.setFormDisabled(true);

                    const childValue = (this.childInput.value || '').trim();
                    const parentValue = (this.parentInput.value || '').trim();
                    const methodValue = (this.methodSelect.value || 'order').trim().toLowerCase();

                    this.processMergeRequest(childValue, parentValue, methodValue)
                        .catch(() => {
                            // Errors are handled in processMergeRequest.
                        })
                        .finally(() => {
                            this.setFormDisabled(false);
                        });
                }

                shouldHandleGameMerge() {
                    const childValue = (this.childInput?.value || '').trim();
                    const parentValue = (this.parentInput?.value || '').trim();
                    const trophyParentValue = (this.trophyParentInput?.value || '').trim();
                    const trophyChildValue = (this.trophyChildInput?.value || '').trim();
                    const cloneValue = (this.cloneInput?.value || '').trim();

                    if (cloneValue !== '') {
                        return false;
                    }

                    if (trophyParentValue !== '' || trophyChildValue !== '') {
                        return false;
                    }

                    if (childValue === '' || parentValue === '') {
                        return false;
                    }

                    return /^\d+$/.test(childValue) && /^\d+$/.test(parentValue);
                }

                async processMergeRequest(childId, parentId, method) {
                    try {
                        const response = await fetch('merge_process.php', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/x-ndjson, application/json',
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                child: childId,
                                parent: parentId,
                                method,
                            }),
                        });

                        const contentType = response.headers.get('Content-Type') ?? '';
                        let finalPayload = null;

                        if (contentType.includes('application/x-ndjson')) {
                            finalPayload = await this.consumeProgressStream(response);
                        } else {
                            let data = null;

                            try {
                                data = await response.json();
                            } catch (error) {
                                data = null;
                            }

                            if (response.ok && data && data.success) {
                                finalPayload = {
                                    type: 'complete',
                                    success: true,
                                    progress: 100,
                                    message: data.message ?? 'The games have been merged.',
                                };
                            } else {
                                const errorMessage = data && (data.error || data.message)
                                    ? (data.error || data.message)
                                    : 'Unable to merge the specified games.';

                                finalPayload = {
                                    type: 'error',
                                    success: false,
                                    progress: 100,
                                    error: errorMessage,
                                };
                            }
                        }

                        this.setProgressAnimated(false);

                        if (finalPayload && finalPayload.success) {
                            const successMessage = finalPayload.message ?? 'The games have been merged.';
                            this.markProgressAsSuccess(successMessage);
                            this.showAlert('success', successMessage, true);

                            return;
                        }

                        const errorMessage = (finalPayload && (finalPayload.error || finalPayload.message))
                            ? (finalPayload.error || finalPayload.message)
                            : 'Unable to merge the specified games.';

                        this.markProgressAsError(finalPayload?.message ?? finalPayload?.error ?? 'Merge failed.');
                        this.showAlert('danger', errorMessage);
                    } catch (error) {
                        this.setProgressAnimated(false);
                        this.markProgressAsError('Merge failed.');
                        this.showAlert('danger', 'An unexpected error occurred while merging the games.');
                    }
                }

                async consumeProgressStream(response) {
                    const reader = response.body?.getReader();

                    if (!reader) {
                        throw new Error('Streaming reader unavailable.');
                    }

                    const decoder = new TextDecoder();
                    let buffer = '';
                    let finalPayload = null;

                    const processPayload = (payload) => {
                        if (!payload || typeof payload !== 'object') {
                            return;
                        }

                        if (payload.type === 'progress') {
                            const progressValue = this.parseProgressValue(payload.progress);
                            const progressMessage = typeof payload.message === 'string' ? payload.message : null;
                            this.updateProgress(progressValue, progressMessage);
                            return;
                        }

                        if (payload.type === 'complete' || payload.type === 'error') {
                            finalPayload = payload;
                            const progressValue = this.parseProgressValue(payload.progress);
                            const finalMessage = payload.message ?? payload.error ?? null;
                            this.updateProgress(progressValue, finalMessage);
                        }
                    };

                    while (true) {
                        const { done, value } = await reader.read();

                        if (done) {
                            break;
                        }

                        buffer += decoder.decode(value, { stream: true });
                        const lines = buffer.split('\n');
                        buffer = lines.pop() ?? '';

                        for (const line of lines) {
                            const trimmed = line.trim();

                            if (trimmed === '') {
                                continue;
                            }

                            try {
                                const payload = JSON.parse(trimmed);
                                processPayload(payload);
                            } catch (error) {
                                // Ignore malformed payloads.
                            }
                        }
                    }

                    buffer += decoder.decode();
                    const remainingLines = buffer.split('\n');
                    buffer = remainingLines.pop() ?? '';

                    for (const line of remainingLines) {
                        const trimmed = line.trim();

                        if (trimmed === '') {
                            continue;
                        }

                        try {
                            const payload = JSON.parse(trimmed);
                            processPayload(payload);
                        } catch (error) {
                            // Ignore malformed payloads.
                        }
                    }

                    const remaining = buffer.trim();

                    if (remaining !== '') {
                        try {
                            const payload = JSON.parse(remaining);
                            processPayload(payload);
                        } catch (error) {
                            // Ignore malformed payloads.
                        }
                    }

                    if (finalPayload === null) {
                        throw new Error('Missing final status from merge process.');
                    }

                    return finalPayload;
                }

                parseProgressValue(value) {
                    if (typeof value === 'number' && Number.isFinite(value)) {
                        return value;
                    }

                    if (typeof value === 'string') {
                        const parsed = Number(value.trim());

                        if (Number.isFinite(parsed)) {
                            return parsed;
                        }
                    }

                    return 0;
                }

                resetProgress() {
                    if (!this.progressBar || !this.progressMessage) {
                        return;
                    }

                    this.clearProgressStatus();
                    this.setProgressAnimated(true);
                    this.progressBar.style.width = '0%';
                    this.progressBar.setAttribute('aria-valuenow', '0');
                    this.progressBar.textContent = '0%';
                    this.progressMessage.textContent = 'Preparing game merge…';
                }

                updateProgress(value, message = null) {
                    if (!this.progressBar) {
                        return;
                    }

                    const clampedValue = Math.min(100, Math.max(0, Number(value) || 0));
                    const displayValue = Math.round(clampedValue);

                    this.progressBar.style.width = `${clampedValue}%`;
                    this.progressBar.setAttribute('aria-valuenow', String(displayValue));
                    this.progressBar.textContent = `${displayValue}%`;

                    if (message !== null && this.progressMessage) {
                        this.progressMessage.textContent = this.toPlainText(message);
                    }
                }

                setProgressAnimated(enabled) {
                    if (!this.progressBar) {
                        return;
                    }

                    if (enabled) {
                        this.progressBar.classList.add('progress-bar-striped', 'progress-bar-animated');
                    } else {
                        this.progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
                    }
                }

                clearProgressStatus() {
                    if (!this.progressBar) {
                        return;
                    }

                    this.progressBar.classList.remove('bg-success', 'bg-danger');
                }

                markProgressAsSuccess(message) {
                    this.clearProgressStatus();
                    if (this.progressBar) {
                        this.progressBar.classList.add('bg-success');
                    }
                    this.updateProgress(100, message ?? 'The games have been merged.');
                }

                markProgressAsError(message) {
                    this.clearProgressStatus();
                    if (this.progressBar) {
                        this.progressBar.classList.add('bg-danger');
                    }
                    this.updateProgress(100, message ?? 'Merge failed.');
                }

                showProgress() {
                    if (this.progressWrapper) {
                        this.progressWrapper.classList.remove('d-none');
                    }
                }

                clearResult() {
                    if (!this.resultContainer) {
                        return;
                    }

                    this.resultContainer.replaceChildren();
                }

                showAlert(type, message, allowHtml = false) {
                    if (!this.resultContainer) {
                        return;
                    }

                    const alert = document.createElement('div');
                    alert.className = `alert alert-${type}`;
                    alert.setAttribute('role', 'alert');

                    if (allowHtml) {
                        alert.innerHTML = message ?? '';
                    } else {
                        alert.textContent = message ?? '';
                    }

                    this.resultContainer.replaceChildren(alert);
                }

                setFormDisabled(disabled) {
                    if (!this.form) {
                        return;
                    }

                    const elements = this.form.querySelectorAll('input, select, button, textarea');

                    elements.forEach((element) => {
                        element.disabled = disabled;
                    });
                }

                toPlainText(value) {
                    if (typeof value !== 'string') {
                        return '';
                    }

                    const container = document.createElement('div');
                    container.innerHTML = value;
                    return container.textContent ?? '';
                }
            }

            new GameMergeFormController({
                formId: 'merge-form',
                childInputId: 'merge-child',
                parentInputId: 'merge-parent',
                methodSelectId: 'merge-method',
                trophyChildInputId: 'merge-trophy-child',
                trophyParentInputId: 'merge-trophy-parent',
                cloneInputId: 'merge-clone',
                submitButtonId: 'merge-submit',
                progressWrapperId: 'merge-progress-wrapper',
                progressBarId: 'merge-progress-bar',
                progressMessageId: 'merge-progress-message',
                resultContainerId: 'merge-result',
            }).initialize();
        </script>
    </body>
</html>
