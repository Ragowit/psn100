<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/PlayerPageAccessGuard.php';
require_once __DIR__ . '/classes/PlayerLogPageContext.php';
require_once __DIR__ . '/classes/PlayerPlatformFilterRenderer.php';
require_once __DIR__ . '/classes/PlayerStatusNotice.php';

$playerPageAccessGuard = PlayerPageAccessGuard::fromAccountId($accountId ?? null);
$accountId = $playerPageAccessGuard->requireAccountId();

$pageContext = PlayerLogPageContext::fromGlobals(
    $database,
    $player,
    (int) $accountId,
    $_GET ?? []
);

$playerSummary = $pageContext->getPlayerSummary();
$playerLogPage = $pageContext->getPlayerLogPage();
$playerLogFilter = $pageContext->getFilter();
$trophiesLog = $pageContext->getTrophies();
$trophyRarityFormatter = $pageContext->getTrophyRarityFormatter();
$playerNavigation = $pageContext->getPlayerNavigation();
$platformFilterOptions = $pageContext->getPlatformFilterOptions();
$platformFilterRenderer = PlayerPlatformFilterRenderer::createDefault();

$playerOnlineId = $pageContext->getPlayerOnlineId();
$playerAccountId = $pageContext->getPlayerAccountId();
$playerStatusNotice = null;

