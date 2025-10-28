<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/PlayerPageAccessGuard.php';
require_once __DIR__ . '/classes/PlayerRandomGamesPageContext.php';

$playerPageAccessGuard = PlayerPageAccessGuard::fromAccountId($accountId ?? null);
$accountId = $playerPageAccessGuard->requireAccountId();

$context = PlayerRandomGamesPageContext::fromGlobals(
    $database,
    $utility,
    $player,
    (int) $accountId,
    $_GET ?? []
);

$playerRandomGamesFilter = $context->getFilter();
$playerSummary = $context->getPlayerSummary();
$randomGames = $context->getRandomGames();
$playerNavigation = $context->getPlayerNavigation();
$platformFilterOptions = $context->getPlatformFilterOptions();

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
            <form>
                    <div class="input-group d-flex justify-content-end">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Filter</button>
                        <ul class="dropdown-menu p-2">
                            <?php foreach ($platformFilterOptions->getOptions() as $platformOption) { ?>
                                <li>
                                    <div class="form-check">
                                        <?php $inputId = htmlspecialchars($platformOption->getInputId(), ENT_QUOTES, 'UTF-8'); ?>
                                        <input
                                            class="form-check-input"
                                            type="checkbox"<?= $platformOption->isSelected() ? ' checked' : ''; ?>
                                            value="true"
                                            onChange="this.form.submit()"
                                            id="<?= $inputId; ?>"
                                            name="<?= htmlspecialchars($platformOption->getInputName(), ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                        <label class="form-check-label" for="<?= $inputId; ?>">
                                            <?= htmlspecialchars($platformOption->getLabel(), ENT_QUOTES, 'UTF-8'); ?>
                                        </label>
                                    </div>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row">
        <?php
        if ($context->shouldShowFlaggedMessage()) {
            ?>
            <div class="col-12 text-center">
                <h3>This player has some funny looking trophy data. This doesn't necessarily mean cheating, but all data from this player will be excluded from site statistics and leaderboards. <a href="https://github.com/Ragowit/psn100/issues?q=label%3Acheater+<?= $player["online_id"]; ?>+OR+<?= $player["account_id"]; ?>">Dispute</a>?</h3>
            </div>
            <?php
        } elseif ($context->shouldShowPrivateMessage()) {
            ?>
            <div class="col-12 text-center">
                <h3>This player seems to have a <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://www.playstation.com/en-us/support/account/privacy-settings-psn/">private</a> profile.</h3>
            </div>
            <?php
        } elseif ($context->shouldShowRandomGames()) {
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
                                        <a href="/game/<?= htmlspecialchars($gameLink, ENT_QUOTES, 'UTF-8'); ?>">
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
                                <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= htmlspecialchars($gameLink, ENT_QUOTES, 'UTF-8'); ?>">
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
