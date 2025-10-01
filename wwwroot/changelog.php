<?php
declare(strict_types=1);

require_once 'classes/ChangelogPage.php';

$changelogPage = ChangelogPage::create($database, $utility, $_GET ?? []);
$dateGroups = $changelogPage->getDateGroups();

$title = $changelogPage->getTitle();

require_once("header.php");
?>

<main class="container">
    <div class="row">
        <div class="col-12">
            <h1>Changelog</h1>
        </div>
    </div>

    <div class="bg-body-tertiary p-3 rounded">
        <div class="row">
            <?php foreach ($dateGroups as $dateGroup) { ?>
                <div class="col-12">
                    <h2><?= $dateGroup->getDateLabel(); ?></h2>
                </div>

                <?php foreach ($dateGroup->getEntries() as $presenter) { ?>
                    <div class="col-1">
                        <?= $presenter->getTimeLabel(); ?>
                    </div>
                    <div class="col-11">
                        <?= $presenter->getMessage(); ?>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <p class="text-center">
                <?= $changelogPage->getRangeStart(); ?>-<?= $changelogPage->getRangeEnd(); ?> of <?= number_format($changelogPage->getTotalCount()); ?>
            </p>
        </div>
        <div class="col-12">
            <?= $paginationRenderer->render(
                $changelogPage->getCurrentPage(),
                $changelogPage->getLastPageNumber(),
                static fn (int $pageNumber): array => ['page' => (string) $pageNumber],
                'Changelog page navigation'
            ); ?>
        </div>
    </div>
</main>

<?php
require_once("footer.php");
?>
