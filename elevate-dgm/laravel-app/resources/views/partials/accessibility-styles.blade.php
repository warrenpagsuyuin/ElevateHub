/* Kept after page styles so keyboard indicators survive local outline resets. */
.sr-only {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip-path: inset(50%) !important;
    white-space: nowrap !important;
    border: 0 !important;
}
.skip-link {
    position: fixed;
    top: 8px;
    left: 8px;
    z-index: 1000;
    transform: translateY(-160%);
    padding: 12px 18px;
    background: #ffffff;
    color: #111827;
    font-weight: 700;
}
.skip-link:focus { transform: translateY(0); }
:is(a, button, input, select, textarea, summary, [tabindex]):focus-visible {
    outline: 3px solid var(--ui-focus) !important;
    outline-offset: 3px !important;
    box-shadow: 0 0 0 3px var(--ui-surface) !important;
}
:is(input:not([type="hidden"]), select, textarea) {
    border-color: #667085 !important;
}
::placeholder { color: #596273 !important; opacity: 1 !important; }
:is(button, input, select, textarea):disabled,
.pagination-disabled {
    opacity: 1 !important;
    color: #4b5563 !important;
    background-color: #e5e7eb !important;
}
.sidebar-footer button, .signout button, .logout-button,
.data-table-button.primary, .btn-primary {
    background: #b42318 !important;
    color: #ffffff !important;
}
.sidebar-footer button:hover, .signout button:hover,
.data-table-button.primary:hover, .btn-primary:hover {
    background: #912018 !important;
}
.data-table-button.danger, .data-table-menu-item.danger { color: #b42318 !important; }
.data-table-search-clear { color: #596273 !important; }
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        scroll-behavior: auto !important;
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
@media (forced-colors: active) {
    :is(a, button, input, select, textarea, summary, [tabindex]):focus-visible {
        outline-color: Highlight !important;
    }
}
