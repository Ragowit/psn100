<?php

require_once 'classes/TrophyPage.php';
require_once 'classes/TrophyRarityFormatter.php';

if (!isset($trophyId)) {
    header("Location: /trophy/", true, 303);
    die();
}

$trophyService = new TrophyService($database);
$trophyRarityFormatter = new TrophyRarityFormatter();

try {
    $trophyPage = TrophyPage::create(
        $trophyService,
        $utility,
        $trophyRarityFormatter,
        (int) $trophyId,
        isset($player) ? (string) $player : null
    );
} catch (TrophyNotFoundException) {
    header("Location: /trophy/", true, 303);
    die();
} catch (TrophyPlayerNotFoundException $exception) {
    $slug = $utility->slugify($exception->getTrophyName());
    header("Location: /trophy/" . $exception->getTrophyId() . '-' . $slug, true, 303);
    die();
}

$trophy = $trophyPage->getTrophy();
$playerTrophy = $trophyPage->getPlayerTrophy();
$firstAchievers = $trophyPage->getFirstAchievers();
$latestAchievers = $trophyPage->getLatestAchievers();
$metaData = $trophyPage->getMetaData();
$title = $trophyPage->getPageTitle();
$trophyRarity = $trophyPage->getTrophyRarity();
$playerOnlineId = $trophyPage->getPlayerOnlineId();

require_once("header.php");
?>

