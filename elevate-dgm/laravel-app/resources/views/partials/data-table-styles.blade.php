        .data-table-shell {
            display: grid;
            gap: 14px;
            margin-bottom: 18px;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .data-table-toolbar,
        .data-table-selection-bar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            border: 1px solid #e8edf3;
            border-radius: 12px;
            background: #fff;
            color: #0f172a;
            padding: 16px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
        }

        .data-table-toolbar {
            align-items: flex-end;
        }

        .data-table-field {
            display: grid;
            gap: 6px;
            min-width: 156px;
            flex: 1 1 180px;
        }

        .data-table-field.search {
            flex: 999 1 300px;
            min-width: min(100%, 260px);
        }

        .data-table-field.compact {
            flex: 0 0 130px;
            min-width: 120px;
        }

        .data-table-field label,
        .data-table-label {
            color: #667085;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .data-table-field input,
        .data-table-field select,
        .data-table-selection-bar select {
            width: 100%;
            height: 44px;
            border: 1px solid #d9dee7;
            border-radius: 8px;
            background: #ffffff;
            color: #0f172a;
            font: inherit;
            font-size: 14px;
            font-weight: 600;
            padding: 0 12px;
            outline: none;
        }

        .data-table-field input:focus,
        .data-table-field select:focus,
        .data-table-selection-bar select:focus {
            border-color: #e33c24;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(227, 60, 36, 0.12);
        }

        .data-table-search {
            position: relative;
        }

        .data-table-search input {
            padding-left: 40px;
            padding-right: 40px;
            background-image: url("data:image/svg+xml,%3Csvg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23667085' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cpath d='m20 20-3.5-3.5'/%3E%3C/svg%3E");
            background-position: 13px center;
            background-repeat: no-repeat;
            background-size: 18px 18px;
        }

        .data-table-search-clear {
            position: absolute;
            top: 50%;
            right: 8px;
            display: inline-flex;
            width: 26px;
            height: 26px;
            align-items: center;
            justify-content: center;
            transform: translateY(-50%);
            border: 0;
            border-radius: 999px;
            background: transparent;
            color: #667085;
            cursor: pointer;
            font: inherit;
            font-size: 18px;
            font-weight: 900;
            line-height: 1;
        }

        .data-table-search-clear:hover,
        .data-table-search-clear:focus {
            background: #fff4f2;
            color: #e33c24;
            outline: none;
        }

        .data-table-actions,
        .data-table-column-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .data-table-button,
        .data-table-link-button,
        .data-table-icon-button,
        .data-table-menu-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            border: 1px solid #d9dee7;
            border-radius: 8px;
            background: #fff;
            color: #0f172a;
            cursor: pointer;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
        }

        .data-table-button,
        .data-table-link-button {
            gap: 8px;
            padding: 0 14px;
        }

        .data-table-icon-button,
        .data-table-menu-button {
            width: 44px;
            padding: 0;
        }

        .data-table-button.primary,
        .data-table-link-button.primary {
            border-color: #e33c24;
            background: #e33c24;
            color: #fff;
        }

        .data-table-button:hover,
        .data-table-link-button:hover,
        .data-table-icon-button:hover,
        .data-table-menu-button:hover {
            border-color: #e33c24;
            color: #e33c24;
            box-shadow: 0 4px 12px rgba(227, 60, 36, 0.14);
        }

        .data-table-button.primary:hover,
        .data-table-link-button.primary:hover {
            background: #c92f1b;
            color: #fff;
        }

        .data-table-button.tertiary,
        .data-table-link-button.tertiary {
            border-color: transparent;
            background: transparent;
            color: #475467;
            box-shadow: none;
        }

        .data-table-button.tertiary:hover,
        .data-table-link-button.tertiary:hover {
            background: #fff4f2;
            color: #c92f1b;
        }

        .data-table-button.danger,
        .data-table-link-button.danger {
            border-color: #b42318;
            background: #b42318;
            color: #ffffff;
        }

        .data-table-button:disabled,
        .data-table-link-button.disabled,
        .data-table-link-button[aria-disabled="true"],
        .data-table-selection-bar [disabled] {
            opacity: 0.45;
            cursor: not-allowed;
            box-shadow: none;
            pointer-events: none;
        }

        .data-table-columns {
            position: relative;
            flex: 0 0 auto;
        }

        .data-table-columns-button span {
            font-size: 10px;
        }

        .data-table-columns-panel {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            z-index: 25;
            display: grid;
            min-width: 260px;
            gap: 18px;
            border: 1px solid #e4e7ec;
            border-radius: 10px;
            background: #fff;
            padding: 24px 26px;
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.16);
        }

        .data-table-column-toggle {
            display: flex;
            min-height: 28px;
            align-items: center;
            gap: 18px;
            border-radius: 6px;
            color: #111827;
            cursor: pointer;
            font-size: 15px;
            font-weight: 900;
            line-height: 1.2;
            padding: 0;
        }

        .data-table-column-toggle:hover,
        .data-table-column-toggle:focus-within {
            color: #c92f1b;
        }

        .data-table-column-toggle .data-table-column-checkbox {
            appearance: none;
            -webkit-appearance: none;
            flex: 0 0 22px;
            width: 22px;
            height: 22px;
            margin: 0;
            border: 2px solid #d0d5dd;
            border-radius: 4px;
            background: #fff;
            background-position: center;
            background-repeat: no-repeat;
            background-size: 16px 16px;
            cursor: pointer;
        }

        .data-table-column-toggle .data-table-column-checkbox:checked {
            border-color: var(--ui-focus);
            background-color: var(--ui-focus);
            background-image: url("data:image/svg+xml,%3Csvg width='16' height='16' viewBox='0 0 16 16' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M3.5 8.2 6.6 11.3 12.8 4.7' fill='none' stroke='white' stroke-width='2.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
        }

        .data-table-column-toggle .data-table-column-checkbox:focus-visible {
            outline: 3px solid rgba(227, 60, 36, 0.2);
            outline-offset: 2px;
        }

        .data-table-count {
            color: #667085;
            font-size: 13px;
            font-weight: 900;
            margin-left: auto;
        }

        .data-table-scroll {
            width: 100%;
            max-height: none;
            overflow-x: auto;
            overflow-y: visible;
        }

        .data-table-scroll table,
        table[data-enhanced-table] {
            min-width: 760px;
        }

        .data-table-sort-link,
        .data-table-sort-button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 0;
            background: transparent;
            color: inherit;
            cursor: pointer;
            font: inherit;
            font-weight: inherit;
            padding: 0;
            text-decoration: none;
        }

        .data-table-sort-indicator {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            border-radius: 999px;
            color: currentColor;
            font-size: 13px;
            line-height: 1;
            opacity: 0.58;
        }

        [aria-sort="ascending"] .data-table-sort-indicator,
        [aria-sort="descending"] .data-table-sort-indicator {
            opacity: 1;
        }

        .data-table-select-col,
        .data-table-action-col {
            width: 52px;
            text-align: center !important;
        }

        .data-table-checkbox {
            width: 18px;
            height: 18px;
            accent-color: #e33c24;
            cursor: pointer;
        }

        .data-table-selection-bar {
            z-index: 10;
            border-color: rgba(227, 60, 36, 0.22);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.10);
        }

        .data-table-selection-summary {
            color: #0f172a;
            font-size: 14px;
            font-weight: 900;
        }

        .data-table-selection-help {
            display: none;
        }

        .data-table-row-menu {
            position: relative;
            display: inline-flex;
            justify-content: center;
        }

        .data-table-menu {
            position: fixed;
            z-index: 20;
            display: none;
            min-width: 190px;
            max-width: calc(100vw - 16px);
            overflow: hidden;
            border: 1px solid #e4e7ec;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.16);
            text-align: left;
        }

        .data-table-row-menu.open .data-table-menu {
            display: grid;
        }

        .data-table-menu button,
        .data-table-menu a {
            display: flex;
            width: 100%;
            min-height: 40px;
            align-items: center;
            border: 0;
            background: #fff;
            color: #0f172a;
            cursor: pointer;
            font: inherit;
            font-size: 13px;
            font-weight: 800;
            padding: 0 13px;
            text-align: left;
            text-decoration: none;
        }

        .data-table-menu button:hover,
        .data-table-menu a:hover,
        .data-table-menu button:focus,
        .data-table-menu a:focus {
            background: #fff4f2;
            color: #c92f1b;
            outline: none;
        }

        .data-table-menu button:disabled {
            color: #98a2b3;
            cursor: not-allowed;
        }

        .data-table-menu .danger,
        .data-table-menu .danger:hover,
        .data-table-menu .danger:focus {
            color: #b42318;
        }

        .data-table-empty-card {
            display: grid;
            justify-items: center;
            gap: 10px;
            padding: 34px 22px;
            color: #475467;
            text-align: center;
        }

        .data-table-empty-card strong {
            color: #0f172a;
            font-size: 16px;
            font-weight: 900;
        }

        .data-table-empty-card span {
            max-width: 520px;
            font-size: 14px;
            font-weight: 700;
        }

        .data-table-filter-chips {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin: -10px 0 18px;
        }

        .data-table-chip {
            display: inline-flex;
            min-height: 34px;
            align-items: center;
            gap: 8px;
            border: 1px solid #f1c3ba;
            border-radius: 999px;
            background: #fff7f5;
            color: #9f2a1d;
            font-size: 13px;
            font-weight: 700;
            padding: 0 12px;
            text-decoration: none;
        }

        .data-table-chip:hover,
        .data-table-chip:focus-visible {
            border-color: #e33c24;
            color: #c92f1b;
            outline: none;
        }

        .data-table-badge {
            display: inline-flex;
            min-height: 28px;
            align-items: center;
            justify-content: center;
            border: 1px solid #d9dee7;
            border-radius: 999px;
            background: #f8fafc;
            color: #344054;
            font-size: 12px;
            font-weight: 800;
            line-height: 1;
            padding: 0 11px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .data-table-badge.muted {
            border-color: #f1c3ba;
            background: #fff7f5;
            color: #b42318;
        }

        .data-table-loading {
            opacity: 0.72;
            cursor: progress;
        }

        .data-table-client-pagination {
            margin: 0;
            padding: 17px 20px 20px;
        }

        .data-table-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 100;
            display: none;
            align-items: flex-start;
            justify-content: center;
            background: rgba(15, 23, 42, 0.48);
            padding: 72px 16px 24px;
        }

        .data-table-modal-backdrop.open {
            display: flex;
        }

        .data-table-modal {
            width: min(100%, 430px);
            border-radius: 14px;
            background: #fff;
            color: #0f172a;
            padding: 24px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
        }

        .data-table-modal h2 {
            margin: 0 0 10px;
            font-size: 20px;
            font-weight: 900;
        }

        .data-table-modal p {
            margin: 0 0 22px;
            color: #475467;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.5;
        }

        .data-table-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        @media (max-width: 768px) {
            .data-table-toolbar,
            .data-table-selection-bar {
                align-items: stretch;
                flex-direction: column;
            }

            .data-table-field,
            .data-table-field.compact,
            .data-table-actions,
            .data-table-column-actions {
                width: 100%;
                flex-basis: auto;
            }

            .data-table-button,
            .data-table-link-button {
                width: 100%;
            }

            .data-table-columns-panel {
                position: static;
                width: 100%;
                margin-top: 8px;
            }
        }
