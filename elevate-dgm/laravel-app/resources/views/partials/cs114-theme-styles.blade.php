/* Elevate DGM: project overrides for the Tailwind CSS foundation.
   Shared by the application and sign-in; loaded after page-specific styles. */
:root {
    --ui-primary: #b42318;
    --ui-primary-hover: #912018;
    --ui-primary-active: #701a12;
    --ui-navigation-accent: #ff6b53;
    --ui-surface: #ffffff;
    --ui-page: #f3f4f6;
    --ui-text: #111111;
    --ui-muted: #667085;
    --ui-border: #667085;
    --ui-divider: #e5e7eb;
    --ui-focus: #1d4ed8;
    --ui-success: #166534;
    --ui-success-surface: #f0fdf4;
    --ui-warning: #854d0e;
    --ui-warning-surface: #fefce8;
    --ui-error: #b42318;
    --ui-error-surface: #fef2f2;
    --ui-disabled-text: #4b5563;
    --ui-disabled-surface: #e5e7eb;
    --ui-space: 4px;
    --ui-space-2: calc(var(--ui-space) * 2);
    --ui-space-3: calc(var(--ui-space) * 3);
    --ui-space-4: calc(var(--ui-space) * 4);
    --ui-space-5: calc(var(--ui-space) * 5);
    --ui-space-6: calc(var(--ui-space) * 6);
    --ui-space-8: calc(var(--ui-space) * 8);
    --ui-text-xs: 12px;
    --ui-text-sm: 14px;
    --ui-text-base: 16px;
    --ui-text-lg: 20px;
    --ui-text-xl: 24px;
    --ui-text-2xl: 28px;
    --ui-text-3xl: 32px;
    --ui-text-metric: 48px;
    --ui-target: 44px;
    --font-ui: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    --brand-red: var(--ui-primary);
    --brand-red-dark: var(--ui-primary-hover);
    --brand-red-soft: var(--ui-error-surface);
    --surface-bg: var(--ui-page);
    --surface-card: var(--ui-surface);
    --text-primary: var(--ui-text);
    --text-muted: var(--ui-muted);
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
}
body, button, input, select, textarea { font-family: var(--font-ui); }
[hidden] { display: none !important; }
body { color: var(--ui-text); font-size: var(--ui-text-base); line-height: 1.5; }
:is(.page-header, .header, .page-title, .title-group) h1,
.admin-users-header h1 { font-size: var(--ui-text-3xl); line-height: 1.2; }
.login-title, .table-card h2 { font-size: var(--ui-text-xl); }
.login-card :is(.login-title, .field-label, .input) { color: var(--ui-text); }
.section-title, .modal-header h2, .summary-title { font-size: var(--ui-text-lg); }
.page-subtitle, .page-kicker, .login-instruction,
.field-label, .field label, .directory-table, .data-table-menu :is(a, button),
.pagination-bar, .pagination-link, .pagination-current, .pagination-disabled {
    font-size: var(--ui-text-sm);
}
.data-table-field label, .data-table-label, .directory-table th,
.data-table-count, .role-badge, .district-pill, .unassigned,
.summary-segment span { font-size: var(--ui-text-xs); }
.data-table-field label, .data-table-label, .data-table-count,
.page-subtitle, .page-kicker, .login-instruction { color: var(--ui-muted); }
.menu a, .sidebar-link { color: var(--ui-navigation-accent); }
.menu a:hover, .sidebar-link:hover { color: var(--ui-surface); }
.menu a.active, .sidebar-link.active, .sidebar-link[aria-current="page"] {
    color: var(--ui-text);
}
.admin-summary-card .stat-value { color: var(--ui-primary); font-size: var(--ui-text-metric); }
.user-summary-card .stat-value { color: var(--ui-primary); font-size: var(--ui-text-3xl); }
.summary-segment strong { color: var(--ui-primary); font-size: var(--ui-text-xl); }
.admin-summary-card .stat-label { font-size: var(--ui-text-base); }
.card .value { font-size: var(--ui-text-3xl); color: var(--ui-surface); }
.card .label { font-size: var(--ui-text-sm); color: var(--ui-surface); opacity: 1; }
.card.red, .table-card h2, .pagination-bar, .table-card .pagination-bar {
    background: var(--ui-primary);
    color: var(--ui-surface);
}
.pagination-summary, .pagination-page-size { color: var(--ui-surface); }
.pagination-bar .pagination-mobile-status, .pagination-bar .pagination-ellipsis { color: var(--ui-surface); }
.pagination-current, .directory-card .pagination-current {
    background: var(--ui-primary);
    border-color: var(--ui-surface);
    color: var(--ui-surface);
}
:is(button, .btn, .data-table-button, .data-table-link-button,
    .pagination-link, .pagination-current, .pagination-disabled,
    .data-table-chip, .data-table-sort-link, .data-table-sort-button,
    .data-table-column-toggle, .data-table-menu a) {
    min-width: var(--ui-target);
    min-height: var(--ui-target);
}
:is(input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]), select) {
    min-height: var(--ui-target);
}
.mobile-menu-button, .modal-close, .leader-modal-close, .seeker-modal-close,
.data-table-icon-button, .data-table-menu-button, .data-table-search-clear {
    width: var(--ui-target);
    height: var(--ui-target);
    flex-shrink: 0;
    color: var(--ui-muted);
}
.data-table-search-clear { right: 0; border-radius: var(--radius-sm); }
.data-table-search input { padding-right: calc(var(--ui-target) + var(--ui-space-2)); }
.data-table-checkbox { width: var(--ui-target); height: var(--ui-target); accent-color: var(--ui-primary); }
.data-table-column-toggle { gap: var(--ui-space-4); }
.data-table-column-toggle .data-table-column-checkbox:checked {
    background-color: var(--ui-primary);
    border-color: var(--ui-primary) !important;
}
:is(.btn, .btn-secondary, .data-table-button, .data-table-link-button,
    .data-table-icon-button, .data-table-menu-button, .mobile-menu-button,
    .pagination-link) { border-color: var(--ui-border); }
