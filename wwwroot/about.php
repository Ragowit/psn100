<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/classes/AboutPageService.php';
require_once __DIR__ . '/classes/AboutPageContext.php';

$aboutPageService = new AboutPageService($database, $utility);
$aboutPageContext = AboutPageContext::create($aboutPageService);

$scanSummary = $aboutPageContext->getScanSummary();
$initialScanLogPlayers = $aboutPageContext->getInitialScanLogPlayers();
$scanLogPlayersData = $aboutPageContext->getScanLogPlayersData();
$initialDisplayCount = $aboutPageContext->getInitialDisplayCount();
$maxScanLogDisplayCount = $aboutPageContext->getMaxInitialDisplayCount();

$title = $aboutPageContext->getTitle();
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
                        PSN 100% is a trophy tracking platform dedicated to creating the ultimate 'clean' trophy list. By merging game stacks and filtering out unobtainable trophies, we provide a unified list of unique, earnable trophies. This ensures every user competes on a level playing field, without the need to replay titles or miss out due to technical issues or retired services.<br>
                        <br>
                        To maintain a competitive edge, PSN 100% calculates statistics exclusively from the top 10,000 players, offering more accurate benchmarks for dedicated hunters. Built by trophy hunters, for trophy hunters.
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
                                        $encodedOnlineId = rawurlencode($onlineId);
                                        $escapedOnlineId = htmlspecialchars($onlineId, ENT_QUOTES, 'UTF-8');
                                        $escapedAvatarUrl = htmlspecialchars($player->getAvatarUrl(), ENT_QUOTES, 'UTF-8');
                                        $lastUpdateElementId = 'lastUpdate' . preg_replace('/[^a-zA-Z0-9_-]/', '', $onlineId);
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
                                                    echo '<span style="color: #9d9d9d;">('
                                                        . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8')
                                                        . ')</span>';
                                                } elseif ($player->isNew()) {
                                                    echo '(New!)';
                                                } elseif ($rankDeltaLabel !== null && $rankDeltaColor !== null) {
                                                    echo "<span style=\"color: " . $rankDeltaColor . ";\">" . $rankDeltaLabel . '</span>';
                                                }
                                                ?>
                                            </th>
                                            <td
                                                class="align-middle text-center js-localized-date"
                                                <?php if ($lastUpdatedDate !== null) { ?>
                                                data-timestamp="<?= htmlspecialchars($lastUpdatedDate, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-time-style="medium"
                                                <?php } ?>
                                            ></td>
                                            <td class="align-middle">
                                                <div class="hstack gap-3">
                                                    <div>
                                                        <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/player/<?= $encodedOnlineId; ?>">
                                                            <img src="/img/avatar/<?= $escapedAvatarUrl; ?>" alt="" height="50" width="50" />
                                                        </a>
                                                    </div>

                                                    <div>
                                                        <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" style="white-space: nowrap;" href="/player/<?= $encodedOnlineId; ?>"><?= $escapedOnlineId; ?></a>
                                                    </div>

                                                    <div class="ms-auto">
                                                        <img src="/img/country/<?= htmlspecialchars($countryCode, ENT_QUOTES, 'UTF-8'); ?>.svg" alt="<?= $countryName; ?>" title="<?= $countryName; ?>" height="50" width="50" style="border-radius: 50%;" />
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
                <?php if ($scanLogPlayersData !== []) { ?>
                    <link rel="stylesheet" href="<?= htmlspecialchars(StaticAsset::url('/css/scan-log-renderer.css'), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php
                    $scanLogConfig = [
                        'scanLogData' => $scanLogPlayersData,
                        'configuredDisplayCount' => max(0, $initialDisplayCount),
                        'fallbackDisplayCount' => max(1, $maxScanLogDisplayCount),
                        'baseScannedPlayers' => (int) $scanSummary->getScannedPlayers(),
                        'baseNewPlayers' => (int) $scanSummary->getNewPlayers(),
                        'pollIntervalMs' => 5000,
                    ];
                    ?>
                    <script type="application/json" id="scan-log-config"><?= json_encode($scanLogConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
                    <script src="<?= htmlspecialchars(StaticAsset::url('/js/scan-log-renderer.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
                <?php } ?>
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
                <h2 class="mb-3">Rarity Leaderboard</h2>
                <p>Points are awarded based on trophy rarity using the following formula:</p>
                <div class="mb-3 text-center">
                    <kbd class="fs-5">1 / x - 1</kbd> <span class="text-muted">(rounded down)</span>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <strong>Examples:</strong><br>
                        <ul class="list-unstyled mb-2">
                            <li>• <strong>50% (0.5):</strong> 1 person has it, 1 doesn't = <strong>1 point</strong></li>
                            <li>• <strong>10% (0.1):</strong> 1 person has it, 9 don't = <strong>9 points</strong></li>
                            <li>• <strong>1% (0.01):</strong> 1 person has it, 99 don't = <strong>99 points</strong></li>
                            <li>• <strong>0.1% (0.001):</strong> 1 person has it, 999 don't = <strong>999 points</strong></li>
                        </ul>
                        <small class="text-muted">
                            Thanks to <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/player/dmland12">dmland12</a> 
                            for this formula (<a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://forum.psnprofiles.com/topic/46506-rarity-leaderboard/?page=8#comment-1852921" target="_blank">source</a>).
                        </small>
                    </div>
                </div>

                <hr>

                <h3 class="h4">Rarity Tiers</h3>
                <p>We use two distinct rarity scales based on player data from the top 10,000 players.</p>

                <div class="row">
                    <!-- Leaderboard Rarity -->
                    <div class="col-md-6">
                        <h4 class="h5 text-decoration-underline">Leaderboard Rarity</h4>
                        <p class="small text-muted">Calculated against the entire top 10,000 player pool.</p>
                        <ul class="list-unstyled">
                            <li><span class="trophy-legendary">0.00 - 0.02% ~ Legendary</span></li>
                            <li><span class="trophy-epic">0.03 - 0.20% ~ Epic</span></li>
                            <li><span class="trophy-rare">0.21 - 2.00% ~ Rare</span></li>
                            <li><span class="trophy-uncommon">2.01 - 10.00% ~ Uncommon</span></li>
                            <li>10.01 - 100% ~ Common</li>
                        </ul>
                    </div>

                    <!-- Game Rarity -->
                    <div class="col-md-6">
                        <h4 class="h5 text-decoration-underline">Game Rarity</h4>
                        <p class="small text-muted">Calculated against owners of the specific game within the top 10,000.</p>
                        <ul class="list-unstyled">
                            <li><span class="trophy-legendary">0.00 - 1.00% ~ Legendary</span></li>
                            <li><span class="trophy-epic">1.01 - 5.00% ~ Epic</span></li>
                            <li><span class="trophy-rare">5.01 - 20.00% ~ Rare</span></li>
                            <li><span class="trophy-uncommon">20.01 - 60.00% ~ Uncommon</span></li>
                            <li>60.01 - 100% ~ Common</li>
                        </ul>
                    </div>
                </div>
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
