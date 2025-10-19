<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/PlayerPageAccessGuard.php';
require_once __DIR__ . '/classes/PlayerLogFilter.php';
require_once __DIR__ . '/classes/PlayerLogService.php';
require_once __DIR__ . '/classes/PlayerLogPage.php';
require_once __DIR__ . '/classes/PlayerSummary.php';
require_once __DIR__ . '/classes/PlayerSummaryService.php';
require_once __DIR__ . '/classes/TrophyRarityFormatter.php';
require_once __DIR__ . '/classes/PlayerNavigation.php';

$playerPageAccessGuard = PlayerPageAccessGuard::fromAccountId($accountId ?? null);
$accountId = $playerPageAccessGuard->requireAccountId();

$playerLogFilter = PlayerLogFilter::fromArray($_GET ?? []);
$playerLogService = new PlayerLogService($database);
$playerSummaryService = new PlayerSummaryService($database);
$playerSummary = $playerSummaryService->getSummary((int) $accountId);
$playerLogPage = new PlayerLogPage(
    $playerLogService,
    $playerLogFilter,
    (int) $player['account_id'],
    (int) $player['status']
);
$trophiesLog = $playerLogPage->getTrophies();
$trophyRarityFormatter = new TrophyRarityFormatter();
$playerNavigation = PlayerNavigation::forSection((string) $player['online_id'], PlayerNavigation::SECTION_LOG);

$title = $player["online_id"] . "'s Trophy Log ~ PSN 100%";
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
                                    <input class="form-check-input" type="checkbox"<?= $playerLogFilter->isPlatformSelected('pc') ? ' checked' : ''; ?> value="true" onChange="this.form.submit()" id="filterPC" name="pc">
                                    <label class="form-check-label" for="filterPC">
                                        PC
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= $playerLogFilter->isPlatformSelected('ps3') ? ' checked' : ''; ?> value="true" onChange="this.form.submit()" id="filterPS3" name="ps3">
                                    <label class="form-check-label" for="filterPS3">
                                        PS3
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= $playerLogFilter->isPlatformSelected('ps4') ? ' checked' : ''; ?> value="true" onChange="this.form.submit()" id="filterPS4" name="ps4">
                                    <label class="form-check-label" for="filterPS4">
                                        PS4
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= $playerLogFilter->isPlatformSelected('ps5') ? ' checked' : ''; ?> value="true" onChange="this.form.submit()" id="filterPS5" name="ps5">
                                    <label class="form-check-label" for="filterPS5">
                                        PS5
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= $playerLogFilter->isPlatformSelected('psvita') ? ' checked' : ''; ?> value="true" onChange="this.form.submit()" id="filterPSVITA" name="psvita">
                                    <label class="form-check-label" for="filterPSVITA">
                                        PSVITA
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= $playerLogFilter->isPlatformSelected('psvr') ? ' checked' : ''; ?> value="true" onChange="this.form.submit()" id="filterPSVR" name="psvr">
                                    <label class="form-check-label" for="filterPSVR">
                                        PSVR
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= $playerLogFilter->isPlatformSelected('psvr2') ? ' checked' : ''; ?> value="true" onChange="this.form.submit()" id="filterPSVR2" name="psvr2">
                                    <label class="form-check-label" for="filterPSVR2">
                                        PSVR2
                                    </label>
                                </div>
                            </li>
                        </ul>

                        <select class="form-select" name="sort" onChange="this.form.submit()">
                            <option disabled>Sort by...</option>
                            <option value="date"<?= $playerLogFilter->isSort(PlayerLogFilter::SORT_DATE) ? ' selected' : ''; ?>>Date</option>
                            <option value="rarity"<?= $playerLogFilter->isSort(PlayerLogFilter::SORT_RARITY) ? ' selected' : ''; ?>>Rarity</option>
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
                            if ($player["status"] == 1) {
                                ?>
                                <tr>
                                    <td colspan="5" class="text-center"><h3>This player have some funny looking trophy data. This doesn't necessarily means cheating, but all data from this player will not be in any of the site statistics or leaderboards. <a href="https://github.com/Ragowit/psn100/issues?q=label%3Acheater+<?= $player["online_id"]; ?>+OR+<?= $player["account_id"]; ?>">Dispute</a>?</h3></td>
                                </tr>
                                <?php
                            } elseif ($player["status"] == 3) {
                                ?>
                                <tr>
                                    <td colspan="5" class="text-center"><h3>This player seems to have a <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="https://www.playstation.com/en-us/support/account/privacy-settings-psn/">private</a> profile.</h3></td>
                                </tr>
                                <?php
                            } else {
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

                                        if ($trophyRarity->isUnobtainable()) {
                                            echo "<span class='badge rounded-pill text-bg-warning p-2'>" . $trophyRarity->getLabel() . '</span>';
                                        } else {
                                            echo $trophyRarity->renderSpan();
                                        }
                                        ?>
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
