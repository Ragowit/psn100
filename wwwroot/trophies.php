<?php

declare(strict_types=1);

require_once __DIR__ . '/classes/TrophyListFilter.php';
require_once __DIR__ . '/classes/TrophyListPage.php';
require_once __DIR__ . '/classes/TrophyListService.php';

$trophyListFilter = TrophyListFilter::fromArray($_GET ?? []);
$trophyListService = new TrophyListService($database);
$trophyListPage = new TrophyListPage($trophyListService, $trophyListFilter);

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
                                ?>
                                <tr>
                                    <td scope="row" class="text-center align-middle">
                                        <a href="/game/<?= $trophy['game_id'] . '-' . $utility->slugify($trophy['game_name']); ?>">
                                            <img src="/img/title/<?= ($trophy['game_icon'] == '.png') ? ((str_contains($trophy['platform'], 'PS5')) ? '../missing-ps5-game-and-trophy.png' : '../missing-ps4-game.png') : $trophy['game_icon']; ?>" alt="<?= htmlentities($trophy['game_name'], ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlentities($trophy['game_name'], ENT_QUOTES, 'UTF-8'); ?>" style="width: 10rem;" />
                                        </a>
                                    </td>
                                    <td class="align-middle">
                                        <div class="hstack gap-3">
                                            <div class="d-flex align-items-center justify-content-center">
                                                <a href="/trophy/<?= $trophy['trophy_id'] . '-' . $utility->slugify($trophy['trophy_name']); ?>">
                                                    <img src="/img/trophy/<?= ($trophy['trophy_icon'] == '.png') ? ((str_contains($trophy['platform'], 'PS5')) ? '../missing-ps5-game-and-trophy.png' : '../missing-ps4-trophy.png') : $trophy['trophy_icon']; ?>" alt="<?= htmlentities($trophy['trophy_name'], ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlentities($trophy['trophy_name'], ENT_QUOTES, 'UTF-8'); ?>" style="width: 5rem;" />
                                                </a>
                                            </div>

                                            <div>
                                                <div class="vstack">
                                                    <span>
                                                        <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/trophy/<?= $trophy['trophy_id'] . '-' . $utility->slugify($trophy['trophy_name']); ?>">
                                                            <b><?= htmlentities($trophy['trophy_name']); ?></b>
                                                        </a>
                                                    </span>
                                                    <?= nl2br(htmlentities($trophy['trophy_detail'], ENT_QUOTES, 'UTF-8')); ?>
                                                    <?php
                                                    if ($trophy['progress_target_value'] != null) {
                                                        echo '<br><b>0/' . $trophy['progress_target_value'] . '</b>';
                                                    }

                                                    if ($trophy['reward_name'] != null && $trophy['reward_image_url'] != null) {
                                                        echo "<br>Reward: <a href='/img/reward/" . $trophy['reward_image_url'] . "'>" . $trophy['reward_name'] . '</a>';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center align-middle">
                                        <div class="vstack gap-1">
                                            <?php
                                            foreach (explode(',', $trophy['platform']) as $platform) {
                                                echo "<span class=\"badge rounded-pill text-bg-primary p-2\">" . $platform . '</span> ';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td class="text-center align-middle">
                                        <?php
                                        if ($trophy['rarity_percent'] <= 0.02) {
                                            echo "<span class='trophy-legendary'>" . $trophy['rarity_percent'] . "%<br>Legendary</span>";
                                        } elseif ($trophy['rarity_percent'] <= 0.2) {
                                            echo "<span class='trophy-epic'>" . $trophy['rarity_percent'] . "%<br>Epic</span>";
                                        } elseif ($trophy['rarity_percent'] <= 2) {
                                            echo "<span class='trophy-rare'>" . $trophy['rarity_percent'] . "%<br>Rare</span>";
                                        } elseif ($trophy['rarity_percent'] <= 10) {
                                            echo "<span class='trophy-uncommon'>" . $trophy['rarity_percent'] . "%<br>Uncommon</span>";
                                        } else {
                                            echo "<span class='trophy-common'>" . $trophy['rarity_percent'] . "%<br>Common</span>";
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center align-middle">
                                        <img src="/img/trophy-<?= $trophy['trophy_type']; ?>.svg" alt="<?= ucfirst($trophy['trophy_type']); ?>" title="<?= ucfirst($trophy['trophy_type']); ?>" height="50" />
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
            <nav aria-label="Trophies page navigation">
                <ul class="pagination justify-content-center">
                    <?php
                    if ($trophyListPage->hasPreviousPage()) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($trophyListPage->getPageQueryParameters($trophyListPage->getPreviousPage())); ?>">&lt;</a></li>
                        <?php
                    }

                    if ($trophyListPage->shouldShowFirstPage()) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($trophyListPage->getPageQueryParameters($trophyListPage->getFirstPage())); ?>"><?= $trophyListPage->getFirstPage(); ?></a></li>
                        <?php

                        if ($trophyListPage->shouldShowLeadingEllipsis()) {
                            ?>
                            <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                            <?php
                        }
                    }

                    foreach ($trophyListPage->getPreviousPages() as $previousPage) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($trophyListPage->getPageQueryParameters($previousPage)); ?>"><?= $previousPage; ?></a></li>
                        <?php
                    }
                    ?>

                    <li class="page-item active" aria-current="page"><a class="page-link" href="?<?= http_build_query($trophyListPage->getPageQueryParameters($trophyListPage->getCurrentPage())); ?>"><?= $trophyListPage->getCurrentPage(); ?></a></li>

                    <?php
                    foreach ($trophyListPage->getNextPages() as $nextPage) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($trophyListPage->getPageQueryParameters($nextPage)); ?>"><?= $nextPage; ?></a></li>
                        <?php
                    }

                    if ($trophyListPage->shouldShowTrailingEllipsis()) {
                        ?>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <?php
                    }

                    if ($trophyListPage->shouldShowLastPage()) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($trophyListPage->getPageQueryParameters($trophyListPage->getLastPage())); ?>"><?= $trophyListPage->getLastPage(); ?></a></li>
                        <?php
                    }

                    if ($trophyListPage->hasNextPage()) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($trophyListPage->getPageQueryParameters($trophyListPage->getNextPage())); ?>">&gt;</a></li>
                        <?php
                    }
                    ?>
                </ul>
            </nav>
        </div>
    </div>
</main>

<?php
require_once('footer.php');
?>
