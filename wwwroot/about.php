<?php
require_once __DIR__ . '/classes/AboutPageService.php';
require_once __DIR__ . '/classes/AboutPagePlayerArraySerializer.php';

$aboutPageService = new AboutPageService($database, $utility);
$scanSummary = $aboutPageService->getScanSummary();
$scanLogPlayers = $aboutPageService->getScanLogPlayers(30);
$maxScanLogDisplayCount = 10;
$initialDisplayCount = min($maxScanLogDisplayCount, count($scanLogPlayers));
$initialScanLogPlayers = array_slice($scanLogPlayers, 0, $initialDisplayCount);
$scanLogPlayersData = AboutPagePlayerArraySerializer::serializeCollection($scanLogPlayers);

$title = "About ~ PSN 100%";
require_once("header.php");
?>

<main class="container">
    <div class="row">
        <div class="col-12">
            <h1>About</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-lg-6 mb-3">
            <div class="vstack gap-3">
                <!-- What is... -->
                <div class="bg-body-tertiary p-3 rounded">
                    <h2>What is PSN 100%?</h2>
                    <p>
                        PSN 100% is a trophy tracking website, focusing on merging game stacks and removing unobtainable trophies to create one list of only unique obtainable trophies so all users have the chance to compete for the same level, without the need to replay the same game multiple times or missed opportunities on trophies that are no longer available for one reason or another. Furthermore PSN 100% only calculates stats from the top 10k players in order to try and be more accurate for those who consider themselves as a trophy hunter. PSN 100% is made by trophy hunters, for trophy hunters.
                    </p>
                </div>

                <!-- What isn't... -->
                <div class="bg-body-tertiary p-3 rounded">
                    <h2>What isn't PSN 100%?</h2>
                    <p>
                        PSN 100% is not a community for discussion (forum), gaming/boosting sessions or trophy guides. Other sites already handle this with greatness, please use them.
                    </p>
                    <ul>
                        <li><a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://psnprofiles.com/">PSNProfiles</a></li>
                        <li><a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://www.playstationtrophies.org/">PlayStation Trophies</a></li>
                        <li><a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://psntrophyleaders.com/">PSN Trophy Leaders</a></li>
                        <li><a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://www.truetrophies.com/">TrueTrophies</a></li>
                        <li><a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://www.exophase.com/">Exophase</a></li>
                        <li><a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://pocketpsn.com/">Pocket PSN</a></li>
                    </ul>
                </div>

                <!-- Merge Guideline -->
                <div class="bg-body-tertiary p-3 rounded">
                    <h2>Merge Guideline Priorities</h2>
                    <p>
                        <ol>
                            <li>Available > Delisted</li>
                            <li>English language > Other language</li>
                            <li>Digital > Physical</li>
                            <li>Remaster/Remake > Original</li>
                            <li>PS5 > PS4 > PS3 > PSVITA</li>
                            <li>Collection/Bundle > Single entry</li>
                        </ol>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6 mb-3">
            <div class="vstack gap-3">
                <!-- Scan Log -->
                <div class="bg-body-tertiary p-3 rounded">
                    <h2>Scan Log</h2>
                    <p id="scanSummaryText">
                        <span id="scanSummaryScanned"><?= number_format($scanSummary->getScannedPlayers()); ?></span> players were scanned in the last 24 hours, and <span id="scanSummaryNew"><?= number_format($scanSummary->getNewPlayers()); ?></span> new players added to the leaderboards this week!
                    </p>

                    <div class="table-responsive-xxl">
                        <table class="table">
                            <thead>
                                <tr class="text-uppercase">
                                    <th scope="col" class="text-center">Rank</th>
                                    <th scope="col" class="text-center">Updated</th>
                                    <th scope="col">User</th>
                                    <th scope="col" class="text-center" style="width: 75px;">Level</th>
                                </tr>
                            </thead>

                            <tbody id="scanLogTableBody">
                                <?php
                                foreach ($initialScanLogPlayers as $player) {
                                        $countryCode = $player->getCountryCode();
                                        $countryName = $player->getCountryName();
                                        $onlineId = $player->getOnlineId();
                                        $lastUpdatedDate = $player->getLastUpdatedDate();
                                        $statusLabel = $player->getStatusLabel();
                                        $rankDeltaLabel = $player->getRankDeltaLabel();
                                        $rankDeltaColor = $player->getRankDeltaColor();
                                        $progress = $player->getProgress();
                                        $level = $player->getLevel();
                                        ?>
                                        <tr>
                                            <th scope="row" class="align-middle text-center">
                                                <?php
                                                if ($player->isRanked()) {
                                                    echo $player->getRanking();

                                                    if ($player->hasHiddenTrophies()) {
                                                        echo " <span style='color: #9d9d9d;'>(H)</span>";
                                                    }
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                                <br>
                                                <?php
                                                if ($statusLabel !== null) {
                                                    echo "<span style='color: #9d9d9d;'>(' . $statusLabel . ')</span>";
                                                } elseif ($player->isNew()) {
                                                    echo '(New!)';
                                                } elseif ($rankDeltaLabel !== null && $rankDeltaColor !== null) {
                                                    echo "<span style=\"color: " . $rankDeltaColor . ";\">" . $rankDeltaLabel . '</span>';
                                                }
                                                ?>
                                            </th>
                                            <td class="align-middle text-center" id="lastUpdate<?= $onlineId; ?>"></td>
                                            <?php
                                            if ($lastUpdatedDate !== null) {
                                                ?>
                                                <script>
                                                    document.getElementById("lastUpdate<?= $onlineId; ?>").innerHTML = new Date('<?= $lastUpdatedDate; ?> UTC').toLocaleString('sv-SE', {timeStyle: 'medium'});
                                                </script>
                                                <?php
                                            }
                                            ?>
                                            <td class="align-middle">
                                                <div class="hstack gap-3">
                                                    <div>
                                                        <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/player/<?= $onlineId; ?>">
                                                            <img src="/img/avatar/<?= $player->getAvatarUrl(); ?>" alt="" height="50" width="50" />
                                                        </a>
                                                    </div>

                                                    <div>
                                                        <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" style="white-space: nowrap;" href="/player/<?= $onlineId; ?>"><?= $onlineId; ?></a>
                                                    </div>

                                                    <div class="ms-auto">
                                                        <img src="/img/country/<?= $countryCode; ?>.svg" alt="<?= $countryName; ?>" title="<?= $countryName; ?>" height="50" width="50" style="border-radius: 50%;" />
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="align-middle text-center">
                                                <?php
                                                if ($player->getStatus() == 1 || $player->getStatus() == 3) {
                                                    echo 'N/A';
                                                } else {
                                                    echo '<img src="/img/star.svg" class="mb-1" alt="Level" title="Level" height="18"/> ' . $level;

                                                    if ($progress !== null) {
                                                        echo '<div class="progress" title="' . $progress . '%">';
                                                        echo '<div class="progress-bar bg-primary" role="progressbar" style="width: ' . $progress . '%" aria-valuenow="' . $progress . '" aria-valuemin="0" aria-valuemax="100"></div>';
                                                        echo '</div>';
                                                    }
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if (!empty($scanLogPlayersData)) : ?>
                    <style>
                        @keyframes scan-log-drop-in {
                            from {
                                opacity: 0;
                                transform: translateY(-10px);
                            }

                            to {
                                opacity: 1;
                                transform: translateY(0);
                            }
                        }

                        .scan-log-row--enter {
                            animation: scan-log-drop-in 0.4s ease-out;
                        }
                    </style>
                    <script>
                        (() => {
                            const scanLogData = <?= json_encode($scanLogPlayersData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                            const configuredDisplayCount = Math.max(0, <?= $initialDisplayCount; ?>);
                            const fallbackDisplayCount = Math.max(1, <?= $maxScanLogDisplayCount; ?>);

                            const tableBody = document.getElementById('scanLogTableBody');
                            const summaryScannedElement = document.getElementById('scanSummaryScanned');
                            const summaryNewElement = document.getElementById('scanSummaryNew');

                            if (!tableBody || !summaryScannedElement || !summaryNewElement) {
                                return;
                            }

                            const baseScannedPlayers = <?= (int) $scanSummary->getScannedPlayers(); ?>;
                            const baseNewPlayers = <?= (int) $scanSummary->getNewPlayers(); ?>;
                            const numberFormatter = new Intl.NumberFormat('en-US');
                            const fetchLimit = Math.max(configuredDisplayCount, Array.isArray(scanLogData) ? scanLogData.length : 0, fallbackDisplayCount);
                            const pollIntervalMs = 5000;

                            const parseLastUpdatedDate = (value) => {
                                if (!value) {
                                    return null;
                                }

                                const parsedDate = new Date(`${value} UTC`);

                                return Number.isNaN(parsedDate.valueOf()) ? null : parsedDate;
                            };

                            const sortScanLogData = (data) => {
                                return [...data].sort((playerA, playerB) => {
                                    const dateA = parseLastUpdatedDate(playerA.lastUpdatedDate);
                                    const dateB = parseLastUpdatedDate(playerB.lastUpdatedDate);

                                    const timeA = dateA ? dateA.getTime() : Number.NEGATIVE_INFINITY;
                                    const timeB = dateB ? dateB.getTime() : Number.NEGATIVE_INFINITY;

                                    return timeB - timeA;
                                });
                            };

                            const createRankCell = (player) => {
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
                            };

                            const createUpdatedCell = (player) => {
                                const updatedCell = document.createElement('td');
                                updatedCell.className = 'align-middle text-center';

                                if (player.lastUpdatedDate) {
                                    const parsedDate = parseLastUpdatedDate(player.lastUpdatedDate);

                                    if (parsedDate) {
                                        updatedCell.textContent = parsedDate.toLocaleString('sv-SE', { timeStyle: 'medium' });
                                    }
                                }

                                return updatedCell;
                            };

                            const createUserCell = (player) => {
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
                            };

                            const createLevelCell = (player) => {
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
                            };

                            const applyRowEntryAnimation = (row) => {
                                row.classList.add('scan-log-row--enter');
                                row.addEventListener(
                                    'animationend',
                                    () => {
                                        row.classList.remove('scan-log-row--enter');
                                    },
                                    { once: true }
                                );
                            };

                            const buildRow = (player) => {
                                const row = document.createElement('tr');
                                const onlineId = typeof player.onlineId === 'string' ? player.onlineId : '';
                                const lastUpdated = typeof player.lastUpdatedDate === 'string' ? player.lastUpdatedDate : '';

                                row.dataset.onlineId = onlineId;
                                row.dataset.lastUpdatedDate = lastUpdated;

                                row.append(
                                    createRankCell(player),
                                    createUpdatedCell(player),
                                    createUserCell(player),
                                    createLevelCell(player)
                                );

                                return row;
                            };

                            const synchronizeTableRows = (playersToDisplay) => {
                                const existingRows = new Map();

                                Array.from(tableBody.querySelectorAll('tr')).forEach((row) => {
                                    const key = row.dataset.onlineId ?? '';

                                    if (key !== '') {
                                        existingRows.set(key, row);
                                    }
                                });

                                const rows = [];
                                const rowsToAnimate = [];

                                playersToDisplay.forEach((player) => {
                                    const row = buildRow(player);
                                    const existingRow = existingRows.get(row.dataset.onlineId ?? '');
                                    const previousTimestamp = existingRow ? existingRow.dataset.lastUpdatedDate ?? '' : '';
                                    const currentTimestamp = row.dataset.lastUpdatedDate ?? '';

                                    if (!existingRow || previousTimestamp !== currentTimestamp) {
                                        rowsToAnimate.push(row);
                                    }

                                    rows.push(row);
                                });

                                tableBody.replaceChildren(...rows);

                                rowsToAnimate.forEach((row) => {
                                    window.requestAnimationFrame(() => applyRowEntryAnimation(row));
                                });
                            };

                            const updateSummary = (scannedPlayers, newPlayers) => {
                                if (typeof scannedPlayers === 'number' && Number.isFinite(scannedPlayers)) {
                                    summaryScannedElement.textContent = numberFormatter.format(scannedPlayers);
                                }

                                if (typeof newPlayers === 'number' && Number.isFinite(newPlayers)) {
                                    summaryNewElement.textContent = numberFormatter.format(newPlayers);
                                }
                            };

                            const renderData = (data) => {
                                if (!Array.isArray(data) || data.length === 0) {
                                    tableBody.replaceChildren();
                                    return;
                                }

                                const sortedData = sortScanLogData(data);
                                const displayCount = configuredDisplayCount > 0
                                    ? configuredDisplayCount
                                    : Math.min(fallbackDisplayCount, sortedData.length);
                                const playersToDisplay = sortedData.slice(0, displayCount);

                                synchronizeTableRows(playersToDisplay);
                            };

                            const fetchLatestScanLog = () => {
                                const url = new URL('scan_log_poll.php', window.location.href);
                                url.searchParams.set('limit', String(fetchLimit));

                                fetch(url.toString(), {
                                    headers: {
                                        Accept: 'application/json',
                                    },
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
                                            renderData(payload.players);
                                        }

                                        if (payload.summary && typeof payload.summary === 'object') {
                                            const scannedValue = Number(payload.summary.scannedPlayers);
                                            const newValue = Number(payload.summary.newPlayers);

                                            updateSummary(
                                                Number.isNaN(scannedValue) ? undefined : scannedValue,
                                                Number.isNaN(newValue) ? undefined : newValue
                                            );
                                        }
                                    })
                                    .catch(() => {
                                        // Ignore polling errors to avoid interrupting the page.
                                    });
                            };

                            renderData(scanLogData);
                            updateSummary(baseScannedPlayers, baseNewPlayers);
                            fetchLatestScanLog();

                            window.setInterval(fetchLatestScanLog, pollIntervalMs);
                        })();
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-lg-6 mb-3">
            <!-- Main Leaderboard -->
            <div class="bg-body-tertiary p-3 rounded">
                <h2>Trophy Leaderboard</h2>
                <p>
                    The trophy leaderboard uses the official point system:
                </p>
                <ul>
                    <li><img src="/img/trophy-platinum.svg" alt="Platinum" height="18" /><span class="trophy-platinum"> ~ 300 points</span></li>
                    <li><img src="/img/trophy-gold.svg" alt="Gold" height="18" /><span class="trophy-gold"> ~ 90 points</span></li>
                    <li><img src="/img/trophy-silver.svg" alt="Silver" height="18" /><span class="trophy-silver"> ~ 30 points</span></li>
                    <li><img src="/img/trophy-bronze.svg" alt="Bronze" height="18" /><span class="trophy-bronze"> ~ 15 points</span></li>
                </ul>
                <p>
                    These are the requirements for each level:
                </p>
                <ul>
                    <li>1-100 ~ 60 points (4 bronze trophies)</li>
                    <li>101-200 ~ 90 points (6 bronze trophies)</li>
                    <li>201-300 ~ 450 points (30 bronze trophies)</li>
                    <li>301-400 ~ 900 points (60 bronze trophies)</li>
                    <li>401-500 ~ 1350 points (90 bronze trophies)</li>
                    <li>501-600 ~ 1800 points (120 bronze trophies)</li>
                    <li>601-700 ~ 2250 points (150 bronze trophies)</li>
                    <li>701-800 ~ 2700 points (180 bronze trophies)</li>
                    <li>801-900 ~ 3150 points (210 bronze trophies)</li>
                    <li>901-1000 ~ 3600 points (240 bronze trophies)</li>
                    <li>...and so on, every 100th level increases the level requirement with 450 points.</li>
                </ul>
            </div>
        </div>

        <div class="col-12 col-lg-6 mb-3">
            <!-- Rarity Leaderboard -->
            <div class="bg-body-tertiary p-3 rounded">
                <h2>Rarity Leaderboard</h2>
                <p>The rarity leaderboard uses the formula <kbd>1/x - 1, rounded down</kbd></p>
                <p>
                    <strong>Examples:</strong><br>
                    50% (0.5):  For every person that has the trophy, 1 person doesn't.  <strong>1 point</strong><br>
                    10% (0.1):  For every person that has the trophy, 9 don't.  <strong>9 points</strong><br>
                    5% (0.05): For every person that has the trophy, 19 don't.  <strong>19 points</strong><br>
                    1% (0.01): For every person that has the trophy, 99 don't.  <strong>99 points</strong><br>
                    0.5% (0.005):  For every person that has the trophy, 199 don't.  <strong>199 points</strong><br>
                    0.1% (0.001): For every person that has the trophy, 999 don't.  <strong>999 points</strong><br>
                    Thanks to <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/player/dmland12">dmland12</a> for bringing this formula to our attention (<a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://forum.psnprofiles.com/topic/46506-rarity-leaderboard/?page=8#comment-1852921" target="_blank">source</a>).
                </p>
                <p>
                    Our rarity naming uses the following numbers:
                </p>
                <ul>
                    <li><span class="trophy-legendary">0-0.02% ~ Legendary</span></li>
                    <li><span class="trophy-epic">0.03-0.2% ~ Epic</span></li>
                    <li><span class="trophy-rare">0.21-2% ~ Rare</span></li>
                    <li><span class="trophy-uncommon">2.01-10% ~ Uncommon</span></li>
                    <li>10.01-100% ~ Common</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <!-- Thanks -->
            <div class="bg-body-tertiary p-3 rounded">
                <h2>Thanks</h2>
                <p>
                    <ul>
                        <li><a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://psnp-plus.huskycode.dev/">PSNP+</a> (<a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://forum.psnprofiles.com/profile/229685-husky/">HusKy</a>) for allowing PSN100 to use the "Unobtainable Trophies Master List" data.</li>
                    </ul>
                </p>
            </div>
        </div>
    </div>
</main>

<?php
require_once("footer.php");
?>
