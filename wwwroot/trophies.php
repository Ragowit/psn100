<?php

declare(strict_types=1);

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
                                            <img src="/img/title/<?= $trophy->getGameIconPath(); ?>" alt="<?= htmlentities($trophy->getGameName(), ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlentities($trophy->getGameName(), ENT_QUOTES, 'UTF-8'); ?>" style="width: 10rem;" />
                                        </a>
                                    </td>
                                    <td class="align-middle">
                                        <div class="hstack gap-3">
                                            <div class="d-flex align-items-center justify-content-center">
                                                <a href="<?= $trophyUrl; ?>">
                                                    <img src="/img/trophy/<?= $trophy->getTrophyIconPath(); ?>" alt="<?= htmlentities($trophy->getTrophyName(), ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlentities($trophy->getTrophyName(), ENT_QUOTES, 'UTF-8'); ?>" style="width: 5rem;" />
                                                </a>
                                            </div>

                                            <div>
                                                <div class="vstack">
                                                    <span>
                                                        <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="<?= $trophyUrl; ?>">
                                                            <b><?= htmlentities($trophy->getTrophyName()); ?></b>
                                                        </a>
                                                    </span>
                                                    <?= nl2br(htmlentities($trophy->getTrophyDetail(), ENT_QUOTES, 'UTF-8')); ?>
                                                    <?php
                                                    $progressTargetValue = $trophy->getProgressTargetValue();
                                                    if ($progressTargetValue !== null) {
                                                        echo '<br><b>0/' . $progressTargetValue . '</b>';
                                                    }

                                                    $rewardName = $trophy->getRewardName();
                                                    $rewardImageUrl = $trophy->getRewardImageUrl();
                                                    if ($rewardName !== null && $rewardImageUrl !== null) {
                                                        echo "<br>Reward: <a href='/img/reward/" . $rewardImageUrl . "'>" . $rewardName . '</a>';
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
                                                echo "<span class=\"badge rounded-pill text-bg-primary p-2\">" . $platform . '</span> ';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td class="text-center align-middle">
                                        <?php
                                        $metaRarity = $trophyRarityFormatter->formatMeta($trophy->getRarityPercent());
                                        $inGameRarity = $trophyRarityFormatter->formatInGame($trophy->getInGameRarityPercent());
                                        ?>
                                        <div class="vstack gap-2">
                                            <div class="small text-uppercase text-secondary">Rarity (Meta)</div>
                                            <div><?= $metaRarity->renderSpan(); ?></div>
                                            <div class="small text-uppercase text-secondary">Rarity (In-Game)</div>
                                            <div><?= $inGameRarity->renderSpan(); ?></div>
                                        </div>
                                    </td>
                                    <td class="text-center align-middle">
                                        <img src="/img/trophy-<?= $trophy->getTrophyType(); ?>.svg" alt="<?= ucfirst($trophy->getTrophyType()); ?>" title="<?= ucfirst($trophy->getTrophyType()); ?>" height="50" />
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
