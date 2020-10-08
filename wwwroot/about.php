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
                    PSN 100% is a trophy tracking website, focusing on merging game stacks and removal of unobtainable trophies to create one list of only obtainable trophies where all users have the chance to get to the same level, without the need to replay the same game multiple times. Furthermore so does PSN 100% only calculate stats from the top 100k players in order to try and be more accurate for those who considers themselves as a trophy hunter. PSN 100% is made by trophy hunters, for trophy hunters.
                </p>

                <div class="alert alert-info" role="alert">
                    <?php
                    $query = $database->prepare("SELECT COUNT(*) FROM player WHERE last_updated_date >= now() - INTERVAL 1 DAY");
                    $query->execute();
                    $scannedPlayers = $query->fetchColumn();

                    $query = $database->prepare("SELECT COUNT(*) FROM player WHERE last_updated_date >= now() - INTERVAL 1 DAY AND status = 0 AND rank_last_week = 0");
                    $query->execute();
                    $scannedNewPlayers = $query->fetchColumn();
                    ?>
                    In the last 24 hours have we scanned <?= $scannedPlayers; ?> players, and out of those are <?= $scannedNewPlayers; ?> new!
                </div>

                <h2>What isn't PSN 100%?</h2>
                <p>
                    PSN 100% is not a community for discussion (forum), gaming/boosting sessions or trophy guides. Other sites already handle this with greatness, please use them.
                </p>
                <ul>
                    <li><a href="https://psnprofiles.com/">PSN Profiles</a></li>
                    <li><a href="https://www.playstationtrophies.org/">PlaystationTrophies</a></li>
                    <li><a href="https://psntrophyleaders.com/">PSN Trophy Leaders</a></li>
                    <li><a href="https://www.truetrophies.com/">TrueTrophies</a></li>
                    <li><a href="http://gamstat.com/">PlayStation games stats</a></li>
                </ul>

                <h2>Merge Guideline Priorities</h2>
                <p>
                    <ol>
                        <li>Available > Delisted</li>
                        <li>English language > Other language</li>
                        <li>Digital > Physical</li>
                        <li>Remaster > Original</li>
                        <li>PS4 > PS3 > PSVITA</li>
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
                    <li>901+ ~ 3600 points (240 bronze trophies)</li>
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
                    <li>50.01-100% ~ Common</li>
                    <li>20.01-50% ~ Uncommon</li>
                    <li>5.01-20% ~ Rare</li>
                    <li>1.01-5% ~ Epic</li>
                    <li>0-1% ~ Legendary</li>
                </ul>
            </div>
        </div>
    </div>
</main>
<?php
require_once("footer.php");
?>
