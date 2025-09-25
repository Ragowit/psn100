<?php
declare(strict_types=1);

require_once 'classes/ChangelogEntry.php';
require_once 'classes/ChangelogEntryPresenter.php';
require_once 'classes/ChangelogPaginator.php';
require_once 'classes/ChangelogService.php';

$title = "Changelog ~ PSN 100%";

$changelogService = new ChangelogService($database);
$requestedPage = isset($_GET['page']) && is_numeric((string) $_GET['page']) ? (int) $_GET['page'] : 1;
$totalChanges = $changelogService->getTotalChangeCount();
$paginator = new ChangelogPaginator($requestedPage, $totalChanges, ChangelogService::PAGE_SIZE);
$changes = $changelogService->getChanges($paginator);
$presenters = array_map(
    static fn(ChangelogEntry $change): ChangelogEntryPresenter => new ChangelogEntryPresenter($change, $utility),
    $changes
);

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
            <?php
            $currentDate = '';
            /** @var ChangelogEntryPresenter $presenter */
            foreach ($presenters as $presenter) {
                $changeDate = $presenter->getDateLabel();

                if ($currentDate !== $changeDate) {
                    ?>
                    <div class="col-12">
                        <h2><?= $changeDate; ?></h2>
                    </div>
                    <?php
                    $currentDate = $changeDate;
                }

                ?>
                <div class="col-1">
                    <?= $presenter->getTimeLabel(); ?>
                </div>
                <div class="col-11">
                    <?= $presenter->getMessage(); ?>
                </div>
                <?php
            }
            ?>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <p class="text-center">
                <?= $paginator->getRangeStart(); ?>-<?= $paginator->getRangeEnd(); ?> of <?= number_format($paginator->getTotalCount()); ?>
            </p>
        </div>
        <div class="col-12">
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php
                    $currentPage = $paginator->getCurrentPage();
                    $totalPages = $paginator->getTotalPages();
                    $lastPageNumber = $paginator->getLastPageNumber();

                    if ($paginator->hasPreviousPage()) {
                        ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $paginator->getPreviousPage(); ?>">Prev</a></li>
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
                        <li class="page-item"><a class="page-link" href="?page=<?= $paginator->getNextPage(); ?>">Next</a></li>
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
