class ScanLogRenderer {
    constructor(options) {
        this.tableBody = options.tableBody ?? null;
        this.summaryScannedElement = options.summaryScannedElement ?? null;
        this.summaryNewElement = options.summaryNewElement ?? null;
        this.initialData = Array.isArray(options.initialData) ? options.initialData : [];
        this.baseScannedPlayers = typeof options.baseScannedPlayers === 'number' ? options.baseScannedPlayers : 0;
        this.baseNewPlayers = typeof options.baseNewPlayers === 'number' ? options.baseNewPlayers : 0;
        this.configuredDisplayCount = Math.max(0, options.configuredDisplayCount ?? 0);
        this.fallbackDisplayCount = Math.max(1, options.fallbackDisplayCount ?? 1);
        this.numberFormatter = options.numberFormatter ?? new Intl.NumberFormat('en-US');
        this.pollIntervalMs = Math.max(0, options.pollIntervalMs ?? 0);
        this.fetchLimit = Math.max(
            this.configuredDisplayCount,
            this.initialData.length,
            this.fallbackDisplayCount,
        );
        this.intervalId = null;
    }

    initialize() {
        if (!this.tableBody || !this.summaryScannedElement || !this.summaryNewElement) {
            return;
        }

        this.renderData(this.initialData);
        this.updateSummary(this.baseScannedPlayers, this.baseNewPlayers);
        this.fetchLatestScanLog();
        this.startPolling();
    }

    startPolling() {
        if (this.pollIntervalMs <= 0) {
            return;
        }

        this.stopPolling();
        this.intervalId = window.setInterval(() => this.fetchLatestScanLog(), this.pollIntervalMs);
    }

    stopPolling() {
        if (this.intervalId !== null) {
            window.clearInterval(this.intervalId);
            this.intervalId = null;
        }
    }

    parseLastUpdatedDate(value) {
        if (!value) {
            return null;
        }

        const parsedDate = new Date(`${value} UTC`);

        return Number.isNaN(parsedDate.valueOf()) ? null : parsedDate;
    }

    sortScanLogData(data) {
        return [...data].sort((playerA, playerB) => {
            const dateA = this.parseLastUpdatedDate(playerA.lastUpdatedDate);
            const dateB = this.parseLastUpdatedDate(playerB.lastUpdatedDate);

            const timeA = dateA ? dateA.getTime() : Number.NEGATIVE_INFINITY;
            const timeB = dateB ? dateB.getTime() : Number.NEGATIVE_INFINITY;

            return timeB - timeA;
        });
    }

    createRankCell(player) {
        const rankCell = document.createElement('th');
        rankCell.scope = 'row';
        rankCell.className = 'align-middle text-center';

        if (player.isRanked && player.ranking !== null && player.ranking !== undefined) {
            rankCell.append(document.createTextNode(player.ranking));

            if (player.hasHiddenTrophies) {
                const hiddenSpan = document.createElement('span');
                hiddenSpan.style.color = '#9d9d9d';
                hiddenSpan.textContent = ' (H)';
                rankCell.append(hiddenSpan);
            }
        } else {
            rankCell.append(document.createTextNode('N/A'));
        }

        rankCell.append(document.createElement('br'));

        if (player.statusLabel) {
            const statusSpan = document.createElement('span');
            statusSpan.style.color = '#9d9d9d';
            statusSpan.textContent = `(${player.statusLabel})`;
            rankCell.append(statusSpan);
        } else if (player.isNew) {
            rankCell.append(document.createTextNode('(New!)'));
        } else if (player.rankDeltaLabel && player.rankDeltaColor) {
            const deltaSpan = document.createElement('span');
            deltaSpan.style.color = player.rankDeltaColor;
            deltaSpan.textContent = player.rankDeltaLabel;
            rankCell.append(deltaSpan);
        }

        return rankCell;
    }

    createUpdatedCell(player) {
        const updatedCell = document.createElement('td');
        updatedCell.className = 'align-middle text-center';

        if (player.lastUpdatedDate) {
            const parsedDate = this.parseLastUpdatedDate(player.lastUpdatedDate);

            if (parsedDate) {
                updatedCell.textContent = parsedDate.toLocaleString('sv-SE', { timeStyle: 'medium' });
            }
        }

        return updatedCell;
    }

