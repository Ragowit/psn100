<?php
require_once __DIR__ . '/classes/PlayerHeaderViewModel.php';

$playerHeaderViewModel = new PlayerHeaderViewModel($player, $playerSummary, $utility);
$alerts = $playerHeaderViewModel->getAlerts();
$trophyLeaderboardRanks = $playerHeaderViewModel->getTrophyLeaderboardRanks();
$rarityLeaderboardRanks = $playerHeaderViewModel->getRarityLeaderboardRanks();
?>

<div class="row">
    <?php foreach ($alerts as $alert) { ?>
        <div class="col-12">
            <div class="alert alert-warning" role="alert">
                <?= $alert; ?>
            </div>
        </div>
    <?php } ?>
</div>

<div class="row">
    <div class="col-12 col-lg-4 mb-3">
        <div class="vstack gap-1 bg-body-tertiary p-3 rounded">
            <div>
                <h1 title="<?= $playerHeaderViewModel->getAboutMe(); ?>"><?= $player["online_id"] ?></h1>
            </div>

            <div class="hstack gap-3">
                <div>
                    <img src="/img/avatar/<?= $player["avatar_url"]; ?>" alt="" height="100" width="100" />
                </div>

                <div class="vstack gap-1">
                    <div class="hstack">
                        <div class="vstack">
                            <?php
                            if (!$playerHeaderViewModel->canShowPlayerStats()) {
                                echo "N/A";
                            } else {
                                ?>
                                <div class="w-75 text-center">
                                <!--  Level -->
                                    <img src="/img/star.svg" class="mb-1" alt="Level" title="Level" height="18" /> <?= $player["level"]; ?>
                                </div>

                                <div class="w-75">
                                    <!-- Progress -->
                                    <div class="progress" title="<?= $player["progress"]; ?>%">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $player["progress"]; ?>%" aria-valuenow="<?= $player["progress"]; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                                <?php
                            }
                            ?>
                        </div>

                        <div class="ms-auto">
                            <?php $countryName = htmlentities($playerHeaderViewModel->getCountryName(), ENT_QUOTES, 'UTF-8'); ?>
                            <img src="/img/country/<?= $playerHeaderViewModel->getCountryCode(); ?>.svg" alt="<?= $countryName; ?>" title="<?= $countryName; ?>" height="50" width="50" style="border-radius: 50%;" />
                        </div>
                    </div>

                    <div>
                        <small>Last Updated: <span id="lastUpdate"></span></small>
                        <?php
                        if ($playerHeaderViewModel->hasLastUpdatedDate()) {
                            $lastUpdatedDate = $playerHeaderViewModel->getLastUpdatedDate();
                            ?>
                            <script>
                                document.getElementById("lastUpdate").innerHTML = new Date('<?= $lastUpdatedDate; ?> UTC').toLocaleString('sv-SE');
                            </script>
                            <?php
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div class="text-center bg-dark-subtle p-3 rounded">
                <?php
                if (!$playerHeaderViewModel->canShowPlayerStats()) {
                    echo "N/A";
                } else {
                    ?>
                    <?= number_format($playerHeaderViewModel->getTotalTrophies()); ?> Trophies<br>
                    <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= number_format($player["platinum"]); ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= number_format($player["gold"]); ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= number_format($player["silver"]); ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= number_format($player["bronze"]); ?></span><br>
                    <span class="trophy-legendary" title="Legendary"><?= number_format($player["legendary"]); ?></span> &bull; <span class="trophy-epic" title="Epic"><?= number_format($player["epic"]); ?></span> &bull; <span class="trophy-rare" title="Rare"><?= number_format($player["rare"]); ?></span> &bull; <span class="trophy-uncommon" title="Uncommon"><?= number_format($player["uncommon"]); ?></span> &bull; <span class="trophy-common" title="Common"><?= number_format($player["common"]); ?></span>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4 mb-3">
        <div class="vstack gap-3 h-100 text-center">
            <div class="hstack gap-3">
                <div class="bg-body-tertiary p-3 rounded w-50">
                    Games
                    <hr class="m-2">
                    <?php
                    if (!$playerHeaderViewModel->canShowPlayerStats()) {
                        echo "N/A";
                    } else {
                        ?>
                        <h2><?= number_format($playerHeaderViewModel->getNumberOfGames()); ?></h2>
                        <?php
                    }
                    ?>
                </div>

                <div class="bg-body-tertiary p-3 rounded w-50">
                    100% Completion
                    <hr class="m-2">
                    <?php
                    if (!$playerHeaderViewModel->canShowPlayerStats()) {
                        echo "N/A";
                    } else {
                        ?>
                        <h2><?= number_format($playerHeaderViewModel->getNumberOfCompletedGames()); ?></h2>
                        <?php
                    }
                    ?>
                </div>
            </div>

            <!-- Main Leaderboard -->
            <div class="bg-body-tertiary p-3 rounded w-100 mt-auto">
                <div class="vstack">
                    <div>
                        Trophy Leaderboard
                    </div>

                    <div>
                        <hr class="m-2">
                    </div>

                    <div class="hstack gap-3">
                        <?php foreach ($trophyLeaderboardRanks as $index => $rank) { ?>
                            <?php if ($index > 0) { ?>
                                <div class="vr"></div>
                            <?php } ?>

                            <div class="w-50">
                                <?= htmlspecialchars($rank->getLabel(), ENT_QUOTES, 'UTF-8'); ?><br>
                                <?php if ($rank->isAvailable()) { ?>
                                    <h3>
                                        <?php $rankUrl = $rank->getUrl(); ?>
                                        <?php if ($rankUrl !== null) { ?>
                                            <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="<?= htmlspecialchars($rankUrl, ENT_QUOTES, 'UTF-8'); ?>"><?= $rank->getRank(); ?></a>
                                        <?php } else { ?>
                                            <?= $rank->getRank(); ?>
                                        <?php } ?>
                                        <?php $change = $rank->getChange(); ?>
                                        <?php if ($change !== null && $change->shouldDisplay()) { ?>
                                            <?php $displayText = $change->getDisplayText(); ?>
                                            <?php if ($change->isNew()) { ?>
                                                <span class="fs-6"><?= htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php } else { ?>
                                                <?php $color = $change->getColor(); ?>
                                                <span class="fs-6"<?php if ($color !== null) { ?> style="color: <?= htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>;"<?php } ?>>
                                                    <?= htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            <?php } ?>
                                        <?php } ?>
                                    </h3>
                                <?php } else { ?>
                                    <h3>N/A</h3>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4 mb-3">
        <div class="vstack gap-3 text-center h-100">
            <div class="hstack gap-3">
                <div class="bg-body-tertiary p-3 rounded w-50">
                    Average Progress
                    <hr class="m-2">
                    <?php
                    if (!$playerHeaderViewModel->canShowPlayerStats()) {
                        echo "N/A";
                    } else {
                        ?>
                        <h2><?= number_format($playerHeaderViewModel->getAverageProgress() ?? 0.0, 2); ?>%</h2>
                        <?php
                    }
                    ?>
                </div>

                <div class="bg-body-tertiary p-3 rounded w-50">
                    Unearned Trophies
                    <hr class="m-2">
                    <?php
                    if (!$playerHeaderViewModel->canShowPlayerStats()) {
                        echo "N/A";
                    } else {
                        ?>
                        <h2><?= number_format($playerHeaderViewModel->getUnearnedTrophies()); ?></h2>
                        <?php
                    }
                    ?>
                </div>
            </div>

            <!-- Rarity Leaderboard -->
            <div class="bg-body-tertiary p-3 rounded w-100 mt-auto">
                <div class="vstack">
                    <div>
                        Rarity Leaderboard
                    </div>

                    <div>
                        <hr class="m-2">
                    </div>

                    <div class="hstack gap-3">
                        <?php foreach ($rarityLeaderboardRanks as $index => $rank) { ?>
                            <?php if ($index > 0) { ?>
                                <div class="vr"></div>
                            <?php } ?>

                            <div class="w-50">
                                <?= htmlspecialchars($rank->getLabel(), ENT_QUOTES, 'UTF-8'); ?><br>
                                <?php if ($rank->isAvailable()) { ?>
                                    <h3>
                                        <?php $rankUrl = $rank->getUrl(); ?>
                                        <?php if ($rankUrl !== null) { ?>
                                            <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="<?= htmlspecialchars($rankUrl, ENT_QUOTES, 'UTF-8'); ?>"><?= $rank->getRank(); ?></a>
                                        <?php } else { ?>
                                            <?= $rank->getRank(); ?>
                                        <?php } ?>
                                        <?php $change = $rank->getChange(); ?>
                                        <?php if ($change !== null && $change->shouldDisplay()) { ?>
                                            <?php $displayText = $change->getDisplayText(); ?>
                                            <?php if ($change->isNew()) { ?>
                                                <span class="fs-6"><?= htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php } else { ?>
                                                <?php $color = $change->getColor(); ?>
                                                <span class="fs-6"<?php if ($color !== null) { ?> style="color: <?= htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>;"<?php } ?>>
                                                    <?= htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            <?php } ?>
                                        <?php } ?>
                                    </h3>
                                <?php } else { ?>
                                    <h3>N/A</h3>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
