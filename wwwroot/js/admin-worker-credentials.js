class WorkerCredentialController {
    constructor() {
        this.endpoint = 'worker-credential.php';
    }

    initialize() {
        document.querySelectorAll('[data-worker-credential-toggle]').forEach((button) => {
            button.addEventListener('click', () => this.handleToggle(button));
        });
    }

    async handleToggle(button) {
        const input = this.findCredentialInput(button);

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        if (input.dataset.revealed === 'true') {
            this.hideCredential(input, button);
            return;
        }

        button.disabled = true;

        try {
            const credential = await this.fetchCredential(input);
            this.revealCredential(input, button, credential);
        } catch (error) {
            window.alert(error instanceof Error ? error.message : 'Unable to reveal credential.');
        } finally {
            button.disabled = false;
        }
    }

    findCredentialInput(button) {
        const container = button.closest('[data-worker-credential-field]');
        return container?.querySelector('[data-worker-credential-input]') ?? null;
    }

    async fetchCredential(input) {
        const workerId = (input.dataset.workerId ?? '').trim();
        const credentialField = (input.dataset.workerCredential ?? '').trim();
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        const response = await fetch(this.endpoint, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                worker_id: workerId,
                credential: credentialField,
                _csrf_token: csrfToken,
            }),
            credentials: 'same-origin',
        });

        let payload = null;

        try {
            payload = await response.json();
        } catch (error) {
            throw new Error('Unexpected response while revealing credential.');
        }

        if (!response.ok || payload?.status !== 'ok') {
            throw new Error(payload?.message ?? 'Unable to reveal credential.');
        }

        return typeof payload.credential === 'string' ? payload.credential : '';
    }

    revealCredential(input, button, credential) {
        input.value = credential;
        input.type = 'text';
        input.dataset.revealed = 'true';
        button.textContent = 'Hide';
        button.setAttribute('aria-pressed', 'true');
    }

    hideCredential(input, button) {
        input.value = '';
        input.type = 'password';
        delete input.dataset.revealed;
        button.textContent = 'Reveal';
        button.setAttribute('aria-pressed', 'false');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const controller = new WorkerCredentialController();
    controller.initialize();
});
