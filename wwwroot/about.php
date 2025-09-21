<?php
require_once __DIR__ . '/classes/AboutPageService.php';

$aboutPageService = new AboutPageService($database, $utility);
$scanSummary = $aboutPageService->getScanSummary();
$scanLogPlayers = $aboutPageService->getScanLogPlayers();

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
                    <p>
                        <?= $scanSummary->getScannedPlayers(); ?> players were scanned in the last 24 hours, and <?= $scanSummary->getNewPlayers(); ?> new players added to the leaderboards this week!

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

                                <tbody>
                                    <?php
                                    foreach ($scanLogPlayers as $player) {
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
                    </p>
                </div>
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
