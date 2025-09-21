<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/PlayerAdvisorFilter.php';
require_once __DIR__ . '/classes/PlayerAdvisorService.php';

if (!isset($accountId)) {
    header("Location: /player/", true, 303);
    die();
}

$playerAdvisorFilter = PlayerAdvisorFilter::fromArray($_GET ?? []);
$playerAdvisorService = new PlayerAdvisorService($database);

$page = $playerAdvisorFilter->getPage();
$limit = PlayerAdvisorService::PAGE_SIZE;
$offset = $playerAdvisorFilter->getOffset($limit);

$totalTrophies = 0;
$advisableTrophies = [];

if ($player["status"] != 1 && $player["status"] != 3) {
    $playerAccountId = (int) $player["account_id"];
    $totalTrophies = $playerAdvisorService->countAdvisableTrophies($playerAccountId, $playerAdvisorFilter);
    $advisableTrophies = $playerAdvisorService->getAdvisableTrophies($playerAccountId, $playerAdvisorFilter, $offset, $limit);
}

$totalPages = (int) ceil($totalTrophies / $limit);
$filterParameters = $playerAdvisorFilter->getFilterParameters();

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
                                            if ($trophy["rarity_percent"] <= 0.02) {
                                                echo "<span class='trophy-legendary'>". $trophy["rarity_percent"] ."%<br>Legendary</span>";
                                            } elseif ($trophy["rarity_percent"] <= 0.2) {
                                                echo "<span class='trophy-epic'>". $trophy["rarity_percent"] ."%<br>Epic</span>";
                                            } elseif ($trophy["rarity_percent"] <= 2) {
                                                echo "<span class='trophy-rare'>". $trophy["rarity_percent"] ."%<br>Rare</span>";
                                            } elseif ($trophy["rarity_percent"] <= 10) {
                                                echo "<span class='trophy-uncommon'>". $trophy["rarity_percent"] ."%<br>Uncommon</span>";
                                            } else {
                                                echo "<span class='trophy-common'>". $trophy["rarity_percent"] ."%<br>Common</span>";
                                            }
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
            <nav aria-label="Player log navigation">
                <ul class="pagination justify-content-center">
                    <?php
                    $baseParameters = $filterParameters;

                    if ($page > 1) {
                        $previousParameters = $baseParameters;
                        $previousParameters['page'] = $page - 1;
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($previousParameters); ?>" aria-label="Previous">&lt;</a></li>
                        <?php
                    }

                    if ($page > 3) {
                        $firstPageParameters = $baseParameters;
                        $firstPageParameters['page'] = 1;
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($firstPageParameters); ?>">1</a></li>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <?php
                    }

                    if ($page-2 > 0) {
                        $twoBeforeParameters = $baseParameters;
                        $twoBeforeParameters['page'] = $page - 2;
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($twoBeforeParameters); ?>"><?= $page-2; ?></a></li>
                        <?php
                    }

                    if ($page-1 > 0) {
                        $oneBeforeParameters = $baseParameters;
                        $oneBeforeParameters['page'] = $page - 1;
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($oneBeforeParameters); ?>"><?= $page-1; ?></a></li>
                        <?php
                    }
                    ?>

                    <?php
                    $currentParameters = $baseParameters;
                    $currentParameters['page'] = $page;
                    ?>
                    <li class="page-item active" aria-current="page"><a class="page-link" href="?<?= http_build_query($currentParameters); ?>"><?= $page; ?></a></li>

                    <?php
                    if ($page + 1 <= $totalPages) {
                        $oneAfterParameters = $baseParameters;
                        $oneAfterParameters['page'] = $page + 1;
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($oneAfterParameters); ?>"><?= $page+1; ?></a></li>
                        <?php
                    }

                    if ($page + 2 <= $totalPages) {
                        $twoAfterParameters = $baseParameters;
                        $twoAfterParameters['page'] = $page + 2;
                        ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($twoAfterParameters); ?>"><?= $page+2; ?></a></li>
                        <?php
                    }

                    if ($page < $totalPages - 2) {
                        $lastPageParameters = $baseParameters;
                        $lastPageParameters['page'] = $totalPages;
                        ?>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($lastPageParameters); ?>"><?= $totalPages; ?></a></li>
                        <?php
                    }

                    if ($page < $totalPages) {
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
