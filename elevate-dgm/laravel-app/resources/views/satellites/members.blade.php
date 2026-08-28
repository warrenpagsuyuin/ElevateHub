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
        'dgroup_leader_name',
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
        'sort' => $sort,
        'direction' => $direction,
        'per_page' => $perPage,
    ]);
    $oldMemberData = [];

    foreach ($memberFieldNames as $fieldName) {
        $oldMemberData[$fieldName] = old($fieldName);
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $satellite->display_name }} Members</title>
    <style>
        :root { --brand-red: #e33c24; --brand-red-dark: #c92f1b; --brand-red-soft: #fff1ee; --ink: #111; --muted: #667085; --line: #e8edf3; --surface: #fff; --page: #f3f4f6; --green-bg: #dcfce7; --green-text: #07843f; --blue-bg: #dbeafe; --blue-text: #1d4ed8; --gray-bg: #e5e7eb; --gray-text: #4b5563; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--page); color: var(--ink); }
        button, input, select, textarea { font: inherit; }
        .page { display: flex; min-height: 100vh; background: var(--page); }
        .sidebar { width: 260px; background: #000; color: #fff; display: flex; flex-direction: column; flex-shrink: 0; padding: 18px 14px; }
        .sidebar .brand { display: flex; justify-content: center; align-items: center; height: 110px; background: #111; padding: 14px; border-radius: 8px; margin-bottom: 18px; }
        .sidebar .brand-icon img { display: block; width: 228px; max-width: 100%; height: auto; object-fit: contain; }
        .menu { display: flex; flex-direction: column; gap: 8px; flex: 1; }
        .menu a { display: flex; align-items: center; gap: 12px; color: var(--brand-red); text-decoration: none; padding: 15px 18px; border-radius: 8px; font-weight: 800; font-size: 16px; transition: 0.15s ease; }
        .menu a:hover { background: rgba(227, 60, 36, 0.12); color: #ff6b53; }
        .menu a.active { background: #fff; color: #111; }
        .signout { margin-top: auto; padding-top: 20px; display: flex; justify-content: center; }
        .signout form { margin: 0; }
        .signout button { width: 170px; border: none; padding: 12px 20px; border-radius: 999px; background: var(--brand-red); color: #fff; font-weight: 800; cursor: pointer; }
        .signout button:hover { background: var(--brand-red-dark); }
        .main { flex: 1; width: 100%; padding: 30px 32px 48px; }
        .page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; margin-bottom: 20px; }
        .page-title { display: flex; flex-direction: column; gap: 7px; min-width: 0; }
        .page-title h1 { margin: 0; color: var(--ink); font-size: 34px; line-height: 1.08; font-weight: 900; }
        .page-kicker { color: var(--muted); font-size: 14px; font-weight: 800; }
        .header-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 9px; min-height: 42px; border: 0; border-radius: 999px; padding: 0 18px; color: var(--ink); background: #fff; font-size: 14px; font-weight: 900; text-decoration: none; cursor: pointer; transition: 0.15s ease; white-space: nowrap; }
        .btn svg { width: 17px; height: 17px; flex: 0 0 auto; }
        .btn-primary { background: var(--brand-red); color: #fff; box-shadow: 0 10px 22px rgba(227, 60, 36, 0.22); }
        .btn-primary:hover, .btn-primary:focus-visible { background: var(--brand-red-dark); color: #fff; outline: none; }
        .btn-secondary { border: 1px solid var(--line); background: #fff; color: var(--ink); }
        .btn-secondary:hover, .btn-secondary:focus-visible { border-color: var(--brand-red); color: var(--brand-red); outline: none; }
        .btn-danger { background: #b42318; color: #fff; }
        .btn-danger:hover, .btn-danger:focus-visible { background: #912018; outline: none; }
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
        .members-table tbody tr:last-child td { border-bottom: none; }
        .members-table tbody tr:hover { background: #fffafa; }
        .sortable-link { display: inline-flex; align-items: center; gap: 7px; color: inherit; text-decoration: none; }
        .sort-mark { width: 11px; height: 11px; color: #b9c0cc; }
        .sort-mark.up, .sort-mark.down { color: var(--brand-red); }
        .member-name-cell { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .member-name-text { display: inline-block; max-width: 290px; overflow: hidden; color: var(--ink); font-weight: 900; text-overflow: ellipsis; white-space: nowrap; }
        .member-details-button { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; padding: 0; border: 0; border-radius: 6px; background: transparent; color: #98a2b3; cursor: pointer; transition: color 0.15s ease, background 0.15s ease; }
        .member-details-button:hover, .member-details-button:focus-visible { background: var(--brand-red-soft); color: var(--brand-red); outline: none; }
        .member-details-button:focus-visible { box-shadow: 0 0 0 3px rgba(227, 60, 36, 0.16); }
        .member-eye-icon { width: 17px; height: 17px; }
        .status-pill { display: inline-flex; align-items: center; justify-content: center; min-width: 62px; height: 27px; padding: 0 12px; border-radius: 999px; font-size: 12px; line-height: 1; font-weight: 900; }
        .status-active { background: var(--green-bg); color: var(--green-text); }
        .status-new { background: var(--blue-bg); color: var(--blue-text); }
        .status-inactive { background: var(--gray-bg); color: var(--gray-text); }
        .leader-empty { color: #98a2b3; font-weight: 900; }
        .actions-cell { width: 80px; text-align: right; }
        .action-menu { position: relative; display: inline-flex; justify-content: flex-end; }
        .row-action-button { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border: 1px solid transparent; border-radius: 9px; background: transparent; color: #667085; cursor: pointer; }
        .row-action-button svg { width: 18px; height: 18px; }
        .row-action-button:hover, .row-action-button:focus-visible { border-color: var(--line); background: #fff; color: var(--brand-red); outline: none; }
        .action-menu-panel { position: absolute; right: 0; top: calc(100% + 8px); z-index: 10; display: none; min-width: 170px; overflow: hidden; border: 1px solid var(--line); border-radius: 12px; background: #fff; box-shadow: 0 18px 35px rgba(15, 23, 42, 0.18); }
        .action-menu.open .action-menu-panel { display: block; }
        .action-menu-panel button { display: flex; align-items: center; gap: 9px; width: 100%; border: 0; background: transparent; color: #344054; padding: 11px 13px; font-size: 13px; font-weight: 850; text-align: left; cursor: pointer; }
        .action-menu-panel button svg { width: 15px; height: 15px; }
        .action-menu-panel button:hover, .action-menu-panel button:focus-visible { background: #fff7f5; color: var(--brand-red); outline: none; }
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
        .modal-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; padding: 24px 28px 18px; border-bottom: 1px solid var(--line); }
        .modal-kicker { display: block; margin-bottom: 5px; color: var(--brand-red); font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.04em; }
        .modal h2 { margin: 0; color: var(--ink); font-size: 24px; line-height: 1.15; font-weight: 900; }
        .modal-close { position: absolute; top: 17px; right: 19px; width: 32px; height: 32px; padding: 0; border: 0; border-radius: 7px; background: transparent; color: #98a2b3; cursor: pointer; font-size: 26px; line-height: 1; }
        .modal-close:hover, .modal-close:focus-visible { background: var(--brand-red-soft); color: var(--brand-red); outline: none; }
        .modal-body { max-height: min(600px, calc(100vh - 186px)); overflow-y: auto; padding: 22px 28px 28px; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 28px 24px; border-top: 1px solid var(--line); }
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
        @include('partials.sidebar-styles')
        @media (max-width: 1100px) {
            .filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 900px) {
            .page { flex-direction: column; }
            .sidebar { width: 100%; min-height: auto; }
            .sidebar .brand { height: 90px; }
            .menu { flex-direction: row; flex-wrap: wrap; }
            .menu a { padding: 12px 14px; font-size: 14px; }
            .signout { justify-content: flex-start; }
            .main { padding: 22px 18px 36px; }
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
    </style>
</head>
<body>
<div class="page">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-icon">
                <img src="{{ asset('assets/images/elevate-logo.png') }}" alt="Elevate logo">
            </div>
        </div>
        <nav class="menu">
            <a href="{{ route('satellites.dashboard', $satellite) }}">Dashboard</a>
            <a href="{{ route('satellites.members', $satellite) }}" class="active">Members</a>
            <a href="{{ route('satellites.seekers', $satellite) }}">Seekers</a>
            <a href="{{ route('satellites.dashboard', $satellite) }}">Analytics</a>
        </nav>
        <div class="signout">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Sign out</button>
            </form>
        </div>
    </aside>

    <main class="main">
        <header class="page-header">
            <div class="page-title">
                <h1>Members</h1>
                <span class="page-kicker">{{ $membersShown }} of {{ $membersTotal }} members - {{ $satellite->display_name }} Satellite</span>
            </div>
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
        </header>

        @if (session('status'))
            <div class="flash flash-success">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="flash flash-error">{{ session('error') }}</div>
        @endif

        <form class="filters-card" method="GET" action="{{ route('satellites.members', $satellite) }}">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <input type="hidden" name="per_page" value="{{ $perPage }}">
            <div class="filter-main-row">
                <label class="search-field">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="8" />
                        <path d="m21 21-4.35-4.35" />
                    </svg>
                    <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search member, leader, campus, email, or contact number">
                </label>
                <button type="submit" class="btn btn-primary">Search</button>
                <button type="button" class="btn btn-secondary filter-toggle" data-filter-toggle aria-expanded="{{ $hasActiveFilters ? 'true' : 'false' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z" />
                    </svg>
                    Filters
                </button>
                @if ($hasActiveFilters)
                    <a class="btn btn-secondary" href="{{ $clearFiltersUrl }}">Clear</a>
                @endif
            </div>
            <div class="filters-panel" data-filter-panel @unless ($hasActiveFilters) hidden @endunless>
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

        <section class="directory-card">
            <div class="table-scroll">
                <table class="members-table">
                    <thead>
                        <tr>
                            <th>
                                <a class="sortable-link" href="{{ $sortUrl('status') }}">
                                    Status
                                    <span class="sort-mark {{ $sortMark('status') }}" aria-hidden="true">{{ $sortMark('status') === 'up' ? '^' : ($sortMark('status') === 'down' ? 'v' : '-') }}</span>
                                </a>
                            </th>
                            <th>
                                <a class="sortable-link" href="{{ $sortUrl('name') }}">
                                    Full Name
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
                            <th>Name of Dgroup Leader</th>
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
                            <tr>
                                <td><span class="status-pill status-{{ $member->status_class }}">{{ $member->status ?: 'Active' }}</span></td>
                                <td>
                                    <div class="member-name-cell">
                                        <button type="button" class="member-details-button" data-member-details='{!! $memberDetailsJson !!}' data-member-edit='{!! $memberFormJson !!}' data-member-update-url="{{ $updateUrl }}" aria-label="View details for {{ $member->display_name }}">
                                            <svg class="member-eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </button>
                                        <span class="member-name-text">{{ $member->display_name }}</span>
                                    </div>
                                </td>
                                <td>{{ $member->display_role }}</td>
                                <td>{{ $member->campus_school ?: '-' }}</td>
                                <td>
                                    @if ($member->dgroup_leader_name)
                                        {{ $member->dgroup_leader_name }}
                                    @else
                                        <span class="leader-empty">-</span>
                                    @endif
                                </td>
                                <td class="actions-cell">
                                    <div class="action-menu" data-action-menu>
                                        <button type="button" class="row-action-button" data-action-menu-trigger aria-haspopup="menu" aria-expanded="false" aria-label="Open actions for {{ $member->display_name }}">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <circle cx="12" cy="12" r="1" />
                                                <circle cx="19" cy="12" r="1" />
                                                <circle cx="5" cy="12" r="1" />
                                            </svg>
                                        </button>
                                        <div class="action-menu-panel" role="menu">
                                            <button type="button" data-member-details='{!! $memberDetailsJson !!}' data-member-edit='{!! $memberFormJson !!}' data-member-update-url="{{ $updateUrl }}" role="menuitem">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                                                    <circle cx="12" cy="12" r="3" />
                                                </svg>
                                                View Details
                                            </button>
                                            <button type="button" data-open-member-edit data-member-edit='{!! $memberFormJson !!}' data-member-update-url="{{ $updateUrl }}" role="menuitem">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M12 20h9" />
                                                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                                </svg>
                                                Edit Member
                                            </button>
                                            <button type="button" class="danger-action" data-open-member-delete data-member-delete-url="{{ $deleteUrl }}" data-member-name="{{ $member->display_name }}" role="menuitem">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M3 6h18" />
                                                    <path d="M8 6V4h8v2" />
                                                    <path d="M19 6l-1 14H6L5 6" />
                                                </svg>
                                                Delete Member
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        @if ($membersTotal === 0)
                                            <h3>No members yet</h3>
                                            <p>Add the first {{ $satellite->display_name }} member manually or upload a CSV/XLSX sheet to start the directory.</p>
                                            <div class="empty-actions">
                                                <button type="button" class="btn btn-primary" data-open-member-create>Add Member</button>
                                                <button type="button" class="btn btn-secondary" data-open-modal="member-import">Upload Sheet</button>
                                            </div>
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
    </main>
</div>

<div class="modal-backdrop" data-modal="member-details" aria-hidden="true">
    <section class="modal modal-wide" role="dialog" aria-modal="true" aria-labelledby="member-details-title">
        <div class="modal-header">
            <div>
                <span class="modal-kicker">Member details</span>
                <h2 id="member-details-title"></h2>
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
            <button type="button" class="btn btn-primary" data-edit-from-details>Edit Member</button>
        </div>
    </section>
</div>

<div class="modal-backdrop" data-modal="member-form" aria-hidden="true">
    <section class="modal modal-wide" role="dialog" aria-modal="true" aria-labelledby="member-form-title">
        <div class="modal-header">
            <div>
                <span class="modal-kicker" data-member-form-kicker>Member record</span>
                <h2 id="member-form-title" data-member-form-title>Add Member</h2>
            </div>
        </div>
        <button type="button" class="modal-close" data-modal-close aria-label="Close member form">&times;</button>
        <form method="POST" action="{{ route('satellites.members.store', $satellite) }}" data-member-form data-store-url="{{ route('satellites.members.store', $satellite) }}" data-default-district="{{ $satellite->display_name }}" data-prevent-double-submit>
            @csrf
            <input type="hidden" name="_method" value="" data-member-form-method disabled>
            <div class="modal-body">
                @if ($memberFormErrorBag->any())
                    <div class="form-errors">
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
                            <select id="member-status" name="status" @class(['is-invalid' => $memberFormErrorBag->has('status')]) required>
                                @foreach ($statuses as $statusOption)
                                    <option value="{{ $statusOption }}" @selected(old('status', 'Active') === $statusOption)>{{ $statusOption }}</option>
                                @endforeach
                            </select>
                            @if ($memberFormErrorBag->has('status'))<span class="field-error">{{ $memberFormErrorBag->first('status') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-role">Dgroup Role *</label>
                            <select id="member-role" name="dgroup_role" @class(['is-invalid' => $memberFormErrorBag->has('dgroup_role')]) required>
                                @foreach ($dgroupRoles as $roleOption)
                                    <option value="{{ $roleOption }}" @selected(old('dgroup_role', 'Member') === $roleOption)>{{ $roleOption }}</option>
                                @endforeach
                            </select>
                            @if ($memberFormErrorBag->has('dgroup_role'))<span class="field-error">{{ $memberFormErrorBag->first('dgroup_role') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-first-name">First Name *</label>
                            <input id="member-first-name" name="first_name" value="{{ old('first_name') }}" @class(['is-invalid' => $memberFormErrorBag->has('first_name')]) required>
                            @if ($memberFormErrorBag->has('first_name'))<span class="field-error">{{ $memberFormErrorBag->first('first_name') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-last-name">Last Name *</label>
                            <input id="member-last-name" name="last_name" value="{{ old('last_name') }}" @class(['is-invalid' => $memberFormErrorBag->has('last_name')]) required>
                            @if ($memberFormErrorBag->has('last_name'))<span class="field-error">{{ $memberFormErrorBag->first('last_name') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-full-name">Full Name</label>
                            <input id="member-full-name" name="full_name" value="{{ old('full_name') }}" @class(['is-invalid' => $memberFormErrorBag->has('full_name')])>
                            @if ($memberFormErrorBag->has('full_name'))<span class="field-error">{{ $memberFormErrorBag->first('full_name') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-gender">Gender</label>
                            <select id="member-gender" name="gender" @class(['is-invalid' => $memberFormErrorBag->has('gender')])>
                                <option value="">Select</option>
                                @foreach ($genders as $genderOption)
                                    <option value="{{ $genderOption }}" @selected(old('gender') === $genderOption)>{{ $genderOption }}</option>
                                @endforeach
                            </select>
                            @if ($memberFormErrorBag->has('gender'))<span class="field-error">{{ $memberFormErrorBag->first('gender') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-birthdate">Birthdate</label>
                            <input id="member-birthdate" type="date" name="birthdate" value="{{ old('birthdate') }}" @class(['is-invalid' => $memberFormErrorBag->has('birthdate')])>
                            @if ($memberFormErrorBag->has('birthdate'))<span class="field-error">{{ $memberFormErrorBag->first('birthdate') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-age">Age</label>
                            <input id="member-age" type="number" min="0" max="130" name="age" value="{{ old('age') }}" @class(['is-invalid' => $memberFormErrorBag->has('age')])>
                            @if ($memberFormErrorBag->has('age'))<span class="field-error">{{ $memberFormErrorBag->first('age') }}</span>@endif
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Contact</h3>
                    <div class="member-form-grid">
                        <div class="field">
                            <label for="member-email">Email Address</label>
                            <input id="member-email" type="email" name="email_address" value="{{ old('email_address') }}" @class(['is-invalid' => $memberFormErrorBag->has('email_address')])>
                            @if ($memberFormErrorBag->has('email_address'))<span class="field-error">{{ $memberFormErrorBag->first('email_address') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-contact">Contact Number</label>
                            <input id="member-contact" name="contact_number" value="{{ old('contact_number') }}" @class(['is-invalid' => $memberFormErrorBag->has('contact_number')])>
                            @if ($memberFormErrorBag->has('contact_number'))<span class="field-error">{{ $memberFormErrorBag->first('contact_number') }}</span>@endif
                        </div>
                        <div class="field full">
                            <label for="member-facebook">Facebook Link</label>
                            <input id="member-facebook" type="url" name="facebook_link" value="{{ old('facebook_link') }}" @class(['is-invalid' => $memberFormErrorBag->has('facebook_link')])>
                            @if ($memberFormErrorBag->has('facebook_link'))<span class="field-error">{{ $memberFormErrorBag->first('facebook_link') }}</span>@endif
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Dgroup</h3>
                    <div class="member-form-grid">
                        <div class="field">
                            <label for="member-leader">Name of Dgroup Leader</label>
                            <input id="member-leader" name="dgroup_leader_name" list="member-leader-options" value="{{ old('dgroup_leader_name') }}" @class(['is-invalid' => $memberFormErrorBag->has('dgroup_leader_name')])>
                            <datalist id="member-leader-options">
                                @foreach ($leaderOptions as $leaderOption)
                                    <option value="{{ $leaderOption }}"></option>
                                @endforeach
                            </datalist>
                            @if ($memberFormErrorBag->has('dgroup_leader_name'))<span class="field-error">{{ $memberFormErrorBag->first('dgroup_leader_name') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-active-disciples">No. of Active Disciples</label>
                            <input id="member-active-disciples" type="number" min="0" name="active_disciples" value="{{ old('active_disciples') }}" @class(['is-invalid' => $memberFormErrorBag->has('active_disciples')])>
                            @if ($memberFormErrorBag->has('active_disciples'))<span class="field-error">{{ $memberFormErrorBag->first('active_disciples') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-weekly-dgroups">How many Dgroups do you lead each week?</label>
                            <input id="member-weekly-dgroups" type="number" min="0" name="weekly_dgroups_led" value="{{ old('weekly_dgroups_led') }}" @class(['is-invalid' => $memberFormErrorBag->has('weekly_dgroups_led')])>
                            @if ($memberFormErrorBag->has('weekly_dgroups_led'))<span class="field-error">{{ $memberFormErrorBag->first('weekly_dgroups_led') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-generation">Generation</label>
                            <input id="member-generation" name="generation" value="{{ old('generation') }}" @class(['is-invalid' => $memberFormErrorBag->has('generation')])>
                            @if ($memberFormErrorBag->has('generation'))<span class="field-error">{{ $memberFormErrorBag->first('generation') }}</span>@endif
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Location</h3>
                    <div class="member-form-grid">
                        <div class="field">
                            <label for="member-district">District *</label>
                            <input id="member-district" name="district" value="{{ old('district', $satellite->display_name) }}" @class(['is-invalid' => $memberFormErrorBag->has('district')]) required>
                            @if ($memberFormErrorBag->has('district'))<span class="field-error">{{ $memberFormErrorBag->first('district') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-area">Area</label>
                            <input id="member-area" name="area" value="{{ old('area') }}" @class(['is-invalid' => $memberFormErrorBag->has('area')])>
                            @if ($memberFormErrorBag->has('area'))<span class="field-error">{{ $memberFormErrorBag->first('area') }}</span>@endif
                        </div>
                        <div class="field full">
                            <label for="member-campus">Campus/School *</label>
                            <input id="member-campus" name="campus_school" value="{{ old('campus_school') }}" @class(['is-invalid' => $memberFormErrorBag->has('campus_school')]) required>
                            @if ($memberFormErrorBag->has('campus_school'))<span class="field-error">{{ $memberFormErrorBag->first('campus_school') }}</span>@endif
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Reporting</h3>
                    <div class="member-form-grid">
                        <div class="field">
                            <label for="member-year-joined">Year Joined</label>
                            <input id="member-year-joined" type="number" min="1900" max="{{ now()->year + 1 }}" name="year_joined" value="{{ old('year_joined') }}" @class(['is-invalid' => $memberFormErrorBag->has('year_joined')])>
                            @if ($memberFormErrorBag->has('year_joined'))<span class="field-error">{{ $memberFormErrorBag->first('year_joined') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-ministry">Ministry Involvement</label>
                            <input id="member-ministry" name="ministry_involvement" value="{{ old('ministry_involvement') }}" @class(['is-invalid' => $memberFormErrorBag->has('ministry_involvement')])>
                            @if ($memberFormErrorBag->has('ministry_involvement'))<span class="field-error">{{ $memberFormErrorBag->first('ministry_involvement') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-refresh-date">Refresh Date</label>
                            <input id="member-refresh-date" type="date" name="refresh_date" value="{{ old('refresh_date') }}" @class(['is-invalid' => $memberFormErrorBag->has('refresh_date')])>
                            @if ($memberFormErrorBag->has('refresh_date'))<span class="field-error">{{ $memberFormErrorBag->first('refresh_date') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-year">Year</label>
                            <input id="member-year" type="number" min="1900" max="{{ now()->year + 1 }}" name="year" value="{{ old('year') }}" @class(['is-invalid' => $memberFormErrorBag->has('year')])>
                            @if ($memberFormErrorBag->has('year'))<span class="field-error">{{ $memberFormErrorBag->first('year') }}</span>@endif
                        </div>
                        <div class="field">
                            <label for="member-month">Month</label>
                            <select id="member-month" name="month" @class(['is-invalid' => $memberFormErrorBag->has('month')])>
                                <option value="">Select</option>
                                @foreach (['January','February','March','April','May','June','July','August','September','October','November','December'] as $monthOption)
                                    <option value="{{ $monthOption }}" @selected(old('month') === $monthOption)>{{ $monthOption }}</option>
                                @endforeach
                            </select>
                            @if ($memberFormErrorBag->has('month'))<span class="field-error">{{ $memberFormErrorBag->first('month') }}</span>@endif
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
                <div class="form-errors">
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
                    <input id="member-sheet" type="file" name="member_sheet" accept=".csv,.txt,.xlsx" required>
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
                <button type="submit" class="btn btn-danger" data-loading-text="Deleting...">Delete Member</button>
            </div>
        </form>
    </section>
</div>

<script>
    (() => {
        const modalBackdrops = Array.from(document.querySelectorAll('[data-modal]'));
        const memberFormModal = document.querySelector('[data-modal="member-form"]');
        const memberDetailsModal = document.querySelector('[data-modal="member-details"]');
        const memberImportModal = document.querySelector('[data-modal="member-import"]');
        const memberDeleteModal = document.querySelector('[data-modal="member-delete"]');
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
                menu.classList.remove('open');
                menu.querySelector('[data-action-menu-trigger]')?.setAttribute('aria-expanded', 'false');
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
            const leader = memberForm.elements.dgroup_leader_name;

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

        const openCreateForm = (trigger = null) => {
            resetMemberForm();
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

        memberForm?.elements.birthdate?.addEventListener('change', () => {
            const birthdate = memberForm.elements.birthdate.value;
            const ageField = memberForm.elements.age;

            if (!birthdate || !ageField || ageField.value) return;

            const birthday = new Date(`${birthdate}T00:00:00`);
            const today = new Date();
            let age = today.getFullYear() - birthday.getFullYear();
            const monthDelta = today.getMonth() - birthday.getMonth();

            if (monthDelta < 0 || (monthDelta === 0 && today.getDate() < birthday.getDate())) {
                age--;
            }

            if (Number.isFinite(age) && age >= 0) {
                ageField.value = age;
            }
        });

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
</body>
</html>
