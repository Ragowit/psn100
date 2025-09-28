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
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php
                    $currentPage = $changelogPage->getCurrentPage();
                    $totalPages = $changelogPage->getTotalPages();
                    $lastPageNumber = $changelogPage->getLastPageNumber();

                    if ($changelogPage->hasPreviousPage()) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $changelogPage->getPreviousPage(); ?>">Prev</a></li>
                        <?php
                    }

                    if ($currentPage > 3) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=1">1</a></li>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">~</a></li>
                        <?php
                    }

                    if ($currentPage - 2 > 0) {
                        $pageNumber = $currentPage - 2;
                        if ($pageNumber >= 1) {
                            ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $pageNumber; ?>"><?= $pageNumber; ?></a></li>
                            <?php
                        }
                    }

                    if ($currentPage - 1 > 0) {
                        $pageNumber = $currentPage - 1;
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $pageNumber; ?>"><?= $pageNumber; ?></a></li>
                        <?php
                    }
                    ?>
                    <li class="page-item active" aria-current="page"><a class="page-link" href="?page=<?= $currentPage; ?>"><?= $currentPage; ?></a></li>
                    <?php
                    if ($currentPage + 1 <= $lastPageNumber) {
                        $pageNumber = $currentPage + 1;
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $pageNumber; ?>"><?= $pageNumber; ?></a></li>
                        <?php
                    }

                    if ($currentPage + 2 <= $lastPageNumber) {
                        $pageNumber = $currentPage + 2;
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $pageNumber; ?>"><?= $pageNumber; ?></a></li>
                        <?php
                    }

                    if ($totalPages > 0 && $currentPage < $totalPages - 2) {
                        ?>
                        <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">~</a></li>
                        <li class="page-item"><a class="page-link" href="?page=<?= $totalPages; ?>"><?= $totalPages; ?></a></li>
                        <?php
                    }

                    if ($currentPage < $totalPages) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $changelogPage->getNextPage(); ?>">Next</a></li>
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
