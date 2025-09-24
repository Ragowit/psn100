<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/GameHeaderService.php';
require_once __DIR__ . '/classes/GameService.php';
require_once __DIR__ . '/classes/PageMetaData.php';

if (!isset($gameId)) {
    header("Location: /game/", true, 303);
    die();
}

$gameService = new GameService($database);

$game = $gameService->getGame((int) $gameId);

if ($game === null) {
    header("Location: /game/", true, 303);
    die();
}

$gameHeaderService = new GameHeaderService($database);
$gameHeaderData = $gameHeaderService->buildHeaderData($game);

$sort = $gameService->resolveSort($_GET);

$accountId = null;
$gamePlayer = null;

if (isset($player)) {
    $accountId = $gameService->getPlayerAccountId((string) $player);

    if ($accountId === null) {
        header("Location: /game/" . $game["id"] . "-" . $utility->slugify($game["name"]), true, 303);
        die();
    }

    $gamePlayer = $gameService->getGamePlayer($game["np_communication_id"], $accountId);
}

$metaData = (new PageMetaData())
    ->setTitle($game["name"] . " Trophies")
    ->setDescription($game["bronze"] . " Bronze ~ " . $game["silver"] . " Silver ~ " . $game["gold"] . " Gold ~ " . $game["platinum"] . " Platinum")
    ->setImage("https://psn100.net/img/title/" . $game["icon_url"])
    ->setUrl("https://psn100.net/game/" . $game["id"] . "-" . $utility->slugify($game["name"]));

$title = $game["name"] ." Trophies ~ PSN 100%";
require_once("header.php");
?>

