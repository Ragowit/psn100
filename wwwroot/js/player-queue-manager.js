class PlayerQueueManager {
    constructor({
        playerInputId,
        buttonId,
        queueResultContainerId,
        messageElementId,
        pollInterval = 3000,
    }) {
        this.playerInput = document.getElementById(playerInputId);
        this.button = document.getElementById(buttonId);
        this.queueResultContainer = document.getElementById(queueResultContainerId);
        this.messageElement = document.getElementById(messageElementId);
        this.pollInterval = pollInterval;
        this.timerId = null;
        this.pollToken = null;
    }

    initialize() {
        if (!this.playerInput || !this.button || !this.queueResultContainer || !this.messageElement) {
            return;
        }

        this.button.addEventListener('click', () => this.addToQueue());
        this.playerInput.addEventListener('keyup', (event) => this.handleKeyUp(event));
    }

    handleKeyUp(event) {
        const key = event.key || event.keyCode;
        if (key === 'Enter' || key === 13) {
            event.preventDefault();
            this.addToQueue();
        }
    }

    addToQueue() {
        const player = this.playerInput.value;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const body = new URLSearchParams({
            q: player,
            _csrf_token: csrfToken,
        });

        this.sendPostRequest('add_to_queue.php', body, (response) => {
            this.updateQueueResult(response);

            if (typeof response.pollToken === 'string' && response.pollToken !== '') {
                this.pollToken = response.pollToken;
            }

            if (response.shouldPoll) {
                this.startPolling(player);
            } else {
                this.stopPolling(true);
            }
        });
    }

    startPolling(player) {
        this.stopPolling();
        this.timerId = window.setInterval(() => this.checkQueuePosition(player), this.pollInterval);
    }

    checkQueuePosition(player) {
        const url = new URL('check_queue_position.php', window.location.href);
        url.searchParams.set('q', player);

        if (typeof this.pollToken === 'string' && this.pollToken !== '') {
            url.searchParams.set('poll_token', this.pollToken);
        }

        this.sendRequest(url.toString(), (response) => {
            this.updateQueueResult(response);

            if (!response.shouldPoll) {
                this.stopPolling(true);
            }
        });
    }

    stopPolling(clearPollToken = false) {
        if (this.timerId !== null) {
            window.clearInterval(this.timerId);
            this.timerId = null;
        }

        if (clearPollToken) {
            this.pollToken = null;
        }
    }

    sendPostRequest(url, body, onSuccess) {
        const request = new XMLHttpRequest();

        request.onreadystatechange = () => {
            this.handleCompletedRequest(request, onSuccess);
        };

        request.onerror = () => this.handleError();

        request.open('POST', url, true);
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        request.send(body.toString());
    }

    sendRequest(url, onSuccess) {
        const request = new XMLHttpRequest();

        request.onreadystatechange = () => {
            this.handleCompletedRequest(request, onSuccess);
        };

        request.onerror = () => this.handleError();

        request.open('GET', url, true);
        request.setRequestHeader('Accept', 'application/json');
        request.send();
    }

    handleCompletedRequest(request, onSuccess) {
        if (request.readyState !== XMLHttpRequest.DONE) {
            return;
        }

        const response = this.parseResponse(request.responseText);

        if (response !== null) {
            if (request.status >= 200 && request.status < 300) {
                onSuccess(response);
                return;
            }

            this.updateQueueResult(response);
            this.stopPolling(true);
            return;
        }

        if (request.status >= 200 && request.status < 300) {
            this.handleError();
            return;
        }

        this.handleError();
    }

    parseResponse(responseText) {
        if (!responseText) {
            return null;
        }

        try {
            const data = JSON.parse(responseText);

            if (typeof data !== 'object' || data === null) {
                return null;
            }

            const message = typeof data.message === 'string' ? data.message : '';
            const shouldPoll = typeof data.shouldPoll === 'boolean' ? data.shouldPoll : false;
            const status = typeof data.status === 'string' ? data.status : 'error';
            const pollToken = typeof data.pollToken === 'string' ? data.pollToken : '';
            const messageParts = Array.isArray(data.messageParts) ? data.messageParts : [];

            return { message, shouldPoll, status, pollToken, messageParts };
        } catch (error) {
            return null;
        }
    }

    updateQueueResult(response) {
        if (!this.messageElement) {
            return;
        }

        if (response && Array.isArray(response.messageParts) && response.messageParts.length > 0) {
            this.renderMessageParts(response.messageParts);
        } else {
            this.messageElement.textContent = response?.message ?? '';
        }

        this.showQueueResult();
    }

    renderMessageParts(parts) {
        this.messageElement.replaceChildren();

        parts.forEach((part) => {
            if (!part || typeof part !== 'object') {
                return;
            }

            switch (part.type) {
                case 'text':
                    this.messageElement.appendChild(document.createTextNode(part.value ?? ''));
                    break;
                case 'link': {
                    const anchor = document.createElement('a');
                    anchor.className = 'link-underline link-underline-opacity-0 link-underline-opacity-100-hover';
                    anchor.href = typeof part.href === 'string' ? part.href : '#';
                    anchor.textContent = part.label ?? '';
                    this.messageElement.appendChild(anchor);
                    break;
                }
                case 'emphasis': {
                    const emphasis = document.createElement('strong');
                    emphasis.textContent = part.value ?? '';
                    this.messageElement.appendChild(emphasis);
                    break;
                }
                case 'spinner': {
                    this.messageElement.appendChild(document.createElement('br'));
                    const spinner = document.createElement('div');
                    spinner.className = 'spinner-border';
                    spinner.setAttribute('role', 'status');
                    const hidden = document.createElement('span');
                    hidden.className = 'visually-hidden';
                    hidden.textContent = 'Loading...';
                    spinner.appendChild(hidden);
                    this.messageElement.appendChild(spinner);
                    break;
                }
                case 'progress': {
                    const progress = document.createElement('div');
                    progress.className = 'progress mt-2';
                    progress.setAttribute('role', 'progressbar');
                    const percentage = Number(part.percentage ?? 0);
                    progress.setAttribute('aria-valuenow', String(percentage));
                    progress.setAttribute('aria-valuemin', '0');
                    progress.setAttribute('aria-valuemax', '100');

                    const bar = document.createElement('div');
                    bar.className = 'progress-bar bg-primary';
                    bar.style.width = `${percentage}%`;
                    progress.appendChild(bar);
                    this.messageElement.appendChild(progress);
                    break;
                }
                default:
                    break;
            }
        });
    }

    showQueueResult() {
        if (this.queueResultContainer) {
            this.queueResultContainer.style.display = '';
        }
    }

    handleError() {
        this.updateQueueResult({
            message: 'An error occurred while contacting the server. Please try again later.',
            messageParts: [],
        });
        this.stopPolling(true);
    }
}

