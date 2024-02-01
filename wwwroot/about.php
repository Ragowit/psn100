<?php
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
                        PSN 100% is a trophy tracking website, focusing on merging game stacks and removal of unobtainable trophies to create one list of only obtainable trophies where all users have the chance to get to the same level, without the need to replay the same game multiple times. Furthermore so does PSN 100% only calculate stats from the top 50k players in order to try and be more accurate for those who considers themselves as a trophy hunter. PSN 100% is made by trophy hunters, for trophy hunters.
                    </p>
                </div>

                <!-- What isn't... -->
                <div class="bg-body-tertiary p-3 rounded">
                    <h2>What isn't PSN 100%?</h2>
                    <p>
                        PSN 100% is not a community for discussion (forum), gaming/boosting sessions or trophy guides. Other sites already handle this with greatness, please use them.
                    </p>
                    <ul>
                        <li><a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://psnprofiles.com/">PSN Profiles</a></li>
                        <li><a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://www.playstationtrophies.org/">PlaystationTrophies</a></li>
                        <li><a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://psntrophyleaders.com/">PSN Trophy Leaders</a></li>
                        <li><a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://www.truetrophies.com/">TrueTrophies</a></li>
                        <li><a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://www.exophase.com/">Exophase</a></li>
                        <li><a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://www.hlprofiles.ee/">HLProfiles</a></li>
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
                        <?php
                        $query = $database->prepare("SELECT COUNT(*) FROM player WHERE last_updated_date >= now() - INTERVAL 1 DAY");
                        $query->execute();
                        $scannedPlayers = $query->fetchColumn();

                        $query = $database->prepare("SELECT COUNT(*) FROM player WHERE status = 0 AND rank_last_week = 0");
                        $query->execute();
                        $scannedNewPlayers = $query->fetchColumn();
                        ?>
                        <?= $scannedPlayers; ?> players were scanned in the last 24 hours, and <?= $scannedNewPlayers; ?> new players added to the leaderboards this week!

                        <div class="table-responsive-xxl">
                            <table class="table">
                                <thead>
                                    <tr class="text-uppercase">
                                        <th scope="col" class="text-center">Rank</th>
                                        <th scope="col" class="text-center">Updated</th>
                                        <th scope="col">User</th>
                                        <th scope="col" class="text-center" style="width: 75px;">Level</th>
                                        <th scope="col" class="text-center">Points</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php
                                    $query = $database->prepare("SELECT
                                            online_id,
                                            country,
                                            avatar_url,
                                            last_updated_date,
                                            `level`,
                                            progress,
                                            points,
                                            `rank`,
                                            rank_last_week,
                                            `status`
                                        FROM
                                            `player`
                                        ORDER BY
                                            last_updated_date
                                        DESC
                                        LIMIT 10");
                                    $query->execute();
                                    $players = $query->fetchAll();

                                    foreach ($players as $player) {
                                        $countryName = Locale::getDisplayRegion("-" . $player["country"], "en");
                                        
                                        if ($player["status"] != 0) {
                                            $rank = "N/A";
                                        } else {
                                            $rank = $player["rank"];
                                        }
                                        $rank .= "<br>";
                                        if ($player["status"] == 1) {
                                            $rank .= "<span style='color: #9d9d9d;'>(Cheater)</span>";
                                        } elseif ($player["status"] == 2) {
                                            $rank .= "<span style='color: #9d9d9d;'>(Hiding)</span>";
                                        } elseif ($player["status"] == 3) {
                                            $rank .= "<span style='color: #9d9d9d;'>(Private)</span>";
                                        } elseif ($player["status"] == 4) {
                                            $rank .= "<span style='color: #9d9d9d;'>(Inactive)</span>";
                                        } elseif ($player["rank_last_week"] == 0) {
                                            $rank .= "(New!)";
                                        } else {
                                            $delta = $player["rank_last_week"] - $player["rank"];

                                            if ($delta < 0) {
                                                $rank .= "<span style='color: #d40b0b;'>(". $delta .")</span>";
                                            } elseif ($delta > 0) {
                                                $rank .= "<span style='color: #0bd413;'>(+". $delta .")</span>";
                                            } else {
                                                $rank .= "<span style='color: #0070d1;'>(=)</span>";
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <th scope="row" class="align-middle text-center"><?= $rank; ?></th>
                                            <td class="align-middle text-center"><?= substr($player["last_updated_date"], 11); ?></td>
                                            <td class="align-middle">
                                                <div class="hstack gap-3">
                                                    <div>
                                                        <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/player/<?= $player["online_id"]; ?>">
                                                            <img src="/img/avatar/<?= $player["avatar_url"]; ?>" alt="" height="50" width="50" />
                                                        </a>
                                                    </div>

                                                    <div>
                                                        <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" style="white-space: nowrap;" href="/player/<?= $player["online_id"]; ?>"><?= $player["online_id"]; ?></a>
                                                    </div>

                                                    <div class="ms-auto">
                                                        <img src="/img/country/<?= $player["country"]; ?>.svg" alt="<?= $countryName; ?>" title="<?= $countryName; ?>" height="50" width="50" style="border-radius: 50%;" />
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="align-middle text-center">
                                                <?php
                                                if ($player["status"] == 3) {
                                                    ?>
                                                    N/A
                                                    <?php
                                                } else {
                                                    ?>
                                                    <img src="/img/star.svg" class="mb-1" alt="Level" title="Level" height="18" /> <?= $player["level"]; ?>
                                                    <div class="progress" title="<?= $player["progress"]; ?>%">
                                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $player["progress"]; ?>%" aria-valuenow="<?= $player["progress"]; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                    <?php
                                                }
                                                ?>
                                            </td>
                                            <td class="align-middle text-center">
                                                <?php
                                                if ($player["status"] == 3) {
                                                    ?>
                                                    N/A
                                                    <?php
                                                } else {
                                                    echo number_format($player["points"]);
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
                <h2>Main Leaderboard</h2>
                <p>
                    The main leaderboard uses the official point system:
                </p>
                <ul>
                    <li><img src="/img/trophy-platinum.svg" alt="Platinum" height="18" /> ~ <span class="trophy-platinum">300 points</span></li>
                    <li><img src="/img/trophy-gold.svg" alt="Gold" height="18" /> ~ <span class="trophy-gold">90 points</span></li>
                    <li><img src="/img/trophy-silver.svg" alt="Silver" height="18" /> ~ <span class="trophy-silver">30 points</span></li>
                    <li><img src="/img/trophy-bronze.svg" alt="Bronze" height="18" /> ~ <span class="trophy-bronze">15 points</span></li>
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
                    <li>0-0.02% ~ <span class="trophy-legendary">Legendary</span></li>
                    <li>0.03-0.2% ~ <span class="trophy-epic">Epic</span></li>
                    <li>0.21-2% ~ <span class="trophy-rare">Rare</span></li>
                    <li>2.01-20% ~ <span class="trophy-uncommon">Uncommon</span></li>
                    <li>20.01-100% ~ Common</li>
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
                        <li><a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://psnp-plus.netlify.app/">PSNP+</a> (<a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://forum.psnprofiles.com/profile/229685-husky/">HusKy</a>) for allowing PSN100 to use the "Unobtainable Trophies Master List" data.</li>
                    </ul>
                </p>
            </div>
        </div>
    </div>
</main>

<?php
require_once("footer.php");
?>
