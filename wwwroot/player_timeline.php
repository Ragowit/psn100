<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/PlayerPageAccessGuard.php';
require_once __DIR__ . '/classes/PlayerTimelineLayout.php';
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
                    $timelineData = $context->getTimelineData();
                    if ($timelineData === null) {
                        echo "No trophies, thus no timeline to show.";
                    } else {
                        $start = $timelineData->getStartDate();
                        $end = $timelineData->getEndDate();
                        $today = new DateTimeImmutable('today');
                        $rows = PlayerTimelineLayout::buildRows($start, $timelineData->getEntries());
                        $playerOnlineId = (string) ($player['online_id'] ?? '');
                        ?>
                        <div class="timeline overflow-auto">
                            <ul class="legend">
                                <?php
                                $period = new DatePeriod($start, new DateInterval('P1M'), $end);

                                foreach ($period as $dt)
                                {
                                    // Each day is 5px
                                    echo "<li style='width: ". cal_days_in_month(CAL_GREGORIAN, (int) $dt->format("n"), (int) $dt->format("Y")) * 5 ."px;'><span>". $dt->format("F Y") ."</span></li>";
                                }
                                ?>
                            </ul>
                            
                            <?php
                            foreach ($rows as $row) {
                                echo "<ul class='timelinerow'>";
                                foreach ($row as $item) {
                                    $entry = $item->getEntry();
                                    $gameSlug = $entry->getGameId() . '-' . $utility->slugify($entry->getName());
                                    $gameUrl = $gameSlug;
                                    if ($playerOnlineId !== '') {
                                        $gameUrl .= '/' . rawurlencode($playerOnlineId);
                                    }

                                    $firstTrophy = $entry->getFirstTrophyDate()->format('Y-m-d');
                                    $lastTrophy = $entry->getLastTrophyDate()->format('Y-m-d');
                                    $title = sprintf('%s (%s - %s)', $entry->getName(), $firstTrophy, $lastTrophy);
                                    $class = $entry->getStatusClass($today);
                                    $marginLeft = $item->getOffsetDays() * 5;
                                    $width = $item->getDurationDays() * 5;

                                    echo "<li style='margin-left: ". $marginLeft ."px; width: ". $width ."px;'>";
                                    echo "<a class='". $class ."' href='https://psn100.net/game/". $gameUrl ."' title=\"". htmlspecialchars($title, ENT_QUOTES) ."\">". htmlspecialchars($entry->getName(), ENT_QUOTES) ."</a>";
                                    echo "</li>";
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
