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
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

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
                                _csrf_token: csrfToken,
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
                        const template = document.createElement('template');
                        template.innerHTML = message ?? '';
                        alert.replaceChildren(...template.content.childNodes);
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

                    return new DOMParser().parseFromString(value, 'text/html').body.textContent ?? '';
                }
}

function initializeGameMergeForm() {
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
}

window.GameMergeFormController = GameMergeFormController;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeGameMergeForm);
} else {
    initializeGameMergeForm();
}