    createUserCell(player) {
        const userCell = document.createElement('td');
        userCell.className = 'align-middle';

        const hstack = document.createElement('div');
        hstack.className = 'hstack gap-3';

        const avatarWrapper = document.createElement('div');
        const avatarLink = document.createElement('a');
        avatarLink.className = 'link-underline link-underline-opacity-0 link-underline-opacity-100-hover';
        const onlineId = typeof player.onlineId === 'string' ? player.onlineId : '';
        avatarLink.href = `/player/${encodeURIComponent(onlineId)}`;

        const avatarImage = document.createElement('img');
        const avatarUrl = typeof player.avatarUrl === 'string' ? player.avatarUrl : '';
        avatarImage.src = `/img/avatar/${avatarUrl}`;
        avatarImage.alt = '';
        avatarImage.height = 50;
        avatarImage.width = 50;
        avatarLink.append(avatarImage);
        avatarWrapper.append(avatarLink);

        const nameWrapper = document.createElement('div');
        const nameLink = document.createElement('a');
        nameLink.className = 'link-underline link-underline-opacity-0 link-underline-opacity-100-hover';
        nameLink.style.whiteSpace = 'nowrap';
        nameLink.href = `/player/${encodeURIComponent(onlineId)}`;
        nameLink.textContent = onlineId;
        nameWrapper.append(nameLink);

        const countryWrapper = document.createElement('div');
        countryWrapper.className = 'ms-auto';

        const countryCode = typeof player.countryCode === 'string' ? player.countryCode : '';
        const countryName = typeof player.countryName === 'string' ? player.countryName : '';

        if (countryCode !== '') {
            const countryImage = document.createElement('img');
            countryImage.src = `/img/country/${countryCode}.svg`;
            countryImage.alt = countryName;
            countryImage.title = countryName;
            countryImage.height = 50;
            countryImage.width = 50;
            countryImage.style.borderRadius = '50%';
            countryWrapper.append(countryImage);
        }

        hstack.append(avatarWrapper, nameWrapper, countryWrapper);
        userCell.append(hstack);

        return userCell;
    }

    createLevelCell(player) {
        const levelCell = document.createElement('td');
        levelCell.className = 'align-middle text-center';

        if (player.status === 1 || player.status === 3) {
            levelCell.textContent = 'N/A';
            return levelCell;
        }

        if (player.level === null || player.level === undefined) {
            levelCell.textContent = 'N/A';
            return levelCell;
        }

        const starImage = document.createElement('img');
        starImage.src = '/img/star.svg';
        starImage.className = 'mb-1';
        starImage.alt = 'Level';
        starImage.title = 'Level';
        starImage.height = 18;
        levelCell.append(starImage, document.createTextNode(` ${player.level}`));

        if (player.progress !== null && player.progress !== undefined) {
            const progressValue = Number.parseFloat(player.progress);

            if (!Number.isNaN(progressValue)) {
                const progressContainer = document.createElement('div');
                progressContainer.className = 'progress';
                progressContainer.title = `${progressValue}%`;

                const progressBar = document.createElement('div');
                progressBar.className = 'progress-bar bg-primary';
                progressBar.role = 'progressbar';
                progressBar.style.width = `${progressValue}%`;
                progressBar.setAttribute('aria-valuenow', String(progressValue));
                progressBar.setAttribute('aria-valuemin', '0');
                progressBar.setAttribute('aria-valuemax', '100');

                progressContainer.append(progressBar);
                levelCell.append(progressContainer);
            }
        }

        return levelCell;
    }

    applyRowEntryAnimation(row) {
        row.classList.add('scan-log-row--enter');
        row.addEventListener(
            'animationend',
            () => {
                row.classList.remove('scan-log-row--enter');
            },
            { once: true },
        );
    }

    buildRow(player) {
        const row = document.createElement('tr');
        const onlineId = typeof player.onlineId === 'string' ? player.onlineId : '';
        const lastUpdated = typeof player.lastUpdatedDate === 'string' ? player.lastUpdatedDate : '';

        row.dataset.onlineId = onlineId;
        row.dataset.lastUpdatedDate = lastUpdated;

        row.append(
            this.createRankCell(player),
            this.createUpdatedCell(player),
            this.createUserCell(player),
            this.createLevelCell(player),
        );

        return row;
    }

