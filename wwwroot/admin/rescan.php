<?php
require_once '../vendor/autoload.php';
require_once '../init.php';
require_once '../classes/Admin/AdminLayoutRenderer.php';

$layoutRenderer = new AdminLayoutRenderer();
$options = AdminLayoutOptions::create()->withContainerClass('container py-4');

echo $layoutRenderer->render('Admin ~ Rescan Game', static function (): void {
    ?>
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

    <div id="result" class="mt-3"></div>

    <script>
        class RescanFormController {
            constructor({
                formId,
                gameInputId,
                submitButtonId,
                progressWrapperId,
                progressBarId,
                progressMessageId,
                resultId,
            }) {
                this.form = document.getElementById(formId);
                this.gameInput = document.getElementById(gameInputId);
                this.submitButton = document.getElementById(submitButtonId);
                this.progressWrapper = document.getElementById(progressWrapperId);
                this.progressBar = document.getElementById(progressBarId);
                this.progressMessage = document.getElementById(progressMessageId);
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
                    this.result,
                ].every((element) => element instanceof HTMLElement);
            }

            handleSubmit(event) {
                event.preventDefault();
                this.clearResult();

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

                        finalPayload = {
                            type: 'complete',
                            success: true,
                            progress: 100,
                            message: data.message ?? 'Rescan completed successfully.',
                        };

                        this.updateProgress(100, finalPayload.message);
                    }

                    this.setProgressAnimated(false);

                    if (finalPayload && finalPayload.success) {
                        const successMessage = finalPayload.message ?? 'Rescan completed successfully.';
                        this.markProgressAsSuccess(successMessage);
                        this.showAlert('success', successMessage);

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

            setFormDisabled(disabled) {
                if (this.submitButton) {
                    this.submitButton.disabled = disabled;
                }

                if (this.gameInput) {
                    this.gameInput.disabled = disabled;
                }
            }

            showAlert(type, message) {
                if (!this.result) {
                    return;
                }

                const alert = document.createElement('div');
                alert.className = `alert alert-${type}`;
                alert.setAttribute('role', 'alert');
                alert.textContent = message;

                this.result.replaceChildren(alert);
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
                resultId: 'result',
            });

            controller.initialize();
            window.rescanFormController = controller;
        });
    </script>
    <?php
}, $options);
