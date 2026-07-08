<?php

declare(strict_types=1);

require_once __DIR__ . '/classes/Html.php';
require_once __DIR__ . '/classes/PlayerQueueService.php';

require_once 'classes/HomepageController.php';
require_once 'classes/HomepagePopularGamesFilter.php';

$popularGamesFilter = HomepagePopularGamesFilter::fromArray($_GET ?? []);
$homepageController = HomepageController::fromDatabase($database)
    ->withPopularGamesFilter($popularGamesFilter);
$homepageViewModel = $homepageController->getViewModel();

$title = $homepageViewModel->getTitle();
$newGames = $homepageViewModel->getNewGames();
$newDlcs = $homepageViewModel->getNewDlcs();
$popularGames = $homepageViewModel->getPopularGames();
$popularGamesFilter = $homepageViewModel->getPopularGamesFilter();
require_once("header.php");
?>

<main class="container">
    <div class="bg-body-tertiary p-3 rounded mb-3">
        <div class="row row-cols">
            <div class="col">
                <div class="input-group mb-1">
                    <input type="text" class="form-control" placeholder="PSN name..." id="player" minlength="3" maxlength="16" pattern="<?= Html::escape(PlayerQueueService::ONLINE_ID_HTML_PATTERN); ?>" aria-label="PSN name..." aria-describedby="player-button">
                    <button class="btn btn-primary" type="button" id="player-button">Update</button>
                </div>
            </div>
        </div>
        
        <div class="row justify-content-center" id="queue-result" style="display: none;">
            <div class="col text-center">
                <span id="add-to-queue-result"></span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-lg-8">
            <!-- New Games -->
            <div class="row mb-3">
                <div class="col">
                    <div class="bg-body-tertiary p-3 rounded">
                        <h1>New Games</h1>
                        <div class="row">
                            <?php
                            foreach ($newGames as $game) {
                                $gameUrl = $game->getRelativeUrl($utility);
                                ?>
                                <div class="col-12 col-md-6 col-lg-4 col-xl-3 text-center mb-2">
                                    <div class="vstack gap-1">
                                        <!-- image, platforms and status -->
                                        <div>
                                            <div class="card">
                                                <div class="d-flex justify-content-center align-items-center" style="min-height: 11.5rem;">
                                                    <a href="<?= $gameUrl; ?>">
                                                        <img class="card-img object-fit-scale" style="height: 11.5rem;" src="<?= $game->getIconPath(); ?>" alt="<?= Html::escape($game->getName()); ?>">
                                                        <div class="card-img-overlay d-flex align-items-end p-2">
                                                            <?php
                                                            foreach ($game->getPlatforms() as $platform) {
                                                                echo "<span class=\"badge rounded-pill text-bg-primary p-2 me-1\">" . Html::escape($platform) . "</span> ";
                                                            }
                                                            ?>
                                                        </div>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- trophies -->
                                        <div>
                                            <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $game->getPlatinum(); ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $game->getGold(); ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $game->getSilver(); ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $game->getBronze(); ?></span>
                                        </div>

                                        <!-- name -->
                                        <div class="text-center">
                                            <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="<?= $gameUrl; ?>">
                                                <?= Html::escape($game->getName()); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New DLCs -->
            <div class="row mb-3">
                <div class="col">
                    <div class="bg-body-tertiary p-3 rounded">
                        <h1>New DLCs</h1>
                        <div class="row">
                            <?php
                            foreach ($newDlcs as $dlc) {
                                $dlcUrl = $dlc->getRelativeUrl($utility);
                                ?>
                                <div class="col-12 col-md-6 col-lg-4 col-xl-3 text-center mb-2">
                                    <!-- image, platforms and status -->
                                    <div class="vstack gap-1">
                                        <div>
                                            <div class="card">
                                                <div class="d-flex justify-content-center align-items-center" style="min-height: 11.5rem;">
                                                    <a href="<?= $dlcUrl; ?>">
                                                        <img class="card-img object-fit-scale" style="height: 11.5rem;" src="<?= $dlc->getIconPath(); ?>" alt="<?= Html::escape($dlc->getGroupName()); ?>">
                                                        <div class="card-img-overlay d-flex align-items-end p-2">
                                                            <?php
                                                            foreach ($dlc->getPlatforms() as $platform) {
                                                                echo "<span class=\"badge rounded-pill text-bg-primary p-2 me-1\">" . Html::escape($platform) . "</span> ";
                                                            }
                                                            ?>
                                                        </div>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- trophies -->
                                        <div>
                                            <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $dlc->getGold(); ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $dlc->getSilver(); ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $dlc->getBronze(); ?></span>
                                        </div>

                                        <!-- name -->
                                        <div class="text-center">
                                            <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="<?= $dlcUrl; ?>">
                                                <small><?= Html::escape($dlc->getName()); ?></small><br><?= Html::escape($dlc->getGroupName()); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Popular Games -->
        <div class="col-12 col-lg-4" id="popular-games" style="scroll-margin-top: 0.5rem;">
            <div class="bg-body-tertiary p-3 rounded">
                <h1>Popular Games</h1>
                <form method="get" class="mb-3" id="popular-games-filter">
                    <div class="row">
                        <div class="col-8">
                            <div class="form-floating">
                                <select class="form-select form-select-sm" name="platform" id="popular-platform">
                                    <?php
                                    foreach (HomepagePopularGamesFilter::getPlatformOptions() as $platformValue => $platformLabel) {
                                        ?>
                                        <option value="<?= Html::escape($platformValue); ?>"<?= ($popularGamesFilter->isPlatformSelected($platformValue) ? ' selected' : ''); ?>>
                                            <?= Html::escape($platformLabel); ?>
                                        </option>
                                        <?php
                                    }
                                    ?>
                                </select>
                                <label for="popular-platform" class="form-label mb-1">Platform</label>
                            </div>
                        </div>
                        <div class="col-4 d-flex align-items-center">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="exclusive" value="true" id="popular-exclusive"<?= ($popularGamesFilter->isExclusiveOnly() ? ' checked' : ''); ?>>
                                <label class="form-check-label" for="popular-exclusive">Exclusive</label>
                            </div>
                        </div>
                    </div>
                </form>
                <?php
                foreach ($popularGames as $game) {
                    $gameUrl = $game->getRelativeUrl($utility);
                    ?>
                    <div class="row mb-3">
                        <!-- image -->
                        <div class="col-4">
                            <div class="card">
                                <div class="d-flex justify-content-center align-items-center" style="height: 7rem;">
                                    <a href="<?= $gameUrl; ?>">
                                        <img class="card-img object-fit-cover" style="height: 7rem;" src="<?= $game->getIconPath(); ?>" alt="<?= Html::escape($game->getName()); ?>">
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- name, platforms and status -->
                        <div class="col-5 d-flex align-items-center">
                            <div>
                                <div class="row">
                                    <div class="col">
                                        <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="<?= $gameUrl; ?>">
                                            <?= Html::escape($game->getName()); ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <?php
                                        foreach ($game->getPlatforms() as $platform) {
                                            echo "<span class=\"badge rounded-pill text-bg-primary p-2 mt-2\">" . Html::escape($platform) . "</span> ";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Players -->
                        <div class="col-3 text-end d-flex align-items-center">
                            <div class="ms-auto">
                                <span class="fw-bold"><?= number_format($game->getRecentPlayers()); ?></span><br>Players
                            </div>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>
</main>

<script src="<?= htmlspecialchars(StaticAsset::url('/js/player-queue-manager.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>

<?php
require_once("footer.php");
?>
