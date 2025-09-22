<?php
declare(strict_types=1);

/** @var GameHeaderData $gameHeaderData */
?>
<div class="row">
    <?php
    if ($gameHeaderData->hasMergedParent()) {
        $parentGame = $gameHeaderData->getParentGame();
        if ($parentGame !== null) {
            $parentLink = $parentGame->getId() . '-' . $utility->slugify($parentGame->getName());
            if (isset($player)) {
                $parentLink .= '/' . $player;
            }
            ?>
            <div class="col-12">
                <div class="alert alert-warning" role="alert">
                    This game has been merged into <a href="/game/<?= $parentLink; ?>"><?= htmlentities($parentGame->getName()); ?></a>. Earned trophies in this entry will not be accounted for on any leaderboard.
                </div>
            </div>
            <?php
        }
    }

    if ($gameHeaderData->hasUnobtainableTrophies()) {
        $unobtainableTrophies = $gameHeaderData->getUnobtainableTrophyCount();
        ?>
        <div class="col-12">
            <div class="alert alert-warning" role="alert">
                This game has <?= $unobtainableTrophies; ?> unobtainable <?= ($unobtainableTrophies === 1) ? 'trophy' : 'trophies'; ?>.
            </div>
        </div>
        <?php
    }

    if (!empty($game['message'])) {
        ?>
        <div class="col-12">
            <div class="alert alert-warning" role="alert">
                <?= $game['message']; ?>
            </div>
        </div>
        <?php
    }
    ?>
</div>

<div class="bg-body-tertiary p-3 rounded mb-3">
    <div class="row">
        <div class="col-12 col-lg-2">
            <img class="card-img object-fit-scale" style="height: 11.5rem;" src="/img/title/<?= ($game['icon_url'] == '.png') ? ((str_contains($game['platform'], 'PS5') || str_contains($game['platform'], 'PSVR2')) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-game.png") : $game['icon_url']; ?>" alt="<?= htmlentities($game['name']); ?>">
        </div>

        <div class="col-12 col-lg-6">
            <div class="vstack gap-3">
                <div class="hstack">
                    <div>
                        <h1><?= htmlentities($game['name']); ?></h1>
                    </div>

                    <?php
                    if ($gameHeaderData->hasStacks()) {
                        $stacks = $gameHeaderData->getStacks();
                        ?>
                        <!-- Stacks -->
                        <div class="dropdown ms-auto align-self-start">
                            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Stacks (<?= count($stacks); ?>)
                            </button>
                            <ul class="dropdown-menu">
                                <?php
                                foreach ($stacks as $stack) {
                                    $stackLink = $stack->getId() . '-' . $utility->slugify($stack->getName());
                                    if (isset($player)) {
                                        $stackLink .= '/' . $player;
                                    }

                                    $region = $stack->getRegion();
                                    ?>
                                    <li class="dropdown-item">
                                        <a class="dropdown-item" href="/game/<?= $stackLink; ?>">
                                            <?= htmlentities($stack->getName()); ?>
                                            <span class="badge rounded-pill text-bg-primary"><?= htmlentities($stack->getPlatform()); ?></span>
                                            <?php
                                            if ($region !== null) {
                                                ?>
                                                <span class="badge rounded-pill text-bg-primary"><?= htmlentities($region); ?></span>
                                                <?php
                                            }
                                            ?>
                                        </a>
                                    </li>
                                    <?php
                                }
                                ?>
                            </ul>
                        </div>
                        <?php
                    }
                    ?>
                </div>

                <div>
                    <?php
                    foreach (explode(',', $game['platform']) as $platform) {
                        echo "<span class=\"badge rounded-pill text-bg-primary p-2 me-1\">" . $platform . "</span> ";
                    }
                    ?>
                </div>

                <div>
                    Version: <?= $game['set_version']; ?><?= (is_null($game['region']) ? '' : " <span class=\"badge rounded-pill text-bg-primary\">" . $game['region'] . '</span>') ?>
                </div>

                <div>
                    <?php
                    if (isset($player)) {
                        ?>
                        <small>Viewing as <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/player/<?= $player; ?>"><?= $player; ?></a></small>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="vstack gap-3 bg-dark-subtle rounded p-3 h-100">
                <div class="text-center">
                    <?php
                    if (isset($gamePlayer)) {
                        ?>
                        <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $gamePlayer['platinum'] ?? '0'; ?>/<?= $game['platinum']; ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $gamePlayer['gold'] ?? '0'; ?>/<?= $game['gold']; ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $gamePlayer['silver'] ?? '0'; ?>/<?= $game['silver']; ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $gamePlayer['bronze'] ?? '0'; ?>/<?= $game['bronze']; ?></span>
                        <?php
                    } else {
                        ?>
                        <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $game['platinum']; ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $game['gold']; ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $game['silver']; ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $game['bronze']; ?></span>
                        <?php
                    }
                    ?>
                </div>

                <?php
                if (isset($gamePlayer)) {
                    ?>
                    <div>
                        <div class="progress" role="progressbar" aria-label="Player trophy progress" aria-valuenow="<?= $gamePlayer['progress'] ?? '0'; ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width: <?= $gamePlayer['progress'] ?? '0'; ?>%"><?= $gamePlayer['progress'] ?? '0'; ?>%</div>
                        </div>
                    </div>
                    <?php
                }
                ?>

                <div>
                    <?= number_format($game['owners_completed']); ?> of <?= number_format($game['owners']); ?> players (<?= $game['difficulty']; ?>%) have 100% this game.
                </div>

                <div>
                    <?php
                    if ($game['status'] == 0) {
                        echo number_format($game['rarity_points']) . ' Rarity Points';
                    } elseif ($game['status'] == 1) {
                        echo "<span class='badge rounded-pill text-bg-warning' title='This game is delisted, no trophies will be accounted for on any leaderboard.'>Delisted</span>";
                    } elseif ($game['status'] == 3) {
                        echo "<span class='badge rounded-pill text-bg-warning' title='This game is obsolete, no trophies will be accounted for on any leaderboard.'>Obsolete</span>";
                    } elseif ($game['status'] == 4) {
                        echo "<span class='badge rounded-pill text-bg-warning' title='This game is delisted &amp; obsolete, no trophies will be accounted for on any leaderboard.'>Delisted &amp; Obsolete</span>";
                    }

                    if (isset($gamePlayer) && $gamePlayer != false) {
                        if ($gamePlayer['progress'] == 100) {
                            echo " <span class='badge rounded-pill text-bg-success' title='Player has completed this game to 100%!'>Completed!</span>";
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
