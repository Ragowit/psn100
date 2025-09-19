<?php
$title = "Trophies ~ PSN 100%";
require_once("header.php");

$url = $_SERVER["REQUEST_URI"];
$url_parts = parse_url($url);
// If URL doesn't have a query string.
if (isset($url_parts["query"])) { // Avoid 'Undefined index: query'
    parse_str($url_parts["query"], $params);
} else {
    $params = array();
}

$query = $database->prepare("SELECT COUNT(*) FROM trophy t
    JOIN trophy_title tt USING (np_communication_id)
    WHERE t.status = 0 AND tt.status = 0");
$query->execute();
$total_pages = $query->fetchColumn();

$page = max(isset($_GET["page"]) && is_numeric($_GET["page"]) ? $_GET["page"] : 1, 1);
$limit = 50;
$offset = ($page - 1) * $limit;
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
                            $trophies = $database->prepare("SELECT
                                    t.id AS trophy_id,
                                    t.type AS trophy_type,
                                    t.name AS trophy_name,
                                    t.detail AS trophy_detail,
                                    t.icon_url AS trophy_icon,
                                    t.rarity_percent,
                                    t.progress_target_value,
                                    t.reward_name,
                                    t.reward_image_url,
                                    tt.id AS game_id,
                                    tt.name AS game_name,
                                    tt.icon_url AS game_icon,
                                    tt.platform
                                FROM
                                    trophy t
                                JOIN trophy_title tt USING(np_communication_id)
                                WHERE
                                    t.status = 0 AND tt.status = 0
                                ORDER BY
                                    t.rarity_percent DESC
                                LIMIT :offset, :limit");
                            $trophies->bindValue(":offset", $offset, PDO::PARAM_INT);
                            $trophies->bindValue(":limit", $limit, PDO::PARAM_INT);
                            $trophies->execute();

                            while ($trophy = $trophies->fetch()) {
                                ?>
                                <tr>
                                    <td scope="row" class="text-center align-middle">
                                        <a href="/game/<?= $trophy["game_id"] ."-". $utility->slugify($trophy["game_name"]); ?>">
                                            <img src="/img/title/<?= ($trophy["game_icon"] == ".png") ? ((str_contains($trophy["platform"], "PS5")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-game.png") : $trophy["game_icon"]; ?>" alt="<?= htmlentities($trophy["game_name"], ENT_QUOTES, "UTF-8"); ?>" title="<?= htmlentities($trophy["game_name"], ENT_QUOTES, "UTF-8"); ?>" style="width: 10rem;" />
                                        </a>
                                    </td>
                                    <td class="align-middle">
                                        <div class="hstack gap-3">
                                            <div class="d-flex align-items-center justify-content-center">
                                                <a href="/trophy/<?= $trophy["trophy_id"] ."-". $utility->slugify($trophy["trophy_name"]); ?>">
                                                    <img src="/img/trophy/<?= ($trophy["trophy_icon"] == ".png") ? ((str_contains($trophy["platform"], "PS5")) ? "../missing-ps5-game-and-trophy.png" : "../missing-ps4-trophy.png") : $trophy["trophy_icon"]; ?>" alt="<?= htmlentities($trophy["trophy_name"], ENT_QUOTES, "UTF-8"); ?>" title="<?= htmlentities($trophy["trophy_name"], ENT_QUOTES, "UTF-8"); ?>" style="width: 5rem;" />
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
                <?= ($total_pages == 0 ? "0" : $offset + 1); ?>-<?= min($offset + $limit, $total_pages); ?> of <?= number_format($total_pages); ?>
            </p>
        </div>
        <div class="col-12">
            <nav aria-label="Trophies page navigation">
                <ul class="pagination justify-content-center">
                    <?php
                    if ($page > 1) {
                        $params["page"] = $page - 1; ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>">&lt;</a></li>
                        <?php
                    }

                    if ($page > 3) {
                        $params["page"] = 1; ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>">1</a></li>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <?php
                    }

                    if ($page-2 > 0) {
                        $params["page"] = $page - 2; ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page-2; ?></a></li>
                        <?php
                    }

                    if ($page-1 > 0) {
                        $params["page"] = $page - 1; ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page-1; ?></a></li>
                        <?php
                    }
                    ?>

                    <?php
                    $params["page"] = $page;
                    ?>
                    <li class="page-item active" aria-current="page"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page; ?></a></li>

                    <?php
                    if ($page+1 < ceil($total_pages / $limit)+1) {
                        $params["page"] = $page + 1; ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page+1; ?></a></li>
                        <?php
                    }

                    if ($page+2 < ceil($total_pages / $limit)+1) {
                        $params["page"] = $page + 2; ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= $page+2; ?></a></li>
                        <?php
                    }

                    if ($page < ceil($total_pages / $limit)-2) {
                        $params["page"] = ceil($total_pages / $limit); ?>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= ceil($total_pages / $limit); ?></a></li>
                        <?php
                    }

                    if ($page < ceil($total_pages / $limit)) {
                        $params["page"] = $page + 1; ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>">&gt;</a></li>
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
