<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/GameListFilter.php';
require_once __DIR__ . '/classes/GameListService.php';
require_once __DIR__ . '/classes/GameListPage.php';
require_once __DIR__ . '/classes/SearchQueryHelper.php';

$title = "Games ~ PSN 100%";

$filter = GameListFilter::fromArray($_GET ?? []);
$searchQueryHelper = new SearchQueryHelper();
$gameListService = new GameListService($database, $searchQueryHelper);
$gameListPage = new GameListPage($gameListService, $filter);

$filter = $gameListPage->getFilter();
$games = $gameListPage->getGames();
$playerName = $gameListPage->getPlayerName();
$totalGames = $gameListPage->getTotalGames();
$startIndex = $gameListPage->getRangeStart();
$endIndex = $gameListPage->getRangeEnd();

require_once("header.php");
?>

<main class="container">
    <div class="row">
        <div class="col-4 col-lg-8">
            <h1>Games</h1>
        </div>

        <div class="col-8 col-lg-4">
            <form>
                <div class="input-group d-flex justify-content-end">
                    <input type="hidden" name="page" value="<?= $gameListPage->getCurrentPage(); ?>">
                    <input type="hidden" name="search" value="<?= htmlentities($filter->getSearch(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="text" name="player" class="form-control rounded-start" maxlength="16" placeholder="View as player..." value="<?= htmlentities($filter->getPlayer() ?? '', ENT_QUOTES, 'UTF-8'); ?>" aria-label="Text input to show completed games for specified player">

                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Filter</button>
                    <ul class="dropdown-menu p-2">
                        <li>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"<?= ($filter->isPlatformSelected(GameListFilter::PLATFORM_PC) ? ' checked' : ''); ?> value="true" onChange="this.form.submit()" id="filterPC" name="pc">
                                <label class="form-check-label" for="filterPC">
                                    PC
                                </label>
                            </div>
                        </li>
                        <li>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"<?= ($filter->isPlatformSelected(GameListFilter::PLATFORM_PS3) ? ' checked' : ''); ?> value="true" onChange="this.form.submit()" id="filterPS3" name="ps3">
                                <label class="form-check-label" for="filterPS3">
                                    PS3
                                </label>
                            </div>
                        </li>
                        <li>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"<?= ($filter->isPlatformSelected(GameListFilter::PLATFORM_PS4) ? ' checked' : ''); ?> value="true" onChange="this.form.submit()" id="filterPS4" name="ps4">
                                <label class="form-check-label" for="filterPS4">
                                    PS4
                                </label>
                            </div>
                        </li>
                        <li>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"<?= ($filter->isPlatformSelected(GameListFilter::PLATFORM_PS5) ? ' checked' : ''); ?> value="true" onChange="this.form.submit()" id="filterPS5" name="ps5">
                                <label class="form-check-label" for="filterPS5">
                                    PS5
                                </label>
                            </div>
                        </li>
                        <li>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"<?= ($filter->isPlatformSelected(GameListFilter::PLATFORM_PSVITA) ? ' checked' : ''); ?> value="true" onChange="this.form.submit()" id="filterPSVITA" name="psvita">
                                <label class="form-check-label" for="filterPSVITA">
                                    PSVITA
                                </label>
                            </div>
                        </li>
                        <li>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"<?= ($filter->isPlatformSelected(GameListFilter::PLATFORM_PSVR) ? ' checked' : ''); ?> value="true" onChange="this.form.submit()" id="filterPSVR" name="psvr">
                                <label class="form-check-label" for="filterPSVR">
                                    PSVR
                                </label>
                            </div>
                        </li>
                        <li>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"<?= ($filter->isPlatformSelected(GameListFilter::PLATFORM_PSVR2) ? ' checked' : ''); ?> value="true" onChange="this.form.submit()" id="filterPSVR2" name="psvr2">
                                <label class="form-check-label" for="filterPSVR2">
                                    PSVR2
                                </label>
                            </div>
                        </li>
                        <?php
                        if ($filter->shouldShowUncompletedOption()) {
                            ?>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= ($filter->shouldFilterUncompleted() ? ' checked' : ''); ?> value="true" onChange="this.form.submit()" id="filterCompletedGames" name="filter">
                                    <label class="form-check-label" for="filterCompletedGames">
                                        Uncompleted Games
                                    </label>
                                </div>
                            </li>
                            <?php
                        }
                        ?>
                    </ul>

                    <select class="form-select" name="sort" onChange="this.form.submit()">
                        <option disabled>Sort by...</option>
                        <option value="added"<?= ($filter->isSort(GameListFilter::SORT_ADDED) ? ' selected' : ''); ?>>Added to Site</option>
                        <?php
                        if ($filter->hasSearch() || $filter->isSort(GameListFilter::SORT_SEARCH)) {
                            ?>
                            <option value="search"<?= ($filter->isSort(GameListFilter::SORT_SEARCH) ? ' selected' : ''); ?>>Best Match</option>
                            <?php
                        }
                        ?>
                        <option value="completion"<?= ($filter->isSort(GameListFilter::SORT_COMPLETION) ? ' selected' : ''); ?>>Completion Rate</option>
                        <option value="owners"<?= ($filter->isSort(GameListFilter::SORT_OWNERS) ? ' selected' : ''); ?>>Owners</option>
                        <option value="rarity"<?= ($filter->isSort(GameListFilter::SORT_RARITY) ? ' selected' : ''); ?>>Rarity Points</option>
                        <option value="in-game-rarity"<?= ($filter->isSort(GameListFilter::SORT_IN_GAME_RARITY) ? ' selected' : ''); ?>>Rarity (In-Game) Points</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <?php
        /** @var GameListItem $game */
        foreach ($games as $game) {
            $gameLink = $game->getRelativeUrl($utility, $playerName);
            $cardClass = $game->getCardBackgroundClass();
            $iconPath = $game->getIconPath();
            $platforms = $game->getPlatforms();
            $owners = $game->getOwners();
            $rarityPoints = $game->getRarityPoints();
            $inGameRarityPoints = $game->getInGameRarityPoints();
            $difficulty = $game->getDifficulty();
            $statusBadge = $game->getStatusBadge();
            ?>
            <div class="col-md-6 col-xl-3">
                <div class="<?= $cardClass; ?> p-3 rounded mb-3 text-center vstack gap-1">
                    <div class="vstack gap-1">
                        <!-- image, platforms and status -->
                        <div>
                            <div class="card">
                                <div class="d-flex justify-content-center align-items-center" style="min-height: 11.5rem;">
                                    <a href="/game/<?= $gameLink; ?>">
                                        <img class="card-img object-fit-scale" style="height: 11.5rem;" src="/img/title/<?= $iconPath; ?>" alt="<?= htmlentities($game->getName()); ?>">
                                        <div class="card-img-overlay d-flex align-items-end p-2">
                                            <?php
                                            foreach ($platforms as $platform) {
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
                            <?= number_format($owners); ?> <?= $game->getOwnersLabel(); ?> (<?= $difficulty; ?>%)
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

                        <!-- rarity points / status -->
                        <div>
                            <?php
                            if ($game->shouldShowRarityPoints() && $filter->isSort(GameListFilter::SORT_RARITY)) {
                                echo number_format($rarityPoints) . ' Rarity Points';
                            } elseif ($game->shouldShowRarityPoints() && $filter->isSort(GameListFilter::SORT_IN_GAME_RARITY)) {
                                echo number_format($inGameRarityPoints) . ' Rarity (In-Game) Points';
                            } elseif ($statusBadge !== null) {
                                ?>
                                <span class="<?= $statusBadge->getCssClass(); ?>" title="<?= $statusBadge->getTooltip(); ?>"><?= $statusBadge->getLabel(); ?></span>
                                <?php
                            }

                            if ($game->isCompleted()) {
                                ?>
                                <span class="badge rounded-pill text-bg-success" title="Player have completed this game to 100%!">Completed!</span>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
        ?>
    </div>

    <div class="row">
        <div class="col-12">
            <p class="text-center">
                <?= $startIndex; ?>-<?= $endIndex; ?> of <?= number_format($totalGames); ?>
            </p>
        </div>
        <div class="col-12">
            <?= $paginationRenderer->render(
                $gameListPage->getCurrentPage(),
                $gameListPage->getLastPage(),
                static fn (int $pageNumber): array => $gameListPage->getPageQueryParameters($pageNumber),
                'Games page navigation'
            ); ?>
        </div>
    </div>
</main>

<?php
require_once("footer.php");
?>
