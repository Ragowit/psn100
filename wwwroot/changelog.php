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
                <?php $entries = $dateGroup->getEntries(); ?>
                <div class="col-12">
                    <h2>
                        <?php if ($entries !== []) { ?>
                            <?php $firstEntry = $entries[0]; ?>
                            <time
                                class="js-localized-changelog-date"
                                datetime="<?= htmlspecialchars($firstEntry->getIsoTimestamp(), ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                <?= $dateGroup->getDateLabel(); ?>
                            </time>
                        <?php } else { ?>
                            <?= $dateGroup->getDateLabel(); ?>
                        <?php } ?>
                    </h2>
                </div>

                <?php foreach ($entries as $presenter) { ?>
                    <div class="col-4 col-sm-2 col-md-1 text-nowrap small text-body-secondary">
                        <time
                            class="js-localized-changelog-time"
                            datetime="<?= htmlspecialchars($presenter->getIsoTimestamp(), ENT_QUOTES, 'UTF-8'); ?>"
                        >
                            <?= $presenter->getTimeLabel(); ?>
                        </time>
                    </div>
                    <div class="col-8 col-sm-10 col-md-11">
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

    <script src="/js/changelog-date-grouping.js" defer></script>
</main>

<?php
require_once("footer.php");
?>
