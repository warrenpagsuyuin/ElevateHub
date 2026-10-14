<div class="leader-assignment-status" data-leader-assignment-status role="status" aria-live="polite" hidden></div>
<div class="leader-modal-backdrop" data-leader-modal aria-hidden="true">
    <section class="leader-modal" role="dialog" aria-modal="true" aria-labelledby="leader-modal-title" tabindex="-1">
        <button type="button" class="leader-modal-close" data-modal-close aria-label="Close dialog">&times;</button>
        <h2 id="leader-modal-title">Assign DGroup Leader</h2>
        <p>Choose the DGroup Leader who will handle this seeker.</p>
        <p class="leader-modal-name">Seeker: <strong data-modal-seeker></strong></p>
        <div class="leader-error" data-leader-error role="alert" aria-live="assertive"></div>
        <form method="POST" data-leader-form>
            @csrf
            @method('PATCH')
            <label>DGroup Leader
                <select name="leader_id" required data-leader-select>
                    <option value="">Search or select a DGroup Leader</option>
                </select>
            </label>
            <div class="leader-modal-actions">
                <button type="button" class="leader-cancel" data-modal-close>Cancel</button>
                <button type="submit" class="leader-submit">Save Assignment</button>
            </div>
        </form>
    </section>
</div>

<script>
(() => {
    const backdrop = document.querySelector('[data-leader-modal]');
    const modal = backdrop?.querySelector('[role="dialog"]');
    const form = backdrop?.querySelector('[data-leader-form]');
    const select = backdrop?.querySelector('[data-leader-select]');
    const error = backdrop?.querySelector('[data-leader-error]');
    const saveButton = backdrop?.querySelector('.leader-submit');
    const seekerName = backdrop?.querySelector('[data-modal-seeker]');
    const success = document.querySelector('[data-leader-assignment-status]');
    let activeTrigger = null;

    const showError = (message) => {
        error.textContent = message;
        error.classList.add('open');
    };

    const closeModal = (restoreFocus = true) => {
        backdrop.classList.remove('open');
        backdrop.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (restoreFocus && activeTrigger?.isConnected) activeTrigger.focus();
    };

    document.querySelectorAll('[data-open-leader-modal]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            activeTrigger = trigger;
            form.reset();
            form.action = trigger.dataset.action;
            seekerName.textContent = trigger.dataset.seekerName || '';
            error.textContent = '';
            error.classList.remove('open');
            success.hidden = true;
            const leaders = JSON.parse(trigger.dataset.leaderOptions || '{}');
            Object.entries(leaders).forEach(([id, name]) => {
                const option = document.createElement('option');
                option.value = id;
                option.textContent = name;
                select.appendChild(option);
            });
            select.value = trigger.dataset.leaderId || '';
            backdrop.classList.add('open');
            backdrop.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            select.focus();
        });
    });

    backdrop?.querySelectorAll('[data-modal-close]').forEach((button) => button.addEventListener('click', () => closeModal()));
    backdrop?.addEventListener('click', (event) => {
        if (event.target === backdrop) closeModal();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && backdrop.classList.contains('open')) closeModal();
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        error.textContent = '';
        error.classList.remove('open');
        saveButton.disabled = true;
        saveButton.textContent = 'Saving assignment...';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            const payload = response.headers.get('content-type')?.includes('application/json')
                ? await response.json()
                : {};

            if (!response.ok) {
                throw new Error(payload.errors?.leader_id?.[0] || payload.message || `Assignment failed (${response.status}). Please try again.`);
            }

            const leader = payload.leader;
            if (activeTrigger && leader) {
                activeTrigger.classList.remove('leader-button');
                activeTrigger.classList.add('leader-assigned');
                activeTrigger.dataset.leader = leader.name;
                activeTrigger.dataset.leaderId = String(leader.id);
                activeTrigger.setAttribute('aria-label', `Change DGroup Leader for ${activeTrigger.dataset.seekerName}`);
                activeTrigger.replaceChildren(document.createTextNode(leader.name));
                const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                icon.setAttribute('class', 'leader-edit-icon');
                icon.setAttribute('viewBox', '0 0 24 24');
                icon.setAttribute('fill', 'none');
                icon.setAttribute('stroke', 'currentColor');
                icon.setAttribute('stroke-width', '2');
                icon.setAttribute('stroke-linecap', 'round');
                icon.setAttribute('stroke-linejoin', 'round');
                icon.setAttribute('aria-hidden', 'true');
                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('d', 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z');
                icon.appendChild(path);
                activeTrigger.appendChild(icon);

                const row = activeTrigger.closest('tr');
                const statusSelect = row?.querySelector('[data-status-select]');
                if (statusSelect) {
                    statusSelect.dataset.hasLeader = 'true';
                    statusSelect.title = '';
                    statusSelect.querySelectorAll('option').forEach((option) => {
                        if (option.value !== 'Pending') option.disabled = false;
                    });
                    const helper = row.querySelector('[data-status-leader-help]');
                    if (helper) {
                        helper.classList.add('is-hidden');
                        helper.setAttribute('aria-hidden', 'true');
                        statusSelect.removeAttribute('aria-describedby');
                    }
                }
                row?.querySelectorAll('form input[name="status"]').forEach((input) => {
                    if (input.value !== 'Pending') {
                        const button = input.form?.querySelector('button[type="submit"]');
                        if (button) {
                            button.disabled = false;
                            button.removeAttribute('title');
                        }
                    }
                });
            }

            success.textContent = payload.message || 'DGroup Leader assigned successfully.';
            success.hidden = false;
            closeModal();
        } catch (requestError) {
            showError(requestError.message || 'Assignment failed. Please try again.');
        } finally {
            saveButton.disabled = false;
            saveButton.textContent = 'Save Assignment';
        }
    });
})();
</script>
