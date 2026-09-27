(() => {
    // Page scripts own opening/closing; this shared layer owns modal focus isolation.
    const dialogSelector = '[role="dialog"][aria-modal="true"]';
    const controls = 'a[href], button, input:not([type="hidden"]), select, textarea, summary, [tabindex]';
    const visible = (element) => element.getClientRects().length > 0
        && !element.closest('[hidden], [aria-hidden="true"]')
        && getComputedStyle(element).visibility !== 'hidden';
    const focusable = (dialog) => [...dialog.querySelectorAll(controls)]
        .filter((element) => !element.disabled && element.tabIndex >= 0 && !element.closest('[inert]') && visible(element));
    let activeDialog = null;
    let lastOutsideFocus = document.activeElement;
    let returnFocus = null;
    const isolated = new Map();

    const releaseBackground = () => {
        isolated.forEach((wasInert, element) => { element.inert = wasInert; });
        isolated.clear();
    };
    const isolateBackground = (dialog) => {
        for (let node = dialog; node && node !== document.body; node = node.parentElement) {
            [...node.parentElement.children].forEach((sibling) => {
                if (sibling === node || sibling.matches('script, style, link')) return;
                isolated.set(sibling, sibling.inert);
                sibling.inert = true;
            });
        }
    };
    const sync = () => {
        const dialogs = [...document.querySelectorAll(dialogSelector)].filter(visible);
        const next = dialogs.at(-1) || null;
        if (next !== activeDialog) {
            releaseBackground();
            const previousReturn = returnFocus;
            activeDialog = next;
            if (next) {
                returnFocus = lastOutsideFocus;
                next.tabIndex = -1;
                isolateBackground(next);
                if (!next.contains(document.activeElement)) (focusable(next)[0] || next).focus();
            } else {
                returnFocus = null;
                if (previousReturn?.isConnected && visible(previousReturn) && !previousReturn.closest('[inert]')) {
                    previousReturn.focus();
                }
            }
        }
        document.querySelectorAll('.table-scroll, .table-wrap, .data-table-scroll, .members-table-wrap, .seekers-table-wrap, .preview-table-wrap').forEach((region) => {
            if (!region.querySelector('table')) return;
            region.tabIndex = region.scrollWidth > region.clientWidth ? 0 : -1;
            region.setAttribute('role', 'region');
            if (!region.hasAttribute('aria-label') && !region.hasAttribute('aria-labelledby')) {
                const caption = region.querySelector('caption')?.textContent.trim();
                region.setAttribute('aria-label', caption || 'Scrollable data table');
            }
        });
    };
    document.addEventListener('focusin', (event) => {
        if (!event.target.closest(dialogSelector)) {
            if (activeDialog && visible(activeDialog)) {
                (focusable(activeDialog)[0] || activeDialog).focus();
            } else {
                lastOutsideFocus = event.target;
            }
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab' || !activeDialog || !visible(activeDialog)) return;
        const items = focusable(activeDialog);
        const first = items[0] || activeDialog;
        const last = items.at(-1) || activeDialog;
        if (!items.length || !activeDialog.contains(document.activeElement)
            || (event.shiftKey && (document.activeElement === first || document.activeElement === activeDialog))
            || (!event.shiftKey && document.activeElement === last)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            (event.shiftKey ? last : first).focus();
        }
    }, true);
    let scheduled = false;
    const schedule = () => {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(() => { scheduled = false; sync(); });
    };
    new MutationObserver(schedule).observe(document.body, {
        subtree: true, childList: true, attributes: true, attributeFilter: ['class', 'hidden', 'aria-hidden'],
    });
    window.addEventListener('resize', schedule);
    document.addEventListener('DOMContentLoaded', sync);
    sync();
})();
