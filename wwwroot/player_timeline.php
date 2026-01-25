<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/PlayerPageAccessGuard.php';
require_once __DIR__ . '/classes/PlayerTimelinePageContext.php';
require_once __DIR__ . '/classes/PlayerStatusNotice.php';

$playerPageAccessGuard = PlayerPageAccessGuard::fromAccountId($accountId ?? null);
$accountId = $playerPageAccessGuard->requireAccountId();

$context = PlayerTimelinePageContext::fromGlobals(
    $database,
    $utility,
    $player,
    (int) $accountId,
    $_GET ?? []
);

$playerSummary = $context->getPlayerSummary();
$playerNavigation = $context->getPlayerNavigation();
$playerStatusNotice = null;

if ($context->shouldShowFlaggedMessage()) {
    $playerStatusNotice = PlayerStatusNotice::flagged(
        (string) $player['online_id'],
        isset($player['account_id']) ? (string) $player['account_id'] : null
    );
} elseif ($context->shouldShowPrivateMessage()) {
    $playerStatusNotice = PlayerStatusNotice::privateProfile();
}

$title = $context->getTitle();
require_once("header.php");
?>

<main class="container">
    <?php
    require_once("player_header.php");
    ?>

    <div class="p-3">
        <div class="row">
            <div class="col-12 col-lg-3">
                <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover text-danger" href="/player/<?= $player["online_id"]; ?>/report">Report Player</a>
            </div>

            <div class="col-12 col-lg-6 mb-3 text-center">
                <?php require __DIR__ . '/player_navigation.php'; ?>
            </div>

            <div class="col-12 col-lg-3 mb-3">
            </div>
        </div>
    </div>

    <div class="row">
        <?php
        if ($playerStatusNotice !== null) {
            ?>
            <div class="col-12 text-center">
                <h3><?= $playerStatusNotice->getMessage(); ?></h3>
            </div>
            <?php
        } elseif ($context->shouldShowTimeline()) {
            ?>
            <div class="row">
                <div class="col-12">
                    <style>
                        div.timeline
                        {
                            height: 650px;
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
                            background-color: #cfe2ff;
                            border-bottom-width: 1px;
                            border-bottom-style: solid;
                            border-bottom-color: #000;
                            border-right-width: 1px;
                            border-right-style: solid;
                            border-right-color: #000;
                            color: #000;
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
                            background-color: #d1e7dd;
                            color: #000;
                        }
                        ul.timelinerow li a.playing
                        {
                            background-color: #fff3cd;
                            color: #000;
                        }
                        ul.timelinerow li a.stalled
                        {
                            background-color: #f8d7da;
                            color: #000;
                        }
                    </style>

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
                    
                    $start    = null;
                    if (isset($startDate)) {
                        $start    = (new DateTime($startDate))->modify('first day of this month');
                    }
                    $end      = null;
                    if (isset($endDate)) {
                        $end      = (new DateTime($endDate))->modify('first day of next month');
                    }

                    if (is_null($start) || is_null($end)) {
                        echo "No trophies, thus no timeline to show.";
                    } else {
                        ?>
                        <div class="timeline overflow-auto">
                            <ul class="legend">
                                <?php
                                $interval = DateInterval::createFromDateString('1 month');
                                $period   = new DatePeriod($start, $interval, $end);

                                foreach ($period as $dt)
                                {
                                    // Each day is 5px
                                    echo "<li style='width: ". cal_days_in_month(CAL_GREGORIAN, (int) $dt->format("n"), (int) $dt->format("Y")) * 5 ."px;'><span>". $dt->format("F Y") ."</span></li>";
                                }
                                ?>
                            </ul>
                            
                            <?php
                            $query = $database->prepare("SELECT
                                    tt.id AS game_id,
                                    tt.name,
                                    ttp.progress,
                                    DATE(MIN(te.earned_date)) AS first_trophy,
                                    DATE(MAX(te.earned_date)) AS last_trophy
                                FROM
                                    trophy_title_player ttp
                                JOIN trophy_earned te USING(
                                        np_communication_id,
                                        account_id
                                    )
                                JOIN trophy_title tt USING(np_communication_id)
                                JOIN trophy_title_meta ttm USING(np_communication_id)
                                WHERE
                                    account_id = :account_id AND ttm.status = 0
                                GROUP BY
                                    np_communication_id
                                ORDER BY
                                    first_trophy");
                            
                            // $query = $database->prepare("SELECT
                            //         tt.id AS game_id,
                            //         tt.name,
                            //         tg.group_id,
                            //         tg.name AS dlc_name,
                            //         tgp.progress,
                            //         DATE(MIN(te.earned_date)) AS first_trophy,
                            //         DATE(MAX(te.earned_date)) AS last_trophy
                            //     FROM
                            //         trophy_group_player tgp
                            //     JOIN trophy_earned te USING(
                            //             np_communication_id,
                            //             group_id,
                            //             account_id
                            //         )
                            //     JOIN trophy_title tt USING(np_communication_id)
                            //     JOIN trophy_title_meta ttm USING(np_communication_id)
                            //     JOIN trophy_group tg USING(np_communication_id, group_id)
                            //     WHERE
                            //         account_id = :account_id AND ttm.status != 2
                            //     GROUP BY
                            //         np_communication_id, group_id
                            //     ORDER BY
                            //         first_trophy");
                            $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                            $query->execute();
                            
                            $games = array();
                            while ($row = $query->fetch())
                            {
                                $game = new stdClass();
                                $game->name = $row["name"];
                                $game->url = $row["game_id"] ."-". $utility->slugify($row["name"]) ."/". $player["online_id"];
                                $game->completed = $row["progress"];
                                $game->firstTrophy = $row["first_trophy"];
                                $game->lastTrophy = $row["last_trophy"];
                                
                                // if ($row["group_id"] !== "default")
                                // {
                                //     $game->name .= " ~ ". $row["dlc_name"];
                                //     $game->url .= "#". $row["group_id"];
                                // }

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
                                    if (!isset($game->firstTrophy) || !isset($game->lastTrophy)) {
                                        $done[$game->url] = true;
                                        continue;
                                    }

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
                                        echo "<a class='". $class ."' href='https://psn100.net/game/". $game->url ."' title=\"". $game->name ." (". $game->firstTrophy ." - ". $game->lastTrophy .")\">". htmlentities($game->name) ."</a>";
                                        echo "</li>";
                                        
                                        $lastGameDate = $lastTrophy;
                                        $done[$game->url] = true;
                                    }
                                }
                                echo "</ul>";
                            }
                            ?>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
            <?php
        }
        ?>
    </div>
</main>

<?php
require_once("footer.php");
?>