const POPULAR_GAMES_SCROLL_KEY = 'home-scroll-popular-games';

function isSessionStorageAvailable() {
    try {
        const testKey = '__storage_test__';
        sessionStorage.setItem(testKey, '1');
        sessionStorage.removeItem(testKey);
        return true;
    } catch (error) {
        return false;
    }
}

function markPopularGamesScrollOnReload() {
    if (!isSessionStorageAvailable()) {
        return;
    }

    try {
        sessionStorage.setItem(POPULAR_GAMES_SCROLL_KEY, '1');
    } catch (error) {
        // Ignore storage failures.
    }
}

function submitPopularGamesFilter(form) {
    markPopularGamesScrollOnReload();

    if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
        return;
    }

    form.submit();
}

function scrollToPopularGamesIfRequested() {
    if (!isSessionStorageAvailable()) {
        return;
    }

    try {
        if (sessionStorage.getItem(POPULAR_GAMES_SCROLL_KEY) !== '1') {
            return;
        }

        sessionStorage.removeItem(POPULAR_GAMES_SCROLL_KEY);
    } catch (error) {
        return;
    }

    const popularGames = document.getElementById('popular-games');
    if (!popularGames) {
        return;
    }

    requestAnimationFrame(() => {
        popularGames.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
}

function initializePopularGamesFilter() {
    const form = document.getElementById('popular-games-filter');
    const platformSelect = document.getElementById('popular-platform');
    const exclusiveCheckbox = document.getElementById('popular-exclusive');

    if (!form) {
        return;
    }

    const handleChange = () => submitPopularGamesFilter(form);

    if (platformSelect) {
        platformSelect.addEventListener('change', handleChange);
    }

    if (exclusiveCheckbox) {
        exclusiveCheckbox.addEventListener('change', handleChange);
    }
}

function initializeHomepageQueue() {
    scrollToPopularGamesIfRequested();
    initializePopularGamesFilter();

    const queueManager = new PlayerQueueManager({
        playerInputId: 'player',
        buttonId: 'player-button',
        queueResultContainerId: 'queue-result',
        messageElementId: 'add-to-queue-result',
        pollInterval: 3000,
    });

    queueManager.initialize();
    window.playerQueueManager = queueManager;
}

window.PlayerQueueManager = PlayerQueueManager;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeHomepageQueue);
} else {
    initializeHomepageQueue();
}