.sidebar-footer button, .signout button, .logout-button,
.data-table-button.primary, .data-table-link-button.primary, .btn-primary,
.new-button, .modal-submit, .primary-button, .btn-danger,
.data-table-button.danger, .data-table-link-button.danger {
    background: var(--ui-primary) !important;
    border-color: var(--ui-primary);
    color: var(--ui-surface) !important;
}
.sidebar-footer button:hover, .signout button:hover, .logout-button:hover,
.data-table-button.primary:hover, .data-table-link-button.primary:hover, .btn-primary:hover,
.new-button:hover, .modal-submit:hover, .primary-button:hover, .btn-danger:hover,
.data-table-button.danger:hover, .data-table-link-button.danger:hover {
    background: var(--ui-primary-hover) !important;
    border-color: var(--ui-primary-hover);
}
.sidebar-footer button:active, .signout button:active, .logout-button:active,
.data-table-button.primary:active, .data-table-link-button.primary:active, .btn-primary:active,
.new-button:active, .modal-submit:active, .primary-button:active, .btn-danger:active {
    background: var(--ui-primary-active) !important;
}
.btn-secondary:hover, .data-table-button:not(.primary):not(.danger):hover,
.data-table-link-button:not(.primary):not(.danger):hover,
.data-table-icon-button:hover, .data-table-menu-button:hover,
.pagination-link:hover, .directory-card .pagination-link:hover,
.secondary-button:hover, .data-table-search-clear:hover, .modal-close:hover {
    background: var(--ui-error-surface);
    color: var(--ui-primary) !important;
    border-color: var(--ui-primary);
}
:is(button, .btn, .data-table-link-button, .pagination-link):active:not(:disabled):not([aria-disabled="true"]) {
    box-shadow: inset 0 0 0 2px currentColor;
}
:is(button, input, select, textarea):disabled,
[aria-disabled="true"], .pagination-disabled, .directory-card .pagination-disabled {
    opacity: 1 !important;
    background: var(--ui-disabled-surface) !important;
    color: var(--ui-disabled-text) !important;
    border-color: var(--ui-border) !important;
    box-shadow: none !important;
    cursor: not-allowed;
}
:is(a, button, input, select, textarea, summary, [tabindex]):focus-visible {
    outline: 3px solid var(--ui-focus) !important;
    outline-offset: 3px !important;
    box-shadow: 0 0 0 3px var(--ui-surface) !important;
}
.notice:not(.error), .toast { color: var(--ui-success); background: var(--ui-success-surface); }
.notice.error, .login-card .error { color: var(--ui-error); background: var(--ui-error-surface); }
.field-error, .data-table-menu .danger { color: var(--ui-error); }
.unassigned, .data-table-badge.muted { color: var(--ui-warning); background: var(--ui-warning-surface); }
.login-card, .modal, .data-table-columns-panel { border-radius: var(--radius-md); }
.admin-summary-card, .user-summary-card, .directory-card, .table-card, .card { border-radius: var(--radius-lg); }
.login-card { padding: var(--ui-space-8); }
.login-copy { margin-bottom: var(--ui-space-6); }
.form, .field { gap: var(--ui-space-5); }
.field { gap: var(--ui-space-2); margin-bottom: var(--ui-space-4); }
.header { margin-bottom: var(--ui-space-6); }
.stats { gap: var(--ui-space-5); margin-bottom: var(--ui-space-6); }
.card { padding: var(--ui-space-5); }
.card .info { gap: var(--ui-space-2); }
.admin-dashboard-header { margin-bottom: var(--ui-space-4); }
.dashboard-intro { margin-bottom: var(--ui-space-6); }
.admin-summary-card, .user-summary-card, .modal { padding: var(--ui-space-6); }
.admin-summary-card { gap: var(--ui-space-4); }
.data-table-shell { gap: var(--ui-space-4); margin-bottom: var(--ui-space-4); }
.data-table-field { gap: var(--ui-space-2); }
.data-table-actions, .data-table-column-actions { gap: var(--ui-space-3); }
.data-table-columns-panel { padding: var(--ui-space-6); gap: var(--ui-space-4); }
.data-table-empty-card { gap: var(--ui-space-3); padding: var(--ui-space-8) var(--ui-space-6); }
.pagination-bar, .table-card .pagination-bar, .directory-card .pagination-bar { padding: var(--ui-space-4) var(--ui-space-5); }
.table-card h2 { padding: var(--ui-space-4) var(--ui-space-6); }
.table-card th, .table-card td { padding: var(--ui-space-4) var(--ui-space-6); }
.table-card .data-table-action-col { width: calc(var(--ui-target) + var(--ui-space-6) * 2); }
.data-table-scroll { position: relative; }
@media (max-width: 767px) {
    .data-table-toolbar { display: grid; }
    .data-table-field, .data-table-field.search { flex: initial; }
    :is(.page-header, .header, .page-title, .title-group) h1,
    .admin-users-header h1 { font-size: var(--ui-text-2xl); }
    .admin-summary-card .stat-value { font-size: var(--ui-text-3xl); }
}
@media (max-width: 1199px) {
    .app-sidebar[aria-hidden="true"] { visibility: hidden; }
}
@media (max-width: 480px) {
    .login-card { padding: var(--ui-space-6); }
}
@media (forced-colors: active) {
    :is(a, button, input, select, textarea, summary, [tabindex]):focus-visible {
        outline-color: Highlight !important;
    }
}
