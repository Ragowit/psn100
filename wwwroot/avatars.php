<?php
require_once 'classes/AvatarService.php';

$title = "Avatars ~ PSN 100%";
$avatarService = new AvatarService($database);

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max($page, 1);
$limit = 48;

$totalAvatarCount = $avatarService->getTotalUniqueAvatarCount();
$totalPages = $totalAvatarCount > 0 ? (int) ceil($totalAvatarCount / $limit) : 0;
$avatars = $avatarService->getAvatars($page, $limit);

require_once("header.php");
?>

<main class="container">
    <div class="row">
        <div class="col-12">
            <h1>Avatars</h1>
        </div>
    </div>

    <div class="row">
        <?php
        foreach ($avatars as $avatar) {
            ?>
            <div class="col">
                <div class="bg-body-tertiary p-3 rounded mb-3 text-center vstack gap-1">
                    <a href="/leaderboard/trophy?avatar=<?= $avatar->getUrl(); ?>">
                        <img src="/img/avatar/<?= $avatar->getUrl(); ?>" class="mx-auto" alt="" width="100" />
                    </a>
                    <?= $avatar->getCount(); ?> <?= $avatar->getPlayerLabel(); ?>
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
                        <li class="page-item"><a class="page-link" href="?page=<?= $page - 1; ?>" aria-label="Previous">&lt;</a></li>
                        <?php
                    }

                    if ($page > 3) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=1">1</a></li>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <?php
                    }

                    if ($page - 2 > 0) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $page - 2; ?>"><?= $page - 2; ?></a></li>
                        <?php
                    }

                    if ($page - 1 > 0) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $page - 1; ?>"><?= $page - 1; ?></a></li>
                        <?php
                    }
                    ?>

                    <li class="page-item active" aria-current="page"><a class="page-link" href="?page=<?= $page; ?>"><?= $page; ?></a></li>

                    <?php
                    if ($page + 1 <= $totalPages) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $page + 1; ?>"><?= $page + 1; ?></a></li>
                        <?php
                    }

                    if ($page + 2 <= $totalPages) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $page + 2; ?>"><?= $page + 2; ?></a></li>
                        <?php
                    }

                    if ($page < $totalPages - 2) {
                        ?>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <li class="page-item"><a class="page-link" href="?page=<?= $totalPages; ?>"><?= $totalPages; ?></a></li>
                        <?php
                    }

                    if ($page < $totalPages) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $page + 1; ?>" aria-label="Next">&gt;</a></li>
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
