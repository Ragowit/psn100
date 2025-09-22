<?php
declare(strict_types=1);

require_once 'classes/ChangelogEntry.php';
require_once 'classes/ChangelogPaginator.php';
require_once 'classes/ChangelogService.php';

$title = "Changelog ~ PSN 100%";

$changelogService = new ChangelogService($database);
$requestedPage = isset($_GET['page']) && is_numeric((string) $_GET['page']) ? (int) $_GET['page'] : 1;
$totalChanges = $changelogService->getTotalChangeCount();
$paginator = new ChangelogPaginator($requestedPage, $totalChanges, ChangelogService::PAGE_SIZE);
$changes = $changelogService->getChanges($paginator);

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
            /** @var ChangelogEntry $change */
            foreach ($changes as $change) {
                $time = $change->getTime();
                $changeDate = $time->format('Y-m-d');

                if ($currentDate !== $changeDate) {
                    ?>
                    <div class="col-12">
                        <h2><?= $changeDate; ?></h2>
                    </div>
                    <?php
                    $currentDate = $changeDate;
                }

                $param1Platforms = $change->getParam1Platforms();
                $param1PlatformBadges = '';

                if ($param1Platforms !== []) {
                    $param1PlatformBadges = implode(
                        ' ',
                        array_map(
                            static fn(string $platform): string => '<span class="badge rounded-pill text-bg-primary">' . htmlentities($platform, ENT_QUOTES, 'UTF-8') . '</span>',
                            $param1Platforms
                        )
                    );
                }

                $param2Platforms = $change->getParam2Platforms();
                $param2PlatformBadges = '';

                if ($param2Platforms !== []) {
                    $param2PlatformBadges = implode(
                        ' ',
                        array_map(
                            static fn(string $platform): string => '<span class="badge rounded-pill text-bg-primary">' . htmlentities($platform, ENT_QUOTES, 'UTF-8') . '</span>',
                            $param2Platforms
                        )
                    );
                }

                $param1Region = $change->getParam1Region();
                $param1RegionBadge = $param1Region !== null && $param1Region !== ''
                    ? ' <span class="badge rounded-pill text-bg-primary">' . htmlentities($param1Region, ENT_QUOTES, 'UTF-8') . '</span>'
                    : '';

                $param2Region = $change->getParam2Region();
                $param2RegionBadge = $param2Region !== null && $param2Region !== ''
                    ? ' <span class="badge rounded-pill text-bg-primary">' . htmlentities($param2Region, ENT_QUOTES, 'UTF-8') . '</span>'
                    : '';
                ?>
                <div class="col-1">
                    <?= $time->format('H:i:s'); ?>
                </div>
                <div class="col-11">
                    <?php
                    $param1Id = (string) ($change->getParam1Id() ?? '');
                    $param1Name = $change->getParam1Name() ?? '';
                    $param1Url = '/game/' . $param1Id . '-' . $utility->slugify((string) $param1Name);

                    $param2Id = (string) ($change->getParam2Id() ?? '');
                    $param2Name = $change->getParam2Name() ?? '';
                    $param2Url = '/game/' . $param2Id . '-' . $utility->slugify((string) $param2Name);

                    switch ($change->getChangeType()) {
                        case ChangelogEntry::TYPE_GAME_CLONE:
                            ?>
                            <a href="<?= $param1Url; ?>"><?= htmlentities($param1Name, ENT_QUOTES, 'UTF-8'); ?></a> (<?= $param1PlatformBadges; ?>) was cloned: <a href="<?= $param2Url; ?>"><?= htmlentities($param2Name, ENT_QUOTES, 'UTF-8'); ?></a> (<?= $param2PlatformBadges; ?>)
                            <?php
                            break;

                        case ChangelogEntry::TYPE_GAME_COPY:
                            ?>
                            Copied trophy data from <a href="<?= $param1Url; ?>"><?= htmlentities($param1Name, ENT_QUOTES, 'UTF-8'); ?></a><?= $param1RegionBadge; ?> (<?= $param1PlatformBadges; ?>) into <a href="<?= $param2Url; ?>"><?= htmlentities($param2Name, ENT_QUOTES, 'UTF-8'); ?></a> (<?= $param2PlatformBadges; ?>).
                            <?php
                            break;

                        case ChangelogEntry::TYPE_GAME_DELETE:
                            ?>
                            The merged game entry for '<?= htmlentities($change->getExtra() ?? '', ENT_QUOTES, 'UTF-8'); ?>' have been deleted.
                            <?php
                            break;

                        case ChangelogEntry::TYPE_GAME_DELISTED:
                            ?>
                            <a href="<?= $param1Url; ?>"><?= htmlentities($param1Name, ENT_QUOTES, 'UTF-8'); ?></a> (<?= $param1PlatformBadges; ?>) status was set to delisted.
                            <?php
                            break;

                        case ChangelogEntry::TYPE_GAME_DELISTED_AND_OBSOLETE:
                            ?>
                            <a href="<?= $param1Url; ?>"><?= htmlentities($param1Name, ENT_QUOTES, 'UTF-8'); ?></a> (<?= $param1PlatformBadges; ?>) status was set to delisted &amp; obsolete.
                            <?php
                            break;

                        case ChangelogEntry::TYPE_GAME_MERGE:
                            ?>
                            <a href="<?= $param1Url; ?>"><?= htmlentities($param1Name, ENT_QUOTES, 'UTF-8'); ?></a><?= $param1RegionBadge; ?> (<?= $param1PlatformBadges; ?>) was merged into <a href="<?= $param2Url; ?>"><?= htmlentities($param2Name, ENT_QUOTES, 'UTF-8'); ?></a> (<?= $param2PlatformBadges; ?>)
                            <?php
                            break;

                        case ChangelogEntry::TYPE_GAME_NORMAL:
                            ?>
                            <a href="<?= $param1Url; ?>"><?= htmlentities($param1Name, ENT_QUOTES, 'UTF-8'); ?></a> (<?= $param1PlatformBadges; ?>) status was set to normal.
                            <?php
                            break;

                        case ChangelogEntry::TYPE_GAME_OBSOLETE:
                            ?>
                            <a href="<?= $param1Url; ?>"><?= htmlentities($param1Name, ENT_QUOTES, 'UTF-8'); ?></a> (<?= $param1PlatformBadges; ?>) status was set to obsolete.
                            <?php
                            break;

                        case ChangelogEntry::TYPE_GAME_OBTAINABLE:
                            ?>
                            Trophies have been tagged as obtainable for <a href="<?= $param1Url; ?>"><?= htmlentities($param1Name, ENT_QUOTES, 'UTF-8'); ?></a><?= $param1RegionBadge; ?> (<?= $param1PlatformBadges; ?>).
                            <?php
                            break;

                        case ChangelogEntry::TYPE_GAME_RESCAN:
                            ?>
                            The game <a href="<?= $param1Url; ?>"><?= htmlentities($param1Name, ENT_QUOTES, 'UTF-8'); ?></a><?= $param1RegionBadge; ?> (<?= $param1PlatformBadges; ?>) have been rescanned for updated/new trophy data and game details.
                            <?php
                            break;

                        case ChangelogEntry::TYPE_GAME_RESET:
                            ?>
                            Merged trophies have been reset for <a href="<?= $param1Url; ?>"><?= htmlentities($param1Name, ENT_QUOTES, 'UTF-8'); ?></a> (<?= $param1PlatformBadges; ?>).
                            <?php
                            break;

                        case ChangelogEntry::TYPE_GAME_UNOBTAINABLE:
                            ?>
                            Trophies have been tagged as unobtainable for <a href="<?= $param1Url; ?>"><?= htmlentities($param1Name, ENT_QUOTES, 'UTF-8'); ?></a><?= $param1RegionBadge; ?> (<?= $param1PlatformBadges; ?>).
                            <?php
                            break;

                        case ChangelogEntry::TYPE_GAME_UPDATE:
                            ?>
                            <a href="<?= $param1Url; ?>"><?= htmlentities($param1Name, ENT_QUOTES, 'UTF-8'); ?></a><?= $param1RegionBadge; ?> (<?= $param1PlatformBadges; ?>) was updated.
                            <?php
                            break;

                        case ChangelogEntry::TYPE_GAME_VERSION:
                            ?>
                            <a href="<?= $param1Url; ?>"><?= htmlentities($param1Name, ENT_QUOTES, 'UTF-8'); ?></a><?= $param1RegionBadge; ?> (<?= $param1PlatformBadges; ?>) has a new version.
                            <?php
                            break;

                        default:
                            ?>
                            Unknown type: <?= htmlentities($change->getChangeType(), ENT_QUOTES, 'UTF-8'); ?>
                            <?php
                            break;
                    }
                    ?>
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