if ($pageContext->isPlayerFlagged()) {
    $playerStatusNotice = PlayerStatusNotice::flagged($playerOnlineId, (string) $playerAccountId);
} elseif ($pageContext->isPlayerPrivate()) {
    $playerStatusNotice = PlayerStatusNotice::privateProfile();
}

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
                        <?= $platformFilterRenderer->renderDropdownControls($platformFilterOptions); ?>

                        <select class="form-select" name="sort" onChange="this.form.submit()">
                            <option disabled>Sort by...</option>
                            <option value="date"<?= $playerLogFilter->isSort(PlayerLogFilter::SORT_DATE) ? ' selected' : ''; ?>>Date</option>
                            <option value="rarity"<?= $playerLogFilter->isSort(PlayerLogFilter::SORT_RARITY) ? ' selected' : ''; ?>>Rarity</option>
                            <option value="in-game-rarity"<?= $playerLogFilter->isSort(PlayerLogFilter::SORT_IN_GAME_RARITY) ? ' selected' : ''; ?>>In-Game Rarity</option>
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
                                <th scope="col" class="text-center">Game</th>
                                <th scope="col">Trophy</th>
                                <th scope="col" class="text-center">Platform</th>
                                <th scope="col" class="text-center">Rarity</th>
                                <th scope="col" class="text-center">Type</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            if ($playerStatusNotice !== null && !$pageContext->shouldDisplayLog()) {
                                ?>
                                <tr>
                                    <td colspan="5" class="text-center"><h3><?= $playerStatusNotice->getMessage(); ?></h3></td>
                                </tr>
                                <?php
                            } elseif ($pageContext->shouldDisplayLog()) {
                                foreach ($trophiesLog as $trophy) {
                                    $rowClassAttribute = $trophy->requiresWarning() ? ' class="table-warning"' : '';
                                    $gameSlug = $trophy->getGameSlug($utility);
                                    $trophySlug = $trophy->getTrophySlug($utility);
                                    $gameUrl = '/game/' . $gameSlug . '/' . $player['online_id'];
                                    $trophyUrl = '/trophy/' . $trophySlug . '/' . $player['online_id'];
                                    $badgeElementId = $trophy->getEarnedBadgeElementId();
                                    $progressDisplay = $trophy->getProgressDisplay();
                                    $rewardName = $trophy->getRewardName();
                                    $rewardImageUrl = $trophy->getRewardImageUrl();
                                    ?>
                                    <tr<?= $rowClassAttribute; ?>>
                                        <td scope="row" class="text-center align-middle">
                                            <a href="<?= htmlspecialchars($gameUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                                <img src="/img/title/<?= htmlspecialchars($trophy->getGameIconRelativePath(), ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlentities($trophy->getGameName(), ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlentities($trophy->getGameName(), ENT_QUOTES, 'UTF-8'); ?>" style="width: 10rem;" />
                                            </a>
                                        </td>
                                        <td class="align-middle">
                                            <div class="hstack gap-3">
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <a href="<?= htmlspecialchars($trophyUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <img src="/img/trophy/<?= htmlspecialchars($trophy->getTrophyIconRelativePath(), ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlentities($trophy->getTrophyName(), ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlentities($trophy->getTrophyName(), ENT_QUOTES, 'UTF-8'); ?>" style="width: 5rem;" />
                                                    </a>
                                                </div>

                                                <div>
                                                    <div class="vstack">
                                                        <span>
                                                            <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="<?= htmlspecialchars($trophyUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                                                <b><?= htmlentities($trophy->getTrophyName(), ENT_QUOTES, 'UTF-8'); ?></b>
                                                            </a>
                                                        </span>
                                                        <?= nl2br(htmlentities($trophy->getTrophyDetail(), ENT_QUOTES, 'UTF-8')); ?>
                                                        <?php
                                                        if ($progressDisplay !== null) {
                                                            echo '<br><b>' . htmlentities($progressDisplay, ENT_QUOTES, 'UTF-8') . '</b>';
                                                        }

                                                        if ($rewardName !== null && $rewardImageUrl !== null) {
                                                            echo "<br>Reward: <a href='/img/reward/" . htmlspecialchars($rewardImageUrl, ENT_QUOTES, 'UTF-8') . "'>"
                                                                . htmlspecialchars($rewardName, ENT_QUOTES, 'UTF-8')
                                                                . '</a>';
                                                        }
                                                        ?>
                                                        <div>
                                                            <span class="badge rounded-pill text-bg-success" id="<?= htmlspecialchars($badgeElementId, ENT_QUOTES, 'UTF-8'); ?>"></span>
                                                            <script>
                                                                document.getElementById(<?= json_encode($badgeElementId); ?>).innerHTML = 'Earned ' + new Date(<?= json_encode($trophy->getEarnedDate() . ' UTC'); ?>).toLocaleString('sv-SE');
                                                            </script>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <div class="vstack gap-1">
                                                <?php
                                                foreach ($trophy->getPlatforms() as $platform) {
                                                    echo "<span class=\"badge rounded-pill text-bg-primary p-2\">" . htmlspecialchars($platform, ENT_QUOTES, 'UTF-8') . '</span> ';
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                        <?php
                                        $trophyRarity = $trophyRarityFormatter->format($trophy->getRarityPercent(), $trophy->getTrophyStatus());
                                        $inGameRarity = $trophyRarityFormatter->formatInGame($trophy->getInGameRarityPercent(), $trophy->getTrophyStatus());
                                        ?>
                                        <div class="vstack gap-2">
                                            <div class="small text-uppercase text-secondary">Rarity (Meta)</div>
                                            <div>
                                                <?php
                                                if ($trophyRarity->isUnobtainable()) {
                                                    echo "<span class='badge rounded-pill text-bg-warning p-2'>" . $trophyRarity->getLabel() . '</span>';
                                                } else {
                                                    echo $trophyRarity->renderSpan();
                                                }
                                                ?>
                                            </div>
                                            <div class="small text-uppercase text-secondary">Rarity (In-Game)</div>
                                            <div>
                                                <?php
                                                if ($inGameRarity->isUnobtainable()) {
                                                    echo "<span class='badge rounded-pill text-bg-warning p-2'>" . $inGameRarity->getLabel() . '</span>';
                                                } else {
                                                    echo $inGameRarity->renderSpan();
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <img src="/img/trophy-<?= htmlspecialchars($trophy->getTrophyType(), ENT_QUOTES, 'UTF-8'); ?>.svg" alt="<?= htmlentities(ucfirst($trophy->getTrophyType()), ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlentities(ucfirst($trophy->getTrophyType()), ENT_QUOTES, 'UTF-8'); ?>" height="50" />
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
                Showing <?= number_format(count($trophiesLog)); ?> of <?= number_format($playerLogPage->getTotalTrophies()); ?> trophies (up to <?= number_format(PlayerLogService::PAGE_SIZE); ?> based on your sort and filters).
            </p>
        </div>
    </div>
</main>

<?php
require_once("footer.php");
?>
