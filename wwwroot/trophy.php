<?php

declare(strict_types=1);

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
$metaRarity = $trophyPage->getMetaRarity();
$inGameRarity = $trophyPage->getInGameRarity();
$playerOnlineId = $trophyPage->getPlayerOnlineId();

require_once("header.php");
?>

<main class="container">
    <?php
    if ($trophy->isUnobtainable()) {
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
                    <img class="card-img object-fit-cover rounded-4" style="height: 25rem;" src="/img/title/<?= htmlspecialchars($trophy->getGameIconPath(), ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($trophy->getGameName(), ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlspecialchars($trophy->getGameName(), ENT_QUOTES, 'UTF-8'); ?>" />
                    <div class="card-img-overlay d-flex align-items-end">
                        <div class="bg-body-tertiary p-3 rounded w-100">
                            <div class="row">
                                <div class="col-7">
                                    <div class="hstack gap-3">
                                        <div>
                                            <img src="/img/trophy/<?= htmlspecialchars($trophy->getTrophyIconPath(), ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($trophy->getName(), ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlspecialchars($trophy->getName(), ENT_QUOTES, 'UTF-8'); ?>" style="width: 5rem;" />
                                        </div>

                                        <div>
                                            <div class="vstack gap-1">
                                                <div class="hstack gap-3">
                                                    <div>
                                                        <b><?= htmlentities($trophy->getName()); ?></b>
                                                    </div>

                                                    <?php
                                                    if ($playerTrophy !== null && $playerTrophy->wasEarned()) {
                                                        $earnedDate = $playerTrophy->getEarnedDate();
                                                        ?>
                                                        <div>
                                                            <span class="badge rounded-pill text-bg-success" id="earnedTrophy"></span>
                                                            <script>
                                                                <?php if ($earnedDate !== null) { ?>
                                                                document.getElementById("earnedTrophy").innerHTML = 'Earned ' + new Date('<?= $earnedDate; ?> UTC').toLocaleString('sv-SE');
                                                                <?php } else { ?>
                                                                document.getElementById("earnedTrophy").innerHTML = 'Earned';
                                                                <?php } ?>
                                                            </script>
                                                        </div>
                                                        <?php
                                                    }
                                                    ?>
                                                </div>

                                                <div>
                                                    <?= nl2br(htmlentities($trophy->getDetail(), ENT_QUOTES, "UTF-8")); ?>
                                                    <?php
                                                    $progressTargetValue = $trophy->getProgressTargetValue();
                                                    if ($progressTargetValue !== null) {
                                                        $progress = $playerTrophy !== null ? $playerTrophy->getProgress() : null;
                                                        ?>
                                                        <br><b><?= htmlspecialchars($progress ?? '0', ENT_QUOTES, 'UTF-8'); ?>/<?= htmlspecialchars($progressTargetValue, ENT_QUOTES, 'UTF-8'); ?></b>
                                                        <?php
                                                    }

                                                    $rewardName = $trophy->getRewardName();
                                                    $rewardImageUrl = $trophy->getRewardImageUrl();
                                                    if ($rewardName !== null && $rewardImageUrl !== null) {
                                                        ?>
                                                        <br>Reward: <a href="/img/reward/<?= htmlspecialchars($rewardImageUrl, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($rewardName, ENT_QUOTES, 'UTF-8'); ?></a>
                                                        <?php
                                                    }
                                                    ?>
                                                </div>

                                                <div>
                                                    <div class="hstack gap-1">
                                                        <?php
                                                        foreach ($trophy->getPlatforms() as $platform) {
                                                            echo "<span class=\"badge rounded-pill text-bg-primary p-2\">" . htmlentities($platform) . "</span> ";
                                                        }
                                                        ?>

                                                        <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="<?= htmlspecialchars($trophy->getGameLink($utility, $playerOnlineId), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlentities($trophy->getGameName()); ?></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-2 text-center align-self-center">
                                    <div class="small text-uppercase text-secondary">Rarity (Meta)</div>
                                    <div>
                                        <?php if ($metaRarity->isUnobtainable()) { ?>
                                            <?= $metaRarity->getLabel(); ?>
                                        <?php } else { ?>
                                            <?= $metaRarity->renderSpan(); ?>
                                        <?php } ?>
                                    </div>
                                </div>

                                <div class="col-2 text-center align-self-center">
                                    <div class="small text-uppercase text-secondary">Rarity (In-Game)</div>
                                    <div>
                                        <?php if ($inGameRarity->isUnobtainable()) { ?>
                                            <?= $inGameRarity->getLabel(); ?>
                                        <?php } else { ?>
                                            <?= $inGameRarity->renderSpan(); ?>
                                        <?php } ?>
                                    </div>
                                </div>

                                <div class="col-1 text-center align-self-center">
                                    <img src="/img/trophy-<?= htmlspecialchars($trophy->getType(), ENT_QUOTES, 'UTF-8'); ?>.svg" alt="<?= ucfirst($trophy->getType()); ?>" title="<?= ucfirst($trophy->getType()); ?>" height="50" />
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
                                        <tr<?= $result->matchesOnlineId($playerOnlineId) ? " class='table-primary'" : ""; ?>>
                                            <th class="align-middle" scope="row">
                                                <?= ++$count; ?>
                                            </th>
                                            <td class="w-100">
                                                <div class="hstack gap-3">
                                                    <img src="/img/avatar/<?= htmlspecialchars($result->getAvatarUrl(), ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($result->getOnlineId(), ENT_QUOTES, 'UTF-8'); ?>" height="60" />
                                                    <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= htmlspecialchars($trophy->getGameSlug($utility), ENT_QUOTES, 'UTF-8'); ?>/<?= rawurlencode($result->getOnlineId()); ?>"><?= htmlspecialchars($result->getOnlineId(), ENT_QUOTES, 'UTF-8'); ?></a>
                                                    <?php
                                                    if ($result->hasHiddenTrophies()) {
                                                        echo " <span style='color: #9d9d9d; font-weight: bold;'>(H)</span>";
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td id="faDate<?= $count; ?>" class="align-middle text-center" style="white-space: nowrap;">
                                            </td>

                                            <script>
                                                document.getElementById("faDate<?= $count; ?>").innerHTML = new Date('<?= $result->getEarnedDate(); ?> UTC').toLocaleString('sv-SE').replace(' ', '<br>');
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
                                        <tr<?= $result->matchesOnlineId($playerOnlineId) ? " class='table-primary'" : ""; ?>>
                                            <th class="align-middle" scope="row">
                                                <?= ++$count; ?>
                                            </th>
                                            <td class="w-100">
                                                <div class="hstack gap-3">
                                                    <img src="/img/avatar/<?= htmlspecialchars($result->getAvatarUrl(), ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($result->getOnlineId(), ENT_QUOTES, 'UTF-8'); ?>" height="60" />
                                                    <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= htmlspecialchars($trophy->getGameSlug($utility), ENT_QUOTES, 'UTF-8'); ?>/<?= rawurlencode($result->getOnlineId()); ?>"><?= htmlspecialchars($result->getOnlineId(), ENT_QUOTES, 'UTF-8'); ?></a>
                                                    <?php
                                                    if ($result->hasHiddenTrophies()) {
                                                        echo " <span style='color: #9d9d9d; font-weight: bold;'>(H)</span>";
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td id="laDate<?= $count; ?>" class="align-middle text-center" style="white-space: nowrap;">
                                            </td>

                                            <script>
                                                document.getElementById("laDate<?= $count; ?>").innerHTML = new Date('<?= $result->getEarnedDate(); ?> UTC').toLocaleString('sv-SE').replace(' ', '<br>');
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
