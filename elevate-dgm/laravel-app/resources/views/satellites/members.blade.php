@extends('layouts.app')

@php
    $memberFormErrorBag = $errors->getBag('memberForm');
    $importErrorBag = $errors->getBag('memberImport');
    $memberFieldNames = [
        'status',
        'dgroup_role',
        'last_name',
        'first_name',
        'full_name',
        'gender',
        'email_address',
        'contact_number',
        'facebook_link',
        'birthdate',
        'age',
        'district',
        'area',
        'campus_school',
        'leader_member_id',
        'active_disciples',
        'year_joined',
        'ministry_involvement',
        'weekly_dgroups_led',
        'generation',
        'refresh_date',
        'year',
        'month',
    ];
    $sortUrl = function (string $key) use ($satellite, $sort, $direction) {
        return route('satellites.members', array_merge(
            ['satellite' => $satellite],
            request()->except(['page', 'sort', 'direction']),
            [
                'sort' => $key,
                'direction' => $sort === $key && $direction === 'asc' ? 'desc' : 'asc',
            ]
        ));
    };
    $sortMark = fn (string $key) => $sort === $key ? ($direction === 'asc' ? 'up' : 'down') : 'neutral';
    $clearFiltersUrl = route('satellites.members', [
        'satellite' => $satellite,
        'view' => $archivedView ? 'archived' : null,
        'sort' => $sort,
        'direction' => $direction,
        'per_page' => $perPage,
    ]);
    $viewUrl = fn (string $view) => route('satellites.members', array_merge(
        ['satellite' => $satellite],
        request()->except(['page', 'view']),
        $view === 'archived' ? ['view' => 'archived'] : []
    ));
    $oldMemberData = [];

    foreach ($memberFieldNames as $fieldName) {
        $oldMemberData[$fieldName] = old($fieldName);
    }
@endphp

@section('title', $satellite->display_name.' Members | Elevate DGM')

