<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/PlayerPageAccessGuard.php';
require_once __DIR__ . '/classes/PlayerRandomGame.php';
require_once __DIR__ . '/classes/PlayerRandomGamesFilter.php';
require_once __DIR__ . '/classes/PlayerRandomGamesService.php';
require_once __DIR__ . '/classes/PlayerRandomGamesPage.php';
require_once __DIR__ . '/classes/PlayerSummary.php';
require_once __DIR__ . '/classes/PlayerSummaryService.php';
require_once __DIR__ . '/classes/PlayerNavigation.php';

$playerPageAccessGuard = PlayerPageAccessGuard::fromAccountId($accountId ?? null);
$accountId = $playerPageAccessGuard->requireAccountId();

$playerRandomGamesFilter = PlayerRandomGamesFilter::fromArray($_GET ?? []);
$playerRandomGamesService = new PlayerRandomGamesService($database, $utility);
$playerSummaryService = new PlayerSummaryService($database);
$playerRandomGamesPage = new PlayerRandomGamesPage(
    $playerRandomGamesService,
    $playerSummaryService,
    $playerRandomGamesFilter,
    (int) $accountId,
    (int) $player["status"]
);

$playerRandomGamesFilter = $playerRandomGamesPage->getFilter();
$playerSummary = $playerRandomGamesPage->getPlayerSummary();
$randomGames = $playerRandomGamesPage->getRandomGames();
$playerNavigation = PlayerNavigation::forSection((string) $player['online_id'], PlayerNavigation::SECTION_RANDOM);

$title = $player["online_id"] . "'s Random Games ~ PSN 100%";
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
            <form>
                    <div class="input-group d-flex justify-content-end">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Filter</button>
                        <ul class="dropdown-menu p-2">
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= ($playerRandomGamesFilter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PC) ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterPC" name="pc">
                                    <label class="form-check-label" for="filterPC">
                                        PC
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= ($playerRandomGamesFilter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PS3) ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterPS3" name="ps3">
                                    <label class="form-check-label" for="filterPS3">
                                        PS3
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= ($playerRandomGamesFilter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PS4) ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterPS4" name="ps4">
                                    <label class="form-check-label" for="filterPS4">
                                        PS4
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= ($playerRandomGamesFilter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PS5) ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterPS5" name="ps5">
                                    <label class="form-check-label" for="filterPS5">
                                        PS5
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= ($playerRandomGamesFilter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PSVITA) ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterPSVITA" name="psvita">
                                    <label class="form-check-label" for="filterPSVITA">
                                        PSVITA
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= ($playerRandomGamesFilter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PSVR) ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterPSVR" name="psvr">
                                    <label class="form-check-label" for="filterPSVR">
                                        PSVR
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= ($playerRandomGamesFilter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PSVR2) ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterPSVR2" name="psvr2">
                                    <label class="form-check-label" for="filterPSVR2">
                                        PSVR2
                                    </label>
                                </div>
                            </li>
                        </ul>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row">
        <?php
        if ($playerRandomGamesPage->shouldShowFlaggedMessage()) {
            ?>
            <div class="col-12 text-center">
                <h3>This player has some funny looking trophy data. This doesn't necessarily mean cheating, but all data from this player will be excluded from site statistics and leaderboards. <a href="https://github.com/Ragowit/psn100/issues?q=label%3Acheater+<?= $player["online_id"]; ?>+OR+<?= $player["account_id"]; ?>">Dispute</a>?</h3>
            </div>
            <?php
        } elseif ($playerRandomGamesPage->shouldShowPrivateMessage()) {
            ?>
            <div class="col-12 text-center">
                <h3>This player seems to have a <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://www.playstation.com/en-us/support/account/privacy-settings-psn/">private</a> profile.</h3>
            </div>
            <?php
        } elseif ($playerRandomGamesPage->shouldShowRandomGames()) {
            foreach ($randomGames as $game) {
                $gameLink = $game->getGameLink($player["online_id"]);
                ?>
                <div class="col-md-6 col-xl-3">
                    <div class="bg-body-tertiary p-3 rounded mb-3 text-center vstack gap-1">
                        <div class="vstack gap-1">
                            <!-- image, platforms -->
                            <div>
                                <div class="card">
                                    <div class="d-flex justify-content-center align-items-center" style="min-height: 11.5rem;">
                                        <a href="/game/<?= $gameLink; ?>">
                                            <img class="card-img object-fit-scale" style="height: 11.5rem;" src="/img/title/<?= $game->getIconUrl(); ?>" alt="<?= htmlentities($game->getName()); ?>">
                                            <div class="card-img-overlay d-flex align-items-end p-2">
                                                <?php
                                                foreach ($game->getPlatforms() as $platform) {
                                                    echo "<span class=\"badge rounded-pill text-bg-primary p-2 me-1\">" . htmlentities($platform) . "</span> ";
                                                }
                                                ?>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- owners & cr -->
                            <div>
                                <?= number_format($game->getOwners()); ?> <?= ($game->getOwners() > 1 ? 'owners' : 'owner'); ?> (<?= $game->getDifficulty(); ?>%)
                            </div>

                            <!-- name -->
                            <div class="text-center">
                                <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= $gameLink; ?>">
                                    <?= htmlentities($game->getName()); ?>
                                </a>
                            </div>

                            <div>
                                <hr class="m-0">
                            </div>

                            <!-- trophies -->
                            <div>
                                <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $game->getPlatinum(); ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $game->getGold(); ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $game->getSilver(); ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $game->getBronze(); ?></span>
                            </div>

                            <!-- rarity points -->
                            <div>
                                <?php
                                echo number_format($game->getRarityPoints()) . " Rarity Points";
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            }
        }
        ?>
    </div>
</main>

<?php
require_once("footer.php");
?>
