<?php
if (!isset($accountId)) {
    header("Location: /player/", true, 303);
    die();
}

$query = $database->prepare("SELECT * FROM player WHERE account_id = :account_id");
$query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
$query->execute();
$player = $query->fetch();

$title = $player["online_id"] . "'s Timeline ~ PSN 100%";
require_once("player_header.php");
?>
        <div class="row">
            <div class="col-2 text-center">
                <h5><a href="/player/<?= $player["online_id"]; ?>">Games</a></h5>
            </div>
            <div class="col-2 text-center">
                <h5><a href="/player/<?= $player["online_id"]; ?>/log">Log</a></h5>
            </div>
            <div class="col-2 text-center">
                <h5><a href="/player/<?= $player["online_id"]; ?>/advisor">Trophy Advisor</a></h5>
            </div>
            <div class="col-2 text-center">
                <h5><a href="/game?sort=completion&player=<?= $player["online_id"]; ?>">Game Advisor</a></h5>
            </div>
            <div class="col-2 text-center">
                <h5>Timeline</h5>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <style>
                    div.timeline
                    {
                        height: 800px;
                    }
                    div.timeline ul
                    {
                        white-space: nowrap;
                    }
                    div.timeline li
                    {
                        display: inline-block;
                    }
                    ul
                    {
                        line-height: 30px;
                        margin: 0;
                        padding: 0;
                    }
                    ul.legend li span
                    {
                        background-color: #d9edf7;
                        border-bottom-width: 1px;
                        border-bottom-style: solid;
                        border-bottom-color: #000;
                        border-right-width: 1px;
                        border-right-style: solid;
                        border-right-color: #000;
                        color: #31708f;
                        display: block;
                        padding-left: 5px;
                    }
                    ul.timelinerow
                    {
                        height: 30px;
                        margin-top: 1px;
                    }
                    ul.timelinerow li a
                    {
                        display: block;
                        margin-right: 1px;
                        overflow: hidden;
                        padding-left: 3px;
                    }
                    ul.timelinerow li a.completed
                    {
                        background-color: #dff0d8;
                        color: #3c763d;
                    }
                    ul.timelinerow li a.playing
                    {
                        background-color: #fcf8e3;
                        color: #8a6d3b;
                    }
                    ul.timelinerow li a.stalled
                    {
                        background-color: #f2dede;
                        color: #a94442;
                    }
                </style>

                <div class="timeline overflow-auto">
                    <ul class="legend">
                        <?php
                        $query = $database->prepare("SELECT Date(Min(earned_date)) AS first_trophy, 
                                    Date(Max(earned_date)) AS last_trophy 
                            FROM   trophy_earned 
                            WHERE  account_id = :account_id ");
                        $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                        $query->execute();
                        $result = $query->fetch();
                        $startDate = $result["first_trophy"];
                        $endDate = $result["last_trophy"];
                        
                        $start    = (new DateTime($startDate))->modify('first day of this month');
                        $end      = (new DateTime($endDate))->modify('first day of next month');
                        $interval = DateInterval::createFromDateString('1 month');
                        $period   = new DatePeriod($start, $interval, $end);
                        
                        foreach ($period as $dt)
                        {
                            // Each day is 5px
                            echo "<li style='width: ". cal_days_in_month(CAL_GREGORIAN, $dt->format("n"), $dt->format("Y")) * 5 ."px;'><span>". $dt->format("F Y") ."</span></li>";
                        }
                        ?>
                    </ul>
                    
                    <?php
                    $query = $database->prepare("SELECT tt.id                     AS game_id, 
                                tt.name, 
                                ttp.progress, 
                                Date(Min(te.earned_date)) AS first_trophy, 
                                Date(Max(te.earned_date)) AS last_trophy 
                        FROM   trophy_title_player ttp 
                                JOIN trophy_earned te USING (np_communication_id, account_id) 
                                JOIN trophy_title tt USING (np_communication_id) 
                        WHERE  account_id = :account_id 
                                AND tt.status != 2 
                        GROUP  BY np_communication_id 
                        ORDER  BY first_trophy ");
                    $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                    $query->execute();
                    
                    $games = array();
                    while ($row = $query->fetch())
                    {
                        $game = new stdClass();
                        $game->name = $row["name"];
                        $game->url = $row["game_id"] ."-". slugify($row["name"]);
                        $game->completed = $row["progress"];
                        $game->firstTrophy = $row["first_trophy"];
                        $game->lastTrophy = $row["last_trophy"];
                        
                        array_push($games, $game);
                    }
                    
                    $done = array();
                    
                    while (count($games) != count($done))
                    {
                        echo "<ul class='timelinerow'>";
                        $lastGameDate = clone $start;
                        $lastGameDate->modify('-1 day');
                        foreach ($games as $game)
                        {
                            $firstTrophy = new DateTime($game->firstTrophy);
                            $lastTrophy = new DateTime($game->lastTrophy);
                            
                            if (!isset($done[$game->url]) && $firstTrophy > $lastGameDate)
                            {
                                $today = new DateTime();
                                
                                $class = "";
                                if ($game->completed == 100)
                                {
                                    $class = "completed";
                                }
                                elseif (date_diff($lastTrophy, $today)->days > 90)
                                {
                                    $class = "stalled";
                                }
                                else
                                {
                                    $class = "playing";
                                }
                                
                                echo "<li style='margin-left: ". (date_diff($lastGameDate, $firstTrophy)->days - 1) * 5 ."px; width: ". (date_diff($firstTrophy, $lastTrophy)->days + 1) * 5 ."px;'>";
                                echo "<a class='". $class ."' href='https://psn100.net/game/". $game->url ."/". $player["online_id"] ."' title=\"". $game->name ." (". $game->firstTrophy ." - ". $game->lastTrophy .")\">". htmlentities($game->name) ."</a>";
                                echo "</li>";
                                
                                $lastGameDate = $lastTrophy;
                                $done[$game->url] = true;
                            }
                        }
                        echo "</ul>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</main>
<?php
require_once("footer.php");
?>
