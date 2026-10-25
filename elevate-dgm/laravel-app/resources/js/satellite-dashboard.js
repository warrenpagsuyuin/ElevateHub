(() => {
    const root = document.querySelector('[data-satellite-dashboard]');
    if (!root) return;
    const content = root.querySelector('[data-dashboard-content]');
    const controls = root.querySelector('[data-dashboard-controls]');
    const subtitle = root.querySelector('[data-dashboard-subtitle]');
    const announcer = root.querySelector('[data-period-announce]');
    const error = root.querySelector('[data-dashboard-error]');
    const names = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const preserveFilters = root.classList.contains('admin-satellites-period');
    let pending = false, timer, stopped = false, controller, resumeRequested = false, navController = null, failedNavigation = null;

    const getForm = () => controls?.querySelector('[data-period-form]');

    const applyControlState = (form, month, year, animateEnter = false) => {
        if (!form) return;
        const isLive = month === 'Live';
        form.dataset.liveMode = String(isLive);
        form.elements.month.value = month;
        const summary = form.querySelector('.month-dropdown summary');
        const textWrap = summary?.querySelector('.period-select-text');
        if (textWrap) {
            textWrap.innerHTML = `${isLive ? '<i class="live-dot" aria-hidden="true"></i>' : ''}<span data-period-label>${month}</span>`;
        }
        const currentYear = Number(form.dataset.currentYear);
        const currentMonth = Number(form.dataset.currentMonth);
        form.querySelectorAll('[data-period]').forEach((btn, index) => {
            const period = btn.dataset.period;
            const disabled = index > 0 && Number(year) === currentYear && index > currentMonth;
            btn.setAttribute('aria-selected', String(period === month));
            btn.setAttribute('aria-disabled', String(disabled));
            btn.disabled = disabled;
        });
        const yearWrap = form.querySelector('[data-year-control]');
        const yearSelect = form.querySelector('select[name=year]');
        if (yearSelect) yearSelect.value = String(year);
        root.querySelectorAll('[data-follow-period="month"]').forEach(input => { input.value = month; });
        root.querySelectorAll('[data-follow-period="year"]').forEach(input => { input.value = String(year); });
        if (yearWrap) {
            const wasHidden = yearWrap.hidden;
            yearWrap.hidden = isLive;
            if (!isLive && wasHidden && animateEnter) {
                yearWrap.dataset.yearAnimating = 'enter';
                setTimeout(() => { delete yearWrap.dataset.yearAnimating; }, 160);
            } else {
                delete yearWrap.dataset.yearAnimating;
            }
        }
        const dropdown = form.querySelector('.month-dropdown');
        if (dropdown) dropdown.open = false;
    };

    const updateSubtitleAndAnnouncement = (data, month, year) => {
        const isLive = month === 'Live';
        if (subtitle) {
            const form = getForm();
            const baseUrl = form ? form.action : window.location.pathname;
            const liveUrl = new URL(window.location.href);
            liveUrl.searchParams.set('month', 'Live');
            liveUrl.searchParams.set('year', data.year ?? year);
            liveUrl.searchParams.delete('page');
            subtitle.innerHTML = isLive
                ? `<span data-dashboard-date>${data.date ?? ''}</span> · <span data-dashboard-updated>Updated ${data.updatedAt ?? ''}</span>`
                : `Viewing ${month} ${year} · <a href="${liveUrl}" data-back-to-live>Back to Live</a>`;
        }
        if (announcer) {
            announcer.textContent = isLive
                ? 'Showing live data'
                : `Showing ${month} ${year}. Year filter available.`;
        }
    };

    const navigate = async (form, month, year, options = {}) => {
        const { pushHistory = true, preserveFocusOn = null } = options;
        const currentYear = Number(form.dataset.currentYear);
        const currentMonth = Number(form.dataset.currentMonth);
        if (month === 'Live') year = currentYear;
        else if (Number(year) === currentYear && names.indexOf(month) + 1 > currentMonth) month = names[currentMonth - 1];
        const url = new URL(form.action, window.location.origin);
        if (preserveFilters) url.search = window.location.search;
        url.searchParams.set('month', month);
        url.searchParams.set('year', year);
        if (preserveFilters) url.searchParams.delete('page');

        const wasLive = form.dataset.liveMode === 'true';
        const isLive = month === 'Live';
        applyControlState(form, month, year, wasLive && !isLive);
        if (preserveFocusOn === 'period') {
            form.querySelector('.month-dropdown summary')?.focus({ preventScroll: true });
        } else if (preserveFocusOn === 'year') {
            form.querySelector('select[name=year]')?.focus({ preventScroll: true });
        }

        clearTimeout(timer);
        controller?.abort();
        navController?.abort();
        navController = new AbortController();
        content.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: navController.signal,
            });
            if (!response.ok) throw new Error('Navigation fetch failed');
            const data = await response.json();
            if (typeof data.html !== 'string') throw new Error('Invalid dashboard response');

            const resolvedMonth = data.selectedMonth || month;
            const resolvedYear = data.selectedYear || Number(year);
            const resolvedLive = resolvedMonth === 'Live';
            const finalUrl = new URL(url);
            finalUrl.searchParams.set('month', resolvedMonth);
            finalUrl.searchParams.set('year', resolvedLive ? (data.year || currentYear) : resolvedYear);

            if (pushHistory) {
                history.pushState({ month: resolvedMonth, year: resolvedLive ? (data.year || currentYear) : resolvedYear }, '', finalUrl);
            } else {
                history.replaceState({ month: resolvedMonth, year: resolvedLive ? (data.year || currentYear) : resolvedYear }, '', finalUrl);
            }

            root.dataset.live = String(resolvedLive);
            if (data.year) root.dataset.year = data.year;
            if (data.month) root.dataset.month = data.month;
            form.dataset.currentYear = data.year ?? currentYear;
            form.dataset.currentMonth = data.month ?? currentMonth;

            applyControlState(form, resolvedMonth, resolvedLive ? (data.year || currentYear) : resolvedYear, false);
            if (controls && typeof data.controls === 'string') {
                controls.innerHTML = data.controls;
            }
            content.innerHTML = data.html;
            updateSubtitleAndAnnouncement(data, resolvedMonth, resolvedLive ? (data.year || currentYear) : resolvedYear);
            if (error) error.hidden = true;
            failedNavigation = null;

            if (preserveFocusOn === 'period') {
                form.querySelector('.month-dropdown summary')?.focus({ preventScroll: true });
            } else if (preserveFocusOn === 'year') {
                form.querySelector('select[name=year]')?.focus({ preventScroll: true });
            }
            if (resolvedLive) {
                stopped = false;
                schedule();
            }
        } catch (err) {
            if (err?.name === 'AbortError') return;
            if (preserveFilters) {
                failedNavigation = { month, year, options };
                applyControlState(form, root.dataset.live === 'true' ? 'Live' : (new URLSearchParams(location.search).get('month') || 'Live'), Number(new URLSearchParams(location.search).get('year')) || currentYear);
                if (error) error.hidden = false;
            } else window.location.assign(url);
        } finally {
            content.removeAttribute('aria-busy');
        }
    };

    root.addEventListener('click', event => {
        const option = event.target.closest('[data-period]');
        if (option && !option.disabled) {
            const form = option.closest('form');
            const chosenPeriod = option.dataset.period;
            const targetYear = chosenPeriod === 'Live'
                ? form.dataset.currentYear
                : (form.dataset.liveMode === 'true' ? form.dataset.currentYear : (form.elements.year?.value || form.dataset.currentYear));
            navigate(form, chosenPeriod, targetYear, { pushHistory: true, preserveFocusOn: 'period' });
            return;
        }
        const backToLive = event.target.closest('[data-back-to-live]');
        if (backToLive) {
            event.preventDefault();
            const form = getForm();
            if (form) navigate(form, 'Live', form.dataset.currentYear, { pushHistory: true, preserveFocusOn: 'period' });
            return;
        }
        if (event.target.closest('[data-dashboard-retry]')) {
            if (failedNavigation) {
                const attempt = failedNavigation;
                const form = getForm();
                if (form) navigate(form, attempt.month, attempt.year, attempt.options);
            } else if (root.dataset.live === 'true') {
                stopped = false;
                refresh();
            }
        }
    });

    root.addEventListener('change', event => {
        if (event.target.matches('[data-period-form] select[name=year]')) {
            const form = event.target.form;
            navigate(form, form.elements.month.value, event.target.value, { pushHistory: true, preserveFocusOn: 'year' });
        }
    });

    root.addEventListener('toggle', event => {
        if (event.target.matches('.month-dropdown')) {
            event.target.querySelector('summary').setAttribute('aria-expanded', String(event.target.open));
        }
    }, true);

    root.addEventListener('keydown', event => {
        const dropdown = event.target.closest('.month-dropdown');
        if (!dropdown) return;
        const options = [...dropdown.querySelectorAll('[data-period]:not(:disabled)')];
        const index = options.indexOf(document.activeElement);
        if (event.key === 'Escape') {
            event.preventDefault();
            dropdown.open = false;
            dropdown.querySelector('summary').focus();
            return;
        }
        if ((event.key === 'Enter' || event.key === ' ') && index >= 0) {
            event.preventDefault();
            options[index]?.click();
            return;
        }
        if (['ArrowDown','ArrowUp','Home','End'].includes(event.key)) {
            event.preventDefault();
            dropdown.open = true;
            const next = event.key === 'Home'
                ? 0
                : event.key === 'End'
                    ? options.length - 1
                    : index < 0
                        ? Math.max(0, options.findIndex(option => option.getAttribute('aria-selected') === 'true'))
                        : (index + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options[next]?.focus();
        }
    });

    document.addEventListener('click', event => {
        root.querySelectorAll('.month-dropdown[open]').forEach(dropdown => {
            if (!dropdown.contains(event.target)) dropdown.open = false;
        });
    });

    const syncControlsFromUrl = (triggerFetch = false) => {
        const form = getForm();
        if (!form) return;
        const params = new URLSearchParams(window.location.search);
        const rawMonth = params.get('month');
        const rawYear = params.get('year');
        const currentYear = Number(form.dataset.currentYear);
        const normalizedMonth = rawMonth ? rawMonth[0].toUpperCase() + rawMonth.slice(1).toLowerCase() : null;
        const availableYears = Array.from(form.elements.year.options, option => Number(option.value));
        const validYear = rawYear && /^\d{4}$/.test(rawYear) && availableYears.includes(Number(rawYear));
        let month = normalizedMonth && names.includes(normalizedMonth) && validYear ? normalizedMonth : 'Live';
        const year = month === 'Live' ? currentYear : Number(rawYear);
        if (month !== 'Live' && year === currentYear && names.indexOf(month) + 1 > Number(form.dataset.currentMonth)) {
            month = names[Number(form.dataset.currentMonth) - 1];
        }
        if (month === 'Live' && rawYear && Number(rawYear) !== currentYear) {
            const normalizedUrl = new URL(window.location.href);
            normalizedUrl.searchParams.set('month', 'Live');
            normalizedUrl.searchParams.set('year', String(currentYear));
            history.replaceState(history.state, '', normalizedUrl);
        }
        if (triggerFetch) {
            navigate(form, month, year, { pushHistory: false });
        } else {
            applyControlState(form, month, year, false);
        }
    };

    window.addEventListener('popstate', () => {
        syncControlsFromUrl(true);
    });

    window.addEventListener('pageshow', event => {
        syncControlsFromUrl(false);
        setTimeout(() => syncControlsFromUrl(false), 0);
        if (event.persisted && root.dataset.live === 'true') {
            stopped = false;
            refresh();
        }
    });

    syncControlsFromUrl(false);

    // Retain the Live refresh cadence (30s on satellites, 60s on admin dashboard).
    const refreshInterval = Number(root.dataset.refreshInterval) || 60000;
    const schedule = () => {
        clearTimeout(timer);
        if (!document.hidden && !stopped && root.dataset.live === 'true') {
            timer = setTimeout(refresh, refreshInterval);
        }
    };

    async function refresh() {
        if (pending || stopped || document.hidden || root.dataset.live !== 'true') return;
        pending = true;
        content.setAttribute('aria-busy', 'true');
        controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const url = new URL(window.location.href);
            url.searchParams.set('month', 'Live');
            url.searchParams.set('year', root.dataset.year);
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal,
            });
            if ([401,403,404,419].includes(response.status)) stopped = true;
            if (!response.ok) throw new Error('Unable to refresh');
            const data = await response.json();
            if (typeof data.html !== 'string' || (controls && typeof data.controls !== 'string')) throw new Error('Invalid dashboard response');
            // Preserve focus during an atomic snapshot update.
            const focused = content.contains(document.activeElement) ? document.activeElement.dataset.focusKey : null;
            const openMenu = preserveFilters ? content.querySelector('.data-table-row-menu:has([data-menu-button][aria-expanded="true"]) [data-menu-button]')?.getAttribute('aria-label') : null;
            if (!content.contains(document.activeElement) || focused) {
                content.innerHTML = data.html;
                if (focused) [...content.querySelectorAll('[data-focus-key]')].find(element => element.dataset.focusKey === focused)?.focus({ preventScroll: true });
                if (openMenu) {
                    const menuButton = [...content.querySelectorAll('[data-menu-button]')].find(button => button.getAttribute('aria-label') === openMenu);
                    menuButton?.click();
                }
            }
            const dateEl = root.querySelector('[data-dashboard-date]');
            const updatedEl = root.querySelector('[data-dashboard-updated]');
            if (dateEl) dateEl.textContent = data.date;
            if (updatedEl) updatedEl.textContent = `Updated ${data.updatedAt}`;
            if (Number(root.dataset.year) !== data.year || Number(root.dataset.month) !== data.month) {
                if (controls) {
                    const controlsFocused = controls.contains(document.activeElement);
                    controls.innerHTML = data.controls;
                    if (controlsFocused) controls.querySelector('summary')?.focus({ preventScroll: true });
                }
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('month', 'Live');
                currentUrl.searchParams.set('year', data.year);
                history.replaceState(history.state, '', currentUrl);
            }
            root.dataset.year = data.year;
            root.dataset.month = data.month;
            if (error) error.hidden = true;
        } catch {
            if (!document.hidden && error) error.hidden = false;
        } finally {
            clearTimeout(timeout);
            pending = false;
            content.removeAttribute('aria-busy');
            if (resumeRequested && !document.hidden) {
                resumeRequested = false;
                refresh();
            } else {
                schedule();
            }
        }
    }

    document.addEventListener('visibilitychange', () => {
        clearTimeout(timer);
        if (document.hidden) controller?.abort();
        else if (root.dataset.live === 'true') {
            if (pending) resumeRequested = true;
            else refresh();
        }
    });
    window.addEventListener('online', () => { if (root.dataset.live === 'true') refresh(); });
    window.addEventListener('pagehide', () => { clearTimeout(timer); stopped = true; controller?.abort(); navController?.abort(); });
    if (root.dataset.live === 'true') schedule();
})();