    synchronizeTableRows(playersToDisplay) {
        const existingRows = new Map();

        Array.from(this.tableBody.querySelectorAll('tr')).forEach((row) => {
            const key = row.dataset.onlineId ?? '';

            if (key !== '') {
                existingRows.set(key, row);
            }
        });

        const rows = [];
        const rowsToAnimate = [];

        playersToDisplay.forEach((player) => {
            const row = this.buildRow(player);
            const existingRow = existingRows.get(row.dataset.onlineId ?? '');
            const previousTimestamp = existingRow ? existingRow.dataset.lastUpdatedDate ?? '' : '';
            const currentTimestamp = row.dataset.lastUpdatedDate ?? '';

            if (!existingRow || previousTimestamp !== currentTimestamp) {
                rowsToAnimate.push(row);
            }

            rows.push(row);
        });

        this.tableBody.replaceChildren(...rows);

        rowsToAnimate.forEach((row) => {
            window.requestAnimationFrame(() => this.applyRowEntryAnimation(row));
        });
    }

    updateSummary(scannedPlayers, newPlayers) {
        if (typeof scannedPlayers === 'number' && Number.isFinite(scannedPlayers)) {
            this.summaryScannedElement.textContent = this.numberFormatter.format(scannedPlayers);
        }

        if (typeof newPlayers === 'number' && Number.isFinite(newPlayers)) {
            this.summaryNewElement.textContent = this.numberFormatter.format(newPlayers);
        }
    }

    getDisplayCount(dataLength) {
        if (this.configuredDisplayCount > 0) {
            return this.configuredDisplayCount;
        }

        return Math.min(this.fallbackDisplayCount, dataLength);
    }

    renderData(data) {
        if (!Array.isArray(data) || data.length === 0) {
            this.tableBody.replaceChildren();
            return;
        }

        const sortedData = this.sortScanLogData(data);
        const displayCount = this.getDisplayCount(sortedData.length);
        const playersToDisplay = sortedData.slice(0, displayCount);

        this.synchronizeTableRows(playersToDisplay);
    }

    fetchLatestScanLog() {
        const url = new URL('scan_log_poll.php', window.location.href);
        url.searchParams.set('limit', String(this.fetchLimit));

        fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
                'Cache-Control': 'no-cache',
            },
            cache: 'no-store',
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Failed to fetch scan log data');
                }

                return response.json();
            })
            .then((payload) => {
                if (!payload || typeof payload !== 'object') {
                    return;
                }

                if (Array.isArray(payload.players)) {
                    this.renderData(payload.players);
                }

                if (payload.summary && typeof payload.summary === 'object') {
                    const scannedValue = Number(payload.summary.scannedPlayers);
                    const newValue = Number(payload.summary.newPlayers);

                    this.updateSummary(
                        Number.isNaN(scannedValue) ? undefined : scannedValue,
                        Number.isNaN(newValue) ? undefined : newValue,
                    );
                }
            })
            .catch(() => {
                // Ignore polling errors to avoid interrupting the page.
            });
    }
}

function readScanLogConfig() {
    const configElement = document.getElementById('scan-log-config');

    if (!configElement) {
        return null;
    }

    try {
        const config = JSON.parse(configElement.textContent ?? '');

        return typeof config === 'object' && config !== null ? config : null;
    } catch (error) {
        return null;
    }
}

function initializeScanLogRenderer() {
    const config = readScanLogConfig();

    if (!config) {
        return;
    }

    const renderer = new ScanLogRenderer({
        tableBody: document.getElementById('scanLogTableBody'),
        summaryScannedElement: document.getElementById('scanSummaryScanned'),
        summaryNewElement: document.getElementById('scanSummaryNew'),
        initialData: Array.isArray(config.scanLogData) ? config.scanLogData : [],
        baseScannedPlayers: Number(config.baseScannedPlayers) || 0,
        baseNewPlayers: Number(config.baseNewPlayers) || 0,
        configuredDisplayCount: Math.max(0, Number(config.configuredDisplayCount) || 0),
        fallbackDisplayCount: Math.max(1, Number(config.fallbackDisplayCount) || 1),
        pollIntervalMs: Math.max(0, Number(config.pollIntervalMs) || 0),
    });

    renderer.initialize();
    window.scanLogRenderer = renderer;
}

window.ScanLogRenderer = ScanLogRenderer;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeScanLogRenderer);
} else {
    initializeScanLogRenderer();
}
