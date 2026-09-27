@extends('layouts.app')

@section('title', 'Seekers Dashboard | Elevate DGM')

@push('styles')
        @include('partials.table-pagination-styles')
        @include('partials.data-table-styles')

        .seekers-page {
            display: grid;
            gap: 24px;
        }

        .seekers-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 0;
        }

        .seekers-header h1 {
            margin: 0;
            color: var(--text-primary);
            font-size: 34px;
            line-height: 1.12;
            font-weight: 800;
        }

        .toolbar-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
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
            background: #ffffff;
            color: #344054;
            cursor: pointer;
            transition: border-color 0.15s ease, color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
        }

        .sync-button {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            min-height: 44px;
            padding: 0 16px;
            border-radius: 8px;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
        }

        .notification-button {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 8px;
        }

        .sync-button:hover,
        .notification-button:hover {
            border-color: var(--brand-red);
            color: var(--brand-red);
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
            border: 2px solid var(--surface-bg);
            font-size: 11px;
            line-height: 1;
            font-weight: 900;
        }

        .seekers-card {
            width: 100%;
            max-width: none;
            overflow: hidden;
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }

        .seekers-table-wrap {
            width: 100%;
            overflow-x: auto;
            overflow-y: visible;
        }

        .seekers-table {
            width: 100%;
            min-width: 980px;
            border-collapse: collapse;
            color: #475467;
            font-size: 14px;
            font-weight: 500;
            table-layout: fixed;
        }

        .seekers-table th,
        .seekers-table td {
            padding: 16px 20px;
            text-align: left;
            border-bottom: 1px solid #eef2f6;
            vertical-align: middle;
        }

        .seekers-table th {
            color: #475467;
            background: #fff7f5;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
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
        .seekers-table tbody tr:hover { background: #fffaf8; }

        .seeker-name {
            color: var(--text-primary);
            font-weight: 800;
        }

        .seeker-name-cell {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .seeker-detail-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            padding: 0;
            border: none;
            background: transparent;
            color: #9aa4b2;
            cursor: pointer;
            border-radius: 8px;
            transition: color 0.15s ease, background-color 0.15s ease, transform 0.15s ease;
            flex-shrink: 0;
        }

        .seeker-detail-button:hover,
        .seeker-detail-button:focus-visible {
            color: var(--brand-red);
            background: rgba(227, 60, 36, 0.08);
            outline: none;
            transform: translateY(-1px);
        }

        .seeker-detail-button svg {
            width: 15px;
            height: 15px;
            display: block;
        }

        .seeker-detail-name {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .seeker-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, 0.46);
        }

        .seeker-modal-backdrop.open {
            display: flex;
        }

        .seeker-modal {
            position: relative;
            width: min(100%, 520px);
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.2);
            border: 1px solid rgba(15, 23, 42, 0.06);
            padding: 28px 24px 20px;
        }

        .seeker-modal-close {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 34px;
            height: 34px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: #98a2b3;
            font-size: 28px;
            line-height: 1;
            cursor: pointer;
        }

        .seeker-modal-close:hover,
        .seeker-modal-close:focus-visible {
            background: rgba(227, 60, 36, 0.08);
            color: var(--brand-red);
            outline: none;
        }

        .seeker-modal-header {
            margin: 0 0 18px;
            padding-right: 30px;
        }

        .seeker-modal-title {
            margin: 0;
            color: var(--text-primary);
            font-size: 26px;
            font-weight: 800;
        }

        .seeker-modal-subtitle {
            margin: 8px 0 0;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .seeker-modal-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 16px;
        }

        .seeker-detail-card {
            display: flex;
            flex-direction: column;
            gap: 5px;
            padding: 14px 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fafbfc;
        }

        .seeker-detail-label {
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .seeker-detail-value {
            color: var(--text-primary);
            font-size: 15px;
            font-weight: 700;
            word-break: break-word;
        }

        .seeker-detail-value a {
            color: var(--brand-red);
            text-decoration: none;
        }

        .seeker-detail-value a:hover,
        .seeker-detail-value a:focus-visible {
            text-decoration: underline;
            outline: none;
        }

        .seeker-detail-card.full-span {
            grid-column: 1 / -1;
        }

        @media (max-width: 640px) {
            .seeker-modal {
                padding: 26px 18px 18px;
            }

            .seeker-modal-grid {
                grid-template-columns: 1fr;
            }
        }

        .district-select {
            width: 170px;
            height: 40px;
            border: 1px solid #d8dde6;
            border-radius: 8px;
            background: #fff;
            color: var(--text-primary);
            font: inherit;
            font-size: 13px;
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
            margin: 0;
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
            min-height: 28px;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 800;
            line-height: 1;
            text-transform: uppercase;
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
            color: var(--text-muted);
            font-weight: 800;
            text-align: center;
        }

        @media (max-width: 900px) {
            .seekers-header {
                align-items: stretch;
                flex-direction: column;
            }

            .toolbar-actions { justify-content: space-between; }
            .sync-button { min-width: 0; }
        }
@endpush

@section('content')
<div class="seekers-page">
        <div class="page-header seekers-header">
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
            $sortGlyph = fn (string $column) => $sort === $column ? ($direction === 'asc' ? '&uarr;' : '&darr;') : '&#8597;';
            $hasActiveFilters = collect($filters)->contains(fn ($value) => $value !== '' && $value !== null);
            $filterChipUrl = fn (string $filterName) => route('admin.seekers', request()->except([$filterName, 'page']));
        @endphp

        <form method="GET" action="{{ route('admin.seekers') }}" class="data-table-toolbar" data-table-filter-form>
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <div class="data-table-field search">
                <label for="seekers-search">Search</label>
                <div class="data-table-search">
                    <input id="seekers-search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Search seekers by name, email, school, or district" data-debounced-submit>
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
                <a class="data-table-link-button tertiary {{ $hasActiveFilters ? '' : 'disabled' }}" href="{{ route('admin.seekers') }}" aria-disabled="{{ $hasActiveFilters ? 'false' : 'true' }}" @unless($hasActiveFilters) tabindex="-1" @endunless>Clear Filters</a>
            </div>
        </form>

        @if ($hasActiveFilters)
            <div class="data-table-filter-chips" aria-label="Active seeker filters">
                @if ($filters['search'] !== '')
                    <a class="data-table-chip" href="{{ $filterChipUrl('search') }}">Search: {{ $filters['search'] }} <span aria-hidden="true">&times;</span></a>
                @endif
                @if ($filters['status'] !== '')
                    <a class="data-table-chip" href="{{ $filterChipUrl('status') }}">Status: {{ $filters['status'] }} <span aria-hidden="true">&times;</span></a>
                @endif
                @if ($filters['district'] !== '')
                    <a class="data-table-chip" href="{{ $filterChipUrl('district') }}">District: {{ $filters['district'] }} <span aria-hidden="true">&times;</span></a>
                @endif
                @if ($filters['school'] !== '')
                    <a class="data-table-chip" href="{{ $filterChipUrl('school') }}">School: {{ $filters['school'] }} <span aria-hidden="true">&times;</span></a>
                @endif
                <a class="data-table-link-button tertiary" href="{{ route('admin.seekers') }}">Clear all</a>
            </div>
        @endif

        <section class="seekers-card directory-card">
            <div class="seekers-table-wrap data-table-scroll">
                <table class="seekers-table" id="admin-seekers-table" data-enhanced-table data-table-id="admin-seekers">
                    <thead>
                        <tr>
                            <th data-column="name" data-label="Name" aria-sort="{{ $sort === 'name' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <a class="data-table-sort-link" href="{{ $sortUrl('name') }}" aria-label="Sort by seeker name. {{ $sortLabel('name') }}">Name <span class="data-table-sort-indicator" aria-hidden="true">{!! $sortGlyph('name') !!}</span></a>
                            </th>
                            <th data-column="email" data-label="Email" aria-sort="{{ $sort === 'email' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <a class="data-table-sort-link" href="{{ $sortUrl('email') }}" aria-label="Sort by email. {{ $sortLabel('email') }}">Email <span class="data-table-sort-indicator" aria-hidden="true">{!! $sortGlyph('email') !!}</span></a>
                            </th>
                            <th data-column="school" data-label="School" aria-sort="{{ $sort === 'school' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <a class="data-table-sort-link" href="{{ $sortUrl('school') }}" aria-label="Sort by school. {{ $sortLabel('school') }}">School <span class="data-table-sort-indicator" aria-hidden="true">{!! $sortGlyph('school') !!}</span></a>
                            </th>
                            <th data-column="district" data-label="Assigned district" aria-sort="{{ $sort === 'district' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <a class="data-table-sort-link" href="{{ $sortUrl('district') }}" aria-label="Sort by assigned district. {{ $sortLabel('district') }}">Assigned district <span class="data-table-sort-indicator" aria-hidden="true">{!! $sortGlyph('district') !!}</span></a>
                            </th>
                            <th data-column="status" data-label="Status" aria-sort="{{ $sort === 'status' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <a class="data-table-sort-link" href="{{ $sortUrl('status') }}" aria-label="Sort by status. {{ $sortLabel('status') }}">Status <span class="data-table-sort-indicator" aria-hidden="true">{!! $sortGlyph('status') !!}</span></a>
                            </th>
                            <th class="data-table-action-col" data-locked-column><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($seekers as $seeker)
                            <tr
                                data-seeker-modal-row
                                data-seeker-name="{{ $seeker->name }}"
                                data-seeker-contact="{{ $seeker->contact_number ?: 'Not provided' }}"
                                data-seeker-email="{{ $seeker->email ?: 'Not provided' }}"
                                data-seeker-facebook="{{ $seeker->facebook_link ?: 'Not provided' }}"
                                data-seeker-age="{{ $seeker->age ?? 'Not provided' }}"
                                data-seeker-gender="{{ $seeker->gender ?: 'Not provided' }}"
                                data-seeker-school="{{ $seeker->school ?: 'Not provided' }}"
                            >
                                <td class="seeker-name">
                                    <div class="seeker-name-cell">
                                        <button type="button" class="seeker-detail-button" data-open-seeker-modal aria-label="View details for {{ $seeker->name }}">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>
                                        <span class="seeker-detail-name">{{ $seeker->name }}</span>
                                    </div>
                                </td>
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
                                            <button type="button" role="menuitem" aria-label="Update district for {{ $seeker->name }}" data-focus-target="#district-{{ $seeker->id }}">Update District</button>
                                            @foreach ($statuses as $statusOption)
                                                @if ($statusOption !== $seeker->status)
                                                    <form method="POST" action="{{ route('admin.seekers.status', $seeker) }}" data-confirm-message="This will change {{ $seeker->name }} from {{ $seeker->status }} to {{ $statusOption }}.">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="{{ $statusOption }}">
                                                        <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                                                        <button type="submit" role="menuitem" aria-label="Mark {{ $statusOption }}: {{ $seeker->name }}">Mark {{ $statusOption }}</button>
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
                                            <strong>No seekers found.</strong>
                                            <span>Try changing your search or filters.</span>
                                            <a class="data-table-link-button tertiary" href="{{ route('admin.seekers') }}">Clear Filters</a>
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
            @include('partials.table-pagination', ['paginator' => $seekers, 'pageSizes' => $pageSizes, 'currentPageSize' => $perPage, 'recordLabel' => 'seekers', 'tableId' => 'admin-seekers'])
        </section>
    </div>
@endsection

@push('scripts')
<div class="seeker-modal-backdrop" data-seeker-modal-backdrop aria-hidden="true">
    <section class="seeker-modal" role="dialog" aria-modal="true" aria-labelledby="seeker-modal-title">
        <button type="button" class="seeker-modal-close" data-close-seeker-modal aria-label="Close seeker details">&times;</button>
        <header class="seeker-modal-header">
            <h2 class="seeker-modal-title" id="seeker-modal-title">Seeker Details</h2>
            <p class="seeker-modal-subtitle">Full profile summary for the selected seeker.</p>
        </header>

        <div class="seeker-modal-grid">
            <div class="seeker-detail-card full-span">
                <span class="seeker-detail-label">Name</span>
                <span class="seeker-detail-value" data-seeker-detail-name>—</span>
            </div>
            <div class="seeker-detail-card">
                <span class="seeker-detail-label">Contact Number</span>
                <span class="seeker-detail-value" data-seeker-detail-contact>—</span>
            </div>
            <div class="seeker-detail-card">
                <span class="seeker-detail-label">Email</span>
                <span class="seeker-detail-value" data-seeker-detail-email>—</span>
            </div>
            <div class="seeker-detail-card">
                <span class="seeker-detail-label">Facebook</span>
                <span class="seeker-detail-value" data-seeker-detail-facebook>—</span>
            </div>
            <div class="seeker-detail-card">
                <span class="seeker-detail-label">Age</span>
                <span class="seeker-detail-value" data-seeker-detail-age>—</span>
            </div>
            <div class="seeker-detail-card">
                <span class="seeker-detail-label">Gender</span>
                <span class="seeker-detail-value" data-seeker-detail-gender>—</span>
            </div>
            <div class="seeker-detail-card full-span">
                <span class="seeker-detail-label">Campus/School</span>
                <span class="seeker-detail-value" data-seeker-detail-school>—</span>
            </div>
        </div>
    </section>
</div>

<script>
(() => {
    const backdrop = document.querySelector('[data-seeker-modal-backdrop]');
    const closeButton = document.querySelector('[data-close-seeker-modal]');
    const modalTitle = document.querySelector('#seeker-modal-title');
    const detailName = document.querySelector('[data-seeker-detail-name]');
    const detailContact = document.querySelector('[data-seeker-detail-contact]');
    const detailEmail = document.querySelector('[data-seeker-detail-email]');
    const detailFacebook = document.querySelector('[data-seeker-detail-facebook]');
    const detailAge = document.querySelector('[data-seeker-detail-age]');
    const detailGender = document.querySelector('[data-seeker-detail-gender]');
    const detailSchool = document.querySelector('[data-seeker-detail-school]');

    const setText = (node, value) => {
        const safe = String(value ?? '').trim();
        node.textContent = safe || '—';
    };

    const fillModal = (row) => {
        const name = row.dataset.seekerName || '—';
        const contact = row.dataset.seekerContact || '—';
        const email = row.dataset.seekerEmail || '—';
        const facebook = row.dataset.seekerFacebook || '—';
        const age = row.dataset.seekerAge || '—';
        const gender = row.dataset.seekerGender || '—';
        const school = row.dataset.seekerSchool || '—';

        modalTitle.textContent = `${name} Details`;
        setText(detailName, name);
        setText(detailContact, contact);
        setText(detailEmail, email);

        if (facebook && facebook !== 'Not provided') {
            detailFacebook.textContent = '';
            const link = document.createElement('a');
            link.href = facebook;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.textContent = facebook;
            detailFacebook.appendChild(link);
        } else {
            detailFacebook.textContent = '—';
        }

        setText(detailAge, age);
        setText(detailGender, gender);
        setText(detailSchool, school);
    };

    const openModal = (button) => {
        const row = button.closest('[data-seeker-modal-row]');
        if (!row || !backdrop) {
            return;
        }

        fillModal(row);
        backdrop.classList.add('open');
        backdrop.setAttribute('aria-hidden', 'false');
        closeButton.focus();
    };

    const closeModal = () => {
        if (!backdrop) {
            return;
        }

        backdrop.classList.remove('open');
        backdrop.setAttribute('aria-hidden', 'true');
    };

    document.querySelectorAll('[data-open-seeker-modal]').forEach((button) => {
        button.addEventListener('click', () => openModal(button));
    });

    if (closeButton) {
        closeButton.addEventListener('click', closeModal);
    }

    if (backdrop) {
        backdrop.addEventListener('click', (event) => {
            if (event.target === backdrop) {
                closeModal();
            }
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && backdrop && backdrop.classList.contains('open')) {
            closeModal();
        }
    });
})();
</script>
@include('partials.data-table-scripts')
@endpush
