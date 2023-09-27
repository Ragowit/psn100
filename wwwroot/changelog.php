<?php
$title = "Changelog ~ PSN 100%";
require_once("header.php");

$query = $database->prepare("SELECT COUNT(*) FROM psn100_change");
$query->execute();
$total_pages = $query->fetchColumn();

$page = max(isset($_GET["page"]) && is_numeric($_GET["page"]) ? $_GET["page"] : 1, 1);
$limit = 50;
$offset = ($page - 1) * $limit;
?>
<main role="main">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1>Changelog</h1>
            </div>
        </div>

        <div class="row">
            <?php
            $query = $database->prepare("SELECT
                    c.*,
                    tt1.name AS param_1_name, tt1.platform AS param_1_platform,
                    tt2.name AS param_2_name, tt2.platform AS param_2_platform
                FROM psn100_change c
                JOIN trophy_title tt1 ON tt1.id = c.param_1
                LEFT JOIN trophy_title tt2 ON tt2.id = c.param_2
                ORDER BY c.time DESC
                LIMIT  :offset, :limit ");
            $query->bindParam(":offset", $offset, PDO::PARAM_INT);
            $query->bindParam(":limit", $limit, PDO::PARAM_INT);
            $query->execute();
            $changes = $query->fetchAll();

            $date = "";
            foreach ($changes as $change) {
                $time = new DateTime($change["time"]);

                if ($date != $time->format("Y-m-d")) {
                    ?>
                    <div class="col-12">
                        <h2><?= $time->format("Y-m-d"); ?></h2>
                    </div>
                    <?php
                    $date = $time->format("Y-m-d");
                }
                ?>
                <div class="col-1">
                    <?= $time->format("H:i:s"); ?>
                </div>
                <div class="col-11">
                    <?php
                    $param_1_platforms = "";
                    foreach (explode(",", $change["param_1_platform"]) as $platform) {
                        $param_1_platforms .= "<span class=\"badge badge-pill badge-primary\">". $platform ."</span> ";
                    }
                    $param_1_platforms = trim($param_1_platforms);

                    if (!is_null($change["param_2_platform"])) {
                        $param_2_platforms = "";
                        foreach (explode(",", $change["param_2_platform"]) as $platform) {
                            $param_2_platforms .= "<span class=\"badge badge-pill badge-primary\">". $platform ."</span> ";
                        }
                        $param_2_platforms = trim($param_2_platforms);
                    }

                    switch ($change["change_type"]) {
                        case "GAME_CLONE":
                            ?>
                            <a href="/game/<?= $change["param_1"] ."-". slugify($change["param_1_name"]); ?>"><?= $change["param_1_name"]; ?></a> (<?= $param_1_platforms; ?>) was cloned: <a href="/game/<?= $change["param_2"] ."-". slugify($change["param_2_name"]); ?>"><?= $change["param_2_name"]; ?></a> (<?= $param_2_platforms; ?>)
                            <?php
                            break;
                        case "GAME_COPY":
                            ?>
                            Copied trophy data from <a href="/game/<?= $change["param_1"] ."-". slugify($change["param_1_name"]); ?>"><?= $change["param_1_name"]; ?></a> (<?= $param_1_platforms; ?>) into <a href="/game/<?= $change["param_2"] ."-". slugify($change["param_2_name"]); ?>"><?= $change["param_2_name"]; ?></a> (<?= $param_2_platforms; ?>).
                            <?php
                            break;
                        case "GAME_DELISTED":
                            ?>
                            <a href="/game/<?= $change["param_1"] ."-". slugify($change["param_1_name"]); ?>"><?= $change["param_1_name"]; ?></a> (<?= $param_1_platforms; ?>) status was set to delisted.
                            <?php
                            break;
                        case "GAME_DELISTED_AND_OBSOLETE":
                            ?>
                            <a href="/game/<?= $change["param_1"] ."-". slugify($change["param_1_name"]); ?>"><?= $change["param_1_name"]; ?></a> (<?= $param_1_platforms; ?>) status was set to delisted &amp; obsolete.
                            <?php
                            break;
                        case "GAME_MERGE":
                            ?>
                            <a href="/game/<?= $change["param_1"] ."-". slugify($change["param_1_name"]); ?>"><?= $change["param_1_name"]; ?></a> (<?= $param_1_platforms; ?>) was merged into <a href="/game/<?= $change["param_2"] ."-". slugify($change["param_2_name"]); ?>"><?= $change["param_2_name"]; ?></a> (<?= $param_2_platforms; ?>)
                            <?php
                            break;
                        case "GAME_NORMAL":
                            ?>
                            <a href="/game/<?= $change["param_1"] ."-". slugify($change["param_1_name"]); ?>"><?= $change["param_1_name"]; ?></a> (<?= $param_1_platforms; ?>) status was set to normal.
                            <?php
                            break;
                        case "GAME_OBSOLETE":
                            ?>
                            <a href="/game/<?= $change["param_1"] ."-". slugify($change["param_1_name"]); ?>"><?= $change["param_1_name"]; ?></a> (<?= $param_1_platforms; ?>) status was set to obsolete.
                            <?php
                            break;
                        case "GAME_UPDATE":
                            ?>
                            <a href="/game/<?= $change["param_1"] ."-". slugify($change["param_1_name"]); ?>"><?= $change["param_1_name"]; ?></a> (<?= $param_1_platforms; ?>) was updated.
                            <?php
                            break;
                        case "GAME_VERSION":
                            ?>
                            <a href="/game/<?= $change["param_1"] ."-". slugify($change["param_1_name"]); ?>"><?= $change["param_1_name"]; ?></a> (<?= $param_1_platforms; ?>) has a new version.
                            <?php
                            break;
                        default:
                            ?>
                            Unknown type: <?= $change["change_type"]; ?>
                            <?php
                            break;
                    }
                    ?>
                </div>
                <?php
            }
            ?>
        </div>

        <div class="row">
            <div class="col-12">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php
                        if ($page > 1) {
                            ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page-1; ?>">Prev</a></li>
                            <?php
                        }

                        if ($page > 3) {
                            ?>
                            <li class="page-item"><a class="page-link" href="?page=1">1</a></li>
                            <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">~</a></li>
                            <?php
                        }

                        if ($page-2 > 0) {
                            ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page-2; ?>"><?= $page-2; ?></a></li>
                            <?php
                        }

                        if ($page-1 > 0) {
                            ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page-1; ?>"><?= $page-1; ?></a></li>
                            <?php
                        }
                        ?>

                        <li class="page-item active" aria-current="page"><a class="page-link" href="?page=<?= $page; ?>"><?= $page; ?></a></li>

                        <?php
                        if ($page+1 < ceil($total_pages / $limit)+1) {
                            ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page+1; ?>"><?= $page+1; ?></a></li>
                            <?php
                        }

                        if ($page+2 < ceil($total_pages / $limit)+1) {
                            ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page+2; ?>"><?= $page+2; ?></a></li>
                            <?php
                        }

                        if ($page < ceil($total_pages / $limit)-2) {
                            ?>
                            <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">~</a></li>
                            <li class="page-item"><a class="page-link" href="?page=<?= ceil($total_pages / $limit); ?>"><?= ceil($total_pages / $limit); ?></a></li>
                            <?php
                        }

                        if ($page < ceil($total_pages / $limit)) {
                            ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page+1; ?>">Next</a></li>
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
