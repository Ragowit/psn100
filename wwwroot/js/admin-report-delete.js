function initializeReportDeleteForms() {
    document.querySelectorAll('.js-report-delete-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm('Are you sure you want to delete this report?')) {
                event.preventDefault();
            }
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeReportDeleteForms);
} else {
    initializeReportDeleteForms();
}
