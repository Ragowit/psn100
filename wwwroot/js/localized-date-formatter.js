class LocalizedDateFormatter {
    constructor(selector = '.js-localized-date', options = {}) {
        this.selector = selector;
        this.locale = options.locale ?? 'sv-SE';
    }

    initialize() {
        document.querySelectorAll(this.selector).forEach((element) => {
            this.formatElement(element);
        });
    }

    formatElement(element) {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        const fallback = element.getAttribute('data-fallback');
        const timestamp = element.getAttribute('data-timestamp');
        const isoString = element.getAttribute('datetime');
        const prefix = element.getAttribute('data-prefix') ?? '';
        const lineBreak = element.getAttribute('data-line-break') === '1';
        const timeStyle = element.getAttribute('data-time-style');
        const showTimezone = element.getAttribute('data-show-timezone') === '1';

        let date = null;

        if (isoString) {
            date = new Date(isoString);
        } else if (timestamp) {
            date = new Date(`${timestamp} UTC`);
        }

        if (!date || Number.isNaN(date.getTime())) {
            if (fallback !== null) {
                element.textContent = fallback;
            }

            return;
        }

        if (showTimezone) {
            this.renderTimezoneFormat(element, date);

            return;
        }

        const formatOptions = timeStyle ? { timeStyle } : undefined;
        const formatted = date.toLocaleString(this.locale, formatOptions);

        if (lineBreak) {
            const spaceIndex = formatted.indexOf(' ');

            if (spaceIndex !== -1) {
                const datePart = formatted.slice(0, spaceIndex);
                const timePart = formatted.slice(spaceIndex + 1);
                element.replaceChildren(
                    document.createTextNode(prefix + datePart),
                    document.createElement('br'),
                    document.createTextNode(timePart),
                );

                return;
            }
        }

        element.textContent = prefix + formatted;
    }

    renderTimezoneFormat(element, date) {
        if (typeof Intl === 'undefined' || typeof Intl.DateTimeFormat !== 'function') {
            return;
        }

        const pad = (value) => value.toString().padStart(2, '0');
        let timeZone = '';

        try {
            timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone ?? '';
        } catch (error) {
            timeZone = '';
        }

        const formattedDate = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        const formattedTime = `${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
        const suffix = timeZone !== '' ? ` ${timeZone}` : '';

        element.textContent = `${formattedDate} ${formattedTime}${suffix}`;

        if (timeZone !== '') {
            element.setAttribute('data-timezone', timeZone);
        }
    }

    static initializeAll() {
        new LocalizedDateFormatter('.js-localized-date').initialize();
        new LocalizedDateFormatter('.js-localized-datetime').initialize();
        new LocalizedDateFormatter('.js-leaderboard-date').initialize();
        new LocalizedDateFormatter('.js-recent-player-date').initialize();
    }
}

window.LocalizedDateFormatter = LocalizedDateFormatter;

function initializeLocalizedDates() {
    LocalizedDateFormatter.initializeAll();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeLocalizedDates);
} else {
    initializeLocalizedDates();
}
