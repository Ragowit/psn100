class LogEntriesBulkActionManager {
    constructor({
        formId = 'log-entries-form',
        selectAllCheckboxId = 'select-all-log-entries',
        deleteButtonId = 'delete-selected-button',
        checkboxSelector = '.js-log-checkbox',
        deleteButtonSelector = '.js-log-delete-button',
    } = {}) {
        this.formId = formId;
        this.selectAllCheckboxId = selectAllCheckboxId;
        this.deleteButtonId = deleteButtonId;
        this.checkboxSelector = checkboxSelector;
        this.deleteButtonSelector = deleteButtonSelector;
        this.form = null;
        this.selectAllCheckbox = null;
        this.deleteSelectedButton = null;
        this.logCheckboxes = [];
        this.lastChangedCheckbox = null;
    }

    initialize() {
        this.form = document.getElementById(this.formId);

        if (!(this.form instanceof HTMLFormElement)) {
            return;
        }

        this.selectAllCheckbox = this.resolveCheckbox(this.selectAllCheckboxId);
        this.deleteSelectedButton = this.resolveButton(this.deleteButtonId);
        this.logCheckboxes = this.resolveLogCheckboxes();

        this.bindEvents();
        this.updateBulkDeleteState();
        this.bindSingleDeleteButtons();
    }

    resolveCheckbox(id) {
        const element = document.getElementById(id);

        return element instanceof HTMLInputElement ? element : null;
    }

    resolveButton(id) {
        const element = document.getElementById(id);

        return element instanceof HTMLButtonElement ? element : null;
    }

    resolveLogCheckboxes() {
        if (!(this.form instanceof HTMLFormElement)) {
            return [];
        }

        return Array.from(this.form.querySelectorAll(this.checkboxSelector))
            .filter((element) => element instanceof HTMLInputElement);
    }

    bindEvents() {
        if (this.selectAllCheckbox) {
            this.selectAllCheckbox.addEventListener('change', () => this.handleSelectAllChange());
        }

        this.logCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('click', (event) => this.handleCheckboxClick(event, checkbox));
            checkbox.addEventListener('change', () => this.handleCheckboxChange(checkbox));
        });

        if (this.deleteSelectedButton) {
            this.deleteSelectedButton.addEventListener('click', (event) => this.handleBulkDeleteClick(event));
        }
    }

    handleSelectAllChange() {
        const shouldCheck = this.selectAllCheckbox?.checked ?? false;

        this.logCheckboxes.forEach((checkbox) => {
            checkbox.checked = shouldCheck;
        });

        this.updateBulkDeleteState();
    }

    handleCheckboxClick(event, checkbox) {
        if (!(event instanceof MouseEvent) || !event.shiftKey) {
            return;
        }

        if (this.lastChangedCheckbox === null || this.lastChangedCheckbox === checkbox) {
            return;
        }

        const startIndex = this.logCheckboxes.indexOf(this.lastChangedCheckbox);
        const endIndex = this.logCheckboxes.indexOf(checkbox);

        if (startIndex === -1 || endIndex === -1) {
            return;
        }

        const [fromIndex, toIndex] = startIndex < endIndex
            ? [startIndex, endIndex]
            : [endIndex, startIndex];
        const shouldCheck = checkbox.checked;

        for (let index = fromIndex; index <= toIndex; index += 1) {
            this.logCheckboxes[index].checked = shouldCheck;
        }

        this.updateBulkDeleteState();
    }

    handleCheckboxChange(checkbox) {
        this.lastChangedCheckbox = checkbox;
        this.updateBulkDeleteState();
    }

    handleBulkDeleteClick(event) {
        const hasSelection = this.logCheckboxes.some((checkbox) => checkbox.checked);

        if (!hasSelection) {
            event.preventDefault();

            return;
        }

        if (!window.confirm('Are you sure you want to delete the selected log entries?')) {
            event.preventDefault();
        }
    }

    bindSingleDeleteButtons() {
        if (!(this.form instanceof HTMLFormElement)) {
            return;
        }

        const buttons = Array.from(this.form.querySelectorAll(this.deleteButtonSelector))
            .filter((element) => element instanceof HTMLElement);

        buttons.forEach((button) => {
            button.addEventListener('click', (event) => {
                if (!window.confirm('Are you sure you want to delete this log entry?')) {
                    event.preventDefault();
                }
            });
        });
    }

    updateBulkDeleteState() {
        const selectedCount = this.logCheckboxes.filter((checkbox) => checkbox.checked).length;

        if (this.deleteSelectedButton) {
            this.deleteSelectedButton.disabled = selectedCount === 0;
        }

        if (!this.selectAllCheckbox) {
            return;
        }

        if (this.logCheckboxes.length === 0) {
            this.selectAllCheckbox.checked = false;
            this.selectAllCheckbox.indeterminate = false;

            return;
        }

        if (selectedCount === 0) {
            this.selectAllCheckbox.checked = false;
            this.selectAllCheckbox.indeterminate = false;

            return;
        }

        if (selectedCount === this.logCheckboxes.length) {
            this.selectAllCheckbox.checked = true;
            this.selectAllCheckbox.indeterminate = false;

            return;
        }

        this.selectAllCheckbox.checked = false;
        this.selectAllCheckbox.indeterminate = true;
    }
}

function initializeLogEntriesBulkActions() {
    const bulkActionManager = new LogEntriesBulkActionManager();
    bulkActionManager.initialize();
}

window.LogEntriesBulkActionManager = LogEntriesBulkActionManager;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeLogEntriesBulkActions);
} else {
    initializeLogEntriesBulkActions();
}
