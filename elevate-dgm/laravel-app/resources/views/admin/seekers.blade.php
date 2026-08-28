<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seekers Dashboard</title>
    <style>
        :root {
            --brand-red: #e33c24;
            --brand-red-dark: #c92f1b;
            --ink: #08111f;
            --muted: #667085;
            --line: #e8edf3;
            --surface: #ffffff;
            --page-bg: #f3f4f6;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--page-bg);
            color: var(--ink);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .page {
            display: flex;
            min-height: 100vh;
            background: var(--page-bg);
        }

        .sidebar {
            width: 260px;
            background: #000;
            color: #fff;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            padding: 18px 14px;
        }

        .sidebar .brand {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 110px;
            background: #111;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .sidebar .brand-icon img {
            display: block;
            width: 228px;
            max-width: 100%;
            height: auto;
            object-fit: contain;
        }

        .menu {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
        }

        .menu a {
            display: flex;
            align-items: center;
            color: var(--brand-red);
            text-decoration: none;
            padding: 15px 18px;
            border-radius: 8px;
            font-weight: 800;
            font-size: 16px;
            transition: 0.15s ease;
        }

        .menu a:hover {
            background: rgba(227, 60, 36, 0.12);
            color: #ff6b53;
        }

        .menu a.active {
            background: #fff;
            color: #111;
        }

        .signout {
            margin-top: auto;
            padding-top: 20px;
            display: flex;
            justify-content: center;
        }

        .signout form { margin: 0; }

        .signout button {
            width: 170px;
            border: none;
            padding: 12px 20px;
            border-radius: 999px;
            background: var(--brand-red);
            color: #fff;
            font-weight: 800;
            cursor: pointer;
        }

        .signout button:hover { background: var(--brand-red-dark); }

        .main {
            flex: 1;
            width: 100%;
            padding: 30px 32px 48px;
            min-width: 0;
        }

        .seekers-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 50px;
        }

        .seekers-header h1 {
            margin: 0;
            color: var(--ink);
            font-size: 28px;
            line-height: 1.15;
            font-weight: 900;
        }

        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .sync-form,
        .district-form {
            margin: 0;
        }

        .sync-button,
        .notification-button {
            border: 1px solid #d8dde6;
            background: var(--surface);
            color: #344054;
            cursor: pointer;
            transition: border-color 0.15s ease, color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
        }

        .sync-button {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            min-height: 36px;
            padding: 0 15px;
            border-radius: 999px;
            font: inherit;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .notification-button {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 999px;
        }

        .sync-button:hover,
        .notification-button:hover {
            border-color: var(--brand-red);
            color: var(--brand-red);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(227, 60, 36, 0.16);
        }

        .button-icon {
            display: inline-flex;
            width: 14px;
            height: 14px;
        }

        .bell-icon {
            width: 18px;
            height: 18px;
        }

        .notification-badge {
            position: absolute;
            top: -7px;
            right: -5px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 21px;
            height: 21px;
            padding: 0 6px;
            border-radius: 999px;
            background: var(--brand-red);
            color: #fff;
            border: 2px solid var(--page-bg);
            font-size: 11px;
            line-height: 1;
            font-weight: 900;
        }

        .seekers-card {
            width: 100%;
            max-width: none;
            overflow: hidden;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }

        .seekers-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .seekers-table {
            width: 100%;
            min-width: 1040px;
            border-collapse: collapse;
            color: #475467;
            font-size: 14px;
            font-weight: 600;
            table-layout: fixed;
        }

        .seekers-table th,
        .seekers-table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #eef2f6;
            vertical-align: middle;
        }

        .seekers-table th {
            color: #98a2b3;
            background: #fff;
            font-size: 13px;
            font-weight: 800;
        }

        .seekers-table th:nth-child(1),
        .seekers-table td:nth-child(1) { width: 18%; }

        .seekers-table th:nth-child(2),
        .seekers-table td:nth-child(2) { width: 22%; }

        .seekers-table th:nth-child(3),
        .seekers-table td:nth-child(3) { width: 24%; }

        .seekers-table th:nth-child(4),
        .seekers-table td:nth-child(4) { width: 21%; }

        .seekers-table th:nth-child(5),
        .seekers-table td:nth-child(5) { width: 14%; }

        .seekers-table th:nth-child(6),
        .seekers-table td:nth-child(6) { width: 52px; }

        .seekers-table tbody tr:last-child td { border-bottom: none; }
        .seekers-table tbody tr:hover { background: #fff9f7; }

        .seeker-name {
            color: var(--ink);
            font-weight: 800;
        }

        .district-select {
            width: 132px;
            height: 34px;
            border: 1px solid #d8dde6;
            border-radius: 8px;
            background: #fff;
            color: var(--ink);
            font: inherit;
            font-size: 12px;
            font-weight: 700;
            padding: 0 10px;
            cursor: pointer;
            outline: none;
        }

        .district-select:focus {
            border-color: var(--brand-red);
            box-shadow: 0 0 0 3px rgba(227, 60, 36, 0.12);
        }

        .notice {
            margin: 0 0 18px;
            border: 1px solid rgba(21, 153, 71, 0.24);
            border-radius: 10px;
            background: #ecfdf3;
            color: #067647;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 800;
        }

        .notice.error {
            border-color: rgba(217, 45, 32, 0.24);
            background: #fff1f1;
            color: #b42318;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 74px;
            min-height: 24px;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 900;
            line-height: 1;
        }

        .status-successful {
            background: #dcfce7;
            color: #159947;
        }

        .status-pending {
            background: #fff4ce;
            color: #c47700;
        }

        .status-unsuccessful {
            background: #ffe5e8;
            color: #d92d20;
        }

        .empty-state {
            color: var(--muted);
            font-weight: 800;
            text-align: center;
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

        @include('partials.table-pagination-styles')
        @include('partials.data-table-styles')

        @media (max-width: 900px) {
            .page { flex-direction: column; }
            .sidebar { width: 100%; min-height: auto; }
            .sidebar .brand { height: 90px; }
            .menu { flex-direction: row; flex-wrap: wrap; }
            .menu a { padding: 12px 14px; font-size: 14px; }
            .signout { justify-content: flex-start; }
            .main { padding: 22px 18px 36px; }
            .seekers-header {
                align-items: stretch;
                flex-direction: column;
                margin-bottom: 28px;
            }
            .toolbar-actions { justify-content: space-between; }
            .sync-button { min-width: 0; }
        }
        @include('partials.sidebar-styles')
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
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <a href="{{ route('admin.satellites') }}">Satellites</a>
            <a href="{{ route('admin.members') }}">Members</a>
            <a href="{{ route('admin.seekers') }}" class="active">Seekers</a>
            <a href="{{ route('admin.users') }}">Users</a>
            <a href="{{ route('admin.reports') }}">Reports</a>
            <a href="{{ route('admin.analytics') }}">Analytics</a>
        </nav>
        <div class="signout">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Sign out</button>
            </form>
        </div>
    </aside>

    <main class="main">
        <div class="seekers-header">
            <h1>Seekers</h1>

            <div class="toolbar-actions">
                <form method="POST" action="{{ route('admin.seekers.sync-google-sheet') }}" class="sync-form">
                    @csrf
                    <button class="sync-button" type="submit">
                        <span class="button-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12a9 9 0 0 1-15 6.7" />
                                <path d="M3 12a9 9 0 0 1 15-6.7" />
                                <path d="M6 19H3v-3" />
                                <path d="M18 5h3v3" />
                            </svg>
                        </span>
                        Sync Google Sheet
                    </button>
                </form>
                <button class="notification-button" type="button" aria-label="Notifications">
                    <svg class="bell-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
                        <path d="M13.7 21a2 2 0 0 1-3.4 0" />
                    </svg>
                    @if ($notificationCount > 0)
                        <span class="notification-badge">{{ $notificationCount }}</span>
                    @endif
                </button>
            </div>
        </div>

        @if (session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="notice error">{{ session('error') }}</div>
        @endif

        @php
            $sortUrl = fn (string $column) => route('admin.seekers', array_merge(request()->except('page'), [
                'sort' => $column,
                'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
            ]));
            $sortLabel = fn (string $column) => $sort === $column ? ($direction === 'asc' ? 'Ascending' : 'Descending') : 'Not sorted';
            $sortGlyph = fn (string $column) => $sort === $column ? ($direction === 'asc' ? 'up' : 'down') : '-';
            $hasActiveFilters = collect($filters)->contains(fn ($value) => $value !== '' && $value !== null);
        @endphp

        <form method="GET" action="{{ route('admin.seekers') }}" class="data-table-toolbar" data-table-filter-form>
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <div class="data-table-field search">
                <label for="seekers-search">Search</label>
                <div class="data-table-search">
                    <input id="seekers-search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Search name, email, school, or district" data-debounced-submit>
                    <button type="button" class="data-table-search-clear" data-search-clear aria-label="Clear search">&times;</button>
                </div>
            </div>
            <div class="data-table-field compact">
                <label for="seekers-status">Status</label>
                <select id="seekers-status" name="status" data-auto-submit>
                    <option value="">All statuses</option>
                    @foreach ($statuses as $statusOption)
                        <option value="{{ $statusOption }}" @selected($filters['status'] === $statusOption)>{{ $statusOption }}</option>
                    @endforeach
                </select>
            </div>
            <div class="data-table-field">
                <label for="seekers-district">District</label>
                <select id="seekers-district" name="district" data-auto-submit>
                    <option value="">All districts</option>
                    @foreach ($districtOptions as $districtOption)
                        <option value="{{ $districtOption }}" @selected($filters['district'] === $districtOption)>{{ $districtOption }}</option>
                    @endforeach
                </select>
            </div>
            <div class="data-table-field">
                <label for="seekers-school">School</label>
                <input id="seekers-school" name="school" type="search" value="{{ $filters['school'] }}" placeholder="Filter by school" data-debounced-submit>
            </div>
            <div class="data-table-actions">
                <a class="data-table-link-button" href="{{ route('admin.seekers.export', request()->query()) }}" aria-label="Export all {{ $seekers->total() }} matching seekers to CSV">Export CSV</a>
                <a class="data-table-link-button {{ $hasActiveFilters ? '' : 'disabled' }}" href="{{ route('admin.seekers') }}" aria-disabled="{{ $hasActiveFilters ? 'false' : 'true' }}" @unless($hasActiveFilters) tabindex="-1" @endunless>Clear</a>
            </div>
        </form>

        <section class="seekers-card directory-card">
            <div class="seekers-table-wrap data-table-scroll">
                <table class="seekers-table" id="admin-seekers-table" data-enhanced-table data-table-id="admin-seekers">
                    <thead>
                        <tr>
                            <th data-column="name" data-label="Name" aria-sort="{{ $sort === 'name' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <a class="data-table-sort-link" href="{{ $sortUrl('name') }}" aria-label="Sort by seeker name. {{ $sortLabel('name') }}">Name <span class="data-table-sort-indicator" aria-hidden="true">{{ $sortGlyph('name') }}</span></a>
                            </th>
                            <th data-column="email" data-label="Email" aria-sort="{{ $sort === 'email' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <a class="data-table-sort-link" href="{{ $sortUrl('email') }}" aria-label="Sort by email. {{ $sortLabel('email') }}">Email <span class="data-table-sort-indicator" aria-hidden="true">{{ $sortGlyph('email') }}</span></a>
                            </th>
                            <th data-column="school" data-label="School" aria-sort="{{ $sort === 'school' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <a class="data-table-sort-link" href="{{ $sortUrl('school') }}" aria-label="Sort by school. {{ $sortLabel('school') }}">School <span class="data-table-sort-indicator" aria-hidden="true">{{ $sortGlyph('school') }}</span></a>
                            </th>
                            <th data-column="district" data-label="Assigned district" aria-sort="{{ $sort === 'district' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <a class="data-table-sort-link" href="{{ $sortUrl('district') }}" aria-label="Sort by assigned district. {{ $sortLabel('district') }}">Assigned district <span class="data-table-sort-indicator" aria-hidden="true">{{ $sortGlyph('district') }}</span></a>
                            </th>
                            <th data-column="status" data-label="Status" aria-sort="{{ $sort === 'status' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <a class="data-table-sort-link" href="{{ $sortUrl('status') }}" aria-label="Sort by status. {{ $sortLabel('status') }}">Status <span class="data-table-sort-indicator" aria-hidden="true">{{ $sortGlyph('status') }}</span></a>
                            </th>
                            <th class="data-table-action-col" data-locked-column><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($seekers as $seeker)
                            <tr>
                                <td class="seeker-name">{{ $seeker->name }}</td>
                                <td>{{ $seeker->email }}</td>
                                <td>
                                    @if ($seeker->school)
                                        {{ $seeker->school }}
                                    @else
                                        <span aria-hidden="true">&mdash;</span><span class="sr-only">No school listed</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.seekers.assign-district', $seeker) }}" class="district-form">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="page" value="{{ $seekers->currentPage() }}">
                                        <label class="sr-only" for="district-{{ $seeker->id }}">Assigned district for {{ $seeker->name }}</label>
                                        <select class="district-select" id="district-{{ $seeker->id }}" name="assigned_district" data-confirm-change="This will reassign {name} from {from} to {to}." data-record-name="{{ $seeker->name }}">
                                            @foreach ($districtOptions as $district)
                                                <option value="{{ $district }}" @selected($district === $seeker->assigned_district_label)>{{ $district }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <span class="status-pill status-{{ $seeker->status_class }}">{{ $seeker->status }}</span>
                                </td>
                                <td class="data-table-action-col">
                                    <div class="data-table-row-menu">
                                        <button type="button" class="data-table-menu-button" data-menu-button aria-haspopup="menu" aria-expanded="false" aria-label="Actions for {{ $seeker->name }}"><span aria-hidden="true">&#8942;</span></button>
                                        <div class="data-table-menu" role="menu">
                                            <button type="button" role="menuitem" data-focus-target="#district-{{ $seeker->id }}">Reassign district</button>
                                            @foreach ($statuses as $statusOption)
                                                @if ($statusOption !== $seeker->status)
                                                    <form method="POST" action="{{ route('admin.seekers.status', $seeker) }}" data-confirm-message="This will change {{ $seeker->name }} from {{ $seeker->status }} to {{ $statusOption }}.">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="{{ $statusOption }}">
                                                        <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                                                        <button type="submit" role="menuitem">Mark {{ $statusOption }}</button>
                                                    </form>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="empty-state">
                                    @if (array_filter($filters))
                                        <div class="data-table-empty-card">
                                            <strong>No matches found.</strong>
                                            <span>No seekers match the current search or filters.</span>
                                            <a class="data-table-link-button" href="{{ route('admin.seekers') }}">Clear</a>
                                        </div>
                                    @else
                                        <div class="data-table-empty-card">
                                            <strong>No seekers yet.</strong>
                                            <span>Sync Google Sheet after the source file is shared with the service account.</span>
                                            <form method="POST" action="{{ route('admin.seekers.sync-google-sheet') }}">
                                                @csrf
                                                <button class="data-table-button primary" type="submit">Sync Google Sheet</button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('partials.table-pagination', ['paginator' => $seekers, 'pageSizes' => $pageSizes, 'currentPageSize' => $perPage])
        </section>
    </main>
</div>
@include('partials.data-table-scripts')
</body>
</html>
