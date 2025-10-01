<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/PlayerAdvisorFilter.php';
require_once __DIR__ . '/classes/PlayerAdvisorService.php';
require_once __DIR__ . '/classes/PlayerAdvisorPage.php';
require_once __DIR__ . '/classes/PlayerSummary.php';
require_once __DIR__ . '/classes/PlayerSummaryService.php';
require_once __DIR__ . '/classes/TrophyRarityFormatter.php';

if (!isset($accountId)) {
    header("Location: /player/", true, 303);
    die();
}

$playerAdvisorFilter = PlayerAdvisorFilter::fromArray($_GET ?? []);
$playerAdvisorService = new PlayerAdvisorService($database);
$playerSummaryService = new PlayerSummaryService($database);
$playerAdvisorPage = new PlayerAdvisorPage(
    $playerAdvisorService,
    $playerSummaryService,
    $playerAdvisorFilter,
    (int) $accountId,
    (int) $player['status']
);

$playerSummary = $playerAdvisorPage->getPlayerSummary();
$page = $playerAdvisorPage->getCurrentPage();
$limit = $playerAdvisorPage->getPageSize();
$offset = $playerAdvisorPage->getOffset();
$totalTrophies = $playerAdvisorPage->getTotalTrophies();
$advisableTrophies = $playerAdvisorPage->getAdvisableTrophies();
$totalPages = $playerAdvisorPage->getTotalPages();
$filterParameters = $playerAdvisorPage->getFilterParameters();
$shouldDisplayAdvisor = $playerAdvisorPage->shouldDisplayAdvisor();
$trophyRarityFormatter = new TrophyRarityFormatter();

$title = $player["online_id"] . "'s Trophy Advisor ~ PSN 100%";
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
                <div class="btn-group">
                    <a class="btn btn-outline-primary" href="/player/<?= $player["online_id"]; ?>">Games</a>
                    <a class="btn btn-outline-primary" href="/player/<?= $player["online_id"]; ?>/log">Log</a>
                    <a class="btn btn-primary active" href="/player/<?= $player["online_id"]; ?>/advisor">Trophy Advisor</a>
                    <a class="btn btn-outline-primary" href="/game?sort=completion&filter=true&player=<?= $player["online_id"]; ?>">Game Advisor</a>
                    <a class="btn btn-outline-primary" href="/player/<?= $player["online_id"]; ?>/random">Random Games</a>
                </div>
            </div>

            <div class="col-12 col-lg-3 mb-3">
                <form>
                    <div class="input-group d-flex justify-content-end">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Filter</button>
                        <ul class="dropdown-menu p-2">
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= $playerAdvisorFilter->isPlatformSelected('pc') ? ' checked' : ''; ?> value="true" onChange="this.form.submit()" id="filterPC" name="pc">
                                    <label class="form-check-label" for="filterPC">
                                        PC
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= $playerAdvisorFilter->isPlatformSelected('ps3') ? ' checked' : ''; ?> value="true" onChange="this.form.submit()" id="filterPS3" name="ps3">
                                    <label class="form-check-label" for="filterPS3">
                                        PS3
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= $playerAdvisorFilter->isPlatformSelected('ps4') ? ' checked' : ''; ?> value="true" onChange="this.form.submit()" id="filterPS4" name="ps4">
                                    <label class="form-check-label" for="filterPS4">
                                        PS4
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= $playerAdvisorFilter->isPlatformSelected('ps5') ? ' checked' : ''; ?> value="true" onChange="this.form.submit()" id="filterPS5" name="ps5">
                                    <label class="form-check-label" for="filterPS5">
                                        PS5
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= $playerAdvisorFilter->isPlatformSelected('psvita') ? ' checked' : ''; ?> value="true" onChange="this.form.submit()" id="filterPSVITA" name="psvita">
                                    <label class="form-check-label" for="filterPSVITA">
                                        PSVITA
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= $playerAdvisorFilter->isPlatformSelected('psvr') ? ' checked' : ''; ?> value="true" onChange="this.form.submit()" id="filterPSVR" name="psvr">
                                    <label class="form-check-label" for="filterPSVR">
                                        PSVR
                                    </label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"<?= $playerAdvisorFilter->isPlatformSelected('psvr2') ? ' checked' : ''; ?> value="true" onChange="this.form.submit()" id="filterPSVR2" name="psvr2">
                                    <label class="form-check-label" for="filterPSVR2">
                                        PSVR2
                                    </label>
                                </div>
                            </li>
                        </ul>
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
                            if (!$shouldDisplayAdvisor) {
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
                                }
                            } else {
                                foreach ($advisableTrophies as $trophy) {
                                    ?>
                                    <tr>
                                        <td scope="row" class="text-center align-middle">
                                            <a href="/game/<?= $trophy["game_id"] ."-". $utility->slugify($trophy["game_name"]); ?>/<?= $player["online_id"]; ?>">
                                                <img src="/img/title/<?= ($trophy["game_icon"] == ".png") ? ((str_contains($trophy["platform"], "PS5")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-game.png") : $trophy["game_icon"]; ?>" alt="<?= $trophy["game_name"]; ?>" title="<?= $trophy["game_name"]; ?>" style="width: 10rem;" />
                                            </a>
                                        </td>
                                        <td class="align-middle">
                                            <div class="hstack gap-3">
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <a href="/trophy/<?= $trophy["trophy_id"] ."-". $utility->slugify($trophy["trophy_name"]); ?>/<?= $player["online_id"]; ?>">
                                                        <img src="/img/trophy/<?= ($trophy["trophy_icon"] == ".png") ? ((str_contains($trophy["platform"], "PS5")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-trophy.png") : $trophy["trophy_icon"]; ?>" alt="<?= $trophy["trophy_name"]; ?>" title="<?= $trophy["trophy_name"]; ?>" style="width: 5rem;" />
                                                    </a>
                                                </div>

                                                <div>
                                                    <div class="vstack">
                                                        <span>
                                                            <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/trophy/<?= $trophy["trophy_id"] ."-". $utility->slugify($trophy["trophy_name"]); ?>">
                                                                <b><?= htmlentities($trophy["trophy_name"]); ?></b>
                                                            </a>
                                                        </span>
                                                        <?= nl2br(htmlentities($trophy["trophy_detail"], ENT_QUOTES, "UTF-8")); ?>
                                                        <?php
                                                        if ($trophy["progress_target_value"] != null) {
                                                            echo "<br><b>0/". $trophy["progress_target_value"] ."</b>";
                                                        }

                                                        if ($trophy["reward_name"] != null && $trophy["reward_image_url"] != null) {
                                                            echo "<br>Reward: <a href='/img/reward/". $trophy["reward_image_url"] ."'>". $trophy["reward_name"] ."</a>";
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <div class="vstack gap-1">
                                                <?php
                                                foreach (explode(",", $trophy["platform"]) as $platform) {
                                                    echo "<span class=\"badge rounded-pill text-bg-primary p-2\">". $platform ."</span> ";
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                        <?php
                                        $trophyRarity = $trophyRarityFormatter->format($trophy["rarity_percent"]);
                                        echo $trophyRarity->renderSpan();
                                        ?>
                                        </td>
                                        <td class="text-center align-middle">
                                            <img src="/img/trophy-<?= $trophy["trophy_type"]; ?>.svg" alt="<?= ucfirst($trophy["trophy_type"]); ?>" title="<?= ucfirst($trophy["trophy_type"]); ?>" height="50" />
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
