<?php
$title = "About ~ PSN 100%";
require_once("header.php");
?>
<main>
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1>About</h1>
            </div>

            <div class="col-12">
                <h2>What is PSN 100%?</h2>
                <p>
                    PSN 100% is a trophy tracking website, focusing on merging game stacks and removal of unobtainable trophies to create one list of only obtainable trophies where all users have the chance to get to the same level, without the need to replay the same game multiple times. Furthermore so does PSN 100% only calculate stats from the top 50k players in order to try and be more accurate for those who considers themselves as a trophy hunter. PSN 100% is made by trophy hunters, for trophy hunters.
                </p>

                <h2>Scan Log</h2>
                <p>
                    <?php
                    $query = $database->prepare("SELECT COUNT(*) FROM player WHERE last_updated_date >= now() - INTERVAL 1 DAY");
                    $query->execute();
                    $scannedPlayers = $query->fetchColumn();

                    $query = $database->prepare("SELECT COUNT(*) FROM player WHERE last_updated_date >= now() - INTERVAL 1 DAY AND status = 0 AND rank_last_week = 0");
                    $query->execute();
                    $scannedNewPlayers = $query->fetchColumn();
                    ?>
                    <?= $scannedPlayers; ?> players were scanned in the last 24 hours, in which <?= $scannedNewPlayers; ?> are new!

                    <table class="table table-responsive table-striped">
                        <tr>
                            <th scope="col" class="align-middle">Rank</th>
                            <th scope="col" class="align-middle" width="11%">Updated</th>
                            <th scope="col"></th>
                            <th scope="col" width="100%"></th>
                            <th scope="col"></th>
                            <th scope="col" class="text-center"><img src="/img/playstation/level.png" alt="Level" /></th>
                            <th scope="col" class="text-center align-middle">Points</th>
                        </tr>

                        <?php
                        $query = $database->prepare("SELECT
                                online_id,
                                country,
                                avatar_url,
                                plus,
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
                                $rank .= "(Cheater)";
                            } elseif ($player["status"] == 2) {
                                $rank .= "(Hiding)";
                            } elseif ($player["status"] == 3) {
                                $rank .= "(Private)";
                            } elseif ($player["rank_last_week"] == 0) {
                                $rank .= "(New!)";
                            } else {
                                $delta = $player["rank_last_week"] - $player["rank"];

                                if ($delta < 0) {
                                    $rank .= "(". $delta .")";
                                } elseif ($delta > 0) {
                                    $rank .= "(+". $delta .")";
                                } else {
                                    $rank .= "(=)";
                                }
                            }
                            ?>
                            <tr>
                                <th scope="row" class="align-middle text-center"><?= $rank; ?></th>
                                <td class="text-center"><?= str_replace(" ", "<br>", $player["last_updated_date"]); ?></td>
                                <td class="text-center">
                                    <div style="position:relative;">
                                        <a href="/player/<?= $player["online_id"]; ?>">
                                            <img src="/img/avatar/<?= $player["avatar_url"]; ?>" alt="" height="50" width="50" />
                                            <?php
                                            if ($player["plus"] === "1") {
                                                ?>
                                                <img src="/img/playstation/plus.png" style="position:absolute; top:-5px; right:-5px; width:25px;" alt="" />
                                                <?php
                                            } ?>
                                        </a>
                                    </div>
                                </td>
                                <td class="align-middle"><a href="/player/<?= $player["online_id"]; ?>"><?= $player["online_id"]; ?></a></td>
                                <td class="text-center">
                                    <img src="/img/country/<?= $player["country"]; ?>.svg" alt="<?= $countryName; ?>" title="<?= $countryName; ?>" height="50" width="50" style="border-radius: 50%;" />
                                </td>
                                <td class="text-center">
                                    <?= $player["level"]; ?>
                                    <div class="progress">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $player["progress"]; ?>%" aria-valuenow="<?= $player["progress"]; ?>" aria-valuemin="0" aria-valuemax="100"><?= $player["progress"]; ?>%</div>
                                    </div>
                                </td>
                                <td class="text-center"><?= number_format($player["points"]); ?></td>
                            </tr>
                            <?php
                        }
                        ?>
                    </table>
                </p>

                <h2>What isn't PSN 100%?</h2>
                <p>
                    PSN 100% is not a community for discussion (forum), gaming/boosting sessions or trophy guides. Other sites already handle this with greatness, please use them.
                </p>
                <ul>
                    <li><a href="https://psnprofiles.com/">PSN Profiles</a></li>
                    <li><a href="https://www.playstationtrophies.org/">PlaystationTrophies</a></li>
                    <li><a href="https://psntrophyleaders.com/">PSN Trophy Leaders</a></li>
                    <li><a href="https://www.truetrophies.com/">TrueTrophies</a></li>
                    <li><a href="https://www.exophase.com/">Exophase</a></li>
                </ul>

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

            <div class="col-12">
                <h2>Main Leaderboard</h2>
                <p>
                    The main leaderboard uses the official point system:
                </p>
                <ul>
                    <li><img src="/img/playstation/bronze.png" alt="Bronze" width="24" /> ~ 15 points</li>
                    <li><img src="/img/playstation/silver.png" alt="Silver" width="24" /> ~ 30 points</li>
                    <li><img src="/img/playstation/gold.png" alt="Gold" width="24" /> ~ 90 points</li>
                    <li><img src="/img/playstation/platinum.png" alt="Platinum" width="24" /> ~ 300 points</li>
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
                    Thanks to <a href="/player/dmland12">dmland12</a> for bringing this formula to our attention (<a href="https://forum.psnprofiles.com/topic/46506-rarity-leaderboard/?page=8#comment-1852921" target="_blank">source</a>).
                </p>
                <p>
                    Our rarity naming uses the following numbers:
                </p>
                <ul>
                    <li>0-0.5% ~ Legendary</li>
                    <li>0.51-2.5% ~ Epic</li>
                    <li>2.51-10% ~ Rare</li>
                    <li>10.01-25% ~ Uncommon</li>
                    <li>25.01-100% ~ Common</li>
                </ul>
            </div>
        </div>
    </div>
</main>
<?php
require_once("footer.php");
?>