@push('styles')
:root { --brand-red: #ef3b24; --brand-red-dark: #c92f1b; --brand-red-soft: #fff1ee; --ink: #111; --muted: #667085; --line: #e8edf3; --surface: #fff; --page: #f3f4f6; --green-bg: #dcfce7; --green-text: #07843f; --blue-bg: #dbeafe; --blue-text: #1d4ed8; --gray-bg: #e5e7eb; --gray-text: #4b5563; }
        .page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; margin-bottom: 20px; }
        .page-title { display: flex; flex-direction: column; gap: 7px; min-width: 0; }
        .member-back-link { display: inline-flex; align-items: center; min-height: 44px; color: var(--muted); font-size: 13px; font-weight: 800; text-decoration: none; }
        .member-back-link:hover, .member-back-link:focus-visible { color: var(--brand-red); outline: none; }
        .page-title h1 { margin: 0; color: var(--ink); font-size: 34px; line-height: 1.08; font-weight: 900; }
        .page-kicker { color: var(--muted); font-size: 14px; font-weight: 800; }
        .header-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 9px; min-height: 42px; border: 0; border-radius: 999px; padding: 0 18px; color: var(--ink); background: #fff; font-size: 14px; font-weight: 900; text-decoration: none; cursor: pointer; transition: 0.15s ease; white-space: nowrap; }
        .btn svg { width: 17px; height: 17px; flex: 0 0 auto; }
        .btn-primary { box-shadow: 0 10px 22px rgba(0, 0, 0, 0.18); }
        .btn-secondary { border: 1px solid var(--line); background: #fff; color: var(--ink); }
        .btn-secondary:hover, .btn-secondary:focus-visible { border-color: var(--brand-red); color: var(--brand-red); outline: none; }
        .btn:disabled { opacity: 0.58; cursor: wait; }
        .flash { margin-bottom: 16px; padding: 13px 16px; border-radius: 12px; font-size: 14px; font-weight: 850; }
        .flash-success { border: 1px solid #bbf7d0; background: #f0fdf4; color: #166534; }
        .flash-error { border: 1px solid #fecaca; background: #fef2f2; color: #991b1b; }
        .filters-card { margin-bottom: 18px; padding: 16px; border: 1px solid var(--line); border-radius: 16px; background: var(--surface); box-shadow: 0 7px 22px rgba(15, 23, 42, 0.06); }
        .filter-main-row { display: flex; align-items: center; gap: 10px; }
        .search-field { position: relative; flex: 1; min-width: 260px; }
        .search-field svg { position: absolute; left: 14px; top: 50%; width: 17px; height: 17px; color: #9aa4b2; transform: translateY(-50%); pointer-events: none; }
        .search-field input { width: 100%; height: 42px; border: 1px solid transparent; border-radius: 9px; background: #f3f4f6; color: var(--ink); font-size: 14px; font-weight: 750; padding: 0 16px 0 42px; outline: none; }
        .search-field input::placeholder { color: #98a2b3; font-weight: 750; }
        .search-field input:focus { border-color: var(--brand-red); background: #fff; box-shadow: 0 0 0 3px rgba(227, 60, 36, 0.12); }
        .filter-toggle[aria-expanded="true"] { border-color: var(--brand-red); color: var(--brand-red); background: var(--brand-red-soft); }
        .filters-panel { margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--line); }
        .filters-panel[hidden] { display: none; }
        .filter-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
        .filter-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
        .filter-field label, .field label { color: #344054; font-size: 12px; font-weight: 900; }
        .filter-field select { width: 100%; height: 40px; border: 1px solid #d9dee7; border-radius: 9px; background: #fff; color: var(--ink); font-size: 13px; font-weight: 800; padding: 0 10px; outline: none; }
        .filter-field select:focus { border-color: var(--brand-red); box-shadow: 0 0 0 3px rgba(227, 60, 36, 0.12); }
        .filter-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .filter-chip { display: inline-flex; align-items: center; min-height: 26px; border-radius: 999px; background: var(--brand-red-soft); color: var(--brand-red); padding: 0 10px; font-size: 12px; font-weight: 900; }
        .directory-card { overflow: visible; border: 1px solid var(--line); border-radius: 16px; background: var(--surface); box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08); }
        .table-scroll { width: 100%; overflow-x: auto; border-radius: 16px 16px 0 0; }
        .members-table { width: 100%; min-width: 1040px; border-collapse: collapse; color: #374151; font-size: 14px; font-weight: 650; }
        .members-table th, .members-table td { padding: 17px 20px; text-align: left; border-bottom: 1px solid var(--line); vertical-align: middle; }
        .members-table th { color: #667085; background: #fff7f5; font-size: 12px; font-weight: 900; text-transform: uppercase; }
        .members-table tr.archiving { opacity: 0; transform: translateX(8px); transition: opacity .2s ease, transform .2s ease; }
        .member-archive-date { color: #667085; white-space: nowrap; }
        .members-table tbody tr:last-child td { border-bottom: none; }
        .members-table tbody tr:hover { background: #fffafa; }
        .sortable-link { display: inline-flex; align-items: center; gap: 7px; color: inherit; text-decoration: none; }
        .sort-mark { width: 11px; height: 11px; color: #b9c0cc; }
        .sort-mark.up, .sort-mark.down { color: var(--brand-red); }
        .member-name-cell { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .member-name-stack { display: flex; flex-direction: column; min-width: 0; }
        .member-name-text { display: inline-block; max-width: 290px; overflow: hidden; color: var(--ink); font-weight: 900; text-overflow: ellipsis; white-space: nowrap; }
        .member-submeta { display: block; margin-top: 2px; max-width: 290px; overflow: hidden; color: #667085; font-size: 12px; font-weight: 650; line-height: 1.3; text-overflow: ellipsis; white-space: nowrap; }
        .member-details-button { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; padding: 0; border: 0; border-radius: 6px; background: transparent; color: #98a2b3; cursor: pointer; transition: color 0.15s ease, background 0.15s ease; }
        .member-details-button:hover, .member-details-button:focus-visible { background: var(--brand-red-soft); color: var(--brand-red); outline: none; }
        .member-details-button:focus-visible { box-shadow: 0 0 0 3px rgba(227, 60, 36, 0.16); }
        .member-eye-icon { width: 17px; height: 17px; }
        .members-stat-card { position: relative; text-decoration: none; cursor: pointer; }
        .members-stat-card:hover { border-color: var(--stat-card-border-hover, #3A3A3A) !important; border-top-color: var(--stat-card-accent, #450d07) !important; }
        .members-stat-card.is-active { border-color: var(--stat-card-focus, #FF5A4D) !important; border-top-color: var(--stat-card-accent, #450d07) !important; }
        .members-stat-card .stat-card-value-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; min-width: 0; }
        .members-stat-card .card-chevron { flex-shrink: 0; color: var(--stat-card-chevron, #B8B8B8); font-size: 20px; line-height: 1; transition: color 0.15s ease; }
        .members-stat-card:hover .card-chevron, .members-stat-card.is-active .card-chevron { color: var(--stat-card-chevron-hover, #F2F2F2); }
        .leader-load-pill { display: inline-flex; align-items: center; min-height: 25px; padding: 0 10px; border-radius: 999px; background: #fff1ee; color: #c92f1b; font-size: 12px; font-weight: 850; white-space: nowrap; }
        .status-pill { display: inline-flex; align-items: center; justify-content: center; min-width: 62px; height: 27px; padding: 0 12px; border-radius: 999px; font-size: 12px; line-height: 1; font-weight: 900; }
        .status-active { background: var(--green-bg); color: var(--green-text); }
        .status-new { background: var(--blue-bg); color: var(--blue-text); }
        .status-inactive { background: var(--gray-bg); color: var(--gray-text); }
        .engagement-high { background: #dcfce7; color: #166534; }
        .engagement-active { background: #dcfce7; color: #166534; }
        .engagement-medium { background: #fef3c7; color: #92400e; }
        .engagement-moderate { background: #fef3c7; color: #92400e; }
        .engagement-low { background: #fee2e2; color: #991b1b; }
        .engagement-needs-follow-up { background: #fee2e2; color: #991b1b; }
        .engagement-inactive,
        .engagement-insufficient,
        .engagement-unclassified { background: #e5e7eb; color: #4b5563; }
        .leader-empty { color: #98a2b3; font-weight: 900; }
        .actions-cell { width: 80px; text-align: right; }
        .action-menu { position: relative; display: inline-flex; justify-content: flex-end; }
        .row-action-button { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border: 1px solid transparent; border-radius: 9px; background: transparent; color: #667085; cursor: pointer; }
        .row-action-button svg { width: 18px; height: 18px; }
        .row-action-button:hover, .row-action-button:focus-visible { border-color: var(--line); background: #fff; color: var(--brand-red); outline: none; }
        .action-menu-panel { position: absolute; right: 0; top: calc(100% + 8px); z-index: 10; display: none; min-width: 196px; overflow: hidden; border: 1px solid var(--line); border-radius: 12px; background: #fff; box-shadow: 0 18px 35px rgba(15, 23, 42, 0.18); }
        .action-menu.open .action-menu-panel { display: block; }
        .action-menu-panel button, .action-menu-panel a { display: flex; align-items: center; gap: 9px; width: 100%; border: 0; background: transparent; color: #344054; padding: 11px 13px; font-size: 13px; font-weight: 850; text-align: left; text-decoration: none; cursor: pointer; box-sizing: border-box; }
        .action-menu-panel button svg, .action-menu-panel a svg { width: 15px; height: 15px; flex-shrink: 0; }
        .action-menu-panel button:hover, .action-menu-panel button:focus-visible, .action-menu-panel a:hover, .action-menu-panel a:focus-visible { background: #fff7f5; color: var(--brand-red); outline: none; }
        .action-menu-panel .danger-action { color: #b42318; }
        .empty-state { padding: 54px 24px; text-align: center; }
        .empty-state h3 { margin: 0 0 8px; color: var(--ink); font-size: 20px; font-weight: 900; }
        .empty-state p { margin: 0 auto 18px; max-width: 460px; color: var(--muted); font-size: 14px; font-weight: 750; }
        .empty-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
        .modal-backdrop { position: fixed; inset: 0; z-index: 30; display: none; align-items: center; justify-content: center; padding: 22px 16px; background: rgba(0, 0, 0, 0.50); }
        .modal-backdrop.open { display: flex; }
        .modal { position: relative; width: min(100%, 760px); max-height: min(820px, calc(100vh - 44px)); overflow: hidden; border-radius: 16px; background: #fff; box-shadow: 0 26px 80px rgba(15, 23, 42, 0.28); }
        .modal-wide { width: min(100%, 980px); }
        .modal-small { width: min(100%, 480px); }
        .member-form-modal { display: flex; flex-direction: column; height: min(820px, calc(100vh - 44px)); }
        .member-form-modal > form[data-member-form] { display: flex; flex: 1 1 auto; flex-direction: column; min-height: 0; }
        .modal-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; padding: 24px 28px 18px; border-bottom: 1px solid var(--line); }
        .modal-kicker { display: block; margin-bottom: 5px; color: var(--brand-red); font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.04em; }
        .modal h2 { margin: 0; color: var(--ink); font-size: 24px; line-height: 1.15; font-weight: 900; }
        .modal-close { position: absolute; top: 17px; right: 19px; width: 32px; height: 32px; padding: 0; border: 0; border-radius: 7px; background: transparent; color: #98a2b3; cursor: pointer; font-size: 26px; line-height: 1; }
        .modal-close:hover, .modal-close:focus-visible { background: var(--brand-red-soft); color: var(--brand-red); outline: none; }
        .modal-body { max-height: min(600px, calc(100vh - 186px)); overflow-y: auto; padding: 22px 28px 28px; }
        .member-form-modal > form .modal-body { flex: 1 1 auto; min-height: 0; max-height: none; }
        .member-form-modal .modal-header, .member-form-modal .modal-footer { flex: 0 0 auto; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 28px 24px; border-top: 1px solid var(--line); }
        .member-form-modal [data-member-form-submit], .member-form-modal [data-member-form-submit]:hover, .member-form-modal [data-member-form-submit]:focus-visible { color: #fff !important; }
        .details-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .member-detail { min-width: 0; padding: 13px 14px; border: 1px solid var(--line); border-radius: 10px; background: #fafafa; }
        .member-detail-label { display: block; margin-bottom: 6px; color: #98a2b3; font-size: 11px; font-weight: 900; text-transform: uppercase; }
        .member-detail-value { display: block; overflow-wrap: anywhere; color: var(--ink); font-size: 14px; font-weight: 800; }
        .form-section { margin-bottom: 22px; }
        .form-section:last-child { margin-bottom: 0; }
        .form-section h3 { margin: 0 0 12px; color: var(--ink); font-size: 15px; font-weight: 900; text-transform: uppercase; }
        .member-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
        .field.full { grid-column: 1 / -1; }
        .field input, .field select, .field textarea { width: 100%; min-height: 42px; border: 1px solid #d9dee7; border-radius: 9px; background: #fff; color: var(--ink); font-size: 14px; font-weight: 750; padding: 0 12px; outline: none; }
        .field textarea { min-height: 82px; resize: vertical; padding-top: 10px; }
        .field input:focus, .field select:focus, .field textarea:focus { border-color: var(--brand-red); box-shadow: 0 0 0 3px rgba(227, 60, 36, 0.12); }
        .field input:disabled { background: #f6f7f9; color: #98a2b3; cursor: not-allowed; }
        .field .is-invalid { border-color: #f04438; }
        .field-error { color: #b42318; font-size: 12px; font-weight: 800; }
        .form-errors { margin: 0 0 18px; padding: 12px 14px; border: 1px solid #fecaca; border-radius: 12px; background: #fef2f2; color: #991b1b; font-size: 13px; font-weight: 800; }
        .form-errors ul { margin: 8px 0 0; padding-left: 18px; }
        .import-drop { display: grid; gap: 12px; margin-bottom: 18px; padding: 20px; border: 1px dashed #cbd5e1; border-radius: 14px; background: #f8fafc; }
        .import-drop strong { color: var(--ink); font-size: 15px; font-weight: 900; }
        .import-drop span { color: var(--muted); font-size: 13px; font-weight: 750; }
        .import-drop input { min-height: 42px; border: 1px solid #d9dee7; border-radius: 9px; background: #fff; padding: 9px 10px; }
        .import-preview { margin-top: 22px; padding-top: 20px; border-top: 1px solid var(--line); }
        .preview-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px; }
        .preview-stat { border: 1px solid var(--line); border-radius: 12px; padding: 14px; background: #fff; }
        .preview-stat strong { display: block; color: var(--brand-red); font-size: 26px; line-height: 1; font-weight: 950; }
        .preview-stat span { display: block; margin-top: 6px; color: var(--muted); font-size: 12px; font-weight: 900; text-transform: uppercase; }
        .preview-table-wrap { overflow-x: auto; border: 1px solid var(--line); border-radius: 12px; }
        .preview-table { width: 100%; min-width: 720px; border-collapse: collapse; font-size: 13px; }
        .preview-table th, .preview-table td { padding: 11px 12px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
        .preview-table th { background: #fff7f5; color: #667085; font-size: 11px; font-weight: 900; text-transform: uppercase; }
        .preview-table tr:last-child td { border-bottom: 0; }
        .preview-state { display: inline-flex; align-items: center; min-height: 24px; border-radius: 999px; padding: 0 9px; font-size: 11px; font-weight: 900; }
        .preview-state.ready { background: #dcfce7; color: #166534; }
        .preview-state.issue { background: #fef2f2; color: #b42318; }
        .preview-state.duplicate { background: #fef3c7; color: #92400e; }
        .preview-messages { display: grid; gap: 3px; color: #b42318; font-weight: 750; }
        .delete-copy { margin: 0; color: var(--muted); font-size: 15px; font-weight: 750; line-height: 1.5; }
        .delete-copy strong { color: var(--ink); font-weight: 900; }
        @include('partials.table-pagination-styles')
        @media (max-width: 1100px) {
            .filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 900px) {
            .page-header, .filter-main-row { align-items: stretch; flex-direction: column; }
            .header-actions { justify-content: flex-start; }
            .search-field { min-width: 0; }
        }
        @media (max-width: 640px) {
            .page-title h1 { font-size: 30px; }
            .filter-grid, .member-form-grid, .details-grid, .preview-stats { grid-template-columns: 1fr; }
            .modal { border-radius: 14px; }
            .modal-header, .modal-body, .modal-footer { padding-left: 18px; padding-right: 18px; }
            .modal-footer { align-items: stretch; flex-direction: column-reverse; }
            .modal-footer .btn { width: 100%; }
        }
@endpush

@section('content')
@php
    $memberSummaryCards = [
        [
            'label' => 'Active Members',
            'active' => ! $archivedView && ($filters['dgroup_role'] ?? '') === '' && ($filters['status'] ?? '') === '',
            'url' => route('satellites.members', array_filter([
                'satellite' => $satellite,
                'search' => $filters['search'] ?: null,
                'sort' => $sort !== 'name' ? $sort : null,
                'direction' => $direction !== 'asc' ? $direction : null,
                'per_page' => $perPage !== 10 ? $perPage : null,
            ])),
            'value' => $activeMembersTotal ?? 0,
            'isFilter' => false,
        ],
        [
            'label' => 'Dgroup Leaders',
            'active' => ! $archivedView && ($filters['dgroup_role'] ?? '') === 'Dgroup Leader',
            'url' => ($filters['dgroup_role'] ?? '') === 'Dgroup Leader'
                ? route('satellites.members', array_filter(['satellite' => $satellite, 'search' => $filters['search'] ?: null, 'per_page' => $perPage !== 10 ? $perPage : null]))
                : route('satellites.members', array_filter(['satellite' => $satellite, 'dgroup_role' => 'Dgroup Leader', 'search' => $filters['search'] ?: null, 'per_page' => $perPage !== 10 ? $perPage : null])),
            'value' => $leadersTotal ?? 0,
            'isFilter' => true,
        ],
        [
            'label' => 'Dgroup Members',
            'active' => ! $archivedView && ($filters['dgroup_role'] ?? '') === 'Member',
            'url' => ($filters['dgroup_role'] ?? '') === 'Member'
                ? route('satellites.members', array_filter(['satellite' => $satellite, 'search' => $filters['search'] ?: null, 'per_page' => $perPage !== 10 ? $perPage : null]))
                : route('satellites.members', array_filter(['satellite' => $satellite, 'dgroup_role' => 'Member', 'search' => $filters['search'] ?: null, 'per_page' => $perPage !== 10 ? $perPage : null])),
            'value' => $dgroupMembersTotal ?? 0,
            'isFilter' => true,
        ],
        [
            'label' => 'New Members',
            'active' => ! $archivedView && ($filters['status'] ?? '') === 'New',
            'url' => ($filters['status'] ?? '') === 'New'
                ? route('satellites.members', array_filter(['satellite' => $satellite, 'search' => $filters['search'] ?: null, 'per_page' => $perPage !== 10 ? $perPage : null]))
                : route('satellites.members', array_filter(['satellite' => $satellite, 'status' => 'New', 'search' => $filters['search'] ?: null, 'per_page' => $perPage !== 10 ? $perPage : null])),
            'value' => $newMembersTotal ?? 0,
            'isFilter' => true,
        ],
    ];
@endphp
<header class="page-header">
            <div class="page-title">
                @if (auth()->user()->normalizedRole() === 'admin')
                    <a class="member-back-link" href="{{ route('admin.members') }}">Back to Members Dashboard</a>
                @endif
                <h1>{{ $satellite->display_name }} Members</h1>
                @if ($archivedView)
                    <span class="page-kicker" data-member-page-kicker>{{ $membersShown }} of {{ $membersTotal }} archived members - {{ $satellite->display_name }} Satellite</span>
                @else
                    <span class="page-kicker" data-member-page-kicker>{{ $membersShown }} of {{ $activeMembersTotal }} members - {{ $satellite->display_name }} Satellite</span>
                @endif
            </div>
            @if ($canManageSatellite)
            <div class="header-actions">
                <button type="button" class="btn btn-secondary" data-open-modal="member-import">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <path d="M17 8 12 3 7 8" />
                        <path d="M12 3v12" />
                    </svg>
                    Upload Sheet
                </button>
                <button type="button" class="btn btn-primary" data-open-member-create>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 5v14" />
                        <path d="M5 12h14" />
                    </svg>
                    Add Member
                </button>
            </div>
            @endif
        </header>

        <div class="stats-grid" style="margin-bottom: 22px;">
            @foreach ($memberSummaryCards as $card)
                @php
                    $formattedValue = number_format((int) $card['value']);
                @endphp
                <a
                    href="{{ $card['url'] }}"
                    aria-label="Filter by {{ $card['label'] }}: {{ $formattedValue }}"
                    @class([
                        'stat-card',
                        'elevate-stat-card',
                        'stat-card-link',
                        'members-stat-card',
                        'is-active' => $card['active'],
                    ])
                >
                    <span class="stat-label stat-card-label">{{ $card['label'] }}</span>
                    <div class="stat-card-value-row">
                        <strong class="stat-value stat-card-value">{{ $formattedValue }}</strong>
                        <span class="card-chevron" aria-hidden="true">{{ $card['active'] && $card['isFilter'] ? '✕' : '›' }}</span>
                    </div>
                </a>
            @endforeach
        </div>

        <x-status-tabs :current="$archivedView ? 'archived' : 'active'" :tabs="[
            ['key' => 'active', 'label' => 'Active', 'count' => $activeMembersTotal, 'url' => $viewUrl('active')],
            ['key' => 'archived', 'label' => 'Archived', 'count' => $archivedMembersTotal, 'url' => $viewUrl('archived')],
        ]" />

        @if (session('status'))
            <div class="flash flash-success">{{ session('status') }}</div>
        @endif
        <div class="flash flash-success" data-member-toast role="status" aria-live="polite" hidden></div>

        @if (session('error'))
            <div class="flash flash-error">{{ session('error') }}</div>
        @endif

        <form class="filters-card" method="GET" action="{{ route('satellites.members', $satellite) }}">
            @if ($archivedView)<input type="hidden" name="view" value="archived">@endif
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <input type="hidden" name="per_page" value="{{ $perPage }}">
            <div class="filter-main-row">
                <label class="search-field" for="member-search">
                    <span class="sr-only">Search members</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="8" />
                        <path d="m21 21-4.35-4.35" />
                    </svg>
                    <input id="member-search" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search member, leader, campus, email, or contact number">
                </label>
                <button type="submit" class="btn btn-primary">Search Members</button>
                <button type="button" class="btn btn-secondary filter-toggle" data-filter-toggle aria-controls="member-filter-panel" aria-expanded="{{ $hasActiveFilters ? 'true' : 'false' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z" />
                    </svg>
                    Filters
                </button>
                @if ($hasActiveFilters)
                    <a class="btn btn-secondary" href="{{ $clearFiltersUrl }}">Clear Filters</a>
                @endif
            </div>
            <div class="filters-panel" id="member-filter-panel" data-filter-panel @unless ($hasActiveFilters) hidden @endunless>
                <div class="filter-grid">
                    <div class="filter-field">
                        <label for="status-filter">Status</label>
                        <select id="status-filter" name="status">
                            <option value="">All</option>
                            @foreach ($statuses as $statusOption)
                                <option value="{{ $statusOption }}" @selected($filters['status'] === $statusOption)>{{ $statusOption }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-field">
                        <label for="role-filter">Dgroup Role</label>
                        <select id="role-filter" name="dgroup_role">
                            <option value="">All</option>
                            @foreach ($dgroupRoles as $roleOption)
                                <option value="{{ $roleOption }}" @selected($filters['dgroup_role'] === $roleOption)>{{ $roleOption }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-field">
                        <label for="campus-filter">Campus/School</label>
                        <select id="campus-filter" name="campus_school">
                            <option value="">All</option>
                            @foreach ($filterOptions['campuses'] as $campus)
                                <option value="{{ $campus }}" @selected($filters['campus_school'] === $campus)>{{ $campus }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-field">
                        <label for="district-filter">District</label>
                        <select id="district-filter" name="district">
                            <option value="">All</option>
                            @foreach ($filterOptions['districts'] as $district)
                                <option value="{{ $district }}" @selected($filters['district'] === $district)>{{ $district }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-field">
                        <label for="area-filter">Area</label>
                        <select id="area-filter" name="area">
                            <option value="">All</option>
                            @foreach ($filterOptions['areas'] as $area)
                                <option value="{{ $area }}" @selected($filters['area'] === $area)>{{ $area }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            @if ($hasActiveFilters)
                <div class="filter-chips" aria-label="Active filters">
                    @if ($filters['search'] !== '')
                        <span class="filter-chip">Search: {{ $filters['search'] }}</span>
                    @endif
                    @if ($filters['status'] !== '')
                        <span class="filter-chip">Status: {{ $filters['status'] }}</span>
                    @endif
                    @if ($filters['dgroup_role'] !== '')
                        <span class="filter-chip">Role: {{ $filters['dgroup_role'] }}</span>
                    @endif
                    @if ($filters['campus_school'] !== '')
                        <span class="filter-chip">Campus: {{ $filters['campus_school'] }}</span>
                    @endif
                    @if ($filters['district'] !== '')
                        <span class="filter-chip">District: {{ $filters['district'] }}</span>
                    @endif
                    @if ($filters['area'] !== '')
                        <span class="filter-chip">Area: {{ $filters['area'] }}</span>
                    @endif
                </div>
            @endif
        </form>

        <section class="directory-card" aria-labelledby="member-directory-title">
            <h2 id="member-directory-title" class="sr-only">Member Directory</h2>
            <div class="table-scroll">
                <table class="members-table">
                    <thead>
                        <tr>
                            <th>
                                <a class="sortable-link" href="{{ $sortUrl('name') }}">
                                    Member
                                    <span class="sort-mark {{ $sortMark('name') }}" aria-hidden="true">{{ $sortMark('name') === 'up' ? '^' : ($sortMark('name') === 'down' ? 'v' : '-') }}</span>
                                </a>
                            </th>
                            <th>
                                <a class="sortable-link" href="{{ $sortUrl('role') }}">
                                    Dgroup Role
                                    <span class="sort-mark {{ $sortMark('role') }}" aria-hidden="true">{{ $sortMark('role') === 'up' ? '^' : ($sortMark('role') === 'down' ? 'v' : '-') }}</span>
                                </a>
                            </th>
                            <th>
                                <a class="sortable-link" href="{{ $sortUrl('school') }}">
                                    Campus/School
                                    <span class="sort-mark {{ $sortMark('school') }}" aria-hidden="true">{{ $sortMark('school') === 'up' ? '^' : ($sortMark('school') === 'down' ? 'v' : '-') }}</span>
                                </a>
                            </th>
                            <th>Dgroup Leader</th>
                            <th>
                                <a class="sortable-link" href="{{ $sortUrl('status') }}">
                                    Status
                                    <span class="sort-mark {{ $sortMark('status') }}" aria-hidden="true">{{ $sortMark('status') === 'up' ? '^' : ($sortMark('status') === 'down' ? 'v' : '-') }}</span>
                                </a>
                            </th>
                            @if ($archivedView)<th><a class="sortable-link" href="{{ $sortUrl('archived_at') }}">Archived on</a></th>@endif
                            <th class="actions-cell">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($members as $member)
                            @php
                                $memberDetailsJson = json_encode($member->detailPayload(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
                                $memberFormJson = json_encode($member->formPayload(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
                                $updateUrl = route('satellites.members.update', [$satellite, $member]);
                                $deleteUrl = route('satellites.members.destroy', [$satellite, $member]);
                            @endphp
                            <tr data-member-row data-member-id="{{ $member->id }}">
                                <td>
                                    <div class="member-name-cell">
                                        <button type="button" class="member-details-button" data-member-details='{!! $memberDetailsJson !!}' data-member-edit='{!! $memberFormJson !!}' data-member-update-url="{{ $updateUrl }}" aria-label="View details for {{ $member->display_name }}">
                                            <svg class="member-eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </button>
                                        <div class="member-name-stack">
                                            <span class="member-name-text">{{ $member->display_name }}</span>
                                            @if ($member->email_address || $member->area)
                                                <span class="member-submeta">{{ collect([$member->email_address, $member->area])->filter()->implode(' · ') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $member->display_role }}</td>
                                <td>{{ $member->campus_school ?: '-' }}</td>
                                <td>
                                    @if ($member->leader?->archived_at)
                                        {{ $member->leader->display_name }} (archived)
                                    @elseif ($member->dgroup_leader_name && in_array($member->dgroup_leader_name, $archivedLeaderNames, true))
                                        {{ $member->dgroup_leader_name }} (archived)
                                    @elseif ($member->dgroup_leader_name)
                                        {{ $member->dgroup_leader_name }}
                                    @elseif ($member->dgroup_role === 'Dgroup Leader')
                                        @php
                                            $dgroupsLed = (int) ($member->weekly_dgroups_led ?? 0);
                                            $activeDisciples = (int) ($member->active_disciples ?? 0);
                                        @endphp
                                        @if ($dgroupsLed > 0 || $activeDisciples > 0)
                                            <span class="leader-load-pill">
                                                Leads {{ $dgroupsLed }} {{ \Illuminate\Support\Str::plural('Dgroup', $dgroupsLed) }} &middot; {{ $activeDisciples }} {{ \Illuminate\Support\Str::plural('Disciple', $activeDisciples) }}
                                            </span>
                                        @else
                                            <span class="leader-load-pill">Primary Dgroup Leader</span>
                                        @endif
                                    @else
                                        <span class="leader-empty">-</span>
                                    @endif
                                </td>
                                <td><span class="status-pill status-{{ $member->status_class }}">{{ $member->status ?: 'Active' }}</span></td>
                                @if ($archivedView)<td class="member-archive-date">{{ $member->archived_at?->format('M d, Y') }}</td>@endif
                                <td class="actions-cell">
                                    <div class="action-menu" data-action-menu>
                                        <button type="button" class="row-action-button" data-action-menu-trigger aria-controls="member-actions-{{ $member->id }}" aria-expanded="false" aria-label="Actions for {{ $member->display_name }}">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <circle cx="12" cy="12" r="1" />
                                                <circle cx="19" cy="12" r="1" />
                                                <circle cx="5" cy="12" r="1" />
                                            </svg>
                                        </button>
                                        <div id="member-actions-{{ $member->id }}" class="action-menu-panel">
                                            <button type="button" data-member-details='{!! $memberDetailsJson !!}' data-member-edit='{!! $memberFormJson !!}' data-member-update-url="{{ $updateUrl }}" aria-label="View details for {{ $member->display_name }}">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                                                    <circle cx="12" cy="12" r="3" />
                                                </svg>
                                                View Details
                                            </button>
                                            <a href="{{ route('satellites.engagement.show', [$satellite, $member]) }}">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M3 3v18h18" />
                                                    <path d="m19 9-5 5-4-4-3 3" />
                                                </svg>
                                                View Engagement
                                            </a>
                                            <a href="{{ route('satellites.follow-ups.index', [$satellite, 'search' => $member->display_name]) }}">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                                                </svg>
                                                Follow-Up History
                                            </a>
                                            @if ($canUpdateMembers)
                                            <button type="button" data-open-member-edit data-member-edit='{!! $memberFormJson !!}' data-member-update-url="{{ $updateUrl }}" aria-label="Edit member {{ $member->display_name }}">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M12 20h9" />
                                                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                                </svg>
                                                Edit Member
                                            </button>
                                            @endif
                                            @if ($archivedView && $canUpdateMembers)
                                                <form method="POST" action="{{ route('satellites.members.restore', [$satellite, $member]) }}" data-confirm-message="Restore {{ $member->display_name }} to the active members list?">
                                                    @csrf
                                                    <button type="submit" aria-label="Restore member {{ $member->display_name }}">Restore</button>
                                                </form>
                                            @endif
                                            @if (!$archivedView && $canManageSatellite)
                                            <button type="button" class="danger-action" data-open-member-delete data-member-delete-url="{{ $deleteUrl }}" data-member-name="{{ $member->display_name }}" aria-label="Delete member {{ $member->display_name }}">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M3 6h18" />
                                                    <path d="M8 6V4h8v2" />
                                                    <path d="M19 6l-1 14H6L5 6" />
                                                </svg>
                                                Delete Member
                                            </button>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $archivedView ? 7 : 6 }}">
                                    <div class="empty-state">
                                        @if ($membersTotal === 0)
                                            <h3>{{ $archivedView ? 'No archived members in this satellite.' : 'No members in this satellite yet.' }}</h3>
                                            @if ($canManageSatellite)
                                            <p>Add the first {{ $satellite->display_name }} member manually or upload a CSV/XLSX sheet to start the directory.</p>
                                            <div class="empty-actions">
                                                <button type="button" class="btn btn-primary" data-open-member-create>Add Member</button>
                                                <button type="button" class="btn btn-secondary" data-open-modal="member-import">Upload Sheet</button>
                                            </div>
                                            @endif
                                        @else
                                            <h3>No members found</h3>
                                            <p>Try changing your search or filters to see more {{ $satellite->display_name }} members.</p>
                                            <a class="btn btn-secondary" href="{{ $clearFiltersUrl }}">Clear Filters</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('partials.table-pagination', ['paginator' => $members, 'pageSizes' => $pageSizes, 'currentPageSize' => $perPage])
        </section>
@endsection

@push('scripts')
<div class="modal-backdrop" data-modal="member-details" aria-hidden="true">
    <section class="modal modal-wide" role="dialog" aria-modal="true" aria-labelledby="member-details-title">
        <div class="modal-header">
            <div>
                <span class="modal-kicker">Member details</span>
                <h2 id="member-details-title">Member Details</h2>
            </div>
        </div>
        <button type="button" class="modal-close" data-modal-close aria-label="Close member details">&times;</button>
        <div class="modal-body">
            <div class="details-grid">
                <div class="member-detail"><span class="member-detail-label">Status</span><span class="member-detail-value" data-member-detail="status"></span></div>
                <div class="member-detail"><span class="member-detail-label">Dgroup Role</span><span class="member-detail-value" data-member-detail="role"></span></div>
                <div class="member-detail"><span class="member-detail-label">Last Name</span><span class="member-detail-value" data-member-detail="lastName"></span></div>
                <div class="member-detail"><span class="member-detail-label">First Name</span><span class="member-detail-value" data-member-detail="firstName"></span></div>
                <div class="member-detail"><span class="member-detail-label">Full Name</span><span class="member-detail-value" data-member-detail="fullName"></span></div>
                <div class="member-detail"><span class="member-detail-label">Gender</span><span class="member-detail-value" data-member-detail="gender"></span></div>
                <div class="member-detail"><span class="member-detail-label">Email Address</span><span class="member-detail-value" data-member-detail="email"></span></div>
                <div class="member-detail"><span class="member-detail-label">Contact Number</span><span class="member-detail-value" data-member-detail="contact"></span></div>
                <div class="member-detail"><span class="member-detail-label">Facebook Link</span><span class="member-detail-value" data-member-detail="facebook"></span></div>
                <div class="member-detail"><span class="member-detail-label">Birthdate</span><span class="member-detail-value" data-member-detail="birthdate"></span></div>
                <div class="member-detail"><span class="member-detail-label">Age</span><span class="member-detail-value" data-member-detail="age"></span></div>
                <div class="member-detail"><span class="member-detail-label">District</span><span class="member-detail-value" data-member-detail="district"></span></div>
                <div class="member-detail"><span class="member-detail-label">Area</span><span class="member-detail-value" data-member-detail="area"></span></div>
                <div class="member-detail"><span class="member-detail-label">Campus/School</span><span class="member-detail-value" data-member-detail="school"></span></div>
                <div class="member-detail"><span class="member-detail-label">Name of Dgroup Leader</span><span class="member-detail-value" data-member-detail="leader"></span></div>
                <div class="member-detail"><span class="member-detail-label">No. of Active Disciples</span><span class="member-detail-value" data-member-detail="activeDisciples"></span></div>
                <div class="member-detail"><span class="member-detail-label">Year Joined</span><span class="member-detail-value" data-member-detail="yearJoined"></span></div>
                <div class="member-detail"><span class="member-detail-label">Ministry Involvement</span><span class="member-detail-value" data-member-detail="ministryInvolvement"></span></div>
                <div class="member-detail"><span class="member-detail-label">How many Dgroups do you lead each week?</span><span class="member-detail-value" data-member-detail="weeklyDgroupsLed"></span></div>
                <div class="member-detail"><span class="member-detail-label">Generation</span><span class="member-detail-value" data-member-detail="generation"></span></div>
                <div class="member-detail"><span class="member-detail-label">Refresh Date</span><span class="member-detail-value" data-member-detail="refreshDate"></span></div>
                <div class="member-detail"><span class="member-detail-label">Year</span><span class="member-detail-value" data-member-detail="year"></span></div>
                <div class="member-detail"><span class="member-detail-label">Month</span><span class="member-detail-value" data-member-detail="month"></span></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
            @if ($canUpdateMembers)
            <button type="button" class="btn btn-primary" data-edit-from-details>Edit Member</button>
            @endif
        </div>
    </section>
</div>

<div class="modal-backdrop" data-modal="member-form" aria-hidden="true">
    <section class="modal modal-wide member-form-modal" role="dialog" aria-modal="true" aria-labelledby="member-form-title">
        <div class="modal-header">
            <div>
                <span class="modal-kicker" data-member-form-kicker>Member record</span>
                <h2 id="member-form-title" data-member-form-title>Add Member</h2>
            </div>
        </div>
        <button type="button" class="modal-close" data-modal-close aria-label="Close member form">&times;</button>
        <form method="POST" action="{{ route('satellites.members.store', $satellite) }}" data-member-form data-store-url="{{ route('satellites.members.store', $satellite) }}" data-default-district="{{ $satellite->display_name }}">
            @csrf
            <input type="hidden" name="_method" value="" data-member-form-method disabled>
            <div class="modal-body">
                @if ($memberFormErrorBag->any())
                    <div class="form-errors" role="alert" tabindex="-1" data-validation-summary>
                        Please check the highlighted member fields.
                        <ul>
                            @foreach ($memberFormErrorBag->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="form-section">
                    <h3>Personal</h3>
                    <div class="member-form-grid">
                        <div class="field">
                            <label for="member-status">Status *</label>
                            <select id="member-status" name="status" @class(['is-invalid' => $memberFormErrorBag->has('status')]) @if ($memberFormErrorBag->has('status')) aria-invalid="true" aria-describedby="member-status-error" @endif required>
                                @foreach ($statuses as $statusOption)
                                    <option value="{{ $statusOption }}" @selected(old('status', 'Active') === $statusOption)>{{ $statusOption }}</option>
                                @endforeach
                            </select>
                            @if ($memberFormErrorBag->has('status'))<span id="member-status-error" class="field-error">{{ $memberFormErrorBag->first('status') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-role">Dgroup Role *</label>
                            <select id="member-role" name="dgroup_role" @class(['is-invalid' => $memberFormErrorBag->has('dgroup_role')]) @if ($memberFormErrorBag->has('dgroup_role')) aria-invalid="true" aria-describedby="member-role-error" @endif required>
                                @foreach ($dgroupRoles as $roleOption)
                                    <option value="{{ $roleOption }}" @selected(old('dgroup_role', 'Member') === $roleOption)>{{ $roleOption }}</option>
                                @endforeach
                            </select>
                            @if ($memberFormErrorBag->has('dgroup_role'))<span id="member-role-error" class="field-error">{{ $memberFormErrorBag->first('dgroup_role') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-first-name">First Name *</label>
                            <input id="member-first-name" name="first_name" value="{{ old('first_name') }}" @class(['is-invalid' => $memberFormErrorBag->has('first_name')]) @if ($memberFormErrorBag->has('first_name')) aria-invalid="true" aria-describedby="member-first-name-error" @endif required>
                            @if ($memberFormErrorBag->has('first_name'))<span id="member-first-name-error" class="field-error">{{ $memberFormErrorBag->first('first_name') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-last-name">Last Name *</label>
                            <input id="member-last-name" name="last_name" value="{{ old('last_name') }}" @class(['is-invalid' => $memberFormErrorBag->has('last_name')]) @if ($memberFormErrorBag->has('last_name')) aria-invalid="true" aria-describedby="member-last-name-error" @endif required>
                            @if ($memberFormErrorBag->has('last_name'))<span id="member-last-name-error" class="field-error">{{ $memberFormErrorBag->first('last_name') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-full-name">Full Name</label>
                            <input id="member-full-name" name="full_name" value="{{ old('full_name') }}" @class(['is-invalid' => $memberFormErrorBag->has('full_name')]) @if ($memberFormErrorBag->has('full_name')) aria-invalid="true" aria-describedby="member-full-name-error" @endif>
                            @if ($memberFormErrorBag->has('full_name'))<span id="member-full-name-error" class="field-error">{{ $memberFormErrorBag->first('full_name') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-gender">Gender</label>
                            <select id="member-gender" name="gender" @class(['is-invalid' => $memberFormErrorBag->has('gender')]) @if ($memberFormErrorBag->has('gender')) aria-invalid="true" aria-describedby="member-gender-error" @endif>
                                <option value="">Select</option>
                                @foreach ($genders as $genderOption)
                                    <option value="{{ $genderOption }}" @selected(old('gender') === $genderOption)>{{ $genderOption }}</option>
                                @endforeach
                            </select>
                            @if ($memberFormErrorBag->has('gender'))<span id="member-gender-error" class="field-error">{{ $memberFormErrorBag->first('gender') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-birthdate">Birthdate</label>
                            <input id="member-birthdate" type="date" name="birthdate" value="{{ old('birthdate') }}" @class(['is-invalid' => $memberFormErrorBag->has('birthdate')]) @if ($memberFormErrorBag->has('birthdate')) aria-invalid="true" aria-describedby="member-birthdate-error" @endif>
                            @if ($memberFormErrorBag->has('birthdate'))<span id="member-birthdate-error" class="field-error">{{ $memberFormErrorBag->first('birthdate') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-age">Age</label>
                            <input id="member-age" type="number" min="0" max="130" name="age" value="{{ old('age') }}" @class(['is-invalid' => $memberFormErrorBag->has('age')]) @if ($memberFormErrorBag->has('age')) aria-invalid="true" aria-describedby="member-age-error" @endif>
                            @if ($memberFormErrorBag->has('age'))<span id="member-age-error" class="field-error">{{ $memberFormErrorBag->first('age') }}</span>@endif
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Contact</h3>
                    <div class="member-form-grid">
                        <div class="field">
                            <label for="member-email">Email Address</label>
                            <input id="member-email" type="email" name="email_address" value="{{ old('email_address') }}" @class(['is-invalid' => $memberFormErrorBag->has('email_address')]) @if ($memberFormErrorBag->has('email_address')) aria-invalid="true" aria-describedby="member-email-error" @endif>
                            @if ($memberFormErrorBag->has('email_address'))<span id="member-email-error" class="field-error">{{ $memberFormErrorBag->first('email_address') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-contact">Contact Number</label>
                            <input id="member-contact" name="contact_number" value="{{ old('contact_number') }}" @class(['is-invalid' => $memberFormErrorBag->has('contact_number')]) @if ($memberFormErrorBag->has('contact_number')) aria-invalid="true" aria-describedby="member-contact-error" @endif>
                            @if ($memberFormErrorBag->has('contact_number'))<span id="member-contact-error" class="field-error">{{ $memberFormErrorBag->first('contact_number') }}</span>@endif
                        </div>
                        <div class="field full">
                            <label for="member-facebook">Facebook Link</label>
                            <input id="member-facebook" type="url" name="facebook_link" value="{{ old('facebook_link') }}" @class(['is-invalid' => $memberFormErrorBag->has('facebook_link')]) @if ($memberFormErrorBag->has('facebook_link')) aria-invalid="true" aria-describedby="member-facebook-error" @endif>
                            @if ($memberFormErrorBag->has('facebook_link'))<span id="member-facebook-error" class="field-error">{{ $memberFormErrorBag->first('facebook_link') }}</span>@endif
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Dgroup</h3>
                    <div class="member-form-grid">
                        <div class="field">
                            <label for="member-leader">Name of Dgroup Leader</label>
                            <select id="member-leader" name="leader_member_id" @if ($memberFormErrorBag->has('leader_member_id')) aria-invalid="true" aria-describedby="member-leader-error" @endif>
                                <option value="">Unassigned / unresolved</option>
                                @foreach ($leaderOptions as $leaderOption)
                                    <option value="{{ $leaderOption->id }}" @selected((string) old('leader_member_id') === (string) $leaderOption->id)>{{ $leaderOption->display_name }} (#{{ $leaderOption->id }})</option>
                                @endforeach
                            </select>
                            <small>Leaders in this district only. Unresolved historical names stay unchanged until assigned.</small>
                            @if ($memberFormErrorBag->has('leader_member_id'))<span id="member-leader-error" class="field-error">{{ $memberFormErrorBag->first('leader_member_id') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-active-disciples">No. of Active Disciples</label>
                            <input id="member-active-disciples" type="number" min="0" name="active_disciples" value="{{ old('active_disciples') }}" @class(['is-invalid' => $memberFormErrorBag->has('active_disciples')]) @if ($memberFormErrorBag->has('active_disciples')) aria-invalid="true" aria-describedby="member-active-disciples-error" @endif>
                            @if ($memberFormErrorBag->has('active_disciples'))<span id="member-active-disciples-error" class="field-error">{{ $memberFormErrorBag->first('active_disciples') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-weekly-dgroups">How many Dgroups do you lead each week?</label>
                            <input id="member-weekly-dgroups" type="number" min="0" name="weekly_dgroups_led" value="{{ old('weekly_dgroups_led') }}" @class(['is-invalid' => $memberFormErrorBag->has('weekly_dgroups_led')]) @if ($memberFormErrorBag->has('weekly_dgroups_led')) aria-invalid="true" aria-describedby="member-weekly-dgroups-error" @endif>
                            @if ($memberFormErrorBag->has('weekly_dgroups_led'))<span id="member-weekly-dgroups-error" class="field-error">{{ $memberFormErrorBag->first('weekly_dgroups_led') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-generation">Generation</label>
                            <input id="member-generation" name="generation" value="{{ old('generation') }}" @class(['is-invalid' => $memberFormErrorBag->has('generation')]) @if ($memberFormErrorBag->has('generation')) aria-invalid="true" aria-describedby="member-generation-error" @endif>
                            @if ($memberFormErrorBag->has('generation'))<span id="member-generation-error" class="field-error">{{ $memberFormErrorBag->first('generation') }}</span>@endif
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Location</h3>
                    <div class="member-form-grid">
                        <div class="field">
                            <label for="member-district">District *</label>
                            <input id="member-district" name="district" value="{{ old('district', $satellite->display_name) }}" @class(['is-invalid' => $memberFormErrorBag->has('district')]) @if ($memberFormErrorBag->has('district')) aria-invalid="true" aria-describedby="member-district-error" @endif required>
                            @if ($memberFormErrorBag->has('district'))<span id="member-district-error" class="field-error">{{ $memberFormErrorBag->first('district') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-area">Area</label>
                            <input id="member-area" name="area" value="{{ old('area') }}" @class(['is-invalid' => $memberFormErrorBag->has('area')]) @if ($memberFormErrorBag->has('area')) aria-invalid="true" aria-describedby="member-area-error" @endif>
                            @if ($memberFormErrorBag->has('area'))<span id="member-area-error" class="field-error">{{ $memberFormErrorBag->first('area') }}</span>@endif
                        </div>
                        <div class="field full">
                            <label for="member-campus">Campus/School *</label>
                            <input id="member-campus" name="campus_school" value="{{ old('campus_school') }}" @class(['is-invalid' => $memberFormErrorBag->has('campus_school')]) @if ($memberFormErrorBag->has('campus_school')) aria-invalid="true" aria-describedby="member-campus-error" @endif required>
                            @if ($memberFormErrorBag->has('campus_school'))<span id="member-campus-error" class="field-error">{{ $memberFormErrorBag->first('campus_school') }}</span>@endif
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Reporting</h3>
                    <div class="member-form-grid">
                        <div class="field">
                            <label for="member-year-joined">Year Joined</label>
                            <input id="member-year-joined" type="number" min="1900" max="{{ now()->year + 1 }}" name="year_joined" value="{{ old('year_joined') }}" @class(['is-invalid' => $memberFormErrorBag->has('year_joined')]) @if ($memberFormErrorBag->has('year_joined')) aria-invalid="true" aria-describedby="member-year-joined-error" @endif>
                            @if ($memberFormErrorBag->has('year_joined'))<span id="member-year-joined-error" class="field-error">{{ $memberFormErrorBag->first('year_joined') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-ministry">Ministry Involvement</label>
                            <input id="member-ministry" name="ministry_involvement" value="{{ old('ministry_involvement') }}" @class(['is-invalid' => $memberFormErrorBag->has('ministry_involvement')]) @if ($memberFormErrorBag->has('ministry_involvement')) aria-invalid="true" aria-describedby="member-ministry-error" @endif>
                            @if ($memberFormErrorBag->has('ministry_involvement'))<span id="member-ministry-error" class="field-error">{{ $memberFormErrorBag->first('ministry_involvement') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-refresh-date">Refresh Date</label>
                            <input id="member-refresh-date" type="date" name="refresh_date" value="{{ old('refresh_date') }}" @class(['is-invalid' => $memberFormErrorBag->has('refresh_date')]) @if ($memberFormErrorBag->has('refresh_date')) aria-invalid="true" aria-describedby="member-refresh-date-error" @endif>
                            @if ($memberFormErrorBag->has('refresh_date'))<span id="member-refresh-date-error" class="field-error">{{ $memberFormErrorBag->first('refresh_date') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-year">Year</label>
                            <input id="member-year" type="number" min="1900" max="{{ now()->year + 1 }}" name="year" value="{{ old('year') }}" @class(['is-invalid' => $memberFormErrorBag->has('year')]) @if ($memberFormErrorBag->has('year')) aria-invalid="true" aria-describedby="member-year-error" @endif>
                            @if ($memberFormErrorBag->has('year'))<span id="member-year-error" class="field-error">{{ $memberFormErrorBag->first('year') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-month">Month</label>
                            <select id="member-month" name="month" @class(['is-invalid' => $memberFormErrorBag->has('month')]) @if ($memberFormErrorBag->has('month')) aria-invalid="true" aria-describedby="member-month-error" @endif>
                                <option value="">Select</option>
                                @foreach (['January','February','March','April','May','June','July','August','September','October','November','December'] as $monthOption)
                                    <option value="{{ $monthOption }}" @selected(old('month') === $monthOption)>{{ $monthOption }}</option>
                                @endforeach
                            </select>
                            @if ($memberFormErrorBag->has('month'))<span id="member-month-error" class="field-error">{{ $memberFormErrorBag->first('month') }}</span>@endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary" data-member-form-submit data-loading-text="Saving...">Save Member</button>
            </div>
        </form>
    </section>
</div>

<div class="modal-backdrop" data-modal="member-import" aria-hidden="true">
    <section class="modal modal-wide" role="dialog" aria-modal="true" aria-labelledby="member-import-title">
        <div class="modal-header">
            <div>
                <span class="modal-kicker">Spreadsheet import</span>
                <h2 id="member-import-title">Upload Member Sheet</h2>
            </div>
        </div>
        <button type="button" class="modal-close" data-modal-close aria-label="Close upload sheet">&times;</button>
        <div class="modal-body">
            @if ($importErrorBag->any())
                <div class="form-errors" role="alert" tabindex="-1" data-validation-summary>
                    Please check the uploaded member sheet.
                    <ul>
                        @foreach ($importErrorBag->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('satellites.members.import-preview', $satellite) }}" enctype="multipart/form-data" data-prevent-double-submit>
                @csrf
                <label class="import-drop" for="member-sheet">
                    <strong>Choose CSV or XLSX file</strong>
                    <span>Columns are matched by header name. Use the template for the safest import.</span>
                    <input id="member-sheet" type="file" name="member_sheet" accept=".csv,.txt,.xlsx" required @if ($importErrorBag->has('member_sheet')) aria-invalid="true" aria-describedby="member-sheet-error" @endif>
                    @if ($importErrorBag->has('member_sheet'))<span id="member-sheet-error" class="field-error">{{ $importErrorBag->first('member_sheet') }}</span>@endif
                </label>
                <div class="header-actions" style="justify-content: flex-start;">
                    <button type="submit" class="btn btn-primary" data-loading-text="Checking...">Preview Import</button>
                    <a class="btn btn-secondary" href="{{ route('satellites.members.import-template', $satellite) }}">Download Template</a>
                </div>
            </form>

            @if ($importPreview)
                <div class="import-preview">
                    <div class="preview-stats">
                        <div class="preview-stat"><strong>{{ $importPreview['validCount'] }}</strong><span>Ready</span></div>
                        <div class="preview-stat"><strong>{{ $importPreview['issueCount'] }}</strong><span>With issues</span></div>
                        <div class="preview-stat"><strong>{{ $importPreview['duplicateCount'] }}</strong><span>Duplicates</span></div>
                    </div>
                    <div class="preview-table-wrap">
                        <table class="preview-table">
                            <thead>
                                <tr>
                                    <th>Row</th>
                                    <th>Full Name</th>
                                    <th>Status</th>
                                    <th>Dgroup Role</th>
                                    <th>Campus/School</th>
                                    <th>Result</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($importPreview['rows'] as $previewRow)
                                    <tr>
                                        <td>{{ $previewRow['number'] }}</td>
                                        <td>{{ $previewRow['name'] }}</td>
                                        <td>{{ $previewRow['status'] }}</td>
                                        <td>{{ $previewRow['role'] }}</td>
                                        <td>{{ $previewRow['school'] }}</td>
                                        <td>
                                            <span class="preview-state {{ $previewRow['state'] }}">{{ ucfirst($previewRow['state']) }}</span>
                                            @if (! empty($previewRow['messages']))
                                                <div class="preview-messages">
                                                    @foreach ($previewRow['messages'] as $message)
                                                        <span>{{ $message }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
            @if ($importPreview && $importPreview['validCount'] > 0)
                <form method="POST" action="{{ route('satellites.members.import', $satellite) }}" data-prevent-double-submit>
                    @csrf
                    <button type="submit" class="btn btn-primary" data-loading-text="Importing...">Confirm Import</button>
                </form>
            @endif
        </div>
    </section>
</div>

<div class="modal-backdrop" data-modal="member-delete" aria-hidden="true">
    <section class="modal modal-small" role="dialog" aria-modal="true" aria-labelledby="member-delete-title">
        <div class="modal-header">
            <div>
                <span class="modal-kicker">Delete member</span>
                <h2 id="member-delete-title">Confirm Delete</h2>
            </div>
        </div>
        <button type="button" class="modal-close" data-modal-close aria-label="Close delete confirmation">&times;</button>
        <form method="POST" action="#" data-member-delete-form data-prevent-double-submit>
            @csrf
            @method('DELETE')
            <div class="modal-body">
                <p class="delete-copy">Delete <strong data-member-delete-name></strong> from {{ $satellite->display_name }} Members? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-danger" data-loading-text="Deleting...">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg>
                    Delete Member
                </button>
            </div>
        </form>
    </section>
</div>

<div class="modal-backdrop" data-modal="member-inactive-confirm" aria-hidden="true">
    <section class="modal modal-small" role="dialog" aria-modal="true" aria-labelledby="member-inactive-confirm-title">
        <div class="modal-header">
            <div>
                <span class="modal-kicker">Confirm status change</span>
                <h2 id="member-inactive-confirm-title">Mark Inactive?</h2>
            </div>
        </div>
        <div class="modal-body">
            <p class="delete-copy" id="member-inactive-confirm-text"></p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-inactive-confirm-cancel>Cancel</button>
            <button type="button" class="btn btn-primary" data-inactive-confirm-accept>Confirm</button>
        </div>
    </section>
</div>

<script>
    (() => {
        const modalBackdrops = Array.from(document.querySelectorAll('[data-modal]'));
        const memberFormModal = document.querySelector('[data-modal="member-form"]');
        const memberDetailsModal = document.querySelector('[data-modal="member-details"]');
        const memberImportModal = document.querySelector('[data-modal="member-import"]');
        const memberDeleteModal = document.querySelector('[data-modal="member-delete"]');
        const inactiveConfirmModal = document.querySelector('[data-modal="member-inactive-confirm"]');
        const inactiveConfirmText = document.querySelector('#member-inactive-confirm-text');
        const inactiveConfirmCancel = document.querySelector('[data-inactive-confirm-cancel]');
        const inactiveConfirmAccept = document.querySelector('[data-inactive-confirm-accept]');
        const memberToast = document.querySelector('[data-member-toast]');
        const pageKicker = document.querySelector('[data-member-page-kicker]');
        const isArchivedView = {{ $archivedView ? 'true' : 'false' }};
        const satelliteName = @json($satellite->display_name);
        const memberForm = document.querySelector('[data-member-form]');
        const methodInput = document.querySelector('[data-member-form-method]');
        const formTitle = document.querySelector('[data-member-form-title]');
        const formKicker = document.querySelector('[data-member-form-kicker]');
        const formSubmit = document.querySelector('[data-member-form-submit]');
        const detailsTitle = document.querySelector('#member-details-title');
        const editFromDetails = document.querySelector('[data-edit-from-details]');
        const deleteForm = document.querySelector('[data-member-delete-form]');
        const deleteName = document.querySelector('[data-member-delete-name]');
        const filterPanel = document.querySelector('[data-filter-panel]');
        const filterToggle = document.querySelector('[data-filter-toggle]');
        let lastModalTrigger = null;
        let activeDetailsEditData = null;
        let activeDetailsEditUrl = null;
        let fullNameTouched = false;
        let currentEditMemberId = null;
        let currentEditMemberName = '';
        let currentEditInitialStatus = 'Active';

        const readJson = (value) => {
            try {
                return JSON.parse(value || '{}');
            } catch (error) {
                return {};
            }
        };

        const displayValue = (value) => {
            return value === null || value === undefined || value === '' ? '-' : String(value);
        };

        const closeActionMenus = () => {
            document.querySelectorAll('[data-action-menu].open').forEach((menu) => {
                const trigger = menu.querySelector('[data-action-menu-trigger]');
                if (menu.contains(document.activeElement)) trigger?.focus();
                menu.classList.remove('open');
                trigger?.setAttribute('aria-expanded', 'false');
            });
        };

        const openModal = (modal, trigger = null) => {
            if (!modal) return;
            lastModalTrigger = trigger || document.activeElement;
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            const focusTarget = modal.querySelector('[data-modal-close], input, select, button');
            focusTarget?.focus();
        };

        const closeModal = (modal) => {
            if (!modal) return;
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');

            if (!document.querySelector('.modal-backdrop.open')) {
                document.body.style.overflow = '';
            }

            if (lastModalTrigger instanceof HTMLElement) {
                lastModalTrigger.focus();
            }
        };

        const closeAllModals = () => {
            modalBackdrops.forEach((modal) => closeModal(modal));
        };

        document.querySelectorAll('[data-open-modal]').forEach((trigger) => {
            trigger.addEventListener('click', () => openModal(document.querySelector(`[data-modal="${trigger.dataset.openModal}"]`), trigger));
        });

        document.querySelectorAll('[data-modal-close]').forEach((trigger) => {
            trigger.addEventListener('click', () => closeModal(trigger.closest('[data-modal]')));
        });

        modalBackdrops.forEach((modal) => {
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal(modal);
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeAllModals();
                closeActionMenus();
            }
        });

        const setInputValue = (name, value) => {
            if (!memberForm?.elements[name]) return;
            memberForm.elements[name].value = value === null || value === undefined ? '' : value;
        };

        const updateLeaderFieldState = () => {
            if (!memberForm) return;
            const role = memberForm.elements.dgroup_role?.value;
            const leader = memberForm.elements.leader_member_id;

            if (!leader) return;

            if (role === 'Dgroup Leader') {
                leader.value = '';
                leader.disabled = true;
            } else {
                leader.disabled = false;
            }
        };

        const updateFullName = () => {
            if (!memberForm || fullNameTouched) return;
            const firstName = memberForm.elements.first_name?.value.trim() || '';
            const lastName = memberForm.elements.last_name?.value.trim() || '';
            setInputValue('full_name', [firstName, lastName].filter(Boolean).join(' '));
        };

        const resetMemberForm = () => {
            if (!memberForm) return;
            memberForm.reset();
            memberForm.action = memberForm.dataset.storeUrl;
            methodInput.disabled = true;
            methodInput.value = '';
            formTitle.textContent = 'Add Member';
            formKicker.textContent = 'Member record';
            formSubmit.textContent = 'Save Member';
            setInputValue('status', 'Active');
            setInputValue('dgroup_role', 'Member');
            setInputValue('district', memberForm.dataset.defaultDistrict || '');
            fullNameTouched = false;
            updateLeaderFieldState();
        };

        const confirmInactive = (name) => new Promise((resolve) => {
            if (!inactiveConfirmModal) {
                resolve(window.confirm(`Mark ${name} as inactive? They will be moved to Archived and won't count toward the active members.`));
                return;
            }
            if (inactiveConfirmText) {
                inactiveConfirmText.textContent = `Mark ${name} as inactive? They will be moved to Archived and won't count toward the active members.`;
            }

            let resolved = false;
            const cleanup = (answer) => {
                if (resolved) return;
                resolved = true;
                inactiveConfirmCancel?.removeEventListener('click', onCancel);
                inactiveConfirmAccept?.removeEventListener('click', onAccept);
                closeModal(inactiveConfirmModal);
                resolve(answer);
            };
            const onCancel = () => cleanup(false);
            const onAccept = () => cleanup(true);

            inactiveConfirmCancel?.addEventListener('click', onCancel);
            inactiveConfirmAccept?.addEventListener('click', onAccept);
            openModal(inactiveConfirmModal);
        });

        const showToast = (message) => {
            if (!memberToast) return;
            memberToast.textContent = message;
            memberToast.hidden = false;
            memberToast.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            setTimeout(() => {
                memberToast.hidden = true;
            }, 5000);
        };

        const updatePageCounts = (counts) => {
            const remainingRows = document.querySelectorAll('tbody tr[data-member-row]').length;
            if (pageKicker) {
                if (isArchivedView) {
                    pageKicker.textContent = `${remainingRows} of ${counts.archived} archived members - ${satelliteName} Satellite`;
                } else {
                    pageKicker.textContent = `${remainingRows} of ${counts.active} members - ${satelliteName} Satellite`;
                }
            }
        };

        const clearValidationErrors = () => {
            const summary = memberForm?.querySelector('[data-validation-summary]');
            if (summary) summary.remove();
            memberForm?.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
            memberForm?.querySelectorAll('.field-error').forEach((el) => el.remove());
        };

        const renderValidationErrors = (errors, fallbackMessage = 'Please check the highlighted member fields.') => {
            clearValidationErrors();
            const modalBody = memberForm?.querySelector('.modal-body');
            if (!modalBody) return;
            const summary = document.createElement('div');
            summary.className = 'form-errors';
            summary.setAttribute('role', 'alert');
            summary.setAttribute('tabindex', '-1');
            summary.dataset.validationSummary = 'true';

            const messages = [];
            Object.entries(errors || {}).forEach(([field, fieldErrors]) => {
                const input = memberForm?.elements[field];
                const errorText = Array.isArray(fieldErrors) ? fieldErrors[0] : fieldErrors;
                if (input instanceof HTMLElement) {
                    input.classList.add('is-invalid');
                    const errorSpan = document.createElement('span');
                    errorSpan.className = 'field-error';
                    errorSpan.id = `member-${field}-error`;
                    errorSpan.textContent = errorText;
                    input.insertAdjacentElement('afterend', errorSpan);
                }
                (Array.isArray(fieldErrors) ? fieldErrors : [fieldErrors]).forEach((msg) => {
                    messages.push(msg);
                });
            });

            if (messages.length === 0 && fallbackMessage) {
                messages.push(fallbackMessage);
            }

            summary.innerHTML = `${fallbackMessage}<ul>${messages.map((m) => `<li>${m}</li>`).join('')}</ul>`;
            modalBody.insertBefore(summary, modalBody.firstChild);
            modalBody.scrollTop = 0;
            summary.focus();
        };

        const openCreateForm = (trigger = null) => {
            resetMemberForm();
            clearValidationErrors();
            openModal(memberFormModal, trigger);
        };

        const openEditForm = (data, action, trigger = null) => {
            resetMemberForm();
            memberForm.action = action || memberForm.dataset.storeUrl;
            methodInput.disabled = false;
            methodInput.value = 'PATCH';
            formTitle.textContent = 'Edit Member';
            formKicker.textContent = displayValue(data.full_name);
            formSubmit.textContent = 'Update Member';
            currentEditMemberId = data.id || trigger?.closest('[data-member-row]')?.dataset.memberId || null;
            currentEditMemberName = displayValue(data.full_name || data.display_name || data.name);
            currentEditInitialStatus = data.status || 'Active';
            clearValidationErrors();
            Object.entries(data).forEach(([name, value]) => setInputValue(name, value));
            fullNameTouched = true;
            updateLeaderFieldState();
            openModal(memberFormModal, trigger);
        };

        document.querySelectorAll('[data-open-member-create]').forEach((trigger) => {
            trigger.addEventListener('click', () => openCreateForm(trigger));
        });

        document.querySelectorAll('[data-open-member-edit]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                closeActionMenus();
                openEditForm(readJson(trigger.dataset.memberEdit), trigger.dataset.memberUpdateUrl, trigger);
            });
        });

        document.querySelectorAll('[data-member-details]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                closeActionMenus();
                const details = readJson(trigger.dataset.memberDetails);
                activeDetailsEditData = readJson(trigger.dataset.memberEdit);
                activeDetailsEditUrl = trigger.dataset.memberUpdateUrl;
                detailsTitle.textContent = displayValue(details.fullName);

                document.querySelectorAll('[data-member-detail]').forEach((field) => {
                    field.textContent = displayValue(details[field.dataset.memberDetail]);
                });

                openModal(memberDetailsModal, trigger);
            });
        });

        editFromDetails?.addEventListener('click', () => {
            closeModal(memberDetailsModal);
            openEditForm(activeDetailsEditData || {}, activeDetailsEditUrl, editFromDetails);
        });

        document.querySelectorAll('[data-open-member-delete]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                closeActionMenus();
                deleteForm.action = trigger.dataset.memberDeleteUrl;
                deleteName.textContent = trigger.dataset.memberName || 'this member';
                openModal(memberDeleteModal, trigger);
            });
        });

        memberForm?.elements.full_name?.addEventListener('input', () => {
            fullNameTouched = true;
        });

        memberForm?.elements.first_name?.addEventListener('input', updateFullName);
        memberForm?.elements.last_name?.addEventListener('input', updateFullName);
        memberForm?.elements.dgroup_role?.addEventListener('change', updateLeaderFieldState);
        updateLeaderFieldState();

        const updateMemberAge = () => {
            const birthdate = memberForm.elements.birthdate.value;
            const ageField = memberForm.elements.age;

            if (!ageField) return;

            if (!birthdate) {
                ageField.value = '';
                return;
            }

            const birthday = new Date(`${birthdate}T00:00:00Z`);
            const today = new Date();
            let age = today.getFullYear() - birthday.getUTCFullYear();
            const monthDelta = today.getMonth() - birthday.getUTCMonth();

            if (monthDelta < 0 || (monthDelta === 0 && today.getDate() < birthday.getUTCDate())) {
                age--;
            }

            ageField.value = Number.isFinite(age) && age >= 0 ? age : '';
        };

        memberForm?.elements.birthdate?.addEventListener('input', updateMemberAge);
        memberForm?.elements.birthdate?.addEventListener('change', updateMemberAge);

        filterToggle?.addEventListener('click', () => {
            const shouldOpen = filterPanel.hidden;
            filterPanel.hidden = !shouldOpen;
            filterToggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        });

        document.querySelectorAll('[data-action-menu-trigger]').forEach((trigger) => {
            trigger.addEventListener('click', (event) => {
                event.stopPropagation();
                const menu = trigger.closest('[data-action-menu]');
                const isOpen = menu.classList.contains('open');
                closeActionMenus();
                menu.classList.toggle('open', !isOpen);
                trigger.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
            });
        });

        document.addEventListener('click', closeActionMenus);

        document.querySelectorAll('[data-auto-submit]').forEach((select) => {
            select.addEventListener('change', () => select.form?.submit());
        });

        memberForm?.addEventListener('submit', async (event) => {
            if (methodInput.value === 'PATCH') {
                event.preventDefault();
                const newStatus = memberForm.elements.status?.value;

                if (newStatus === 'Inactive' && currentEditInitialStatus !== 'Inactive') {
                    const confirmed = await confirmInactive(currentEditMemberName);
                    if (!confirmed) {
                        setInputValue('status', currentEditInitialStatus);
                        return;
                    }
                }

                formSubmit.disabled = true;
                const originalText = formSubmit.textContent;
                formSubmit.textContent = formSubmit.dataset.loadingText || 'Saving...';
                clearValidationErrors();

                try {
                    const formData = new FormData(memberForm);
                    formData.set('_method', 'PATCH');
                    if (isArchivedView) {
                        formData.set('view', 'archived');
                    }

                    const response = await fetch(memberForm.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    });

                    if (response.ok) {
                        const result = await response.json();
                        closeModal(memberFormModal);

                        if (result.archived) {
                            const row = document.querySelector(`[data-member-row][data-member-id="${currentEditMemberId}"]`);
                            if (row) {
                                row.style.transition = 'opacity 300ms ease, transform 300ms ease';
                                row.style.opacity = '0';
                                row.style.transform = 'translateX(10px)';
                                setTimeout(() => {
                                    row.remove();
                                    if (result.counts) {
                                        updatePageCounts(result.counts);
                                    }
                                }, 300);
                            } else if (result.counts) {
                                updatePageCounts(result.counts);
                            }
                            showToast('Member archived');
                        } else if (isArchivedView && newStatus !== 'Inactive') {
                            const row = document.querySelector(`[data-member-row][data-member-id="${currentEditMemberId}"]`);
                            if (row) {
                                row.style.transition = 'opacity 300ms ease, transform 300ms ease';
                                row.style.opacity = '0';
                                setTimeout(() => {
                                    row.remove();
                                    if (result.counts) {
                                        updatePageCounts(result.counts);
                                    }
                                }, 300);
                            } else if (result.counts) {
                                updatePageCounts(result.counts);
                            }
                            showToast('Member restored to the active list.');
                        } else {
                            window.location.reload();
                        }
                    } else if (response.status === 422) {
                        const data = await response.json();
                        renderValidationErrors(data.errors || {}, data.message || 'Please check the highlighted member fields.');
                    } else if (response.status === 403) {
                        renderValidationErrors({ permission: 'You do not have permission to update this member.' }, 'Permission denied.');
                    } else {
                        renderValidationErrors({ error: 'An unexpected server error occurred. Please try again.' }, 'Save failed.');
                    }
                } catch (error) {
                    renderValidationErrors({ network: 'A network error occurred. Please try again.' }, 'Connection error.');
                } finally {
                    formSubmit.disabled = false;
                    formSubmit.textContent = originalText;
                }
            } else {
                formSubmit.disabled = true;
                if (formSubmit.dataset.loadingText) {
                    formSubmit.textContent = formSubmit.dataset.loadingText;
                }
            }
        });

        document.querySelectorAll('form[data-prevent-double-submit]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const submitter = event.submitter;

                if (!(submitter instanceof HTMLButtonElement)) {
                    return;
                }

                submitter.disabled = true;

                if (submitter.dataset.loadingText) {
                    submitter.textContent = submitter.dataset.loadingText;
                }
            });
        });

        @if (session('openMemberFormModal') || $memberFormErrorBag->any())
            const failedMemberFormData = @json($oldMemberData);
            const failedMemberFormMode = @json(session('memberFormMode', 'create'));
            const failedMemberFormAction = @json(session('memberFormAction'));

            if (failedMemberFormMode === 'edit') {
                openEditForm(failedMemberFormData, failedMemberFormAction);
            } else {
                openCreateForm();
                Object.entries(failedMemberFormData).forEach(([name, value]) => setInputValue(name, value));
            }

            fullNameTouched = true;
            updateLeaderFieldState();
        @endif

        @if (session('openImportModal') || $importErrorBag->any() || $importPreview)
            openModal(memberImportModal);
        @endif
    })();
</script>
@endpush
