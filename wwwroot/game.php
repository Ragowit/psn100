<?php
declare(strict_types=1);

require_once __DIR__ . '/classes/GamePage.php';
require_once __DIR__ . '/classes/GameTrophyFilter.php';
require_once __DIR__ . '/classes/TrophyRarityFormatter.php';

if (!isset($gameId)) {
    header("Location: /game/", true, 303);
    die();
}

$gameService = new GameService($database);
$gameHeaderService = new GameHeaderService($database);

try {
    $gamePage = new GamePage(
        $gameService,
        $gameHeaderService,
        $utility,
        (int) $gameId,
        $_GET ?? [],
        isset($player) ? (string) $player : null
    );
} catch (GameNotFoundException $exception) {
    header("Location: /game/", true, 303);
    die();
} catch (GameLeaderboardPlayerNotFoundException $exception) {
    $slug = $utility->slugify($exception->getGameName());
    header("Location: /game/" . $exception->getGameId() . "-" . $slug, true, 303);
    die();
}

$game = $gamePage->getGame();
$gameHeaderData = $gamePage->getGameHeaderData();
$sort = $gamePage->getSort();
$accountId = $gamePage->getPlayerAccountId();
$gamePlayer = $gamePage->getGamePlayer();
$gameTrophyFilter = GameTrophyFilter::fromQueryParameters($_GET ?? [], $accountId !== null);
$metaData = $gamePage->createMetaData();
$title = $gamePage->getPageTitle();
$trophyRarityFormatter = new TrophyRarityFormatter();
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
                    <a class="btn btn-primary active" href="/game/<?= $game->getId() . '-' . $utility->slugify($game->getName()); ?><?= (isset($player) ? '/' . $player : ''); ?>">Trophies</a>
                    <a class="btn btn-outline-primary" href="/game-leaderboard/<?= $game->getId() . '-' . $utility->slugify($game->getName()); ?><?= (isset($player) ? '/' . $player : ''); ?>">Leaderboard</a>
                    <a class="btn btn-outline-primary" href="/game-recent-players/<?= $game->getId() . '-' . $utility->slugify($game->getName()); ?><?= (isset($player) ? '/' . $player : ''); ?>">Recent Players</a>
                    <a class="btn btn-outline-primary" href="/game-history/<?= $game->getId() . '-' . $utility->slugify($game->getName()); ?><?= (isset($player) ? '/' . $player : ''); ?>">History</a>
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
                                        <input class="form-check-input" type="checkbox"<?= ($gameTrophyFilter->shouldShowUnearnedOnly() ? " checked" : "") ?> value="true" onChange="this.form.submit()" id="filterUnearnedTrophies" name="unearned">
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
                $trophyGroups = $gamePage->getTrophyGroups();
                foreach ($trophyGroups as $trophyGroup) {
                    $trophyGroupId = $trophyGroup->getId();
                    $trophyGroupPlayer = $gamePage->getTrophyGroupPlayer($trophyGroupId);

                    $previousTimeStamp = null;

                    if (!$gameTrophyFilter->shouldDisplayGroup($trophyGroupPlayer)) {
                        continue;
                    }
                    ?>
                    <div class="table-responsive-xxl">
                        <table class="table" id="<?= htmlspecialchars($trophyGroupId, ENT_QUOTES, 'UTF-8'); ?>">
                            <thead>
                                <tr>
                                    <th scope="col" colspan="5" class="bg-dark-subtle">
                                        <div class="hstack gap-3">
                                            <div>
                                                <img class="card-img object-fit-cover" style="height: 7rem;" src="/img/group/<?= htmlspecialchars($trophyGroup->getIconPath(), ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlentities($trophyGroup->getName()); ?>">
                                            </div>
                                            
                                            <div>
                                                <b><?= htmlentities($trophyGroup->getName()); ?></b><br>
                                                <?= nl2br(htmlentities($trophyGroup->getDetail(), ENT_QUOTES, 'UTF-8')); ?>
                                            </div>

                                            <div class="ms-auto">
                                                <?php
                                                if ($trophyGroupPlayer !== null) {
                                                    $bronzeEarned = $trophyGroupPlayer->getBronzeCount();
                                                    $silverEarned = $trophyGroupPlayer->getSilverCount();
                                                    $goldEarned = $trophyGroupPlayer->getGoldCount();
                                                    $platinumEarned = $trophyGroupPlayer->getPlatinumCount();
                                                    $progress = $trophyGroupPlayer->getProgress();
                                                    if ($trophyGroup->isDefaultGroup()) {
                                                        ?>
                                                        <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $platinumEarned; ?>/<?= $trophyGroup->getPlatinumCount(); ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $goldEarned; ?>/<?= $trophyGroup->getGoldCount(); ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $silverEarned; ?>/<?= $trophyGroup->getSilverCount(); ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $bronzeEarned; ?>/<?= $trophyGroup->getBronzeCount(); ?></span>
                                                        <?php
                                                    } else {
                                                        ?>
                                                        <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $goldEarned; ?>/<?= $trophyGroup->getGoldCount(); ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $silverEarned; ?>/<?= $trophyGroup->getSilverCount(); ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $bronzeEarned; ?>/<?= $trophyGroup->getBronzeCount(); ?></span>
                                                        <?php
                                                    }
                                                    ?>
                                                    <div>
                                                        <div class="progress mt-1" role="progressbar" aria-label="Player trophy progress" aria-valuenow="<?= $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                                            <div class="progress-bar" style="width: <?= $progress; ?>%"><?= $progress; ?>%</div>
                                                        </div>
                                                    </div>
                                                    <?php
                                                } else {
                                                    if ($trophyGroup->isDefaultGroup()) {
                                                        ?>
                                                        <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $trophyGroup->getPlatinumCount(); ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $trophyGroup->getGoldCount(); ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $trophyGroup->getSilverCount(); ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $trophyGroup->getBronzeCount(); ?></span>
                                                        <?php
                                                    } else {
                                                        ?>
                                                        <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $trophyGroup->getGoldCount(); ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $trophyGroup->getSilverCount(); ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $trophyGroup->getBronzeCount(); ?></span>
                                                        <?php
                                                    }
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </th>
                                </tr>

                                <tr>
                                    <th scope="col" class="align-middle">Trophy</th>
                                    <th scope="col" class="w-auto align-middle">Details</th>
                                    <th scope="col" class="text-center align-middle" style="width: 8rem;">
                                        <?php
                                        if ($accountId !== null && isset($player)) {
                                            ?>
                                            Earned On
                                            <?php
                                        }
                                        ?>
                                    </th>
                                    <th scope="col" class="text-center align-middle" style="width: 6rem;">Rarity<br>(Meta)</th>
                                    <th scope="col" class="text-center align-middle" style="width: 6rem;">Rarity<br>(In-Game)</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php
                                $trophyRows = $gamePage->getTrophyRows($trophyGroupId);

                                foreach ($trophyRows as $trophyRow) {
                                    if (!$gameTrophyFilter->shouldDisplayTrophy($trophyRow)) {
                                        continue;
                                    }

                                    $rowAttributes = $trophyRow->getRowAttributes($accountId);
                                    $trophyLink = $trophyRow->getTrophyLink(isset($player) ? (string) $player : null);
                                    $trophyTypeColor = $trophyRow->getTypeColor();

                                    $earnedCellStyles = [];
                                    $earnedCellClasses = [];

                                    if (preg_match('/^#([0-9a-fA-F]{6})$/', $trophyTypeColor, $matches)) {
                                        $trophyTypeColor = '#' . $matches[1];

                                        if ($accountId !== null && $trophyRow->isEarned()) {
                                            $trophyTypeColor .= '60';
                                        }
                                    }

                                    $earnedCellClasses[] = 'trophy-earned-cell';
                                    $earnedCellStyles[] = '--trophy-earned-color: ' . $trophyTypeColor;

                                    $earnedCellStyle = implode('; ', $earnedCellStyles);
                                    $earnedCellClass = implode(' ', $earnedCellClasses);
                                    ?>
                                    <tr scope="row"<?= $rowAttributes; ?>>
                                        <td style="width: 5rem;">
                                            <div>
                                                <img
                                                    class="card-img object-fit-scale"
                                                    style="height: 5rem;"
                                                    src="/img/trophy/<?= htmlspecialchars($trophyRow->getIconPath(), ENT_QUOTES, 'UTF-8'); ?>"
                                                    alt="<?= htmlentities($trophyRow->getName()); ?>"
                                                >
                                            </div>
                                        </td>

                                        <td class="w-auto">
                                            <div class="vstack">
                                                <span>
                                                    <a
                                                        class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover"
                                                        href="<?= htmlspecialchars($trophyLink, ENT_QUOTES, 'UTF-8'); ?>"
                                                    >
                                                        <b><?= htmlentities($trophyRow->getName()); ?></b>
                                                    </a>
                                                </span>
                                                <?= nl2br(htmlentities($trophyRow->getDetail(), ENT_QUOTES, 'UTF-8')); ?>
                                                <?php
                                                $progressDisplay = $trophyRow->getProgressDisplay();
                                                if ($progressDisplay !== null) {
                                                    echo '<span>' . htmlspecialchars($progressDisplay, ENT_QUOTES, 'UTF-8') . '</span>';
                                                }

                                                if ($trophyRow->hasReward()) {
                                                    $rewardName = htmlspecialchars((string) $trophyRow->getRewardName(), ENT_QUOTES, 'UTF-8');
                                                    $rewardImageUrl = htmlspecialchars((string) $trophyRow->getRewardImageUrl(), ENT_QUOTES, 'UTF-8');
                                                    echo "<span>Reward: <a class='link-underline link-underline-opacity-0 link-underline-opacity-100-hover' href='/img/reward/{$rewardImageUrl}'>"
                                                        . $rewardName
                                                        . '</a></span>';
                                                }
                                                ?>
                                            </div>
                                        </td>

                                        <td class="w-auto text-center align-middle<?= $earnedCellClass !== '' ? ' ' . $earnedCellClass : ''; ?>"<?= $earnedCellStyle !== '' ? ' style="' . htmlspecialchars($earnedCellStyle, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
                                            <?php
                                            if ($accountId !== null && $trophyRow->isEarned()) {
                                                $earnedElementId = $trophyRow->getEarnedElementId();
                                                ?>
                                                <span id="<?= htmlspecialchars($earnedElementId, ENT_QUOTES, 'UTF-8'); ?>" style="text-wrap: nowrap;"></span>
                                                <script>
                                                    <?php if ($trophyRow->hasRecordedEarnedDate()) { ?>
                                                        document.getElementById(<?= json_encode($earnedElementId); ?>).innerHTML = new Date(<?= json_encode($trophyRow->getEarnedDate() . ' UTC'); ?>).toLocaleString('sv-SE').replace(' ', '<br>');
                                                    <?php } elseif ($trophyRow->shouldDisplayNoTimestampMessage()) { ?>
                                                        document.getElementById(<?= json_encode($earnedElementId); ?>).innerHTML = 'No Timestamp';
                                                    <?php } ?>
                                                </script>
                                                <?php
                                                if (
                                                    $sort == "date"
                                                    && $previousTimeStamp !== null
                                                    && $trophyRow->hasRecordedEarnedDate()
                                                ) {
                                                    echo "<br>";
                                                    $datetime1 = date_create($previousTimeStamp);
                                                    $datetime2 = date_create($trophyRow->getEarnedDate());
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
                                                    $previousTimeStamp = $trophyRow->hasRecordedEarnedDate()
                                                        ? $trophyRow->getEarnedDate()
                                                        : null;
                                                }
                                            }
                                            ?>
                                        </td>

                                        <td style="width: 5rem;" class="text-center align-middle">
                                            <?php
                                            $metaRarity = $trophyRarityFormatter->formatMeta(
                                                $trophyRow->getRarityPercent(),
                                                $trophyRow->getStatus()
                                            );

                                            $metaContent = $metaRarity->isUnobtainable()
                                                ? $metaRarity->renderSpan('<br>', true)
                                                : $metaRarity->renderSpan();
                                            ?>
                                            <div class="vstack gap-2">
                                                <div><?= $metaContent; ?></div>
                                            </div>
                                        </td>

                                        <td style="width: 5rem;" class="text-center align-middle">
                                            <?php
                                            $inGameRarity = $trophyRarityFormatter->formatInGame(
                                                $trophyRow->getInGameRarityPercent(),
                                                $trophyRow->getStatus()
                                            );

                                            $inGameContent = $inGameRarity->isUnobtainable()
                                                ? $inGameRarity->renderSpan('<br>', true)
                                                : $inGameRarity->renderSpan();
                                            ?>
                                            <div class="vstack gap-2">
                                                <div><?= $inGameContent; ?></div>
                                            </div>
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