<main class="container">
    <?php
    if ($trophy["status"] == 1) {
        ?>
        <div class="row">
            <div class="col-12">
                <div class="alert alert-warning" role="alert">
                    This trophy is unobtainable and not accounted for on any leaderboard.
                </div>
            </div>
        </div>
        <?php
    }
    ?>

    <div class="row">
        <div class="col-12">
            <div class="card rounded-4">
                <div class="d-flex justify-content-center align-items-center">
                    <img class="card-img object-fit-cover rounded-4" style="height: 25rem;" src="/img/title/<?= ($trophy["game_icon"] == ".png") ? ((str_contains($trophy["platform"], "PS5")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-game.png") : $trophy["game_icon"]; ?>" alt="<?= $trophy["game_name"]; ?>" title="<?= $trophy["game_name"]; ?>" />
                    <div class="card-img-overlay d-flex align-items-end">
                        <div class="bg-body-tertiary p-3 rounded w-100">
                            <div class="row">
                                <div class="col-8">
                                    <div class="hstack gap-3">
                                        <div>
                                            <img src="/img/trophy/<?= ($trophy["trophy_icon"] == ".png") ? ((str_contains($trophy["platform"], "PS5")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-trophy.png") : $trophy["trophy_icon"]; ?>" alt="<?= $trophy["trophy_name"]; ?>" title="<?= $trophy["trophy_name"]; ?>" style="width: 5rem;" />
                                        </div>

                                        <div>
                                            <div class="vstack gap-1">
                                                <div class="hstack gap-3">
                                                    <div>
                                                        <b><?= htmlentities($trophy["trophy_name"]); ?></b>
                                                    </div>

                                                    <?php
                                                    if (isset($playerTrophy) && $playerTrophy && $playerTrophy["earned"] == 1) {
                                                        ?>
                                                        <div>
                                                            <span class="badge rounded-pill text-bg-success" id="earnedTrophy"></span>
                                                            <script>
                                                                document.getElementById("earnedTrophy").innerHTML = 'Earned ' + new Date('<?= $playerTrophy["earned_date"]; ?> UTC').toLocaleString('sv-SE');
                                                            </script>
                                                        </div>
                                                        <?php
                                                    }
                                                    ?>
                                                </div>

                                                <div>
                                                    <?= nl2br(htmlentities($trophy["trophy_detail"], ENT_QUOTES, "UTF-8")); ?>
                                                    <?php
                                                    if ($trophy["progress_target_value"] != null) {
                                                        echo "<br><b>";
                                                        if (isset($playerTrophy) && isset($playerTrophy["progress"])) {
                                                            echo $playerTrophy["progress"];
                                                        } else {
                                                            echo "0";
                                                        }
                                                        echo "/". $trophy["progress_target_value"] ."</b>";
                                                    }

                                                    if ($trophy["reward_name"] != null && $trophy["reward_image_url"] != null) {
                                                        echo "<br>Reward: <a href='/img/reward/". $trophy["reward_image_url"] ."'>". $trophy["reward_name"] ."</a>";
                                                    }
                                                    ?>
                                                </div>
                                                
                                                <div>
                                                    <div class="hstack gap-1">
                                                        <?php
                                                        foreach (explode(",", $trophy["platform"]) as $platform) {
                                                            echo "<span class=\"badge rounded-pill text-bg-primary p-2\">". $platform ."</span> ";
                                                        }
                                                        ?>

                                                        <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= $trophy["game_id"] ."-". $utility->slugify($trophy["game_name"]); ?><?= ($playerOnlineId !== null ? '/' . $playerOnlineId : ''); ?>"><?= htmlentities($trophy["game_name"]); ?></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-2 text-center align-self-center">
                                    <?php if ($trophyRarity->isUnobtainable()) { ?>
                                        <?= $trophyRarity->getLabel(); ?>
                                    <?php } else { ?>
                                        <?= $trophyRarity->renderSpan(); ?>
                                    <?php } ?>
                                </div>
                                
                                <div class="col-2 text-center align-self-center">
                                    <img src="/img/trophy-<?= $trophy["trophy_type"]; ?>.svg" alt="<?= ucfirst($trophy["trophy_type"]); ?>" title="<?= ucfirst($trophy["trophy_type"]); ?>" height="50" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-body-tertiary p-3 rounded mt-3">
        <div class="row">
            <div class="col-6">
                <div class="row">
                    <div class="col-12 text-center">
                        <h4>First Achievers</h4>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="table-responsive-xxl">
                            <table class="table">
                                <thead>
                                    <tr class="text-uppercase">
                                        <th scope="col"></th>
                                        <th scope="col">User</th>
                                        <th scope="col" class="text-center">Date</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php
                                    $count = 0;

                                    foreach ($firstAchievers as $result) {
                                        ?>
                                        <tr<?= ($playerOnlineId !== null && $result["online_id"] === $playerOnlineId) ? " class='table-primary'" : ""; ?>>
                                            <th class="align-middle" scope="row">
                                                <?= ++$count; ?>
                                            </th>
                                            <td class="w-100">
                                                <div class="hstack gap-3">
                                                    <img src="/img/avatar/<?= $result["avatar_url"]; ?>" alt="<?= $result["online_id"]; ?>" height="60" />
                                                    <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= $trophy["game_id"] ."-". $utility->slugify($trophy["game_name"]); ?>/<?= $result["online_id"]; ?>"><?= $result["online_id"]; ?></a>
                                                    <?php
                                                    if ($result["trophy_count_npwr"] < $result["trophy_count_sony"]) {
                                                        echo " <span style='color: #9d9d9d; font-weight: bold;'>(H)</span>";
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td id="faDate<?= $count; ?>" class="align-middle text-center" style="white-space: nowrap;">
                                            </td>

                                            <script>
                                                document.getElementById("faDate<?= $count; ?>").innerHTML = new Date('<?= $result["earned_date"]; ?> UTC').toLocaleString('sv-SE').replace(' ', '<br>');
                                            </script>
                                        </tr>
                                        <?php
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6">
                <div class="row">
                    <div class="col-12 text-center">
                        <h4>Latest Achievers</h4>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="table-responsive-xxl">
                            <table class="table">
                                <thead>
                                    <tr class="text-uppercase">
                                        <th scope="col"></th>
                                        <th scope="col">User</th>
                                        <th scope="col" class="text-center">Date</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php
                                    $count = 0;

                                    foreach ($latestAchievers as $result) {
                                        ?>
                                        <tr<?= ($playerOnlineId !== null && $result["online_id"] === $playerOnlineId) ? " class='table-primary'" : ""; ?>>
                                            <th class="align-middle" scope="row">
                                                <?= ++$count; ?>
                                            </th>
                                            <td class="w-100">
                                                <div class="hstack gap-3">
                                                    <img src="/img/avatar/<?= $result["avatar_url"]; ?>" alt="<?= $result["online_id"]; ?>" height="60" />
                                                    <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= $trophy["game_id"] ."-". $utility->slugify($trophy["game_name"]); ?>/<?= $result["online_id"]; ?>"><?= $result["online_id"]; ?></a>
                                                    <?php
                                                    if ($result["trophy_count_npwr"] < $result["trophy_count_sony"]) {
                                                        echo " <span style='color: #9d9d9d; font-weight: bold;'>(H)</span>";
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td id="laDate<?= $count; ?>" class="align-middle text-center" style="white-space: nowrap;">
                                            </td>

                                            <script>
                                                document.getElementById("laDate<?= $count; ?>").innerHTML = new Date('<?= $result["earned_date"]; ?> UTC').toLocaleString('sv-SE').replace(' ', '<br>');
                                            </script>
                                        </tr>
                                        <?php
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
require_once("footer.php");
?>
