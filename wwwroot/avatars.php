<?php
$title = "Avatars ~ PSN 100%";
require_once("header.php");

$query = $database->prepare("SELECT COUNT(DISTINCT avatar_url) FROM player p WHERE p.status = 0 AND (p.rank <= 50000 OR p.rarity_rank <= 50000)");
$query->execute();
$total_pages = $query->fetchColumn();

$page = max(isset($_GET["page"]) && is_numeric($_GET["page"]) ? $_GET["page"] : 1, 1);
$limit = 48;
$offset = ($page - 1) * $limit;
?>

<main class="container">
    <div class="row">
        <div class="col-12">
            <h1>Avatars</h1>
        </div>
    </div>

    <div class="row">
        <?php
        $query = $database->prepare("SELECT Count(*) AS count, 
                    avatar_url 
            FROM   player p
            WHERE  p.status = 0 
            AND  (p.rank <= 50000 OR p.rarity_rank <= 50000)
            GROUP  BY avatar_url 
            ORDER  BY count DESC, 
                    avatar_url 
            LIMIT  :offset, :limit ");
        $query->bindParam(":offset", $offset, PDO::PARAM_INT);
        $query->bindParam(":limit", $limit, PDO::PARAM_INT);
        $query->execute();
        $avatars = $query->fetchAll();

        foreach ($avatars as $avatar) {
            ?>
            <div class="col">
                <div class="bg-body-tertiary p-3 rounded mb-3 text-center vstack gap-1">
                    <a href="/leaderboard/trophy?avatar=<?= $avatar["avatar_url"] ?>">
                        <img src="/img/avatar/<?= $avatar["avatar_url"] ?>" class="mx-auto" alt="" width="100" />
                    </a>
                    <?= $avatar["count"]; ?> <?= ($avatar["count"] > 1 ? "players" : "player"); ?>
                </div>
            </div>
            <?php
        }
        ?>
    </div>

    <div class="row">
        <div class="col-12">
            <nav aria-label="Avatars page navigation">
                <ul class="pagination justify-content-center">
                    <?php
                    if ($page > 1) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $page-1; ?>" aria-label="Previous">&lt;</a></li>
                        <?php
                    }

                    if ($page > 3) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=1">1</a></li>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
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
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <li class="page-item"><a class="page-link" href="?page=<?= ceil($total_pages / $limit); ?>"><?= ceil($total_pages / $limit); ?></a></li>
                        <?php
                    }

                    if ($page < ceil($total_pages / $limit)) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $page+1; ?>" aria-label="Next">&gt;</a></li>
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
