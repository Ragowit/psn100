<?php
require_once("../vendor/autoload.php");
require_once("../init.php");
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <title>Admin ~ Rescan Game</title>
        <style>
            .diff-card .card-header {
                font-weight: 600;
            }

            .diff-entry + .diff-entry {
                margin-top: 1rem;
            }

            .diff-metadata {
                font-size: 0.875rem;
                color: var(--bs-secondary-color);
                margin-bottom: 0.5rem;
            }

            .diff-pane {
                border: 1px solid var(--bs-border-color);
                border-radius: var(--bs-border-radius);
                padding: 0.75rem;
                background-color: var(--bs-body-bg);
                min-height: 120px;
            }

            .diff-pane-old {
                border-color: rgba(220, 53, 69, 0.4);
                background-color: rgba(220, 53, 69, 0.1);
            }

            .diff-pane-new {
                border-color: rgba(25, 135, 84, 0.4);
                background-color: rgba(25, 135, 84, 0.1);
            }

            .diff-pane-header {
                font-size: 0.75rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                margin-bottom: 0.5rem;
                color: var(--bs-secondary-color);
            }

            .diff-pre {
                margin-bottom: 0;
                white-space: pre-wrap;
                word-break: break-word;
                font-family: var(--bs-font-monospace);
                font-size: 0.875rem;
            }
        </style>
    </head>
    <body>
        <div class="container py-4">
            <a href="/admin/">Back</a>
            <h1 class="h3 mt-3">Rescan Game</h1>
            <p class="text-body-secondary">Enter the numeric game identifier to trigger a rescan.</p>

            <form id="rescan-form" class="row row-cols-lg-auto g-3 align-items-center" autocomplete="off">
                <div class="col-12">
                    <label class="form-label" for="game">Game ID</label>
                    <input type="text" class="form-control" id="game" name="game" inputmode="numeric" pattern="[0-9]*" required>
                </div>
                <div class="col-12 align-self-end">
                    <button type="submit" class="btn btn-primary" id="rescan-submit">Rescan</button>
                </div>
            </form>

            <div id="progress-wrapper" class="mt-4 d-none">
                <div class="progress">
                    <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">0%</div>
                </div>
                <p id="progress-message" class="text-body-secondary small mt-2">Preparing rescan…</p>
            </div>

            <div id="log-wrapper" class="mt-3 d-none">
                <h2 class="h6 mb-2">Activity log</h2>
                <div id="log-entries" class="border rounded p-2 small font-monospace bg-body-tertiary" style="max-height: 240px; overflow-y: auto;"></div>
            </div>

            <div id="result" class="mt-3"></div>
        </div>

        <script>
            class RescanFormController {
                constructor({
                    formId,
                    gameInputId,
                    submitButtonId,
                    progressWrapperId,
                    progressBarId,
                    progressMessageId,
                    logWrapperId,
                    logEntriesId,
                    resultId,
                }) {
                    this.form = document.getElementById(formId);
                    this.gameInput = document.getElementById(gameInputId);
                    this.submitButton = document.getElementById(submitButtonId);
                    this.progressWrapper = document.getElementById(progressWrapperId);
                    this.progressBar = document.getElementById(progressBarId);
                    this.progressMessage = document.getElementById(progressMessageId);
                    this.logWrapper = document.getElementById(logWrapperId);
                    this.logEntries = document.getElementById(logEntriesId);
                    this.result = document.getElementById(resultId);
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
                        this.gameInput,
                        this.submitButton,
                        this.progressWrapper,
                        this.progressBar,
                        this.progressMessage,
                        this.logWrapper,
                        this.logEntries,
                        this.result,
                    ].every((element) => element instanceof HTMLElement);
                }

                handleSubmit(event) {
                    event.preventDefault();
                    this.clearResult();
                    this.resetLog();

                    const trimmedValue = (this.gameInput.value || '').trim();
                    if (!/^\d+$/.test(trimmedValue)) {
                        this.showAlert('danger', 'Please provide a valid game id.');
                        return;
                    }

                    this.resetProgress();
                    this.showProgress();
                    this.setFormDisabled(true);

                    this.processRescan(trimmedValue)
                        .catch(() => {
                            // Errors are handled in processRescan; this catch prevents
                            // unhandled promise rejections in older browsers.
                        })
                        .finally(() => {
                            this.setFormDisabled(false);
                        });
                }

                async processRescan(gameId) {
                    try {
                        const response = await fetch('rescan_process.php', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/x-ndjson, application/json',
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({ game: gameId }),
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

                            if (!response.ok || !data || !data.success) {
                                const errorMessage = data && (data.error || data.message)
                                    ? (data.error || data.message)
                                    : 'Unable to rescan the specified game.';

                                this.markProgressAsError('Rescan failed.');
                                this.showAlert('danger', errorMessage);

                                return;
                            }

                            const dataDifferences = Array.isArray(data && data.differences) ? data.differences : [];

                            finalPayload = {
                                type: 'complete',
                                success: true,
                                progress: 100,
                                message: data.message ?? 'Rescan completed successfully.',
                                differences: dataDifferences,
                            };

                            this.updateProgress(100, finalPayload.message);
                        }

                        this.setProgressAnimated(false);

                        if (finalPayload && finalPayload.success) {
                            const successMessage = finalPayload.message ?? 'Rescan completed successfully.';
                            const differences = Array.isArray(finalPayload.differences)
                                ? finalPayload.differences
                                : [];
                            this.markProgressAsSuccess(successMessage);
                            this.showAlert('success', successMessage, differences);

                            return;
                        }

                        const errorMessage = (finalPayload && (finalPayload.error || finalPayload.message))
                            ? (finalPayload.error || finalPayload.message)
                            : 'Unable to rescan the specified game.';

                        this.markProgressAsError(finalPayload?.message ?? finalPayload?.error ?? 'Rescan failed.');
                        this.showAlert('danger', errorMessage);
                    } catch (error) {
                        this.markProgressAsError('Rescan failed.');
                        this.showAlert('danger', 'An unexpected error occurred while processing the rescan request.');
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

                        if (payload.type === 'log') {
                            if (typeof payload.message === 'string' && payload.message.trim() !== '') {
                                this.appendLogMessage(payload.message);
                            }

                            return;
                        }

                        if (payload.type === 'progress') {
                            let progressValue = 0;

                            if (typeof payload.progress === 'number' && Number.isFinite(payload.progress)) {
                                progressValue = payload.progress;
                            } else if (typeof payload.progress === 'string') {
                                const parsedProgress = Number(payload.progress.trim());

                                if (Number.isFinite(parsedProgress)) {
                                    progressValue = parsedProgress;
                                }
                            }

                            this.updateProgress(progressValue, payload.message ?? null);

                            return;
                        }

                        if (payload.type === 'complete' || payload.type === 'error') {
                            finalPayload = payload;
                            if (typeof payload.progress === 'number') {
                                const finalMessage = payload.message ?? payload.error ?? null;
                                this.updateProgress(payload.progress, finalMessage);
                            }
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
                    const finalLines = buffer.split('\n');
                    buffer = finalLines.pop() ?? '';

                    for (const line of finalLines) {
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
                        throw new Error('Missing final status from rescan process.');
                    }

                    return finalPayload;
                }

                resetProgress() {
                    if (!this.progressBar || !this.progressMessage) {
                        return;
                    }

                    this.progressBar.style.width = '0%';
                    this.progressBar.setAttribute('aria-valuenow', '0');
                    this.progressBar.textContent = '0%';
                    this.clearProgressStatus();
                    this.setProgressAnimated(true);
                    this.progressMessage.textContent = 'Preparing rescan…';
                }

                resetLog() {
                    if (this.logEntries instanceof HTMLElement) {
                        this.logEntries.replaceChildren();
                    }

                    if (this.logWrapper instanceof HTMLElement) {
                        this.logWrapper.classList.add('d-none');
                    }
                }

                updateProgress(value, message = null) {
                    if (!this.progressBar) {
                        return;
                    }

                    const numericValue = Number.isFinite(value) ? value : Number(value);
                    const clampedValue = Math.min(100, Math.max(0, typeof numericValue === 'number' && Number.isFinite(numericValue) ? numericValue : 0));
                    const displayValue = Math.round(clampedValue);

                    this.progressBar.style.width = `${clampedValue}%`;
                    this.progressBar.setAttribute('aria-valuenow', String(displayValue));
                    this.progressBar.textContent = `${displayValue}%`;

                    if (message !== null && this.progressMessage) {
                        this.progressMessage.textContent = message;
                    }
                }

                appendLogMessage(message) {
                    if (!(this.logEntries instanceof HTMLElement)) {
                        return;
                    }

                    if (this.logWrapper instanceof HTMLElement) {
                        this.logWrapper.classList.remove('d-none');
                    }

                    const entry = document.createElement('div');
                    entry.textContent = message;

                    this.logEntries.appendChild(entry);
                    this.logEntries.scrollTop = this.logEntries.scrollHeight;
                }

                setFormDisabled(disabled) {
                    if (this.submitButton) {
                        this.submitButton.disabled = disabled;
                    }

                    if (this.gameInput) {
                        this.gameInput.disabled = disabled;
                    }
                }

                showAlert(type, message, differences = []) {
                    if (!this.result) {
                        return;
                    }

                    const alert = document.createElement('div');
                    alert.className = `alert alert-${type}`;
                    alert.setAttribute('role', 'alert');
                    alert.textContent = message;

                    this.result.replaceChildren(alert);

                    const diffContainer = this.buildDifferencesContainer(differences);
                    if (diffContainer !== null) {
                        this.result.appendChild(diffContainer);
                    }
                }

                buildDifferencesContainer(differences) {
                    if (!Array.isArray(differences) || differences.length === 0) {
                        return null;
                    }

                    const filtered = differences.filter((diff) => diff && typeof diff === 'object');
                    if (filtered.length === 0) {
                        return null;
                    }

                    const card = document.createElement('div');
                    card.className = 'card mt-3 diff-card';

                    const header = document.createElement('div');
                    header.className = 'card-header';
                    header.textContent = 'Detected changes';
                    card.appendChild(header);

                    const body = document.createElement('div');
                    body.className = 'card-body';
                    card.appendChild(body);

                    filtered.forEach((diff) => {
                        const context = typeof diff.context === 'string' && diff.context.trim() !== ''
                            ? diff.context.trim()
                            : 'Change';
                        const field = typeof diff.field === 'string' && diff.field.trim() !== ''
                            ? diff.field.trim()
                            : 'Field';

                        const entry = document.createElement('div');
                        entry.className = 'diff-entry';

                        const metadata = document.createElement('div');
                        metadata.className = 'diff-metadata';
                        metadata.textContent = `${context} • ${field}`;
                        entry.appendChild(metadata);

                        const row = document.createElement('div');
                        row.className = 'row g-2';
                        entry.appendChild(row);

                        const previousCol = document.createElement('div');
                        previousCol.className = 'col-12 col-lg-6';
                        previousCol.appendChild(this.createDiffPane('Previous', diff.previous, 'diff-pane-old'));
                        row.appendChild(previousCol);

                        const currentCol = document.createElement('div');
                        currentCol.className = 'col-12 col-lg-6';
                        currentCol.appendChild(this.createDiffPane('Updated', diff.current, 'diff-pane-new'));
                        row.appendChild(currentCol);

                        body.appendChild(entry);
                    });

                    if (!body.hasChildNodes()) {
                        return null;
                    }

                    return card;
                }

                createDiffPane(title, value, additionalClass) {
                    const pane = document.createElement('div');
                    pane.className = `diff-pane ${additionalClass}`;

                    const header = document.createElement('div');
                    header.className = 'diff-pane-header';
                    header.textContent = title;
                    pane.appendChild(header);

                    const pre = document.createElement('pre');
                    pre.className = 'diff-pre';
                    pre.textContent = this.formatDifferenceValue(value);
                    pane.appendChild(pre);

                    return pane;
                }

                formatDifferenceValue(value) {
                    if (value === null || value === undefined) {
                        return '—';
                    }

                    if (typeof value === 'string') {
                        return value.trim() === '' ? '—' : value;
                    }

                    return String(value);
                }

                clearResult() {
                    if (this.result) {
                        this.result.replaceChildren();
                    }
                }

                showProgress() {
                    if (this.progressWrapper) {
                        this.progressWrapper.classList.remove('d-none');
                    }
                }

                clearProgressStatus() {
                    if (this.progressBar) {
                        this.progressBar.classList.remove('bg-success', 'bg-danger');
                    }
                }

                setProgressAnimated(animated) {
                    if (!this.progressBar) {
                        return;
                    }

                    if (animated) {
                        this.progressBar.classList.add('progress-bar-animated');
                    } else {
                        this.progressBar.classList.remove('progress-bar-animated');
                    }
                }

                markProgressAsSuccess(message) {
                    this.setProgressAnimated(false);
                    this.clearProgressStatus();
                    if (this.progressBar) {
                        this.progressBar.classList.add('bg-success');
                    }
                    this.updateProgress(100, message);
                }

                markProgressAsError(message) {
                    this.setProgressAnimated(false);
                    this.clearProgressStatus();
                    if (this.progressBar) {
                        this.progressBar.classList.add('bg-danger');
                    }
                    this.updateProgress(100, message);
                }
            }

            document.addEventListener('DOMContentLoaded', () => {
                const controller = new RescanFormController({
                    formId: 'rescan-form',
                    gameInputId: 'game',
                    submitButtonId: 'rescan-submit',
                    progressWrapperId: 'progress-wrapper',
                    progressBarId: 'progress-bar',
                    progressMessageId: 'progress-message',
                    logWrapperId: 'log-wrapper',
                    logEntriesId: 'log-entries',
                    resultId: 'result',
                });

                controller.initialize();
                window.rescanFormController = controller;
            });
        </script>
    </body>
</html>
