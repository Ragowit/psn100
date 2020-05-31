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
                    $query = $database->prepare("SELECT COUNT(*) FROM player_queue WHERE request_time = '2020-12-25'");
                    $query->execute();
                    $queue = $query->fetchColumn();
                    ?>
                    Until the site have reached 100k users so may the statistics on this site change drastically. We have <?= $queue; ?> players in the queue. Players added on the front page take priority.
                </div>

                <h2>What isn't PSN 100%?</h2>
                <p>
                    PSN 100% is not a community for discussion (forum), gaming/boosting sessions or trophy guides. Other sites already handle this with greatness, please use them.
                </p>
                <ul>
                    <li><a href="https://psnprofiles.com/">PSN Profiles</a></li>
                    <li><a href="https://www.playstationtrophies.org/">PlaystationTrophies</a></li>
                    <li><a href="https://psntrophyleaders.com/">PSN Trophy Leaders</a></li>
                    <li><a href="http://gamstat.com/">PlayStation games stats</a></li>
                </ul>

                <h2>Merge Guidelines</h2>
                <p>
                    In the case where the game have different trophy list while still being the same game so do we go by console priority. PS4 > PS3 > PSVITA.
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
                    <li><img src="/img/playstation/platinum.png" alt="Platinum" width="24" /> ~ 180 points</li>
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
