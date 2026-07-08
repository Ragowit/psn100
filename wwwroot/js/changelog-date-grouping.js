(() => {
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

    const initializeChangelogDateGrouping = () => {
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
                messageNode: messageColumn.cloneNode(true),
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

        rowElement.replaceChildren();

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
                messageColumn.replaceChildren(
                    ...Array.from(entry.messageNode.childNodes, (node) => node.cloneNode(true)),
                );

                rowElement.appendChild(messageColumn);
            });
        });
    };

    document.addEventListener('DOMContentLoaded', initializeChangelogDateGrouping);
})();
