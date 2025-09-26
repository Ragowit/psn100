<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/GameHeaderService.php';
require_once __DIR__ . '/classes/GamePlayerFilter.php';
require_once __DIR__ . '/classes/GameRecentPlayersService.php';
require_once __DIR__ . '/classes/GameRecentPlayer.php';

if (!isset($gameId)) {
    header("Location: /game/", true, 303);
    die();
}

$gameRecentPlayersService = new GameRecentPlayersService($database);

$game = $gameRecentPlayersService->getGame((int) $gameId);

if ($game === null) {
    header("Location: /game/", true, 303);
    die();
}

$gameHeaderService = new GameHeaderService($database);
$gameHeaderData = $gameHeaderService->buildHeaderData($game);

$accountId = null;
if (isset($player)) {
    $accountId = $gameRecentPlayersService->getPlayerAccountId($player);

    if ($accountId === null) {
        header("Location: /game/" . $game["id"] . "-" . $utility->slugify($game["name"]), true, 303);
        die();
    }

    $gamePlayer = $gameRecentPlayersService->getGamePlayer($game["np_communication_id"], $accountId);
}

$filter = GamePlayerFilter::fromArray($_GET ?? []);
$recentPlayers = $gameRecentPlayersService->getRecentPlayers($game["np_communication_id"], $filter);

$gameSlug = $game["id"] . "-" . $utility->slugify($game["name"]);

$title = $game["name"] ." Recent Players ~ PSN 100%";
require_once("header.php");

?>

<main class="container">
    <?php
    require_once("game_header.php");
    ?>

    <div class="p-3 mb-3">
        <div class="row">
            <div class="col-3">
            </div>

            <div class="col-6 text-center">
                <div class="btn-group">
                    <a class="btn btn-outline-primary" href="/game/<?= $game["id"] ."-". $utility->slugify($game["name"]); ?><?= (isset($player) ? "/".$player : "") ?>">Trophies</a>
                    <a class="btn btn-outline-primary" href="/game-leaderboard/<?= $game["id"] ."-". $utility->slugify($game["name"]); ?><?= (isset($player) ? "/".$player : "") ?>">Leaderboard</a>
                    <a class="btn btn-primary active" href="/game-recent-players/<?= $game["id"] ."-". $utility->slugify($game["name"]); ?><?= (isset($player) ? "/".$player : "") ?>">Recent Players</a>
                </div>
            </div>

            <div class="col-3">
            </div>
        </div>
    </div>

    <div class="bg-body-tertiary p-3 rounded">
        <div class="row">
            <div class="col-12">
                <div class="table-responsive-xxl">
                    <table class="table">
                        <thead>
                            <tr class="text-uppercase">
                                <th scope="col"></th>
                                <th scope="col">User</th>
                                <th scope="col" class="text-center">Date</th>
                                <th scope="col" class="text-center">Progress</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            foreach ($recentPlayers as $index => $recentPlayer) {
                                $rank = $index + 1;
                                $countryName = $recentPlayer->getCountryName($utility);
                                $paramsAvatar = $recentPlayer->getAvatarQueryParameters($filter);
                                $paramsCountry = $recentPlayer->getCountryQueryParameters($filter);
                                ?>
                                <tr<?= $recentPlayer->matchesAccountId($accountId) ? " class='table-primary'" : ""; ?>>
                                    <th class="align-middle" style="width: 2rem;" scope="row"><?= $rank; ?></th>

                                    <td>
                                        <div class="hstack gap-3">
                                            <div>
                                                <a href="?<?= http_build_query($paramsAvatar); ?>">
                                                    <img src="/img/avatar/<?= htmlspecialchars($recentPlayer->getAvatarUrl(), ENT_QUOTES, 'UTF-8'); ?>" alt="" height="50" width="50" />
                                                </a>
                                            </div>

                                            <div>
                                                <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= $gameSlug; ?>/<?= rawurlencode($recentPlayer->getOnlineId()); ?>"><?= htmlspecialchars($recentPlayer->getOnlineId(), ENT_QUOTES, 'UTF-8'); ?></a>
                                                <?php if ($recentPlayer->hasHiddenTrophies()) { ?>
                                                    <span style='color: #9d9d9d; font-weight: bold;'>(H)</span>
                                                <?php } ?>
                                            </div>

                                            <div class="ms-auto">
                                                <a href="?<?= http_build_query($paramsCountry); ?>">
                                                    <img src="/img/country/<?= htmlspecialchars($recentPlayer->getCountryCode(), ENT_QUOTES, 'UTF-8'); ?>.svg" alt="<?= $countryName; ?>" title="<?= $countryName; ?>" height="50" width="50" style="border-radius: 50%;" />
                                                </a>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="align-middle text-center" style="white-space: nowrap; width: 5rem;">
                                        <span id="date<?= $rank; ?>"></span>
                                        <script>
                                            document.getElementById("date<?= $rank; ?>").innerHTML = new Date(<?= json_encode($recentPlayer->getLastKnownDate() . ' UTC'); ?>).toLocaleString('sv-SE').replace(' ', '<br>');
                                        </script>
                                    </td>

                                    <td class="align-middle text-center" style="white-space: nowrap; width: 10rem;">
                                        <div class="vstack gap-1">
                                            <div>
                                                <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $recentPlayer->getPlatinumCount(); ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $recentPlayer->getGoldCount(); ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $recentPlayer->getSilverCount(); ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $recentPlayer->getBronzeCount(); ?></span>
                                            </div>

                                            <div>
                                                <div class="progress" role="progressbar" aria-label="Player game progress" aria-valuenow="<?= $recentPlayer->getProgress(); ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <div class="progress-bar" style="width: <?= $recentPlayer->getProgress(); ?>%"><?= $recentPlayer->getProgress(); ?>%</div>
                                                </div>
                                            </div>
                                        </div>
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
</main>

<?php
require_once("footer.php");
?>
