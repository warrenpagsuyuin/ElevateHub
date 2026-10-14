@extends('layouts.app')

@section('title', 'Seekers Dashboard | Elevate DGM')

@push('styles')
        @include('seekers.archive-styles')
        @include('seekers.details-modal-styles')
        @include('seekers.leader-assignment-modal-styles')
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
        .seekers-table td:nth-child(1) { width: calc((100% - 88px) * .18); }

        .seekers-table th:nth-child(2),
        .seekers-table td:nth-child(2) { width: calc((100% - 88px) * .22); }

        .seekers-table th:nth-child(3),
        .seekers-table td:nth-child(3) { width: calc((100% - 88px) * .24); }

        .seekers-table th:nth-child(4),
        .seekers-table td:nth-child(4) { width: calc((100% - 88px) * .21); }

        .seekers-table th:nth-child(5),
        .seekers-table td:nth-child(5) { width: calc((100% - 88px) * .15); }

        .seekers-table th:nth-child(6),
        .seekers-table td:nth-child(6) { width: 88px; padding-inline: 20px; }

        .seekers-table tbody tr:last-child td { border-bottom: none; }
        .seekers-table tbody tr:hover { background: #fffaf8; }

        .seeker-name {
            color: var(--text-primary);
            font-weight: 800;
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
            $hasActiveFilters = collect($filters)->except('view')->contains(fn ($value) => $value !== '' && $value !== null);
            $filterChipUrl = fn (string $filterName) => route('admin.seekers', request()->except([$filterName, 'page']));
        @endphp

        <x-status-tabs :current="$filters['view']" :tabs="[
            ['key' => 'active', 'label' => 'Active', 'count' => $activeCount, 'url' => route('admin.seekers')],
            ['key' => 'archive', 'label' => 'Archived', 'count' => $archiveCount, 'url' => route('admin.seekers', ['view' => 'archive'])],
        ]" />
        @include('seekers.archive-controls', ['hideArchiveBulk' => $filters['view'] !== 'archive'])
        <form method="GET" action="{{ route('admin.seekers') }}" class="data-table-toolbar" data-table-filter-form>
            <input type="hidden" name="view" value="{{ $filters['view'] }}">
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
                    @foreach ($filterDistrictOptions as $districtOption)
                        <option value="{{ $districtOption }}" @selected($filters['district'] === $districtOption)>{{ $districtOption }}</option>
                    @endforeach
                </select>
            </div>
            <div class="data-table-field">
                <label for="seekers-school">School</label>
                <input id="seekers-school" name="school" type="search" value="{{ $filters['school'] }}" placeholder="Filter by school" data-debounced-submit>
            </div>
            @if ($filters['view'] === 'archive')
            <div class="data-table-field"><label for="archived-from">Archived from</label><input id="archived-from" type="date" name="archived_from" value="{{ $filters['archived_from'] }}" data-auto-submit></div>
            <div class="data-table-field"><label for="archived-to">Archived to</label><input id="archived-to" type="date" name="archived_to" value="{{ $filters['archived_to'] }}" data-auto-submit></div>
            @endif
            <div class="data-table-actions">
                <a class="data-table-link-button" href="{{ route('admin.seekers.export', array_merge(request()->query(), ['view' => $filters['view']])) }}" aria-label="Export all {{ $seekers->total() }} matching seekers to CSV">Export CSV</a><a class="data-table-link-button tertiary" href="{{ route('admin.seekers.export', ['view' => 'all']) }}">Historical CSV (all records)</a>
                <a class="data-table-link-button tertiary {{ $hasActiveFilters ? '' : 'disabled' }}" href="{{ route('admin.seekers', ['view' => $filters['view']]) }}" aria-disabled="{{ $hasActiveFilters ? 'false' : 'true' }}" @unless($hasActiveFilters) tabindex="-1" @endunless>Clear Filters</a>
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
                <a class="data-table-link-button tertiary" href="{{ route('admin.seekers', ['view' => $filters['view']]) }}">Clear all</a>
            </div>
        @endif

        <section class="seekers-card directory-card">
            @if ($filters['view'] === 'archive')
                @include('seekers.archive-table')
            @else
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
                            @php
                                $seekerLeaderOptions = $leaderOptionsBySatellite[$seeker->assigned_satellite_slug] ?? [];
                                $seekerLeaderOptionsJson = json_encode($seekerLeaderOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
                                $currentSeekerLeaderId = array_search($seeker->dgroup_leader_name, $seekerLeaderOptions, true);
                            @endphp
                            <tr
                                data-seeker-modal-row
                                data-seeker-name="{{ $seeker->name }}"
                                data-seeker-contact="{{ $seeker->contact_number }}"
                                data-seeker-email="{{ $seeker->email }}"
                                data-seeker-facebook="{{ $seeker->facebook_link }}"
                                data-seeker-age="{{ $seeker->age }}"
                                data-seeker-gender="{{ $seeker->gender }}"
                                data-seeker-school="{{ $seeker->school }}"
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
                                            @if ($seeker->satellite && !$seeker->archived_at && $seekerLeaderOptions)
                                                <button type="button" role="menuitem" data-open-leader-modal data-seeker-name="{{ $seeker->name }}" data-leader-id="{{ $currentSeekerLeaderId ?: '' }}" data-leader-options="{{ $seekerLeaderOptionsJson }}" data-action="{{ route('admin.seekers.assign-leader', $seeker) }}" aria-label="Assign DGroup Leader for {{ $seeker->name }}">Assign DGroup Leader</button>
                                            @endif
                                            <button type="button" role="menuitem" data-archive-action="archive" data-action-url="{{ route('seekers.archive', $seeker) }}" data-name="{{ $seeker->name }}">Archive</button>
                                            <button type="button" role="menuitem" aria-label="Update district for {{ $seeker->name }}" data-focus-target="#district-{{ $seeker->id }}">Update District</button>
                                            @foreach ($statuses as $statusOption)
                                                @if ($statusOption !== $seeker->status)
                                                    <form method="POST" action="{{ route('admin.seekers.status', $seeker) }}" data-confirm-message="This will change {{ $seeker->name }} from {{ $seeker->status }} to {{ $statusOption }}.">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="{{ $statusOption }}">
                                                        <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                                                        <button type="submit" role="menuitem" aria-label="Mark {{ $statusOption }}: {{ $seeker->name }}" @disabled(!$currentSeekerLeaderId && $statusOption !== \App\Models\Seeker::STATUS_PENDING) @if(!$currentSeekerLeaderId && $statusOption !== \App\Models\Seeker::STATUS_PENDING) title="Assign a DGroup Leader first" @endif>Mark {{ $statusOption }}</button>
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
                                    @if (collect($filters)->except('view')->filter()->isNotEmpty())
                                        <div class="data-table-empty-card">
                                            <strong>No seekers found.</strong>
                                            <span>Try changing your search or filters.</span>
                                            <a class="data-table-link-button tertiary" href="{{ route('admin.seekers', ['view' => $filters['view']]) }}">Clear Filters</a>
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
            @endif
            @include('partials.table-pagination', ['paginator' => $seekers, 'pageSizes' => $pageSizes, 'currentPageSize' => $perPage, 'recordLabel' => 'seekers', 'tableId' => 'admin-seekers'])
        </section>
    </div>
@endsection

@push('scripts')
@include('seekers.leader-assignment-modal')
@include('seekers.details-modal')
@include('partials.data-table-scripts')
@endpush
