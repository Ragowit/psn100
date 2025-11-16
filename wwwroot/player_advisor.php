<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/PlayerPageAccessGuard.php';
require_once __DIR__ . '/classes/PlayerAdvisorPageContext.php';
require_once __DIR__ . '/classes/PlayerPlatformFilterRenderer.php';

$playerPageAccessGuard = PlayerPageAccessGuard::fromAccountId($accountId ?? null);
$accountId = $playerPageAccessGuard->requireAccountId();

$playerAdvisorPageContext = PlayerAdvisorPageContext::fromGlobals(
    $database,
    $utility,
    $player,
    (int) $accountId,
    $_GET ?? []
);

$playerAdvisorPage = $playerAdvisorPageContext->getPlayerAdvisorPage();
$playerSummary = $playerAdvisorPageContext->getPlayerSummary();
$playerAdvisorFilter = $playerAdvisorPageContext->getFilter();
$page = $playerAdvisorPage->getCurrentPage();
$limit = $playerAdvisorPage->getPageSize();
$offset = $playerAdvisorPage->getOffset();
$totalTrophies = $playerAdvisorPage->getTotalTrophies();
$advisableTrophies = $playerAdvisorPage->getAdvisableTrophies();
$totalPages = $playerAdvisorPage->getTotalPages();
$filterParameters = $playerAdvisorPage->getFilterParameters();
$trophyRarityFormatter = $playerAdvisorPageContext->getTrophyRarityFormatter();
$playerNavigation = $playerAdvisorPageContext->getPlayerNavigation();
$platformFilterOptions = $playerAdvisorPageContext->getPlatformFilterOptions();
$platformFilterRenderer = PlayerPlatformFilterRenderer::createDefault();
$playerStatusNotice = $playerAdvisorPageContext->getPlayerStatusNotice();
$playerOnlineId = $playerAdvisorPageContext->getPlayerOnlineId();

$title = $playerAdvisorPageContext->getTitle();
require_once("header.php");
?>

<main class="container">
    <?php
    require_once("player_header.php");
    ?>

    <div class="p-3">
        <div class="row">
            <div class="col-12 col-lg-3">
                <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover text-danger" href="/player/<?= htmlspecialchars($playerOnlineId, ENT_QUOTES, 'UTF-8'); ?>/report">Report Player</a>
            </div>

            <div class="col-12 col-lg-6 mb-3 text-center">
                <?php require __DIR__ . '/player_navigation.php'; ?>
            </div>

            <div class="col-12 col-lg-3 mb-3">
                <?= $platformFilterRenderer->render($platformFilterOptions); ?>
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
                            if ($playerStatusNotice !== null) {
                                ?>
                                <tr>
                                    <td colspan="5" class="text-center"><h3><?= $playerStatusNotice->getMessage(); ?></h3></td>
                                </tr>
                                <?php
                            } else {
                                foreach ($advisableTrophies as $trophy) {
                                    $gameLink = $trophy->getGameLink($playerOnlineId);
                                    $trophyLink = $trophy->getTrophyLink($playerOnlineId);
                                    $progressLabel = $trophy->getProgressTargetLabel();
                                    ?>
                                    <tr>
                                        <td scope="row" class="text-center align-middle">
                                            <a href="/game/<?= htmlspecialchars($gameLink, ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php $gameName = htmlentities($trophy->getGameName(), ENT_QUOTES, 'UTF-8'); ?>
                                                <img src="/img/title/<?= htmlspecialchars($trophy->getGameIconUrl(), ENT_QUOTES, 'UTF-8'); ?>" alt="<?= $gameName; ?>" title="<?= $gameName; ?>" style="width: 10rem;" />
                                            </a>
                                        </td>
                                        <td class="align-middle">
                                            <div class="hstack gap-3">
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <a href="/trophy/<?= htmlspecialchars($trophyLink, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php $trophyName = htmlentities($trophy->getTrophyName(), ENT_QUOTES, 'UTF-8'); ?>
                                                        <img src="/img/trophy/<?= htmlspecialchars($trophy->getTrophyIconUrl(), ENT_QUOTES, 'UTF-8'); ?>" alt="<?= $trophyName; ?>" title="<?= $trophyName; ?>" style="width: 5rem;" />
                                                    </a>
                                                </div>

                                                <div>
                                                    <div class="vstack">
                                                        <span>
                                                            <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/trophy/<?= htmlspecialchars($trophyLink, ENT_QUOTES, 'UTF-8'); ?>">
                                                                <b><?= $trophyName; ?></b>
                                                            </a>
                                                        </span>
                                                        <?= nl2br(htmlentities($trophy->getTrophyDetail(), ENT_QUOTES, 'UTF-8')); ?>
                                                        <?php
                                                        if ($progressLabel !== null) {
                                                            echo '<br><b>' . htmlspecialchars($progressLabel, ENT_QUOTES, 'UTF-8') . '</b>';
                                                        }

                                                        if ($trophy->hasReward()) {
                                                            $rewardName = htmlentities((string) $trophy->getRewardName(), ENT_QUOTES, 'UTF-8');
                                                            $rewardImage = htmlspecialchars((string) $trophy->getRewardImageUrl(), ENT_QUOTES, 'UTF-8');
                                                            echo "<br>Reward: <a href='/img/reward/{$rewardImage}'>{$rewardName}</a>";
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <div class="vstack gap-1">
                                                <?php
                                                foreach ($trophy->getPlatforms() as $platform) {
                                                    echo '<span class="badge rounded-pill text-bg-primary p-2">' . htmlentities($platform, ENT_QUOTES, 'UTF-8') . '</span> ';
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                        <?php
                                        $trophyRarity = $trophyRarityFormatter->format($trophy->getRarityPercent());
                                        echo $trophyRarity->renderSpan();
                                        ?>
                                        </td>
                                        <td class="text-center align-middle">
                                            <?php $trophyTypeLabel = ucfirst($trophy->getTrophyType()); ?>
                                            <img src="/img/trophy-<?= htmlspecialchars($trophy->getTrophyType(), ENT_QUOTES, 'UTF-8'); ?>.svg" alt="<?= htmlspecialchars($trophyTypeLabel, ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlspecialchars($trophyTypeLabel, ENT_QUOTES, 'UTF-8'); ?>" height="50" />
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
                <?= ($totalTrophies === 0 ? '0' : $offset + 1); ?>-<?= min($offset + $limit, $totalTrophies); ?> of <?= number_format($totalTrophies); ?>
            </p>
        </div>
        <div class="col-12">
            <?= $paginationRenderer->render(
                $page,
                $totalPages,
                static fn (int $pageNumber): array => array_merge(
                    $filterParameters,
                    ['page' => (string) $pageNumber]
                ),
                'Player log navigation'
            ); ?>
        </div>
    </div>
</main>

<?php
require_once("footer.php");
?>
