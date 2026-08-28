<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members Dashboard</title>
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
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; }
        .header h1 { margin: 0; font-size: 32px; font-weight: 900; }
        .stats { display: grid; grid-template-columns: repeat(4, minmax(220px, 1fr)); gap: 18px; margin-bottom: 24px; }
        .card { border-radius: 14px; padding: 18px 20px; display: flex; justify-content: space-between; align-items: center; min-height: 105px; color: #fff; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.10); }
        .card.red { background: #e33c24; }
        .card.black { background: #111; }
        .card .info { display: flex; flex-direction: column; gap: 6px; }
        .card .label { font-size: 14px; font-weight: 800; opacity: 0.95; }
        .card .value { font-size: 30px; font-weight: 900; line-height: 1; }
@include('partials.admin-summary-table-styles')
@include('partials.table-pagination-styles')
@include('partials.data-table-styles')
        @media (max-width: 1200px) { .stats { grid-template-columns: repeat(2, minmax(220px, 1fr)); } }
        @media (max-width: 768px) { .page { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .sidebar .brand { height: 90px; } .menu { flex-direction: row; flex-wrap: wrap; } .menu a { padding: 12px 14px; font-size: 14px; } .signout { justify-content: flex-start; } .main { padding: 20px; } .stats { grid-template-columns: 1fr; } .header h1 { font-size: 26px; } .table-card { overflow-x: auto; } }
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
            <a href="{{ route('admin.members') }}" class="active">Members</a>
            <a href="{{ route('admin.seekers') }}">Seekers</a>
            <a href="{{ route('admin.users') }}">Users</a>
            <a href="{{ route('admin.reports') }}">Reports</a>
            <a href="{{ route('admin.analytics') }}">Analytics</a>
        </nav>
        <div class="signout"><form method="POST" action="<?php echo route('logout'); ?>"><?php echo csrf_field(); ?><button type="submit">Sign out</button></form></div>
    </aside>
    <main class="main">
        <div class="header"><h1>Members Dashboard</h1></div>
        <div class="stats">
            <div class="card red"><div class="info"><span class="label">Total Members</span><span class="value">{{ $totalMembers }}</span></div></div>
            <div class="card black"><div class="info"><span class="label">Total Satellites</span><span class="value">{{ $totalSatellites }}</span></div></div>
            <div class="card red"><div class="info"><span class="label">Total Leaders</span><span class="value">{{ $totalLeaders }}</span></div></div>
            <div class="card black"><div class="info"><span class="label">New Members</span><span class="value">{{ $newMembers }}</span></div></div>
        </div>

        @php
            $sortUrl = fn (string $column) => route('admin.members', array_merge(request()->except('page'), [
                'sort' => $column,
                'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
            ]));
            $sortLabel = fn (string $column) => $sort === $column ? ($direction === 'asc' ? 'Ascending' : 'Descending') : 'Not sorted';
            $sortGlyph = fn (string $column) => $sort === $column ? ($direction === 'asc' ? 'up' : 'down') : '-';
            $hasActiveFilters = collect($filters)->contains(fn ($value) => $value !== '' && $value !== null);
        @endphp

        <form method="GET" action="{{ route('admin.members') }}" class="data-table-toolbar" data-table-filter-form>
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <div class="data-table-field search">
                <label for="members-search">Search</label>
                <div class="data-table-search">
                    <input id="members-search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Search satellites..." data-debounced-submit>
                    <button type="button" class="data-table-search-clear" data-search-clear aria-label="Clear search">&times;</button>
                </div>
            </div>
            <div class="data-table-actions">
                <a class="data-table-link-button" href="{{ route('admin.members.export', request()->query()) }}" aria-label="Export all {{ $satellites->total() }} matching member rows to CSV">Export CSV</a>
                <a class="data-table-link-button {{ $hasActiveFilters ? '' : 'disabled' }}" href="{{ route('admin.members') }}" aria-disabled="{{ $hasActiveFilters ? 'false' : 'true' }}" @unless($hasActiveFilters) tabindex="-1" @endunless>Clear</a>
            </div>
        </form>

        <section class="table-card">
            <h2>Member Breakdown</h2>
            <div class="data-table-scroll">
            <table id="admin-members-table" data-enhanced-table data-table-id="admin-members">
                <thead>
                    <tr>
                        <th data-column="satellite" data-label="Satellite" aria-sort="{{ $sort === 'name' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="data-table-sort-link" href="{{ $sortUrl('name') }}" aria-label="Sort by satellite. {{ $sortLabel('name') }}">Satellite <span class="data-table-sort-indicator" aria-hidden="true">{{ $sortGlyph('name') }}</span></a>
                        </th>
                        <th data-column="members" data-label="Members" aria-sort="{{ $sort === 'members' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="data-table-sort-link" href="{{ $sortUrl('members') }}" aria-label="Sort by members. {{ $sortLabel('members') }}">Members <span class="data-table-sort-indicator" aria-hidden="true">{{ $sortGlyph('members') }}</span></a>
                        </th>
                        <th data-column="new_members" data-label="New Members" aria-sort="{{ $sort === 'new_members' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="data-table-sort-link" href="{{ $sortUrl('new_members') }}" aria-label="Sort by new members. {{ $sortLabel('new_members') }}">New Members <span class="data-table-sort-indicator" aria-hidden="true">{{ $sortGlyph('new_members') }}</span></a>
                        </th>
                        <th class="data-table-action-col" data-locked-column><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($satellites as $satellite)
                        <tr>
                            <td>{{ $satellite->display_name }}</td>
                            <td>{{ $satellite->members_count }}</td>
                            <td>{{ $satellite->new_members_count }}</td>
                            <td class="data-table-action-col">
                                <div class="data-table-row-menu">
                                    <button type="button" class="data-table-menu-button" data-menu-button aria-haspopup="menu" aria-expanded="false" aria-label="Actions for {{ $satellite->display_name }}"><span aria-hidden="true">&#8942;</span></button>
                                    <div class="data-table-menu" role="menu">
                                        <a href="{{ route('admin.users', ['district' => $satellite->display_name]) }}" role="menuitem">View members</a>
                                        <a href="{{ route('admin.satellites', ['search' => $satellite->display_name]) }}" role="menuitem">View satellite details</a>
                                        <a href="{{ route('admin.users.export', ['district' => $satellite->display_name]) }}" role="menuitem">Export satellite members</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                @if (array_filter($filters))
                                    <div class="data-table-empty-card">
                                        <strong>No matches found.</strong>
                                        <span>No member rows match the current search.</span>
                                        <a class="data-table-link-button" href="{{ route('admin.members') }}">Clear</a>
                                    </div>
                                @else
                                    <div class="data-table-empty-card">
                                        <strong>No satellites yet.</strong>
                                        <span>Create a satellite before member breakdowns can be shown.</span>
                                        <a class="data-table-link-button primary" href="{{ route('admin.satellites') }}">Open satellites</a>
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
@include('partials.data-table-scripts')
</body>
</html>
