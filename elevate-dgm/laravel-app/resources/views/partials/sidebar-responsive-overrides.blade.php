        .page,
        .app-shell,
        .main,
        .app-main,
        .page-container,
        .page-inner,
        .directory-card,
        .table-card,
        .ui-card,
        .filters-card {
            max-width: 100% !important;
            box-sizing: border-box;
        }

        .sidebar,
        .app-sidebar {
            width: var(--sidebar-width) !important;
            min-width: var(--sidebar-width) !important;
            max-width: var(--sidebar-width) !important;
        }

        .main,
        .app-main {
            margin-left: var(--sidebar-width) !important;
            width: calc(100% - var(--sidebar-width)) !important;
            min-width: 0 !important;
        }

        .data-table-scroll,
        .table-scroll,
        .table-wrap,
        .seekers-table-wrap,
        .members-table-wrap,
        .preview-table-wrap {
            width: 100% !important;
            max-width: 100% !important;
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
        }

        @media (max-width: 1199px) {
            .sidebar,
            .app-sidebar {
                width: min(320px, 84vw) !important;
                min-width: 0 !important;
                max-width: 320px !important;
                transform: translateX(-100%) !important;
            }

            html.sidebar-open .sidebar,
            html.sidebar-open .app-sidebar,
            body.sidebar-open .sidebar,
            body.sidebar-open .app-sidebar {
                transform: translateX(0) !important;
            }

            .main,
            .app-main {
                margin-left: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                padding: 24px !important;
            }

            .mobile-topbar {
                display: flex !important;
            }

            .stats,
            .stats-grid,
            .summary-grid,
            .insights-grid,
            .dashboard-grid,
            .filter-grid,
            .responsive-panel-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            .data-table-toolbar,
            .filter-toolbar {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 767px) {
            .main,
            .app-main {
                padding: 20px !important;
            }

            .page-header,
            .header,
            .seekers-header,
            .seekers-toolbar,
            .filter-main-row,
            .toolbar-actions,
            .header-actions {
                align-items: stretch !important;
                flex-direction: column !important;
            }

            .stats,
            .stats-grid,
            .summary-grid,
            .insights-grid,
            .dashboard-grid,
            .filter-grid,
            .data-table-toolbar,
            .filter-toolbar,
            .member-form-grid,
            .details-grid,
            .preview-stats,
            .seeker-modal-grid,
            .leader-modal-grid,
            .account-grid,
            .responsive-panel-grid {
                grid-template-columns: 1fr !important;
            }

            .search-field,
            .data-table-field,
            .data-table-field.search,
            .filter-field {
                width: 100% !important;
                min-width: 0 !important;
            }

            .header-actions .btn,
            .toolbar-actions .btn,
            .new-button,
            .sync-button,
            .data-table-actions,
            .data-table-actions > *,
            .seeker-modal-actions button,
            .leader-modal-actions button,
            .modal-footer .btn {
                width: 100% !important;
            }

            .page-title h1,
            .page-header h1,
            .title-group h1,
            .header h1,
            .seekers-header h1 {
                overflow-wrap: anywhere;
            }
        }

        @media (max-width: 430px) {
            .main,
            .app-main {
                padding: 16px !important;
            }
        }
