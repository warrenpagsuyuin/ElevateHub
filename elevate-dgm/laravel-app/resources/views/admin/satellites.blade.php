<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satellites Dashboard</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f3f4f6; color: #111; }
        .page { display: flex; min-height: 100vh; background: #f3f4f6; }
        .sidebar { width: 260px; background: #000; color: #fff; display: flex; flex-direction: column; flex-shrink: 0; padding: 18px 14px; }
        .sidebar .brand { display: flex; justify-content: center; align-items: center; height: 110px; background: #111; padding: 14px; border-radius: 8px; margin-bottom: 18px; }
        .sidebar .brand-icon img { display: block; width: 228px; max-width: 100%; height: auto; object-fit: contain; }
        .menu { display: flex; flex-direction: column; gap: 8px; flex: 1; }
        .menu a { display: flex; align-items: center; gap: 12px; color: #e33c24; text-decoration: none; padding: 15px 18px; border-radius: 8px; font-weight: 800; font-size: 16px; transition: 0.15s ease; }
        .menu a:hover { background: rgba(227, 60, 36, 0.12); color: #ff6b53; }
        .menu a.active { background: #fff; color: #111; }
        .signout { margin-top: auto; padding-top: 20px; display: flex; justify-content: center; }
        .signout form { margin: 0; }
        .signout button { width: 170px; border: none; padding: 12px 20px; border-radius: 999px; background: #e33c24; color: #fff; font-weight: 800; cursor: pointer; }
        .signout button:hover { background: #c92f1b; }
        .main { flex: 1; width: 100%; max-width: none; margin: 0; padding: 30px 32px; }
        .header { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 22px; }
        .header h1 { margin: 0; font-size: 32px; font-weight: 900; }
        .new-button { display: inline-flex; align-items: center; gap: 10px; border: none; border-radius: 999px; background: #e33c24; color: #fff; cursor: pointer; font-size: 15px; font-weight: 900; padding: 14px 24px; box-shadow: 0 4px 12px rgba(227, 60, 36, 0.22); }
        .new-button:hover { background: #c92f1b; }
        .new-button span { font-size: 22px; line-height: 0; }
        .notice { margin-bottom: 18px; border: 1px solid rgba(227, 60, 36, 0.18); border-radius: 10px; background: #fff; color: #111; padding: 12px 16px; font-weight: 700; }
        .stats { display: grid; grid-template-columns: repeat(4, minmax(220px, 1fr)); gap: 18px; margin-bottom: 24px; }
        .card { border-radius: 14px; padding: 18px 20px; display: flex; justify-content: space-between; align-items: center; min-height: 105px; color: #fff; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.10); }
        .card.red { background: #e33c24; }
        .card.black { background: #111; }
        .card .info { display: flex; flex-direction: column; gap: 6px; }
        .card .label { font-size: 14px; font-weight: 800; opacity: 0.95; }
        .card .value { font-size: 30px; font-weight: 900; line-height: 1; }
@include('partials.admin-summary-table-styles')
        .satellite-name { color: #fff; font: inherit; }
        .muted { color: rgba(255, 255, 255, 0.78); font-size: 13px; font-weight: 600; }
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
        @media (max-width: 768px) { .page { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .sidebar .brand { height: 90px; } .menu { flex-direction: row; flex-wrap: wrap; } .menu a { padding: 12px 14px; font-size: 14px; } .signout { justify-content: flex-start; } .main { padding: 20px; } .header { align-items: flex-start; flex-direction: column; } .stats { grid-template-columns: 1fr; } .header h1 { font-size: 26px; } .table-card { overflow-x: auto; } }
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
            <a href="{{ route('admin.satellites') }}" class="active">Satellites</a>
            <a href="{{ route('admin.members') }}">Members</a>
            <a href="{{ route('admin.seekers') }}">Seekers</a>
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
        <div class="header">
            <h1>Satellites Dashboard</h1>
            <button type="button" class="new-button" data-open-satellite-modal><span>+</span> New satellite</button>
        </div>

        @if (session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif

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
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <div class="data-table-field search">
                <label for="satellites-search">Search</label>
                <div class="data-table-search">
                    <input id="satellites-search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Search satellite, hub, or assignee" data-debounced-submit>
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
                <a class="data-table-link-button" href="{{ route('admin.satellites.export', request()->query()) }}" aria-label="Export all {{ $satellites->total() }} matching satellites to CSV">Export CSV</a>
                <a class="data-table-link-button {{ $hasActiveFilters ? '' : 'disabled' }}" href="{{ route('admin.satellites') }}" aria-disabled="{{ $hasActiveFilters ? 'false' : 'true' }}" @unless($hasActiveFilters) tabindex="-1" @endunless>Clear</a>
            </div>
        </form>

        <section class="table-card">
            <h2>Satellite Summary</h2>
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
                                <div class="muted">Assigned to {{ $satellite->assignedUser?->name ?? 'Unassigned' }}</div>
                            </td>
                            <td>{{ $satellite->hub ?: 'Unassigned' }}</td>
                            <td>{{ $satellite->leaders_count }}</td>
                            <td>{{ $satellite->members_count }}</td>
                            <td class="data-table-action-col">
                                <div class="data-table-row-menu">
                                    <button type="button" class="data-table-menu-button" data-menu-button aria-haspopup="menu" aria-expanded="false" aria-label="Actions for {{ $satellite->display_name }}"><span aria-hidden="true">&#8942;</span></button>
                                    <div class="data-table-menu" role="menu">
                                        <a href="{{ route('admin.members', ['search' => $satellite->display_name]) }}" role="menuitem">View members</a>
                                        <a href="{{ route('admin.seekers', ['district' => $satellite->display_name]) }}" role="menuitem">View seekers</a>
                                        @if ($satellite->assignedUser)
                                            <a href="{{ route('admin.users.show', $satellite->assignedUser) }}" role="menuitem">View assigned user</a>
                                        @endif
                                        <a href="{{ route('admin.satellites.export', ['search' => $satellite->display_name]) }}" role="menuitem">Export satellite row</a>
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
    </main>
</div>

<div class="modal-backdrop {{ $errors->any() ? 'open' : '' }}" data-satellite-modal>
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="new-satellite-title">
        <div class="modal-header">
            <h2 id="new-satellite-title">New satellite</h2>
            <button type="button" class="modal-close" aria-label="Close new satellite form" data-close-satellite-modal>x</button>
        </div>

        <form method="POST" action="{{ route('admin.satellites.store') }}">
            @csrf
            <div class="field">
                <label for="satellite-name">Name of satellite</label>
                <input id="satellite-name" name="name" type="text" value="{{ old('name') }}" placeholder="e.g. Santolan" required>
            </div>
            @error('name')
                <div class="field-error">{{ $message }}</div>
            @enderror

            <div class="field">
                <label for="satellite-hub">Elevate hub</label>
                <select id="satellite-hub" name="hub" required>
                    <option value="">Select a hub</option>
                    @foreach ($hubs as $hub)
                        <option value="{{ $hub }}" @selected(old('hub') === $hub)>{{ $hub }}</option>
                    @endforeach
                </select>
            </div>
            @error('hub')
                <div class="field-error">{{ $message }}</div>
            @enderror

            <div class="field">
                <label for="satellite-assigned-user">Assigned user</label>
                <select id="satellite-assigned-user" name="assigned_user_id">
                    <option value="">Unassigned</option>
                    @foreach ($assignableUsers as $assignableUser)
                        <option value="{{ $assignableUser->id }}" @selected(old('assigned_user_id') == $assignableUser->id)>
                            {{ $assignableUser->name }} ({{ $assignableUser->email }})
                        </option>
                    @endforeach
                </select>
            </div>
            @error('assigned_user_id')
                <div class="field-error">{{ $message }}</div>
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
</body>
</html>
