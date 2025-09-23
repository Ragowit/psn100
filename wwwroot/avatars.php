<?php
require_once 'classes/AvatarService.php';
require_once 'classes/AvatarPage.php';

$title = "Avatars ~ PSN 100%";
$avatarService = new AvatarService($database);
$avatarPage = AvatarPage::fromQueryParameters($avatarService, $_GET ?? []);

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
        foreach ($avatarPage->getAvatars() as $avatar) {
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
                    if ($avatarPage->hasPreviousPage()) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $avatarPage->getPreviousPage(); ?>" aria-label="Previous">&lt;</a></li>
                        <?php
                    }

                    if ($avatarPage->shouldShowFirstPage()) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $avatarPage->getFirstPage(); ?>"><?= $avatarPage->getFirstPage(); ?></a></li>
                        <?php
                    }

                    if ($avatarPage->shouldShowLeadingEllipsis()) {
                        ?>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <?php
                    }

                    foreach ($avatarPage->getPreviousPages() as $previousPage) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $previousPage; ?>"><?= $previousPage; ?></a></li>
                        <?php
                    }
                    ?>

                    <li class="page-item active" aria-current="page"><a class="page-link" href="?page=<?= $avatarPage->getCurrentPage(); ?>"><?= $avatarPage->getCurrentPage(); ?></a></li>

                    <?php
                    foreach ($avatarPage->getNextPages() as $nextPage) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $nextPage; ?>"><?= $nextPage; ?></a></li>
                        <?php
                    }

                    if ($avatarPage->shouldShowTrailingEllipsis()) {
                        ?>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                        <?php
                    }

                    if ($avatarPage->shouldShowLastPage()) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $avatarPage->getLastPage(); ?>"><?= $avatarPage->getLastPage(); ?></a></li>
                        <?php
                    }

                    if ($avatarPage->hasNextPage()) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $avatarPage->getNextPage(); ?>" aria-label="Next">&gt;</a></li>
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
