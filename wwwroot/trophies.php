<?php

declare(strict_types=1);

require_once __DIR__ . '/classes/Html.php';

require_once __DIR__ . '/classes/TrophyListFilter.php';
require_once __DIR__ . '/classes/TrophyListPage.php';
require_once __DIR__ . '/classes/TrophyListService.php';
require_once __DIR__ . '/classes/TrophyRarityFormatter.php';

$trophyListFilter = TrophyListFilter::fromArray($_GET ?? []);
$trophyListService = new TrophyListService($database);
$trophyListPage = new TrophyListPage($trophyListService, $trophyListFilter);
$trophyRarityFormatter = new TrophyRarityFormatter();

$title = "Trophies ~ PSN 100%";
require_once('header.php');
?>

<main class="container">
    <div class="row">
        <div class="col-12">
            <h1>Trophies</h1>
        </div>
    </div>

    <div class="bg-body-tertiary p-3 rounded mt-3">
        <div class="row">
            <div class="col-12">
                <div class="table-responsive-xxl">
                    <table class="table">
                        <thead>
                            <tr class="text-uppercase">
                                <th scope="col" class="text-center">Game</th>
                                <th scope="col">Trophy</th>
                                <th scope="col" class="text-center">Platform</th>
                                <th scope="col" class="text-center">Rarity</th>
                                <th scope="col" class="text-center">Rarity (Game)</th>
                                <th scope="col" class="text-center">Type</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            foreach ($trophyListPage->getTrophies() as $trophy) {
                                $gameUrl = $trophy->getGameUrl($utility);
                                $trophyUrl = $trophy->getTrophyUrl($utility);
                                ?>
                                <tr>
                                    <td scope="row" class="text-center align-middle">
                                        <a href="<?= $gameUrl; ?>">
                                            <img src="/img/title/<?= htmlspecialchars($trophy->getGameIconPath(), ENT_QUOTES, 'UTF-8'); ?>" alt="<?= Html::escape($trophy->getGameName()); ?>" title="<?= Html::escape($trophy->getGameName()); ?>" style="width: 10rem;" />
                                        </a>
                                    </td>
                                    <td class="align-middle">
                                        <div class="hstack gap-3">
                                            <div class="d-flex align-items-center justify-content-center">
                                                <a href="<?= $trophyUrl; ?>">
                                                    <img src="/img/trophy/<?= htmlspecialchars($trophy->getTrophyIconPath(), ENT_QUOTES, 'UTF-8'); ?>" alt="<?= Html::escape($trophy->getTrophyName()); ?>" title="<?= Html::escape($trophy->getTrophyName()); ?>" style="width: 5rem;" />
                                                </a>
                                            </div>

                                            <div>
                                                <div class="vstack">
                                                    <span>
                                                        <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="<?= $trophyUrl; ?>">
                                                            <b><?= Html::escape($trophy->getTrophyName()); ?></b>
                                                        </a>
                                                    </span>
                                                    <?= nl2br(Html::escape($trophy->getTrophyDetail())); ?>
                                                    <?php
                                                    $progressTargetValue = $trophy->getProgressTargetValue();
                                                    if ($progressTargetValue !== null) {
                                                        echo '<br><b>0/' . htmlspecialchars((string) $progressTargetValue, ENT_QUOTES, 'UTF-8') . '</b>';
                                                    }

                                                    $rewardName = $trophy->getRewardName();
                                                    $rewardImageUrl = $trophy->getRewardImageUrl();
                                                    if ($rewardName !== null && $rewardImageUrl !== null) {
                                                        echo '<br>Reward: <a href="/img/reward/'
                                                            . htmlspecialchars($rewardImageUrl, ENT_QUOTES, 'UTF-8') . '">'
                                                            . htmlspecialchars($rewardName, ENT_QUOTES, 'UTF-8') . '</a>';
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
                                                echo '<span class="badge rounded-pill text-bg-primary p-2">'
                                                    . Html::escape($platform) . '</span> ';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td class="text-center align-middle">
                                        <?php
                                        $metaRarity = $trophyRarityFormatter->formatMeta($trophy->getRarityPercent());
                                        ?>
                                        <div><?= $metaRarity->renderSpan(); ?></div>
                                    </td>
                                    <td class="text-center align-middle">
                                        <?php
                                        $inGameRarity = $trophyRarityFormatter->formatInGame($trophy->getInGameRarityPercent());
                                        ?>
                                        <div><?= $inGameRarity->renderSpan(); ?></div>
                                    </td>
                                    <td class="text-center align-middle">
                                        <img src="<?= Html::escape($trophy->getTrophyType()->iconPath()); ?>" alt="<?= Html::escape($trophy->getTrophyType()->label()); ?>" title="<?= Html::escape($trophy->getTrophyType()->label()); ?>" height="50" />
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <p class="text-center">
                <?= $trophyListPage->getRangeStart(); ?>-<?= $trophyListPage->getRangeEnd(); ?> of <?= number_format($trophyListPage->getTotalCount()); ?>
            </p>
        </div>
        <div class="col-12">
            <?= $paginationRenderer->render(
                $trophyListPage->getCurrentPage(),
                $trophyListPage->getLastPage(),
                static fn (int $pageNumber): array => $trophyListPage->getPageQueryParameters($pageNumber),
                'Trophies page navigation'
            ); ?>
        </div>
    </div>
</main>

<?php
require_once('footer.php');
?>
