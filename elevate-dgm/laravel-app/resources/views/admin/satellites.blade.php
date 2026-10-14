@extends('layouts.app')

@section('title', 'Satellites Dashboard | Elevate DGM')

@push('styles')
.header { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 22px; }
        .header h1 { margin: 0; font-size: 32px; font-weight: 900; }
        .new-button { display: inline-flex; align-items: center; gap: 10px; border: none; border-radius: 999px; background: #e33c24; color: #fff; cursor: pointer; font-size: 15px; font-weight: 900; padding: 14px 24px; box-shadow: 0 4px 12px rgba(227, 60, 36, 0.22); }
        .new-button:hover { background: #c92f1b; }
        .new-button span { font-size: 22px; line-height: 0; }
        .notice { margin-bottom: 18px; border: 1px solid rgba(227, 60, 36, 0.18); border-radius: 10px; background: #fff; color: #111; padding: 12px 16px; font-weight: 700; }
        .notice.error { color: #b42318; }
        .stats { display: grid; grid-template-columns: repeat(4, minmax(220px, 1fr)); gap: 18px; margin-bottom: 24px; }
        .card { border-radius: 14px; padding: 18px 20px; display: flex; justify-content: space-between; align-items: center; min-height: 105px; color: #fff; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.10); }
        .card.red { background: #e33c24; }
        .card.black { background: #111; }
        .card .info { display: flex; flex-direction: column; gap: 6px; }
        .card .label { font-size: 14px; font-weight: 800; opacity: 0.95; }
        .card .value { font-size: 30px; font-weight: 900; line-height: 1; }
@include('partials.admin-summary-table-styles')
        .satellite-name { color: inherit; font: inherit; font-weight: 800; }
        .muted { color: rgba(17, 24, 39, 0.68); font-size: 13px; font-weight: 600; }
@include('partials.table-pagination-styles')
@include('partials.data-table-styles')
        .modal-backdrop { position: fixed; inset: 0; z-index: 20; display: none; align-items: flex-start; justify-content: center; background: rgba(0, 0, 0, 0.44); padding: 64px 16px 24px; }
        .modal-backdrop.open { display: flex; }
        .modal { width: min(100%, 450px); border-radius: 14px; background: #fff; padding: 26px 24px 24px; box-shadow: 0 24px 70px rgba(15, 23, 42, 0.24); }
        .modal-header { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 24px; }
        .modal-header h2 { margin: 0; color: #0f172a; font-size: 20px; font-weight: 900; }
        .modal-close { border: none; background: transparent; color: #94a3b8; cursor: pointer; font-size: 28px; line-height: 1; padding: 0; }
        .field { display: grid; gap: 7px; margin-bottom: 18px; }
        .field label { color: #344054; font-size: 13px; font-weight: 600; }
        .field input, .field select { width: 100%; height: 43px; border: 1px solid #d9dee7; border-radius: 7px; background: #fff; color: #111827; font: inherit; padding: 0 14px; outline: none; }
        .field input:focus, .field select:focus { border-color: #e33c24; box-shadow: 0 0 0 3px rgba(227, 60, 36, 0.12); }
        .field-error { margin-top: -8px; margin-bottom: 14px; color: #b42318; font-size: 12px; font-weight: 700; }
        .modal-submit { width: 100%; min-height: 45px; border: none; border-radius: 999px; background: #e33c24; color: #fff; cursor: pointer; font: inherit; font-weight: 900; }
        .modal-submit:hover { background: #c92f1b; }
        @media (max-width: 1200px) { .stats { grid-template-columns: repeat(2, minmax(220px, 1fr)); } }
@endpush

@section('content')
<div class="header">
            <h1>Satellites Dashboard</h1>
            <button type="button" class="new-button" data-open-satellite-modal><span>+</span> New satellite</button>
        </div>

        @if (session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="notice error" role="alert">{{ session('error') }}</div>
        @endif

        <x-status-tabs :current="$archivedView ? 'archived' : 'active'" :tabs="[
            ['key' => 'active', 'label' => 'Active', 'count' => $statusTabCounts['active'], 'url' => route('admin.satellites')],
            ['key' => 'archived', 'label' => 'Archived', 'count' => $statusTabCounts['archived'], 'url' => route('admin.satellites', ['view' => 'archived'])],
        ]" />
        <div id="satellite-toast" class="notice" role="status" hidden></div>
        <div class="stats">
            <div class="card red"><div class="info"><span class="label">Total Satellites</span><span class="value">{{ $totalSatellites }}</span></div></div>
            <div class="card black"><div class="info"><span class="label">Active Leaders</span><span class="value">{{ $activeLeaders }}</span></div></div>
            <div class="card red"><div class="info"><span class="label">Total Members</span><span class="value">{{ $activeMembers }}</span></div></div>
            <div class="card black"><div class="info"><span class="label">Open Reports</span><span class="value">{{ $openReports }}</span></div></div>
        </div>

        @php
            $sortUrl = fn (string $column) => route('admin.satellites', array_merge(request()->except('page'), [
                'sort' => $column,
                'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
            ]));
            $sortLabel = fn (string $column) => $sort === $column ? ($direction === 'asc' ? 'Ascending' : 'Descending') : 'Not sorted';
            $sortGlyph = fn (string $column) => $sort === $column ? ($direction === 'asc' ? 'up' : 'down') : '-';
            $hasActiveFilters = collect($filters)->contains(fn ($value) => $value !== '' && $value !== null);
        @endphp

        <form method="GET" action="{{ route('admin.satellites') }}" class="data-table-toolbar" data-table-filter-form>
            <input type="hidden" name="view" value="{{ $archivedView ? 'archived' : 'active' }}">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            @if ($filters['satellite'] !== '')
                <input type="hidden" name="satellite" value="{{ $filters['satellite'] }}">
            @endif
            <div class="data-table-field search">
                <label for="satellites-search">Search</label>
                <div class="data-table-search">
                    <input id="satellites-search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Search satellite, hub, or assigned user" data-debounced-submit>
                    <button type="button" class="data-table-search-clear" data-search-clear aria-label="Clear search">&times;</button>
                </div>
            </div>
            <div class="data-table-field compact">
                <label for="satellites-hub">Hub</label>
                <select id="satellites-hub" name="hub" data-auto-submit>
                    <option value="">All hubs</option>
                    @foreach ($hubs as $hub)
                        <option value="{{ $hub }}" @selected($filters['hub'] === $hub)>{{ $hub }}</option>
                    @endforeach
                </select>
            </div>
            <div class="data-table-field compact">
                <label for="satellites-assignment">Assignment</label>
                <select id="satellites-assignment" name="assignment" data-auto-submit>
                    <option value="">Any</option>
                    <option value="assigned" @selected($filters['assignment'] === 'assigned')>Assigned</option>
                    <option value="unassigned" @selected($filters['assignment'] === 'unassigned')>Unassigned</option>
                </select>
            </div>
            <div class="data-table-actions">
                @unless ($archivedView)
                <a class="data-table-link-button" href="{{ route('admin.satellites.export', request()->query()) }}" aria-label="Export all {{ $satellites->total() }} matching satellites to CSV">Export CSV</a>
                @endunless
                <a class="data-table-link-button {{ $hasActiveFilters ? '' : 'disabled' }}" href="{{ route('admin.satellites') }}" aria-disabled="{{ $hasActiveFilters ? 'false' : 'true' }}" @unless($hasActiveFilters) tabindex="-1" @endunless>Clear</a>
            </div>
        </form>

        <section class="table-card">
            <h2>{{ $archivedView ? 'Archived Satellites' : 'Satellite Summary' }}</h2>
            <div class="data-table-scroll">
            <table id="admin-satellites-table" data-enhanced-table data-table-id="admin-satellites">
                <thead>
                    <tr>
                        <th data-column="satellite" data-label="Satellite" aria-sort="{{ $sort === 'name' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="data-table-sort-link" href="{{ $sortUrl('name') }}" aria-label="Sort by satellite. {{ $sortLabel('name') }}">Satellite <span class="data-table-sort-indicator" aria-hidden="true">{{ $sortGlyph('name') }}</span></a>
                        </th>
                        <th data-column="hub" data-label="Hub" aria-sort="{{ $sort === 'hub' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="data-table-sort-link" href="{{ $sortUrl('hub') }}" aria-label="Sort by hub. {{ $sortLabel('hub') }}">Hub <span class="data-table-sort-indicator" aria-hidden="true">{{ $sortGlyph('hub') }}</span></a>
                        </th>
                        <th data-column="leaders" data-label="Leaders" aria-sort="{{ $sort === 'leaders' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="data-table-sort-link" href="{{ $sortUrl('leaders') }}" aria-label="Sort by leaders. {{ $sortLabel('leaders') }}">Leaders <span class="data-table-sort-indicator" aria-hidden="true">{{ $sortGlyph('leaders') }}</span></a>
                        </th>
                        <th data-column="members" data-label="Members" aria-sort="{{ $sort === 'members' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="data-table-sort-link" href="{{ $sortUrl('members') }}" aria-label="Sort by members. {{ $sortLabel('members') }}">Members <span class="data-table-sort-indicator" aria-hidden="true">{{ $sortGlyph('members') }}</span></a>
                        </th>
                        <th class="data-table-action-col" data-locked-column><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($satellites as $satellite)
                        <tr>
                            <td>
                                <span class="satellite-name">{{ $satellite->display_name }}</span>
                                @if ($archivedView)<div>Archived on {{ $satellite->archived_at->timezone('Asia/Manila')->format('M d, Y H:i').' PHT' }}</div>@endif
                                <div class="muted">{{ $satellite->assignedUsers->count() }} assigned {{ \Illuminate\Support\Str::plural('user', $satellite->assignedUsers->count()) }}</div>
                            </td>
                            <td>{{ $satellite->hub ?: 'Unassigned' }}</td>
                            <td>{{ $satellite->leaders_count }}</td>
                            <td>{{ $satellite->members_count }}</td>
                            <td class="data-table-action-col">
                                <div class="data-table-row-menu">
                                    <button type="button" class="data-table-menu-button" data-menu-button aria-haspopup="menu" aria-expanded="false" aria-label="Actions for {{ $satellite->display_name }}"><span aria-hidden="true">&#8942;</span></button>
                                    <div class="data-table-menu" role="menu">
                                        @if ($archivedView)
                                        <a href="{{ route('admin.satellites.archived-details', $satellite->slug) }}" role="menuitem">View details</a>
                                        <form method="POST" action="{{ route('admin.satellites.restore', $satellite->slug) }}" data-satellite-action data-confirm-message="Restore {{ $satellite->display_name }}? Its pages and counts will return to the dashboard.">
                                            @csrf
                                            <button type="submit" role="menuitem">Restore</button>
                                        </form>
                                        @else
                                        <a href="{{ route('admin.members', ['search' => $satellite->display_name]) }}" role="menuitem">View members</a>
                                        <a href="{{ route('admin.seekers', ['district' => $satellite->display_name]) }}" role="menuitem">View seekers</a>
                                        <a href="{{ route('admin.users', ['district' => $satellite->display_name]) }}" role="menuitem">View assigned users</a>
                                        <a href="{{ route('admin.satellites.export', ['search' => $satellite->display_name]) }}" role="menuitem">Export satellite row</a>
                                        <form method="POST" action="{{ route('admin.satellites.destroy', $satellite) }}" data-satellite-action data-confirm-message="Archive {{ $satellite->display_name }}? It will be hidden from the dashboard and its history will be kept. @if ($satellite->warning_members_count || $satellite->warning_seekers_count || $satellite->assignedUsers->count() || $satellite->assigned_user_id) This satellite still has {{ $satellite->warning_members_count }} members, {{ $satellite->warning_seekers_count }} seekers, and {{ $satellite->assignedUsers->pluck('id')->push($satellite->assigned_user_id)->filter()->unique()->count() }} assigned users. Their satellite access will be locked. @endif">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="danger" role="menuitem">Archive satellite</button>
                                        </form>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                @if (array_filter($filters))
                                    <div class="data-table-empty-card">
                                        <strong>No matches found.</strong>
                                        <span>No satellites match the current search or filters.</span>
                                        <a class="data-table-link-button" href="{{ route('admin.satellites') }}">Clear</a>
                                    </div>
                                @else
                                    <div class="data-table-empty-card">
                                        <strong>No satellites yet.</strong>
                                        <span>Create a satellite dashboard to start tracking leaders, members, and reports.</span>
                                        <button type="button" class="data-table-button primary" data-open-satellite-modal>Create satellite</button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            @include('partials.table-pagination', ['paginator' => $satellites, 'pageSizes' => $pageSizes, 'currentPageSize' => $perPage])
        </section>
@endsection

@push('scripts')
<div class="modal-backdrop {{ $errors->any() ? 'open' : '' }}" data-satellite-modal>
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="new-satellite-title">
        <div class="modal-header">
            <h2 id="new-satellite-title">New satellite</h2>
            <button type="button" class="modal-close" aria-label="Close new satellite form" data-close-satellite-modal>x</button>
        </div>

        <form method="POST" action="{{ route('admin.satellites.store') }}">
            @csrf
            @if ($errors->any())
                <div class="field-error" role="alert" tabindex="-1" data-validation-summary>Please correct the satellite fields below.</div>
            @endif
            <div class="field">
                <label for="satellite-name">Name of satellite</label>
                <input id="satellite-name" @error('name') aria-invalid="true" aria-describedby="satellite-name-error" @enderror name="name" type="text" value="{{ old('name') }}" placeholder="e.g. Santolan" required>
            </div>
            @error('name')
                <div id="satellite-name-error" class="field-error">{{ $message }}</div>
            @enderror

            <div class="field">
                <label for="satellite-hub">Elevate hub</label>
                <select id="satellite-hub" @error('hub') aria-invalid="true" aria-describedby="satellite-hub-error" @enderror name="hub" required>
                    <option value="">Select a hub</option>
                    @foreach ($hubs as $hub)
                        <option value="{{ $hub }}" @selected(old('hub') === $hub)>{{ $hub }}</option>
                    @endforeach
                </select>
            </div>
            @error('hub')
                <div id="satellite-hub-error" class="field-error">{{ $message }}</div>
            @enderror

            <div class="field">
                <label for="satellite-initial-user">Initial district account</label>
                <select id="satellite-initial-user" @error('initial_user_id') aria-invalid="true" aria-describedby="satellite-initial-user-error" @enderror name="initial_user_id">
                    <option value="">Unassigned</option>
                    @foreach ($assignableUsers as $assignableUser)
                        <option value="{{ $assignableUser->id }}" @selected(old('initial_user_id') == $assignableUser->id)>
                            {{ $assignableUser->name }} ({{ $assignableUser->email }})
                        </option>
                    @endforeach
                </select>
            </div>
            @error('initial_user_id')
                <div id="satellite-initial-user-error" class="field-error">{{ $message }}</div>
            @enderror

            <button type="submit" class="modal-submit">Add satellite</button>
        </form>
    </section>
</div>

<script>
    const modal = document.querySelector('[data-satellite-modal]');
    const closeButton = document.querySelector('[data-close-satellite-modal]');

    document.querySelectorAll('[data-open-satellite-modal]').forEach((openButton) => {
        openButton.addEventListener('click', () => modal?.classList.add('open'));
    });
    closeButton?.addEventListener('click', () => modal?.classList.remove('open'));
    modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.classList.remove('open');
        }
    });
</script>
@include('partials.data-table-scripts')
@endpush
