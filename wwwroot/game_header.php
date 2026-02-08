<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/Game/GamePlayerProgress.php';

/** @var GameDetails $game */
/** @var GameHeaderData $gameHeaderData */
/** @var GamePlayerProgress|null $gamePlayer */
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

    if ($gameHeaderData->hasPsnpPlusNote()) {
        ?>
        <div class="col-12">
            <div class="alert alert-info" role="alert">
                <strong>PSNP+ note:</strong> <?= $gameHeaderData->getPsnpPlusNote(); ?>
            </div>
        </div>
        <?php
    }

    $status = $game->getStatus();

    $replacementText = null;
    if ($gameHeaderData->hasObsoleteReplacements()) {
        $replacementLinks = [];
        foreach ($gameHeaderData->getObsoleteReplacements() as $replacement) {
            $replacementLink = '/game/' . $replacement->getId() . '-' . $utility->slugify($replacement->getName());
            if (isset($player)) {
                $replacementLink .= '/' . $player;
            }

            $replacementLinks[] = sprintf(
                '<a href="%s">%s</a>',
                htmlentities($replacementLink, ENT_QUOTES, 'UTF-8'),
                htmlentities($replacement->getName(), ENT_QUOTES, 'UTF-8')
            );
        }

        if (count($replacementLinks) === 2) {
            $replacementText = implode(' and ', $replacementLinks);
        } elseif (count($replacementLinks) > 2) {
            $lastLink = array_pop($replacementLinks);
            $replacementText = implode(', ', $replacementLinks) . ', and ' . $lastLink;
        } else {
            $replacementText = $replacementLinks[0];
        }
    }

    if ($status === 4) {
        ?>
        <div class="col-12">
            <div class="alert alert-warning" role="alert">
                This game is delisted &amp; obsolete, no trophies will be accounted for on any leaderboard.
                <?php
                if ($replacementText !== null) {
                    ?>
                    Please play <?= $replacementText; ?> instead.
                    <?php
                }
                ?>
            </div>
        </div>
        <?php
    } elseif ($status === 1) {
        ?>
        <div class="col-12">
            <div class="alert alert-warning" role="alert">
                This game is delisted, no trophies will be accounted for on any leaderboard.
            </div>
        </div>
        <?php
    } elseif ($gameHeaderData->hasObsoleteReplacements()) {
        ?>
        <div class="col-12">
            <div class="alert alert-warning" role="alert">
                This game is obsolete, no trophies will be accounted for on any leaderboard. Please play <?= $replacementText; ?> instead.
            </div>
        </div>
        <?php
    }

    if ($game->hasMessage()) {
        ?>
        <div class="col-12">
            <div class="alert alert-warning" role="alert">
                <?= $game->getMessage(); ?>
            </div>
        </div>
        <?php
    }
    ?>
</div>

<div class="bg-body-tertiary p-3 rounded mb-3">
    <div class="row">
        <div class="col-12 col-lg-2">
            <?php
            $gameIconUrl = $game->getIconUrl();
            $gamePlatform = $game->getPlatform();
            $iconPath = ($gameIconUrl === '.png')
                ? ((str_contains($gamePlatform, 'PS5') || str_contains($gamePlatform, 'PSVR2'))
                    ? '../missing-ps5-game-and-trophy.png'
                    : '../missing-ps4-game.png')
                : $gameIconUrl;
            ?>
            <img class="card-img object-fit-scale" style="height: 11.5rem;" src="/img/title/<?= $iconPath; ?>" alt="<?= htmlentities($game->getName()); ?>">
        </div>

        <div class="col-12 col-lg-6">
            <div class="vstack gap-3">
                <div class="hstack">
                    <div>
                        <h1><?= htmlentities($game->getName()); ?></h1>
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
                    foreach (explode(',', $gamePlatform) as $platform) {
                        echo "<span class=\"badge rounded-pill text-bg-primary p-2 me-1\">" . $platform . "</span> ";
                    }
                    ?>
                </div>

                <div>
                    <?php
                    $region = $game->getRegion();
                    ?>
                    Version: <?= htmlentities($game->getSetVersion(), ENT_QUOTES, 'UTF-8'); ?>
                    <?= ($region === null ? '' : " <span class=\"badge rounded-pill text-bg-primary\">" . htmlentities($region, ENT_QUOTES, 'UTF-8') . '</span>') ?>
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
                    if (isset($gamePlayer) && $gamePlayer instanceof GamePlayerProgress) {
                        ?>
                        <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $gamePlayer->getPlatinumCount(); ?>/<?= $game->getPlatinum(); ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $gamePlayer->getGoldCount(); ?>/<?= $game->getGold(); ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $gamePlayer->getSilverCount(); ?>/<?= $game->getSilver(); ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $gamePlayer->getBronzeCount(); ?>/<?= $game->getBronze(); ?></span>
                        <?php
                    } else {
                        ?>
                        <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $game->getPlatinum(); ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $game->getGold(); ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $game->getSilver(); ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $game->getBronze(); ?></span>
                        <?php
                    }
                    ?>
                </div>

                <?php
                if (isset($gamePlayer) && $gamePlayer instanceof GamePlayerProgress) {
                    ?>
                    <div>
                        <div class="progress" role="progressbar" aria-label="Player trophy progress" aria-valuenow="<?= $gamePlayer->getProgress(); ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width: <?= $gamePlayer->getProgress(); ?>%"><?= $gamePlayer->getProgress(); ?>%</div>
                        </div>

                        <?php
                        if (isset($gamePlayer) && $gamePlayer instanceof GamePlayerProgress && $gamePlayer->isCompleted()) {
                            ?>
                            <div class="text-center mt-2">
                                <span class='badge rounded-pill text-bg-success' title='Player has completed this game to 100%!'>Completed!</span>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                    <?php
                }
                ?>

                <div class="text-center">
                    <?= number_format($game->getOwnersCompleted()); ?> of <?= number_format($game->getOwners()); ?> players (<?= $game->getDifficulty(); ?>%) have 100% this game.
                </div>

                <div class="text-center">
                    <?php
                    $details = [];

                    if ($status === 0) {
                        $details[] = number_format($game->getRarityPoints()) . ' Rarity (Meta) Points';
                        $details[] = number_format($game->getInGameRarityPoints()) . ' Rarity (In-Game) Points';
                    } elseif ($status === 1) {
                        $details[] = "<span class='badge rounded-pill text-bg-warning' title='This game is delisted, no trophies will be accounted for on any leaderboard.'>Delisted</span>";
                    } elseif ($status === 3) {
                        $details[] = "<span class='badge rounded-pill text-bg-warning' title='This game is obsolete, no trophies will be accounted for on any leaderboard.'>Obsolete</span>";
                    } elseif ($status === 4) {
                        $details[] = "<span class='badge rounded-pill text-bg-warning' title='This game is delisted &amp; obsolete, no trophies will be accounted for on any leaderboard.'>Delisted &amp; Obsolete</span>";
                    }

                    echo implode('<br>', $details);
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
