<?php
$title = "Trophies ~ PSN100.net";
require_once("header.php");

$url = $_SERVER["REQUEST_URI"];
$url_parts = parse_url($url);
// If URL doesn't have a query string.
if (isset($url_parts["query"])) { // Avoid 'Undefined index: query'
    parse_str($url_parts["query"], $params);
} else {
    $params = array();
}

$query = $database->prepare("SELECT COUNT(*) FROM trophy");
$query->execute();
$total_pages = $query->fetchColumn();

$page = max(isset($_GET["page"]) && is_numeric($_GET["page"]) ? $_GET["page"] : 1, 1);
$limit = 50;
$offset = ($page - 1) * $limit;
?>
<main role="main">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h1>Trophies</h1>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <table class="table table-responsive table-striped">
                    <tr>
                        <th scope="col">Game Icon</th>
                        <th scope="col">Trophy Icon</th>
                        <th scope="col" width="100%">Description</th>
                        <th scope="col">Rarity</th>
                        <th scope="col">Type</th>
                    </tr>

                    <?php
                    $trophies = $database->prepare("SELECT t.id AS trophy_id, t.type AS trophy_type, t.name AS trophy_name, t.detail AS trophy_detail, t.icon_url AS trophy_icon, t.rarity_percent, tt.id AS game_id, tt.name AS game_name, tt.icon_url AS game_icon FROM trophy t
                        JOIN trophy_title tt USING (np_communication_id)
                        ORDER BY t.id DESC
                        LIMIT :offset, :limit");
                    $trophies->bindParam(":offset", $offset, PDO::PARAM_INT);
                    $trophies->bindParam(":limit", $limit, PDO::PARAM_INT);
                    $trophies->execute();

                    while ($trophy = $trophies->fetch()) {
                        ?>
                        <tr>
                            <td scope="row">
                                <a href="/game/<?= $trophy["game_id"] ."-". slugify($trophy["game_name"]); ?>">
                                    <img src="/img/title/<?= $trophy["game_icon"]; ?>" alt="<?= $trophy["game_name"]; ?>" title="<?= $trophy["game_name"]; ?>" height="60" />
                                </a>
                            </td>
                            <td>
                                <a href="/trophy/<?= $trophy["trophy_id"] ."-". slugify($trophy["trophy_name"]); ?>">
                                    <img src="/img/trophy/<?= $trophy["trophy_icon"]; ?>" alt="<?= $trophy["trophy_name"]; ?>" title="<?= $trophy["trophy_name"]; ?>" height="60" />
                                </a>
                            </td>
                            <td>
                                <a href="/trophy/<?= $trophy["trophy_id"] ."-". slugify($trophy["trophy_name"]); ?>">
                                    <b><?= $trophy["trophy_name"]; ?></b><br>
                                </a>
                                <?= $trophy["trophy_detail"]; ?>
                            </td>
                            <td class="text-center">
                                <?= $trophy["rarity_percent"]; ?>%<br>
                                <?php
                                if ($trophy["rarity_percent"] <= 1.00) {
                                    echo "Legendary";
                                } elseif ($trophy["rarity_percent"] <= 5.00) {
                                    echo "Epic";
                                } elseif ($trophy["rarity_percent"] <= 20.00) {
                                    echo "Rare";
                                } elseif ($trophy["rarity_percent"] <= 50.00) {
                                    echo "Uncommon";
                                } else {
                                    echo "Common";
                                } ?>
                            </td>
                            <td>
                                <img src="/img/playstation/<?= $trophy["trophy_type"]; ?>.png" alt="<?= ucfirst($trophy["trophy_type"]); ?>" title="<?= ucfirst($trophy["trophy_type"]); ?>" />
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </table>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php
                        if ($page > 1) {
                            $params["page"] = $page - 1; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>">Prev</a></li>
                            <?php
                        }

                        if ($page > 3) {
                            $params["page"] = 1; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>">1</a></li>
                            <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">~</a></li>
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
                            <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">~</a></li>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>"><?= ceil($total_pages / $limit); ?></a></li>
                            <?php
                        }

                        if ($page < ceil($total_pages / $limit)) {
                            $params["page"] = $page + 1; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query($params); ?>">Next</a></li>
                            <?php
                        }
                        ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</main>
<?php
require_once("footer.php");
?>
