<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/PlayerPageAccessGuard.php';
require_once __DIR__ . '/classes/PlayerGamesPageContext.php';
require_once __DIR__ . '/classes/PlayerNavigation.php';
require_once __DIR__ . '/classes/PlayerPlatformFilterOptions.php';

$playerPageAccessGuard = PlayerPageAccessGuard::fromAccountId($accountId ?? null);
$accountId = $playerPageAccessGuard->requireAccountId();

$pageContext = PlayerGamesPageContext::fromGlobals(
    $database,
    $player,
    (int) $accountId,
    $_GET ?? []
);

$playerSummary = $pageContext->getPlayerSummary();
$playerGamesFilter = $pageContext->getFilter();
$playerGamesPage = $pageContext->getPlayerGamesPage();
$playerGames = $pageContext->getGames();
$metaData = $pageContext->getMetaData();
$playerSearch = $pageContext->getSearch();
$sort = $pageContext->getSort();
$playerNavigation = PlayerNavigation::forSection((string) $player['online_id'], PlayerNavigation::SECTION_GAMES);
$platformFilterOptions = PlayerPlatformFilterOptions::fromSelectionCallback(
    static fn (string $platform): bool => $playerGamesFilter->isPlatformSelected($platform)
);
$title = $pageContext->getTitle();
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
                        <input type="text" name="search" class="form-control rounded-start" placeholder="Game..." value="<?= htmlentities($playerSearch, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Text input to search for a game within the player">

                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Filter</button>
                        <ul class="dropdown-menu p-2">
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= ($playerGamesFilter->isCompletedSelected() ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterCompletedGames" name="completed">
                                    <label class="form-check-label" for="filterCompletedGames">
                                        100% (All)
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= ($playerGamesFilter->isBaseSelected() ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterBase" name="base">
                                    <label class="form-check-label" for="filterBase">
                                        100% (Base)
                                    </label>
                                </div>
                            </li>
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
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= ($playerGamesFilter->isUncompletedSelected() ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterUncompletedGames" name="uncompleted">
                                    <label class="form-check-label" for="filterUncompletedGames">
                                        Uncompleted Games
                                    </label>
                                </div>
                            </li>
                        </ul>

                        <select class="form-select" name="sort" onChange="this.form.submit()">
                            <option disabled>Sort by...</option>
                            <option value="search"<?= ($sort == "search" ? " selected" : ""); ?>>Best Match</option>
                            <option value="date"<?= ($sort == "date" ? " selected" : ""); ?>>Date</option>
                            <option value="max-rarity"<?= ($sort == "max-rarity" ? " selected" : ""); ?>>Max Rarity</option>
                            <option value="name"<?= ($sort == "name" ? " selected" : ""); ?>>Name</option>
                            <option value="rarity"<?= ($sort == "rarity" ? " selected" : ""); ?>>Rarity</option>
                        </select>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 mb-3">
            <div class="bg-body-tertiary p-3 rounded">
                <div class="table-responsive-xxl">
                    <table class="table">
                        <thead>
                            <tr class="text-uppercase">
                                <th scope="col">Game</th>
                                <th scope="col" class="text-center">Platform</th>
                                <th scope="col" class="text-center">Progress</th>
                                <th scope="col" class="text-center">Rarity Points</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            if ($player["status"] == 1) {
                                ?>
                                <tr>
                                    <td colspan="4" class="text-center"><h3>This player has some funny looking trophy data. This doesn't necessarily mean cheating, but all data from this player will be excluded from site statistics and leaderboards. <a href="https://github.com/Ragowit/psn100/issues?q=label%3Acheater+<?= $player["online_id"]; ?>+OR+<?= $player["account_id"]; ?>">Dispute</a>?</h3></td>
                                </tr>
                                <?php
                            } elseif ($player["status"] == 3) {
                                ?>
                                <tr>
                                    <td colspan="4" class="text-center"><h3>This player seems to have a <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://www.playstation.com/en-us/support/account/privacy-settings-psn/">private</a> profile.</h3></td>
                                </tr>
                                <?php
                            } else {
                                foreach ($playerGames as $playerGame) {
                                    $rowClass = $playerGame->getRowClass();
                                    $rowTitle = $playerGame->getRowTitle();
                                    $rowAttributes = '';
                                    if ($rowClass !== null) {
                                        $rowAttributes .= ' class="' . $rowClass . '"';
                                    }
                                    if ($rowTitle !== null) {
                                        $rowAttributes .= ' title="' . $rowTitle . '"';
                                    }
                                    ?>
                                    <tr<?= $rowAttributes; ?>>
                                        <td scope="row">
                                            <div class="hstack gap-3">
                                                <img src="/img/title/<?= $playerGame->getIconFileName(); ?>" alt="<?= htmlentities($playerGame->getName()); ?>" width="100" />

                                                <div class="vstack">
                                                    <span>
                                                        <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= $playerGame->getId() ."-". $utility->slugify($playerGame->getName()); ?>/<?= $player["online_id"]; ?>">
                                                            <?= htmlentities($playerGame->getName()); ?>
                                                        </a>
                                                    </span>

                                                    <span id="<?= $playerGame->getId(); ?>"></span>
                                                    <script>
                                                        document.getElementById("<?= $playerGame->getId(); ?>").innerHTML = new Date('<?= $playerGame->getLastUpdatedDate(); ?> UTC').toLocaleString('sv-SE');
                                                    </script>

                                                    <?php
                                                    $completionLabel = $playerGame->getCompletionDurationLabel();
                                                    if ($completionLabel !== null) {
                                                        echo '<br>' . $completionLabel;
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="align-middle text-center">
                                            <?php
                                            foreach ($playerGame->getPlatforms() as $platform) {
                                                echo "<span class=\"badge rounded-pill text-bg-primary p-2 me-1 mb-1\">" . htmlentities($platform) . "</span> ";
                                            }
                                            ?>
                                        </td>
                                        <td class="align-middle text-center" style="white-space: nowrap; width: 10rem;">
                                            <div class="vstack gap-1">
                                                <div>
                                                    <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $playerGame->getPlatinum(); ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $playerGame->getGold(); ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $playerGame->getSilver(); ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $playerGame->getBronze(); ?></span>
                                                </div>

                                                <div>
                                                    <?php
                                                    $progressBarClasses = 'progress-bar';
                                                    if (!$playerGame->isActive()) {
                                                        $progressBarClasses .= ' bg-warning';
                                                    } elseif ($playerGame->isCompleted()) {
                                                        $progressBarClasses .= ' bg-success';
                                                    }
                                                    ?>
                                                    <div class="progress" role="progressbar" aria-label="Player game progress" aria-valuenow="<?= $playerGame->getProgress(); ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <div class="<?= $progressBarClasses; ?>" style="width: <?= $playerGame->getProgress(); ?>%">
                                                            <?= $playerGame->getProgress(); ?>%
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="align-middle text-center">
                                            <?php
                                            if ($player["status"] == 0 && $playerGame->getStatus() == 0) {
                                                echo number_format($playerGame->getRarityPoints());
                                                if (!$playerGame->isCompleted()) {
                                                    echo '/'. number_format($playerGame->getMaxRarityPoints());
                                                }
                                            } elseif ($playerGame->getStatus() == 1) {
                                                echo "<span class=\"badge rounded-pill text-bg-warning\">Delisted</span>";
                                            } elseif ($playerGame->getStatus() == 3) {
                                                echo "<span class=\"badge rounded-pill text-bg-warning\">Obsolete</span>";
                                            } elseif ($playerGame->getStatus() == 4) {
                                                echo "<span class=\"badge rounded-pill text-bg-warning\">Delisted &amp; Obsolete</span>";
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <p class="text-center">
                <?= $playerGamesPage->getRangeStart(); ?>-<?= $playerGamesPage->getRangeEnd(); ?> of <?= number_format($playerGamesPage->getTotalGames()); ?>
            </p>
        </div>
        <div class="col-12">
            <?= $paginationRenderer->render(
                $playerGamesPage->getCurrentPage(),
                $playerGamesPage->getLastPage(),
                static fn (int $pageNumber): array => $playerGamesPage->getPageQueryParameters($pageNumber),
                'Player games navigation'
            ); ?>
        </div>
    </div>
</main>

<?php
require_once("footer.php");
?>
