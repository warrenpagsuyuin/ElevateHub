(() => {
    const dialog = document.querySelector('[data-seeker-status-dialog]');
    const notice = document.querySelector('[data-seeker-status-notice]');
    let busy = false;
    const announce = (message, error = false, row = null) => {
        notice.replaceChildren(document.createTextNode(message));
        if (error && row && /Assign a DGroup Leader/.test(message)) {
            notice.append(' ');
            const assign = document.createElement('button');
            assign.type = 'button';
            assign.className = 'data-table-link-button';
            assign.textContent = 'Assign DLeader';
            assign.addEventListener('click', () => row.querySelector('[data-open-leader-modal]')?.click());
            notice.append(assign);
        }
        notice.classList.toggle('error', error);
        notice.setAttribute('role', error ? 'alert' : 'status');
        notice.hidden = false;
        if (error) notice.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    };
    const confirm = message => new Promise(resolve => {
        dialog.querySelector('[data-seeker-status-message]').textContent = message;
        dialog.returnValue = 'cancel';
        dialog.addEventListener('close', () => resolve(dialog.returnValue === 'confirm'), { once: true });
        dialog.showModal();
    });
    const submit = async (form, control, status, restore = false) => {
        const previous = control.dataset.currentStatus || control.value;
        if (busy) { if (!restore) control.value = previous; return; }
        if (!restore && status === previous) return;
        busy = true;
        const name = restore ? form.dataset.seekerName : control.dataset.seekerName;
        const message = restore ? `Restore ${name} to Pending?`
            : status === 'Successful' ? `Mark ${name} as successful and move to Members?`
            : `Mark ${name} as unsuccessful and archive?`;
        try {
            if (!(await confirm(message))) {
                if (!restore) control.value = previous;
                return;
            }
            // Capture the selected value before disabling it (disabled fields are not submitted).
            const body = new FormData(form);
            control.disabled = true;
            form.setAttribute('aria-busy', 'true');
            const response = await fetch(form.action, {
                method: 'POST', credentials: 'same-origin', body,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json().catch(() => null);
            if (!response.ok || !data?.counts) {
                const fallback = response.status === 403 ? 'You do not have permission to update this seeker.'
                    : response.status === 419 ? 'Your session expired. Refresh the page and try again.'
                    : response.status >= 500 ? 'Unable to save this change. Please try again.'
                    : 'Unable to save this change. Refresh the page and try again.';
                throw new Error(response.status < 500 && data?.errors ? Object.values(data.errors).flat().join(' ')
                    : response.status < 500 && data?.message ? data.message : fallback);
            }
            if (!restore) control.dataset.currentStatus = data.status;
            document.querySelectorAll('[data-seeker-count]').forEach(el => {
                const key = el.dataset.seekerCount;
                el.textContent = data.counts[key];
                if (key === 'pending') el.hidden = data.counts.pending === 0;
            });
            if (restore || data.archived || data.member_id) {
                const row = form.closest('tr');
                const tbody = row.parentElement;
                if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    await row.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 180 }).finished;
                }
                const columns = row.children.length;
                row.remove();
                if (!tbody.children.length) {
                    const emptyRow = document.createElement('tr');
                    const cell = document.createElement('td');
                    cell.colSpan = columns; cell.className = 'empty-state';
                    cell.append('No seekers left on this page. ');
                    const link = document.createElement('a');
                    const url = new URL(location.href); url.searchParams.delete('page');
                    link.href = url.href; link.textContent = 'Refresh list'; cell.append(link);
                    emptyRow.append(cell); tbody.append(emptyRow);
                }
                const summary = document.querySelector('#satellite-seekers-pagination .pagination-summary');
                if (summary) summary.textContent = `${data.counts.active} active seekers`;
                const nextControl = tbody.querySelector('select:not(:disabled), button, a');
                nextControl?.focus();
            }
            announce(data.message);
        } catch (error) {
            if (!restore) control.value = previous;
            announce(error.message || 'Unable to save this change. Please try again.', true, control.closest('tr'));
        } finally {
            control.disabled = false;
            form.removeAttribute('aria-busy');
            busy = false;
        }
    };
    document.addEventListener('change', event => {
        const select = event.target;
        if (!(select instanceof HTMLSelectElement) || !select.matches('[data-status-select]')) return;
        submit(select.form, select, select.value);
    });
    document.querySelectorAll('[data-seeker-restore]').forEach(form => {
        form.addEventListener('submit', event => {
            event.preventDefault();
            submit(form, form.querySelector('button'), 'Pending', true);
        });
    });
})();
