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
            <?php renderPagination(
                $avatarPage->getCurrentPage(),
                $avatarPage->getLastPage(),
                static fn (int $pageNumber): array => ['page' => (string) $pageNumber],
                'Avatars page navigation'
            ); ?>
        </div>
    </div>
</main>

<?php
require_once("footer.php");
?>