<main class="container">
    <?php
    require_once("game_header.php");
    ?>

    <div class="p-3">
        <div class="row">
            <div class="col-12 col-lg-3">
            </div>

            <div class="col-12 col-lg-6 mb-3 text-center">
                <div class="btn-group">
                    <a class="btn btn-primary active" href="/game/<?= $game["id"] ."-". $utility->slugify($game["name"]); ?><?= (isset($player) ? "/".$player : "") ?>">Trophies</a>
                    <a class="btn btn-outline-primary" href="/game-leaderboard/<?= $game["id"] ."-". $utility->slugify($game["name"]); ?><?= (isset($player) ? "/".$player : "") ?>">Leaderboard</a>
                    <a class="btn btn-outline-primary" href="/game-recent-players/<?= $game["id"] ."-". $utility->slugify($game["name"]); ?><?= (isset($player) ? "/".$player : "") ?>">Recent Players</a>
                </div>
            </div>

            <div class="col-12 col-lg-3 mb-3">
                <form>
                    <div class="input-group d-flex justify-content-end">
                        <?php
                        if (isset($player)) {
                            ?>
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Filter</button>
                            <ul class="dropdown-menu p-2">
                                <li>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"<?= (!empty($_GET["unearned"]) ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterUnearnedTrophies" name="unearned">
                                        <label class="form-check-label" for="filterUnearnedTrophies">
                                            Unearned Trophies
                                        </label>
                                    </div>
                                </li>
                            </ul>
                            <?php
                        }
                        ?>
                        <select class="form-select" name="sort" onChange="this.form.submit()">
                            <option disabled>Sort by...</option>
                            <option value="default"<?= ($sort == "default" ? " selected" : ""); ?>>Default</option>
                            <?php
                            if (isset($player)) {
                                ?>
                                <option value="date"<?= ($sort == "date" ? " selected" : ""); ?>>Date</option>
                                <?php
                            }
                            ?>
                            <option value="rarity"<?= ($sort == "rarity" ? " selected" : ""); ?>>Rarity</option>
                        </select>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="bg-body-tertiary p-3 rounded">
        <div class="row">
            <div class="col-12">
                <?php
                $trophyGroups = $gameService->getTrophyGroups($game["np_communication_id"]);
                foreach ($trophyGroups as $trophyGroup) {
                    $trophyGroupPlayer = null;

                    if ($accountId !== null) {
                        $trophyGroupPlayer = $gameService->getTrophyGroupPlayer(
                            $game["np_communication_id"],
                            (string) $trophyGroup["group_id"],
                            $accountId
                        );
                    }

                    $previousTimeStamp = null;

                    if ($accountId !== null
                        && $trophyGroupPlayer !== null
                        && ($trophyGroupPlayer["progress"] ?? null) == 100
                        && !empty($_GET["unearned"])) {
                        continue;
                    }
                    ?>
                    <div class="table-responsive-xxl">
                        <table class="table" id="<?= $trophyGroup["group_id"]; ?>">
                            <thead>
                                <tr>
                                    <th scope="col" colspan="4" class="bg-dark-subtle">
                                        <div class="hstack gap-3">
                                            <div>
                                                <img class="card-img object-fit-cover" style="height: 7rem;" src="/img/group/<?= ($trophyGroup["icon_url"] == ".png") ? ((str_contains($game["platform"], "PS5") || str_contains($game["platform"], "PSVR2")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-game.png") : $trophyGroup["icon_url"]; ?>" alt="<?= htmlentities($trophyGroup["name"]); ?>">
                                            </div>
                                            
                                            <div>
                                                <b><?= htmlentities($trophyGroup["name"]); ?></b><br>
                                                <?= nl2br(htmlentities($trophyGroup["detail"], ENT_QUOTES, "UTF-8")); ?>
                                            </div>

                                            <div class="ms-auto">
                                                <?php
                                                if ($trophyGroupPlayer !== null) {
                                                    if ($trophyGroup["group_id"] == "default") {
                                                        ?>
                                                        <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $trophyGroupPlayer["platinum"] ?? "0"; ?>/<?= $trophyGroup["platinum"]; ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $trophyGroupPlayer["gold"] ?? "0"; ?>/<?= $trophyGroup["gold"]; ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $trophyGroupPlayer["silver"] ?? "0"; ?>/<?= $trophyGroup["silver"]; ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $trophyGroupPlayer["bronze"] ?? "0"; ?>/<?= $trophyGroup["bronze"]; ?></span>
                                                        <?php
                                                    } else {
                                                        ?>
                                                        <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $trophyGroupPlayer["gold"] ?? "0"; ?>/<?= $trophyGroup["gold"]; ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $trophyGroupPlayer["silver"] ?? "0"; ?>/<?= $trophyGroup["silver"]; ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $trophyGroupPlayer["bronze"] ?? "0"; ?>/<?= $trophyGroup["bronze"]; ?></span>
                                                        <?php
                                                    }
                                                    ?>
                                                    <div>
                                                        <div class="progress mt-1" role="progressbar" aria-label="Player trophy progress" aria-valuenow="<?= $trophyGroupPlayer["progress"] ?? "0"; ?>" aria-valuemin="0" aria-valuemax="100">
                                                            <div class="progress-bar" style="width: <?= $trophyGroupPlayer["progress"] ?? "0"; ?>%"><?= $trophyGroupPlayer["progress"] ?? "0"; ?>%</div>
                                                        </div>
                                                    </div>
                                                    <?php
                                                } else {
                                                    if ($trophyGroup["group_id"] == "default") {
                                                        ?>
                                                        <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $trophyGroup["platinum"]; ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $trophyGroup["gold"]; ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $trophyGroup["silver"]; ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $trophyGroup["bronze"]; ?></span>
                                                        <?php
                                                    } else {
                                                        ?>
                                                        <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $trophyGroup["gold"]; ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $trophyGroup["silver"]; ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $trophyGroup["bronze"]; ?></span>
                                                        <?php
                                                    }
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php
                                $trophies = $gameService->getTrophies(
                                    $game["np_communication_id"],
                                    (string) $trophyGroup["group_id"],
                                    $accountId,
                                    $sort
                                );

                                foreach ($trophies as $trophy) {
                                    if ($accountId !== null && ($trophy["earned"] ?? null) == 1 && !empty($_GET["unearned"])) {
                                        continue;
                                    }

                                    // A game can have been updated with a progress_target_value, while the user earned the trophy while it hadn't one. This fixes this issue.
                                    if ($accountId !== null && ($trophy["earned"] ?? null) == 1 && $trophy["progress_target_value"] != null) {
                                        $trophy["progress"] = $trophy["progress_target_value"];
                                    }

                                    $trClass = "";
                                    if ($trophy["status"] == 1) {
                                        $trClass = " class=\"table-warning\" title=\"This trophy is unobtainable and not accounted for on any leaderboard.\"";
                                    } elseif ($accountId !== null && ($trophy["earned"] ?? null) == 1) {
                                        $trClass = " class=\"table-success\"";
                                    }
                                    ?>
                                    <tr scope="row"<?= $trClass; ?>>
                                        <?php
                                        switch ($trophy["type"]) {
                                            case "bronze":
                                                $trophyColor = "#c46438";
                                                break;
                                            case "silver":
                                                $trophyColor = "#777777";
                                                break;
                                            case "gold":
                                                $trophyColor = "#c2903e";
                                                break;
                                            case "platinum":
                                                $trophyColor = "#667fb2";
                                                break;
                                        }
                                        ?>
                                        <td style="width: 5rem;">
                                            <div>
                                                <img class="card-img object-fit-scale" style="height: 5rem;" src="/img/trophy/<?= ($trophy["icon_url"] == ".png") ? ((str_contains($game["platform"], "PS5") || str_contains($game["platform"], "PSVR2")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-trophy.png") : $trophy["icon_url"]; ?>" alt="<?= htmlentities($trophy["name"]); ?>">
                                            </div>
                                        </td>

                                        <td class="w-auto">
                                            <div class="vstack">
                                                <span>
                                                    <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="/trophy/<?= $trophy["id"] ."-". $utility->slugify($trophy["name"]); ?><?= (isset($player) ? "/".$player : ""); ?>">
                                                        <b><?= htmlentities($trophy["name"]); ?></b>
                                                    </a>
                                                </span>
                                                <?= nl2br(htmlentities($trophy["detail"], ENT_QUOTES, "UTF-8")); ?>
                                                <?php
                                                if ($trophy["progress_target_value"] != null) {
                                                    echo "<span>";
                                                    if (isset($trophy["progress"])) {
                                                        echo $trophy["progress"];
                                                    } else {
                                                        echo "0";
                                                    }
                                                    echo "/". $trophy["progress_target_value"] ."</span>";
                                                }

                                                if ($trophy["reward_name"] != null && $trophy["reward_image_url"] != null) {
                                                    echo "<span>Reward: <a class='link-underline link-underline-opacity-0 link-underline-opacity-100-hover' href='/img/reward/". $trophy["reward_image_url"] ."'>". $trophy["reward_name"] ."</a></span>";
                                                }
                                                ?>
                                            </div>
                                        </td>

                                        <td class="w-auto text-end align-middle">
                                            <?php
                                            if ($accountId !== null && ($trophy["earned"] ?? null) == 1) {
                                                ?>
                                                <span id="earned<?= $trophy["order_id"]; ?>" style="text-wrap: nowrap;"></span>
                                                <script>
                                                    <?php
                                                    if ($trophy["earned_date"] == "No Timestamp") {
                                                        ?>
                                                        document.getElementById("earned<?= $trophy["order_id"]; ?>").innerHTML = "No Timestamp";
                                                        <?php
                                                    } else {
                                                        ?>
                                                        document.getElementById("earned<?= $trophy["order_id"]; ?>").innerHTML = new Date('<?= $trophy["earned_date"]; ?> UTC').toLocaleString('sv-SE').replace(' ', '<br>');
                                                        <?php
                                                    }
                                                    ?>
                                                </script>
                                                <?php
                                                if ($sort == "date" && $previousTimeStamp !== null && $previousTimeStamp != "No Timestamp" && $trophy["earned_date"] != "No Timestamp") {
                                                    echo "<br>";
                                                    $datetime1 = date_create($previousTimeStamp);
                                                    $datetime2 = date_create($trophy["earned_date"]);
                                                    $completionTimes = explode(", ", date_diff($datetime1, $datetime2)->format("%y years, %m months, %d days, %h hours, %i minutes, %s seconds"));
                                                    $first = -1;
                                                    $second = -1;
                                                    for ($i = 0; $i < count($completionTimes); $i++) {
                                                        if ($completionTimes[$i][0] == "0") {
                                                            continue;
                                                        }
    
                                                        if ($first == -1) {
                                                            $first = $i;
                                                        } elseif ($second == -1) {
                                                            $second = $i;
                                                        }
                                                    }
    
                                                    if ($first >= 0 && $second >= 0) {
                                                        echo "(+". $completionTimes[$first] .", ". $completionTimes[$second] .")";
                                                    } elseif ($first >= 0 && $second == -1) {
                                                        echo "(+". $completionTimes[$first] .")";
                                                    }
                                                }
                                                if ($sort == "date") {
                                                    $previousTimeStamp = $trophy["earned_date"];
                                                }
                                            }
                                            ?>
                                        </td>

                                        <td style="width: 5rem; background: linear-gradient(to top right, var(--bs-table-bg), var(--bs-table-bg), var(--bs-table-bg), <?= $trophyColor; ?>);" class="text-center align-middle">
                                            <?php
                                            if ($trophy["status"] == 1) {
                                                echo "<span>". $trophy["rarity_percent"] ."%<br>Unobtainable</span>";
                                            } elseif ($trophy["rarity_percent"] <= 0.02) {
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
                                    </tr>
                                    <?php
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                <?php
                }
                ?>
            </div>
        </div>
    </div>
</main>

<?php
require_once("footer.php");
?>
