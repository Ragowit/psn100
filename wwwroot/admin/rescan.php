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
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <title>Admin ~ Rescan Game</title>
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

            <div id="result" class="mt-3"></div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const form = document.getElementById('rescan-form');
                const gameInput = document.getElementById('game');
                const submitButton = document.getElementById('rescan-submit');
                const progressWrapper = document.getElementById('progress-wrapper');
                const progressBar = document.getElementById('progress-bar');
                const progressMessage = document.getElementById('progress-message');
                const result = document.getElementById('result');

                const resetProgress = () => {
                    progressBar.style.width = '0%';
                    progressBar.setAttribute('aria-valuenow', '0');
                    progressBar.textContent = '0%';
                    progressBar.classList.remove('bg-success', 'bg-danger');
                    progressBar.classList.add('progress-bar-animated');
                    progressMessage.textContent = 'Preparing rescan…';
                };

                const updateProgress = (value, message = null) => {
                    const clampedValue = Math.min(100, Math.max(0, Math.round(value)));
                    progressBar.style.width = clampedValue + '%';
                    progressBar.setAttribute('aria-valuenow', String(clampedValue));
                    progressBar.textContent = clampedValue + '%';

                    if (message !== null) {
                        progressMessage.textContent = message;
                    }
                };

                const setFormDisabled = (disabled) => {
                    submitButton.disabled = disabled;
                    gameInput.disabled = disabled;
                };

                const showAlert = (type, message) => {
                    const alert = document.createElement('div');
                    alert.className = `alert alert-${type}`;
                    alert.setAttribute('role', 'alert');
                    alert.textContent = message;

                    result.replaceChildren(alert);
                };

                const consumeProgressStream = async (response) => {
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
                            const progressValue = typeof payload.progress === 'number' ? payload.progress : 0;
                            updateProgress(progressValue, payload.message ?? null);

                            return;
                        }

                        if (payload.type === 'complete' || payload.type === 'error') {
                            finalPayload = payload;
                            if (typeof payload.progress === 'number') {
                                const finalMessage = payload.message ?? payload.error ?? null;
                                updateProgress(payload.progress, finalMessage);
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
                };

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    result.replaceChildren();

                    const trimmedValue = gameInput.value.trim();
                    if (!/^\d+$/.test(trimmedValue)) {
                        showAlert('danger', 'Please provide a valid game id.');
                        return;
                    }

                    resetProgress();
                    progressWrapper.classList.remove('d-none');
                    setFormDisabled(true);

                    try {
                        const response = await fetch('rescan_process.php', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/x-ndjson, application/json',
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams({ game: trimmedValue })
                        });

                        const contentType = response.headers.get('Content-Type') ?? '';
                        let finalPayload = null;

                        if (contentType.includes('application/x-ndjson')) {
                            finalPayload = await consumeProgressStream(response);
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

                                progressBar.classList.remove('progress-bar-animated');
                                progressBar.classList.add('bg-danger');
                                updateProgress(100, 'Rescan failed.');
                                showAlert('danger', errorMessage);

                                return;
                            }

                            finalPayload = {
                                type: 'complete',
                                success: true,
                                progress: 100,
                                message: data.message ?? 'Rescan completed successfully.',
                            };

                            updateProgress(100, finalPayload.message);
                        }

                        progressBar.classList.remove('progress-bar-animated');

                        if (finalPayload && finalPayload.success) {
                            progressBar.classList.add('bg-success');
                            const successMessage = finalPayload.message ?? 'Rescan completed successfully.';
                            updateProgress(100, successMessage);
                            showAlert('success', successMessage);

                            return;
                        }

                        progressBar.classList.add('bg-danger');
                        const errorMessage = (finalPayload && (finalPayload.error || finalPayload.message))
                            ? (finalPayload.error || finalPayload.message)
                            : 'Unable to rescan the specified game.';

                        updateProgress(100, finalPayload?.message ?? finalPayload?.error ?? 'Rescan failed.');
                        showAlert('danger', errorMessage);
                    } catch (error) {
                        progressBar.classList.remove('progress-bar-animated');
                        progressBar.classList.add('bg-danger');
                        updateProgress(100, 'Rescan failed.');
                        showAlert('danger', 'An unexpected error occurred while processing the rescan request.');
                    } finally {
                        setFormDisabled(false);
                    }
                });
            });
        </script>
    </body>
</html>
