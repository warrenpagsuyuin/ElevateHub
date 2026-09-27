(() => {
    if (window.__elevateSidebarInitialized) {
        return;
    }

    window.__elevateSidebarInitialized = true;

    const sidebar = document.querySelector('.app-sidebar, .sidebar');
    const main = document.querySelector('.app-main, .main');

    if (!sidebar || !main) {
        return;
    }

    const root = document.documentElement;
    const body = document.body;
    const openClass = 'sidebar-open';

    if (!sidebar.id) {
        sidebar.id = 'app-sidebar';
    }

    const pageTitle = () => {
        const title = document.querySelector('.page-header h1, .header h1, .title-group h1, .page-title h1');

        return title?.textContent?.trim() || 'Elevate DGM';
    };

    const createIcon = () => {
        const icon = document.createElement('span');
        icon.className = 'mobile-menu-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.append(document.createElement('span'));

        return icon;
    };

    const ensureBackdrop = () => {
        let backdrop = document.querySelector('[data-sidebar-backdrop]');

        if (!backdrop) {
            backdrop = document.createElement('button');
            backdrop.type = 'button';
            backdrop.className = 'mobile-sidebar-backdrop';
            backdrop.dataset.sidebarBackdrop = '';
            backdrop.dataset.sidebarClose = '';
            backdrop.setAttribute('aria-label', 'Close navigation');
            body.append(backdrop);
        }

        return backdrop;
    };

    const ensureTopbar = () => {
        let button = document.querySelector('[data-sidebar-toggle]');

        if (!button) {
            const topbar = document.createElement('div');
            topbar.className = 'mobile-topbar';

            button = document.createElement('button');
            button.type = 'button';
            button.className = 'mobile-menu-button';
            button.dataset.sidebarToggle = '';
            button.setAttribute('aria-label', 'Open navigation');
            button.setAttribute('aria-controls', sidebar.id);
            button.setAttribute('aria-expanded', 'false');
            button.append(createIcon());

            const label = document.createElement('span');
            label.className = 'mobile-topbar-title';
            label.textContent = pageTitle();

            topbar.append(button, label);
            main.prepend(topbar);
        }

        return button;
    };

    const backdrop = ensureBackdrop();
    const toggle = ensureTopbar();
    const desktop = window.matchMedia('(min-width: 1200px)');
    const skipLink = document.querySelector('.skip-link');
    const drawerControls = () => [...sidebar.querySelectorAll('a[href], button, input, select, [tabindex]'), backdrop]
        .filter((element) => !element.disabled && element.tabIndex >= 0 && element.getClientRects().length > 0);
    const setOpen = (isOpen) => {
        const wasOpen = body.classList.contains(openClass);
        isOpen = isOpen && !desktop.matches;
        root.classList.toggle(openClass, isOpen);
        body.classList.toggle(openClass, isOpen);
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
        sidebar.inert = !desktop.matches && !isOpen;
        sidebar.setAttribute('aria-hidden', String(sidebar.inert));
        main.inert = isOpen;
        if (skipLink) skipLink.inert = isOpen;
        backdrop.tabIndex = isOpen ? 0 : -1;
        backdrop.setAttribute('aria-hidden', String(!isOpen));
        if (isOpen) {
            drawerControls()[0]?.focus();
        } else if (wasOpen && !desktop.matches) {
            toggle.focus();
        }
    };
    setOpen(false);

    toggle.addEventListener('click', () => {
        setOpen(!body.classList.contains(openClass));
    });

    backdrop.addEventListener('click', () => setOpen(false));

    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => setOpen(false));
    });

    document.addEventListener('keydown', (event) => {
        if (!body.classList.contains(openClass)) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);
        }
        if (event.key === 'Tab') {
            const controls = drawerControls();
            const first = controls[0];
            const last = controls.at(-1);
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        }
    });

    desktop.addEventListener('change', () => setOpen(false));
})();
