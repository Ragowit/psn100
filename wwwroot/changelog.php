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
                    <div class="col-1">
                        <time
                            class="js-localized-changelog-time"
                            datetime="<?= htmlspecialchars($presenter->getIsoTimestamp(), ENT_QUOTES, 'UTF-8'); ?>"
                        >
                            <?= $presenter->getTimeLabel(); ?>
                        </time>
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

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const pad = (value) => value.toString().padStart(2, '0');

            const formatUtcDate = (date) => `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}`;
            const formatUtcTime = (date) => `${pad(date.getUTCHours())}:${pad(date.getUTCMinutes())}:${pad(date.getUTCSeconds())}`;

            document.querySelectorAll('.js-localized-changelog-date').forEach((element) => {
                const isoString = element.getAttribute('datetime');

                if (!isoString) {
                    return;
                }

                const date = new Date(isoString);

                if (Number.isNaN(date.getTime())) {
                    return;
                }

                element.textContent = formatUtcDate(date);
            });

            document.querySelectorAll('.js-localized-changelog-time').forEach((element) => {
                const isoString = element.getAttribute('datetime');

                if (!isoString) {
                    return;
                }

                const date = new Date(isoString);

                if (Number.isNaN(date.getTime())) {
                    return;
                }

                element.textContent = formatUtcTime(date);
            });
        });
    </script>
</main>

<?php
require_once("footer.php");
?>
