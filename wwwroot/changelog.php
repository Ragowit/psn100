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

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const pad = (value) => value.toString().padStart(2, '0');
            const formatLocalDateLabel = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
            const formatLocalDateKey = formatLocalDateLabel;
            const formatLocalTime = (date) => `${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;

            const parseIsoDate = (isoString) => {
                if (!isoString) {
                    return null;
                }

                const date = new Date(isoString);

                if (Number.isNaN(date.getTime())) {
                    return null;
                }

                return date;
            };

            const rowElement = document.querySelector('.bg-body-tertiary .row');

            if (!rowElement) {
                return;
            }

            const entries = Array.from(rowElement.querySelectorAll('.js-localized-changelog-time')).map((timeElement) => {
                const isoString = timeElement.getAttribute('datetime');
                const date = parseIsoDate(isoString);

                if (!date) {
                    return null;
                }

                const parentColumn = timeElement.parentElement;

                if (!parentColumn) {
                    return null;
                }

                const messageColumn = parentColumn.nextElementSibling;

                if (!messageColumn) {
                    return null;
                }

                return {
                    isoString,
                    date,
                    messageHtml: messageColumn.innerHTML,
                };
            }).filter((entry) => entry !== null);

            if (entries.length === 0) {
                Array.from(rowElement.querySelectorAll('.js-localized-changelog-date')).forEach((dateElement) => {
                    const date = parseIsoDate(dateElement.getAttribute('datetime'));

                    if (!date) {
                        return;
                    }

                    dateElement.textContent = formatLocalDateLabel(date);
                });

                return;
            }

            const dateGroups = new Map();

            entries.forEach((entry) => {
                const dateKey = formatLocalDateKey(entry.date);

                if (!dateGroups.has(dateKey)) {
                    dateGroups.set(dateKey, {
                        date: entry.date,
                        entries: [],
                    });
                }

                dateGroups.get(dateKey).entries.push(entry);
            });

            rowElement.innerHTML = '';

            dateGroups.forEach((group) => {
                const headingColumn = document.createElement('div');
                headingColumn.className = 'col-12';

                const heading = document.createElement('h2');
                const headingTime = document.createElement('time');
                headingTime.className = 'js-localized-changelog-date';
                headingTime.setAttribute('datetime', group.entries[0]?.isoString ?? group.date.toISOString());
                headingTime.textContent = formatLocalDateLabel(group.date);

                heading.appendChild(headingTime);
                headingColumn.appendChild(heading);
                rowElement.appendChild(headingColumn);

                group.entries.forEach((entry) => {
                    const timeColumn = document.createElement('div');
                    timeColumn.className = 'col-4 col-sm-2 col-md-1 text-nowrap small text-body-secondary';

                    const timeElement = document.createElement('time');
                    timeElement.className = 'js-localized-changelog-time';
                    timeElement.setAttribute('datetime', entry.isoString);
                    timeElement.textContent = formatLocalTime(entry.date);

                    timeColumn.appendChild(timeElement);
                    rowElement.appendChild(timeColumn);

                    const messageColumn = document.createElement('div');
                    messageColumn.className = 'col-8 col-sm-10 col-md-11';
                    messageColumn.innerHTML = entry.messageHtml;

                    rowElement.appendChild(messageColumn);
                });
            });
        });
    </script>
</main>

<?php
require_once("footer.php");
?>
