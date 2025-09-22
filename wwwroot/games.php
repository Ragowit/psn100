<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/GameListFilter.php';
require_once __DIR__ . '/classes/GameListService.php';

$title = "Games ~ PSN 100%";

$filter = GameListFilter::fromArray($_GET ?? []);
$gameListService = new GameListService($database);
$filter = $filter->withPlayer($gameListService->resolvePlayer($filter->getPlayer()));

$limit = $gameListService->getLimit();
$offset = $gameListService->getOffset($filter);
$totalGames = $gameListService->countGames($filter);
$games = $gameListService->getGames($filter);
$page = $filter->getPage();
$actualLastPage = (int) ceil($totalGames / $limit);
$lastPage = $actualLastPage > 0 ? $actualLastPage : 1;
$playerName = $filter->getPlayer();
$startIndex = $totalGames === 0 ? 0 : $offset + 1;
$endIndex = min($offset + $limit, $totalGames);
$paginationParameters = $filter->getQueryParametersForPagination();

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
                    <input type="hidden" name="page" value="<?= $page; ?>">
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
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <?php
        foreach ($games as $game) {
            $status = isset($game['status']) ? (int) $game['status'] : 0;
            $progress = isset($game['progress']) ? (int) $game['progress'] : 0;

            $divBgColor = 'bg-body-tertiary';
            if ($status === 1 || $status === 3 || $status === 4) {
                $divBgColor = 'bg-warning-subtle';
            }

            if ($progress === 100) {
                $divBgColor = 'bg-success-subtle';
            }

            $gameLink = $game['id'] . '-' . $utility->slugify($game['name']);
            if ($playerName !== null) {
                $gameLink .= '/' . $playerName;
            }

            $owners = isset($game['owners']) ? (int) $game['owners'] : 0;
            $rarityPoints = isset($game['rarity_points']) ? (int) $game['rarity_points'] : 0;
            $difficulty = $game['difficulty'] ?? 0;
            $platformValue = (string) ($game['platform'] ?? '');
            $platforms = $platformValue === '' ? [] : explode(',', $platformValue);
            ?>
            <div class="col-md-6 col-xl-3">
                <div class="<?= $divBgColor; ?> p-3 rounded mb-3 text-center vstack gap-1">
                    <div class="vstack gap-1">
                        <!-- image, platforms and status -->
                        <div>
                            <div class="card">
                                <div class="d-flex justify-content-center align-items-center" style="min-height: 11.5rem;">
                                    <a href="/game/<?= $gameLink; ?>">
                                        <img class="card-img object-fit-scale" style="height: 11.5rem;" src="/img/title/<?= ($game['icon_url'] == '.png') ? ((str_contains($platformValue, 'PS5') || str_contains($platformValue, 'PSVR2')) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-game.png") : $game['icon_url']; ?>" alt="<?= htmlentities($game['name']); ?>">
                                        <div class="card-img-overlay d-flex align-items-end p-2">
                                            <?php
                                            foreach ($platforms as $platform) {
                                                echo "<span class=\"badge rounded-pill text-bg-primary p-2 me-1\">" . $platform . "</span> ";
                                            }
                                            ?>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- owners & cr -->
                        <div>
                            <?= number_format($owners); ?> <?= ($owners === 1 ? 'owner' : 'owners'); ?> (<?= $difficulty; ?>%)
                        </div>

                        <!-- name -->
                        <div class="text-center">
                            <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= $gameLink; ?>">
                                <?= htmlentities($game['name']); ?>
                            </a>
                        </div>

                        <div>
                            <hr class="m-0">
                        </div>

                        <!-- trophies -->
                        <div>
                            <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $game['platinum']; ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $game['gold']; ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $game['silver']; ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $game['bronze']; ?></span>
                        </div>

                        <!-- rarity points / status -->
                        <div>
                            <?php
                            if ($status === 0) {
                                echo number_format($rarityPoints) . ' Rarity Points';
                            } elseif ($status === 1) {
                                echo "<span class='badge rounded-pill text-bg-warning' title='This game is delisted, no trophies will be accounted for on any leaderboard.'>Delisted</span>";
                            } elseif ($status === 3) {
                                echo "<span class='badge rounded-pill text-bg-warning' title='This game is obsolete, no trophies will be accounted for on any leaderboard.'>Obsolete</span>";
                            } elseif ($status === 4) {
                                echo "<span class='badge rounded-pill text-bg-warning' title='This game is delisted &amp; obsolete, no trophies will be accounted for on any leaderboard.'>Delisted &amp; Obsolete</span>";
                            }

                            if ($progress === 100) {
                                echo " <span class='badge rounded-pill text-bg-success' title='Player have completed this game to 100%!'>Completed!</span>";
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
            <nav aria-label="Games page navigation">
                <ul class="pagination justify-content-center">
                    <?php
                    $baseParameters = $paginationParameters;

                    if ($page > 1) {
                        $previousParameters = $baseParameters;
                        $previousParameters['page'] = $page - 1;
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($previousParameters); ?>" aria-label="Previous">&lt;</a></li>
                        <?php
                    }

                    if ($page > 3) {
                        $firstParameters = $baseParameters;
                        $firstParameters['page'] = 1;
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($firstParameters); ?>">1</a></li>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <?php
                    }

                    if ($page - 2 > 0) {
                        $params = $baseParameters;
                        $params['page'] = $page - 2;
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page - 2; ?></a></li>
                        <?php
                    }

                    if ($page - 1 > 0) {
                        $params = $baseParameters;
                        $params['page'] = $page - 1;
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page - 1; ?></a></li>
                        <?php
                    }

                    $currentParameters = $baseParameters;
                    $currentParameters['page'] = $page;
                    ?>
                    <li class="page-item active" aria-current="page"><a class="page-link" href="?<?= http_build_query($currentParameters); ?>"><?= $page; ?></a></li>

                    <?php
                    if ($page + 1 <= $lastPage) {
                        $params = $baseParameters;
                        $params['page'] = $page + 1;
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page + 1; ?></a></li>
                        <?php
                    }

                    if ($page + 2 <= $lastPage) {
                        $params = $baseParameters;
                        $params['page'] = $page + 2;
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page + 2; ?></a></li>
                        <?php
                    }

                    if ($page < $lastPage - 2) {
                        $params = $baseParameters;
                        $params['page'] = $lastPage;
                        ?>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $lastPage; ?></a></li>
                        <?php
                    }

                    if ($page < $lastPage) {
                        $nextParameters = $baseParameters;
                        $nextParameters['page'] = $page + 1;
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($nextParameters); ?>" aria-label="Next">&gt;</a></li>
                        <?php
                    }
                    ?>
                </ul>
            </nav>
        </div>
    </div>
</main>

<?php
require_once("footer.php");
?>
