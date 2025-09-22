<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/GameHeaderService.php';
require_once __DIR__ . '/classes/GamePlayerFilter.php';
require_once __DIR__ . '/classes/GameRecentPlayersService.php';

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
$rows = $gameRecentPlayersService->getRecentPlayers($game["np_communication_id"], $filter);

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
                            $rank = 0;
                            foreach ($rows as $row) {
                                $countryName = $utility->getCountryName($row["country"]);
                                $paramsAvatar = $filter->withAvatar($row["avatar_url"]);
                                $paramsCountry = $filter->withCountry($row["country"]);
                                ?>
                                <tr<?= ($accountId !== null && $row["account_id"] === $accountId) ? " class='table-primary'" : ""; ?>>
                                    <th class="align-middle" style="width: 2rem;" scope="row"><?= ++$rank; ?></th>

                                    <td>
                                        <div class="hstack gap-3">
                                            <div>
                                                <a href="?<?= http_build_query($paramsAvatar); ?>">
                                                    <img src="/img/avatar/<?= $row["avatar_url"]; ?>" alt="" height="50" width="50" />
                                                </a>
                                            </div>

                                            <div>
                                                <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/game/<?= $game["id"] ."-". $utility->slugify($game["name"]); ?>/<?= $row["name"]; ?>"><?= $row["name"]; ?></a>
                                                <?php
                                                if ($row["trophy_count_npwr"] < $row["trophy_count_sony"]) {
                                                    echo " <span style='color: #9d9d9d; font-weight: bold;'>(H)</span>";
                                                }
                                                ?>
                                            </div>

                                            <div class="ms-auto">
                                                <a href="?<?= http_build_query($paramsCountry); ?>">
                                                    <img src="/img/country/<?= $row["country"]; ?>.svg" alt="<?= $countryName; ?>" title="<?= $countryName; ?>" height="50" width="50" style="border-radius: 50%;" />
                                                </a>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="align-middle text-center" style="white-space: nowrap; width: 5rem;">
                                        <span id="date<?= $rank; ?>"></span>
                                        <script>
                                            document.getElementById("date<?= $rank; ?>").innerHTML = new Date('<?= $row["last_known_date"]; ?> UTC').toLocaleString('sv-SE').replace(' ', '<br>');
                                        </script>
                                    </td>

                                    <td class="align-middle text-center" style="white-space: nowrap; width: 10rem;">
                                        <div class="vstack gap-1">
                                            <div>
                                                <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $row["platinum"]; ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $row["gold"]; ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $row["silver"]; ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $row["bronze"]; ?></span>
                                            </div>

                                            <div>
                                                <div class="progress" role="progressbar" aria-label="Player game progress" aria-valuenow="<?= $row["progress"]; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <div class="progress-bar" style="width: <?= $row["progress"]; ?>%"><?= $row["progress"]; ?>%</div>
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
