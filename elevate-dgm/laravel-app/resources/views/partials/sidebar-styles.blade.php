        :root {
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 84px;
            --brand-red: #ef3b24;
            --brand-red-dark: #c92f1b;
            --brand-red-soft: #fff1ee;
            --sidebar-bg: #050505;
            --sidebar-panel: #111111;
            --sidebar-active-bg: #ffffff;
            --sidebar-active-text: #08111f;
            --surface-bg: #f3f4f6;
            --surface-card: #ffffff;
            --text-primary: #111111;
            --text-muted: #667085;
            --line: #e5e7eb;
            --border: #e5e7eb;
            --shadow-card: 0 8px 24px rgba(15, 23, 42, 0.06);
            --shadow-card-strong: 0 16px 34px rgba(17, 24, 39, 0.10);
            --page-padding: 32px;
            --font-ui: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            --z-sidebar: 50;
            --z-sidebar-backdrop: 60;
            --z-sidebar-mobile: 70;
            --z-modal-backdrop: 90;
            --z-modal: 100;
            --z-toast: 110;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            max-width: 100%;
            min-height: 100%;
            margin: 0;
            overflow-x: hidden;
            background: var(--surface-bg);
            color: var(--text-primary);
            font-family: var(--font-ui);
            font-size: 15px;
            line-height: 1.5;
        }

        html.sidebar-open,
        body.sidebar-open {
            overflow: hidden;
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        img,
        svg {
            max-width: 100%;
        }

        a {
            color: inherit;
        }

        .app-navbar {
            display: none !important;
        }

        .page,
        .app-shell {
            width: 100%;
            max-width: 100%;
            min-height: 100dvh;
            display: block;
            overflow: visible;
            background: var(--surface-bg);
            color: var(--text-primary);
            font-family: var(--font-ui);
        }

        .sidebar,
        .app-sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: var(--z-sidebar);
            width: var(--sidebar-width);
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);
            height: 100dvh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            overflow-x: hidden;
            overflow-y: hidden;
            background: var(--sidebar-bg);
            color: #ffffff;
            padding: max(20px, env(safe-area-inset-top)) max(20px, env(safe-area-inset-right)) max(20px, env(safe-area-inset-bottom)) max(20px, env(safe-area-inset-left));
            scrollbar-color: rgba(255, 255, 255, 0.24) transparent;
            scrollbar-width: thin;
        }

        .sidebar .brand,
        .sidebar-logo {
            width: 100%;
            min-height: 124px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            padding: 18px 8px 22px;
            margin: 0 0 16px;
            border-radius: 0;
            background: var(--sidebar-bg);
            box-shadow: none;
        }

        .sidebar .brand-icon {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-logo img,
        .sidebar .brand-icon img {
            width: 100%;
            max-width: 230px;
            height: auto;
            max-height: 76px;
            display: block;
            object-fit: contain;
        }

        .menu,
        .sidebar-nav {
            display: flex;
            flex: 1 1 auto;
            min-height: 0;
            flex-direction: column;
            gap: 8px;
            padding: 4px 0;
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior-y: contain;
        }

        .menu a,
        .sidebar-link {
            width: 100%;
            min-height: 48px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 20px;
            border-radius: var(--radius-sm);
            background: transparent;
            color: var(--brand-red);
            font-size: 15px;
            font-weight: 800;
            line-height: 1.2;
            text-decoration: none;
            transition: background 0.16s ease, color 0.16s ease, box-shadow 0.16s ease;
        }

        .menu a:hover,
        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.07);
            color: #ff6b53;
        }

        .menu a.active,
        .sidebar-link.active,
        .sidebar-link[aria-current="page"] {
            background: var(--sidebar-active-bg);
            color: var(--sidebar-active-text);
            box-shadow: inset 4px 0 0 var(--ui-border-red, #450d07);
        }

        .sidebar-section-label {
            margin: 14px 12px 2px;
            color: rgba(255, 255, 255, 0.54);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .signout,
        .sidebar-footer {
            width: 100%;
            display: flex;
            justify-content: center;
            flex: 0 0 auto;
            margin-top: auto;
            padding: 20px 0 0;
        }

        .signout form,
        .sidebar-footer form {
            width: 100%;
            display: flex;
            justify-content: center;
            margin: 0;
        }

        .signout button,
        .sidebar-footer button,
        .logout-button {
            width: 100%;
            max-width: 220px;
            min-height: 48px;
            border: none;
            border-radius: 999px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 800;
            line-height: 1;
            margin-inline: auto;
            padding: 0 20px;
        }

        .main,
        .app-main {
            width: calc(100% - var(--sidebar-width));
            max-width: calc(100% - var(--sidebar-width));
            min-width: 0;
            min-height: 100dvh;
            margin: 0 0 0 var(--sidebar-width);
            overflow: visible;
            background: var(--surface-bg);
            padding: var(--page-padding);
        }

        .page-container,
        .page-inner {
            width: 100%;
            max-width: 1600px;
            min-width: 0;
            margin: 0 auto;
        }

        .guest-main {
            min-height: 100dvh;
            display: grid;
            align-items: center;
            background: var(--surface-bg);
            padding: var(--page-padding);
        }

        .guest-main .page-container {
            max-width: 720px;
        }

        .mobile-topbar {
            display: none;
        }

        .mobile-sidebar-backdrop {
            position: fixed;
            inset: 0;
            z-index: var(--z-sidebar-backdrop);
            display: none;
            border: 0;
            background: rgba(15, 23, 42, 0.52);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.18s ease;
        }

        .header,
        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 24px;
            min-width: 0;
        }

        .header h1,
        .page-title,
        .page-title h1,
        .page-header h1,
        .title-group h1 {
            margin: 0;
            color: var(--text-primary);
            font-size: 36px;
            line-height: 1.12;
            font-weight: 800;
        }

        .title-group,
        .page-title {
            min-width: 0;
        }

        .page-subtitle,
        .page-kicker {
            margin: 8px 0 0;
            color: var(--text-muted);
            font-size: 15px;
            font-weight: 600;
        }

        .section-title {
            margin: 0 0 14px;
            color: var(--text-primary);
            font-size: 20px;
            line-height: 1.2;
            font-weight: 700;
        }

        .stats,
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .card,
        .stat-card,
        .data-card,
        .ui-card,
        .directory-card,
        .seekers-card,
        .chart-card {
            background: var(--surface-card);
            border: 1px solid rgba(229, 231, 235, 0.88);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            color: var(--text-primary);
        }

        .card,
        .stat-card {
            min-height: 132px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            padding: 24px;
        }

        .card .info,
        .stat-card .info {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .card .value,
        .stat-value {
            color: #ffffff;
            font-size: 40px;
            line-height: 1;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .card .label,
        .stat-label {
            color: rgba(255, 255, 255, 0.96);
            font-size: 14px;
            line-height: 1.25;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.01em;
        }

        .data-card,
        .ui-card {
            padding: 24px;
        }

        .filter-toolbar,
        .data-table-toolbar {
            width: 100%;
            min-width: 0;
        }

        .filter-main-row,
        .toolbar-actions,
        .data-table-actions,
        .data-table-column-actions,
        .header-actions {
            min-width: 0;
        }

        .search-field,
        .data-table-field,
        .data-table-field.search {
            min-width: 0;
        }

        .search-field input,
        .data-table-search input,
        .filter-field input,
        .filter-field select {
            width: 100%;
            min-width: 0;
        }

        .table-card,
        .directory-card,
        .seekers-card {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            overflow: hidden;
            background: var(--surface-card);
            border-radius: var(--radius-lg);
        }

        .table-scroll,
        .table-wrap,
        .data-table-scroll,
        .seekers-table-wrap,
        .members-table-wrap,
        .preview-table-wrap {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            overflow-x: hidden;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
        }

        .table-card table,
        .data-table,
        .members-table,
        .seekers-table,
        .analytics-table,
        table[data-enhanced-table] {
            width: 100%;
            min-width: 0;
            border-collapse: collapse;
            background: #ffffff;
        }

        .table-card th,
        .table-card td,
        .data-table th,
        .data-table td {
            padding: 15px 18px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: middle;
        }

        .table-card th,
        .data-table th {
            background: #fff7f5;
            color: #475467;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .btn,
.btn-secondary,
.btn-danger,
.data-table-button,
.data-table-link-button {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: var(--radius-sm);
            border: 1px solid transparent;
            padding: 0 18px;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-primary {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: var(--radius-sm);
            border: 1px solid transparent;
            padding: 0 18px;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-secondary {
            background: #ffffff;
            border-color: #d1d5db;
            color: #111827;
        }

        .btn-secondary:hover,
        .btn-secondary:focus-visible {
            border-color: var(--ui-border-red, #450d07);
            color: var(--brand-red);
        }

        .btn-danger {
            background: #b42318;
            color: #ffffff;
        }

        .btn-danger:hover,
        .btn-danger:focus-visible {
            background: #912018;
        }

        input,
        select,
        textarea {
            max-width: 100%;
            border: 1px solid #d1d5db;
            border-radius: var(--radius-sm);
            background: #ffffff;
            color: var(--text-primary);
            outline: none;
        }

        input:not([type="checkbox"]):not([type="radio"]),
        select {
            min-height: 44px;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--ui-border-red, #450d07);
            box-shadow: 0 0 0 3px rgba(69, 13, 7, 0.12);
        }

        .notice,
        .toast {
            margin-bottom: 18px;
            border: 1px solid rgba(22, 163, 74, 0.24);
            border-radius: var(--radius-md);
            background: #f0fdf4;
            color: #166534;
            padding: 12px 16px;
            font-weight: 700;
        }

        .notice.error {
            border-color: rgba(217, 45, 32, 0.24);
            background: #fff1f1;
            color: #b42318;
        }

        .empty-state {
            padding: 28px;
            border: 1px dashed #cbd5e1;
            border-radius: var(--radius-md);
            background: #f8fafc;
            color: var(--text-muted);
            text-align: center;
            font-weight: 600;
        }

        .muted {
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
        }

        .modal-backdrop,
        .leader-modal-backdrop,
        .seeker-modal-backdrop,
        .data-table-modal-backdrop {
            z-index: var(--z-modal-backdrop);
        }

        .modal,
        .leader-modal,
        .seeker-modal,
        .data-table-modal {
            z-index: var(--z-modal);
        }

        @media (max-height: 760px) and (min-width: 1024px) {
            .sidebar,
            .app-sidebar {
                padding: max(18px, env(safe-area-inset-top)) max(18px, env(safe-area-inset-right)) max(18px, env(safe-area-inset-bottom)) max(18px, env(safe-area-inset-left));
            }

            .sidebar .brand,
            .sidebar-logo {
                min-height: 104px;
                margin-bottom: 14px;
            }

            .menu,
            .sidebar-nav {
                gap: 7px;
            }

            .menu a,
            .sidebar-link {
                min-height: 48px;
                font-size: 15px;
            }
        }

        @media (max-width: 1279px) {
            .stats,
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 1199px) {
            :root {
                --page-padding: 24px;
            }

            .page-container,
            .page-inner {
                max-width: 100%;
            }

            .header,
            .page-header,
            .seekers-toolbar {
                align-items: flex-start;
                flex-wrap: wrap;
            }

            .filter-toolbar,
            .data-table-toolbar {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                align-items: end;
            }

            .data-table-field.search {
                grid-column: 1 / -1;
            }

            .table-card table,
            .data-table,
            .members-table,
            .seekers-table,
            .analytics-table,
            table[data-enhanced-table] {
                min-width: 860px;
            }
        }

        @media (max-width: 1023px) {

            .sidebar,
            .app-sidebar {
                z-index: var(--z-sidebar-mobile);
                width: min(86vw, var(--sidebar-width));
                min-width: 0;
                max-width: 320px;
                transform: translateX(-100%);
                transition: transform 0.22s ease;
                box-shadow: 28px 0 64px rgba(0, 0, 0, 0.28);
            }

            html.sidebar-open .sidebar,
            html.sidebar-open .app-sidebar,
            body.sidebar-open .sidebar,
            body.sidebar-open .app-sidebar {
                transform: translateX(0);
            }

            .main,
            .app-main {
                width: 100%;
                max-width: 100%;
                min-height: 100dvh;
                margin-left: 0;
                padding: var(--page-padding);
            }

            .mobile-topbar {
                position: sticky;
                top: 0;
                z-index: 40;
                display: flex;
                align-items: center;
                gap: 12px;
                min-height: 56px;
                margin: calc(var(--page-padding) * -1) calc(var(--page-padding) * -1) 24px;
                padding: max(10px, env(safe-area-inset-top)) max(var(--page-padding), env(safe-area-inset-right)) 10px max(var(--page-padding), env(safe-area-inset-left));
                border-bottom: 1px solid var(--line);
                background: rgba(255, 255, 255, 0.96);
                backdrop-filter: blur(12px);
            }

            .mobile-menu-button {
                width: 42px;
                height: 42px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex: 0 0 auto;
                border: 1px solid #d1d5db;
                border-radius: var(--radius-sm);
                background: #ffffff;
                color: var(--text-primary);
                cursor: pointer;
            }

            .mobile-menu-icon {
                position: relative;
                width: 18px;
                height: 14px;
                display: block;
            }

            .mobile-menu-icon::before,
            .mobile-menu-icon::after,
            .mobile-menu-icon span {
                position: absolute;
                left: 0;
                width: 18px;
                height: 2px;
                border-radius: 999px;
                background: currentColor;
                content: "";
            }

            .mobile-menu-icon::before {
                top: 0;
            }

            .mobile-menu-icon span {
                top: 6px;
            }

            .mobile-menu-icon::after {
                bottom: 0;
            }

            .mobile-topbar-title {
                min-width: 0;
                overflow: hidden;
                color: var(--text-primary);
                font-size: 18px;
                font-weight: 800;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .mobile-sidebar-backdrop {
                display: block;
            }

            html.sidebar-open .mobile-sidebar-backdrop,
            body.sidebar-open .mobile-sidebar-backdrop {
                opacity: 1;
                pointer-events: auto;
            }
        }

        @media (max-width: 767px) {
            :root {
                --page-padding: 20px;
            }

            .sidebar,
            .app-sidebar {
                width: min(88vw, var(--sidebar-width));
                max-width: 300px;
                padding: max(18px, env(safe-area-inset-top)) max(18px, env(safe-area-inset-right)) max(18px, env(safe-area-inset-bottom)) max(18px, env(safe-area-inset-left));
            }

            .sidebar .brand,
            .sidebar-logo {
                min-height: 104px;
                padding: 16px;
            }

            .sidebar-logo img,
            .sidebar .brand-icon img {
                max-width: 220px;
                max-height: 66px;
            }

            .header,
            .page-header,
            .seekers-toolbar,
            .filter-main-row {
                align-items: stretch;
                flex-direction: column;
            }

            .header h1,
            .page-title,
            .page-title h1,
            .page-header h1,
            .title-group h1 {
                font-size: 30px;
            }

            .stats,
            .stats-grid,
            .filter-grid,
            .member-form-grid,
            .details-grid,
            .preview-stats,
            .dashboard-panels,
            .quick-action-grid {
                grid-template-columns: 1fr !important;
            }

            .filter-toolbar,
            .data-table-toolbar {
                grid-template-columns: 1fr;
            }

            .toolbar-actions,
            .header-actions,
            .data-table-actions,
            .data-table-column-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .btn,
.btn-secondary,
.btn-danger,
.data-table-button,
.data-table-link-button {
                width: 100%;
            }

            .btn-primary {
                width: 100%;
            }

            .card,
            .stat-card {
                min-height: 120px;
                padding: 20px;
            }

            .card .value,
            .stat-value {
                font-size: 34px;
            }

            .table-card table,
            .data-table,
            .members-table,
            .seekers-table,
            .analytics-table,
            table[data-enhanced-table] {
                min-width: 760px;
            }
        }

        @media (max-width: 430px) {
            :root {
                --page-padding: 16px;
            }

            .header h1,
            .page-title,
            .page-title h1,
            .page-header h1,
            .title-group h1 {
                font-size: 28px;
            }

            .data-card,
            .ui-card {
                padding: 20px;
            }
        }
