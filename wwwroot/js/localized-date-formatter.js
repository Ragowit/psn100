class LocalizedDateFormatter {
    constructor(selector, locale = 'sv-SE') {
        this.selector = selector;
        this.locale = locale;
    }

    initialize() {
        const elements = document.querySelectorAll(this.selector);

        elements.forEach((element) => {
            const timestamp = element.getAttribute('data-timestamp');
            const formattedValue = this.formatTimestamp(timestamp);

            if (formattedValue !== null) {
                element.innerHTML = formattedValue;
            }
        });
    }

    formatTimestamp(timestamp) {
        if (!timestamp) {
            return null;
        }

        const date = new Date(`${timestamp} UTC`);

        if (Number.isNaN(date.getTime())) {
            return null;
        }

        return date.toLocaleString(this.locale).replace(' ', '<br>');
    }
}

window.LocalizedDateFormatter = LocalizedDateFormatter;
