<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Dashboard</title>
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
        .directory-card { width: 100%; overflow: hidden; border-radius: 16px; background: #fff; color: #0f172a; }
        .directory-table { width: 100%; border-collapse: collapse; font-size: 15px; }
        .directory-table th { color: #9aa3b2; background: #fff; font-weight: 800; text-align: left; padding: 18px 20px 12px; border-bottom: 1px solid #edf0f4; }
        .directory-table td { padding: 17px 20px; border-bottom: 1px solid #f1f3f6; vertical-align: middle; }
        .directory-table tr:last-child td { border-bottom: 0; }
        .name-cell { color: #0f172a; font-weight: 900; line-height: 1.35; }
        .email-cell { color: #475569; }
        .role-cell { color: #475569; text-transform: capitalize; }
        .district-pill { display: inline-flex; min-height: 24px; align-items: center; border-radius: 999px; background: #e3e8f0; color: #0f172a; font-size: 13px; font-weight: 800; padding: 0 12px; }
        .unassigned { color: #ef3b2b; font-size: 13px; font-weight: 800; }
        .open-account { display: inline-flex; min-width: 86px; min-height: 44px; align-items: center; justify-content: center; border: 1px solid #d9dee7; border-radius: 999px; color: #000; font-size: 13px; font-weight: 800; line-height: 1.1; text-align: center; text-decoration: none; }
        .open-account:hover { border-color: #e33c24; color: #e33c24; }
        .empty-row { color: #64748b; font-weight: 700; text-align: center; }
@include('partials.table-pagination-styles')
@include('partials.data-table-styles')
        .modal-backdrop { position: fixed; inset: 0; z-index: 20; display: none; align-items: flex-start; justify-content: center; background: rgba(0, 0, 0, 0.44); padding: 64px 16px 24px; }
        .modal-backdrop.open { display: flex; }
        .modal { width: min(100%, 470px); border-radius: 14px; background: #fff; padding: 26px 24px 24px; box-shadow: 0 24px 70px rgba(15, 23, 42, 0.24); }
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
        .account-modal { width: min(100%, 446px); }
        .account-summary { display: grid; gap: 4px; margin-bottom: 24px; }
        .account-name { color: #0f172a; font-size: 15px; font-weight: 900; }
        .account-email { color: #475569; font-size: 13px; }
        .account-role { color: #64748b; font-size: 13px; text-transform: capitalize; }
        @media (max-width: 1200px) { .stats { grid-template-columns: repeat(2, minmax(220px, 1fr)); } }
        @media (max-width: 768px) { .page { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .sidebar .brand { height: 90px; } .menu { flex-direction: row; flex-wrap: wrap; } .menu a { padding: 12px 14px; font-size: 14px; } .signout { justify-content: flex-start; } .main { padding: 20px; } .header { align-items: flex-start; flex-direction: column; } .stats { grid-template-columns: 1fr; } .header h1 { font-size: 26px; } .directory-card { overflow-x: auto; } .directory-table { min-width: 720px; } }
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
            <a href="{{ route('admin.seekers') }}">Seekers</a>
            <a href="{{ route('admin.users') }}" class="active">Users</a>
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
            <h1>Users Dashboard</h1>
            <button type="button" class="new-button" data-open-user-modal><span>+</span> New user</button>
        </div>

        @if (session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif

        <div class="stats">
            <div class="card red"><div class="info"><span class="label">Total Users</span><span class="value">{{ $totalUsers }}</span></div></div>
            <div class="card black"><div class="info"><span class="label">Active Members</span><span class="value">{{ $activeMembers }}</span></div></div>
            <div class="card red"><div class="info"><span class="label">Total Leaders</span><span class="value">{{ $totalLeaders }}</span></div></div>
            <div class="card black"><div class="info"><span class="label">Pending Approvals</span><span class="value">{{ $pendingApprovals }}</span></div></div>
        </div>

        @php
            $sortUrl = fn (string $column) => route('admin.users', array_merge(request()->except('page'), [
                'sort' => $column,
                'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
            ]));
            $sortLabel = fn (string $column) => $sort === $column ? ($direction === 'asc' ? 'Ascending' : 'Descending') : 'Not sorted';
            $sortGlyph = fn (string $column) => $sort === $column ? ($direction === 'asc' ? 'up' : 'down') : '-';
            $hasActiveFilters = collect($filters)->contains(fn ($value) => $value !== '' && $value !== null);
        @endphp

        <form method="GET" action="{{ route('admin.users') }}" class="data-table-toolbar" data-table-filter-form>
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <div class="data-table-field search">
                <label for="users-search">Search</label>
                <div class="data-table-search">
                    <input id="users-search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Search name or email" data-debounced-submit>
                    <button type="button" class="data-table-search-clear" data-search-clear aria-label="Clear search">&times;</button>
                </div>
            </div>
            <div class="data-table-field compact">
                <label for="users-role">Role</label>
                <select id="users-role" name="role" data-auto-submit>
                    <option value="">All roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected($filters['role'] === $role)>{{ ucwords(str_replace('_', ' ', $role)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="data-table-field">
                <label for="users-district">District</label>
                <select id="users-district" name="district" data-auto-submit>
                    <option value="">All districts</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district }}" @selected($filters['district'] === $district)>{{ $district }}</option>
                    @endforeach
                    <option value="unassigned" @selected($filters['district'] === 'unassigned')>Unassigned</option>
                </select>
            </div>
            <div class="data-table-actions">
                <a class="data-table-link-button" href="{{ route('admin.users.export', request()->query()) }}" aria-label="Export all {{ $users->total() }} matching users to CSV">Export CSV</a>
                <a class="data-table-link-button {{ $hasActiveFilters ? '' : 'disabled' }}" href="{{ route('admin.users') }}" aria-disabled="{{ $hasActiveFilters ? 'false' : 'true' }}" @unless($hasActiveFilters) tabindex="-1" @endunless>Clear</a>
            </div>
        </form>

        <section class="directory-card" data-bulk-scope data-matching-count="{{ $users->total() }}">
            <form method="POST" action="{{ route('admin.users.bulk') }}" data-bulk-form data-confirm-template="This will {action} for {count} user records.">
                @csrf
                @method('PATCH')
                <input type="hidden" name="selection_scope" value="ids" data-selection-scope>
                <input type="hidden" name="bulk_action" value="" data-bulk-action-input>
                <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                @foreach ($filters as $filterName => $filterValue)
                    <input type="hidden" name="filters[{{ $filterName }}]" value="{{ $filterValue }}">
                @endforeach
                <span data-selected-ids></span>
                <div class="data-table-selection-bar" data-selection-bar hidden>
                    <span class="data-table-selection-summary" data-selection-summary></span>
                    <span class="data-table-selection-help" data-selection-help></span>
                    <select name="district" aria-label="Bulk user district">
                        <option value="">Unassigned</option>
                        @foreach ($districts as $district)
                            <option value="{{ $district }}">{{ $district }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="data-table-button primary" data-bulk-action="assign_district" data-action-label="reassign selected users" data-confirm-template="This will reassign {count} user accounts to the chosen district.">Assign district</button>
                    <button type="button" class="data-table-button" data-clear-selection>Clear</button>
                </div>
            </form>

            <div class="data-table-scroll">
            <table class="directory-table" id="admin-users-table" data-enhanced-table data-table-id="admin-users">
                <thead>
                    <tr>
                        <th class="data-table-select-col" data-locked-column>
                            <input class="data-table-checkbox" type="checkbox" data-select-page aria-label="Select all users on this page">
                        </th>
                        <th data-column="name" data-label="Name" aria-sort="{{ $sort === 'name' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="data-table-sort-link" href="{{ $sortUrl('name') }}" aria-label="Sort by name. {{ $sortLabel('name') }}">Name <span class="data-table-sort-indicator" aria-hidden="true">{{ $sortGlyph('name') }}</span></a>
                        </th>
                        <th data-column="email" data-label="Email" aria-sort="{{ $sort === 'email' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="data-table-sort-link" href="{{ $sortUrl('email') }}" aria-label="Sort by email. {{ $sortLabel('email') }}">Email <span class="data-table-sort-indicator" aria-hidden="true">{{ $sortGlyph('email') }}</span></a>
                        </th>
                        <th data-column="role" data-label="Role" aria-sort="{{ $sort === 'role' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="data-table-sort-link" href="{{ $sortUrl('role') }}" aria-label="Sort by role. {{ $sortLabel('role') }}">Role <span class="data-table-sort-indicator" aria-hidden="true">{{ $sortGlyph('role') }}</span></a>
                        </th>
                        <th data-column="district" data-label="District" aria-sort="{{ $sort === 'district' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="data-table-sort-link" href="{{ $sortUrl('district') }}" aria-label="Sort by district. {{ $sortLabel('district') }}">District <span class="data-table-sort-indicator" aria-hidden="true">{{ $sortGlyph('district') }}</span></a>
                        </th>
                        <th class="data-table-action-col" data-locked-column><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td class="data-table-select-col">
                                <input class="data-table-checkbox" type="checkbox" value="{{ $user->id }}" data-row-checkbox aria-label="Select {{ $user->name }}">
                            </td>
                            <td class="name-cell">{{ $user->name }}</td>
                            <td class="email-cell">{{ $user->email }}</td>
                            <td class="role-cell">{{ str_replace('_', ' ', $user->role ?: 'member') }}</td>
                            <td>
                                @php
                                    $assignedDistrict = $user->assignedSatellites->first()?->name ?? $user->district;
                                @endphp

                                @if ($assignedDistrict)
                                    <span class="district-pill">{{ $assignedDistrict }}</span>
                                @else
                                    <span class="unassigned">Unassigned</span>
                                @endif
                            </td>
                            <td class="data-table-action-col">
                                <div class="data-table-row-menu">
                                    <button type="button" class="data-table-menu-button" data-menu-button aria-haspopup="menu" aria-expanded="false" aria-label="Actions for {{ $user->name }}"><span aria-hidden="true">&#8942;</span></button>
                                    <div class="data-table-menu" role="menu">
                                        <a href="{{ route('admin.users.show', $user) }}" role="menuitem">View account</a>
                                        <button
                                            type="button"
                                            role="menuitem"
                                            data-open-account-modal
                                            data-user-id="{{ $user->id }}"
                                            data-user-name="{{ $user->name }}"
                                            data-user-email="{{ $user->email }}"
                                            data-user-role="{{ ucwords(str_replace('_', ' ', $user->role ?: 'member')) }}"
                                            data-user-district="{{ $user->district }}"
                                            data-update-url="{{ route('admin.users.update', $user) }}"
                                        >
                                            Edit assignment
                                        </button>
                                        @if ($assignedDistrict)
                                            <form method="POST" action="{{ route('admin.users.update', $user) }}" data-confirm-message="This will unassign {{ $user->name }} from {{ $assignedDistrict }}.">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="district" value="">
                                                <button type="submit" role="menuitem">Unassign</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty-row" colspan="6">
                                @if (array_filter($filters))
                                    <div class="data-table-empty-card">
                                        <strong>No matches found.</strong>
                                        <span>No users match the current search or filters.</span>
                                        <a class="data-table-link-button" href="{{ route('admin.users') }}">Clear</a>
                                    </div>
                                @else
                                    <div class="data-table-empty-card">
                                        <strong>No users exist yet.</strong>
                                        <span>Create the first non-admin user account to populate this table.</span>
                                        <button type="button" class="data-table-button primary" data-open-user-modal>Add user</button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            <div class="data-table-toolbar" style="border-width: 0 0 1px; border-radius: 0; box-shadow: none;">
                <button type="button" class="data-table-button" data-select-matching>Select all {{ $users->total() }} matching current filters</button>
            </div>
            @include('partials.table-pagination', ['paginator' => $users, 'pageSizes' => $pageSizes, 'currentPageSize' => $perPage])
        </section>
    </main>
</div>

<div class="modal-backdrop" data-account-modal>
    <section class="modal account-modal" role="dialog" aria-modal="true" aria-labelledby="account-modal-title">
        <div class="modal-header">
            <h2 id="account-modal-title">User account</h2>
            <button type="button" class="modal-close" aria-label="Close user account" data-close-account-modal>x</button>
        </div>

        <div class="account-summary">
            <div class="account-name" data-account-name></div>
            <div class="account-email" data-account-email></div>
            <div class="account-role" data-account-role></div>
        </div>

        <form method="POST" data-account-form data-confirm-message="This will update this user's district assignment.">
            @csrf
            @method('PATCH')
            <div class="field">
                <label for="account-district">Assign district</label>
                <select id="account-district" name="district" data-account-district>
                    <option value="">Unassigned</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district }}">{{ $district }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="modal-submit">Save changes</button>
        </form>
    </section>
</div>

<div class="modal-backdrop {{ $errors->any() ? 'open' : '' }}" data-user-modal>
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="new-user-title">
        <div class="modal-header">
            <h2 id="new-user-title">New user</h2>
            <button type="button" class="modal-close" aria-label="Close new user form" data-close-user-modal>x</button>
        </div>

        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="field">
                <label for="user-name">Name</label>
                <input id="user-name" name="name" type="text" value="{{ old('name') }}" placeholder="e.g. Maria Santos" required>
            </div>
            @error('name')<div class="field-error">{{ $message }}</div>@enderror

            <div class="field">
                <label for="user-email">Email</label>
                <input id="user-email" name="email" type="email" value="{{ old('email') }}" placeholder="name@elevate.org" required>
            </div>
            @error('email')<div class="field-error">{{ $message }}</div>@enderror

            <div class="field">
                <label for="user-role">Role</label>
                <select id="user-role" name="role" required>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(old('role', 'member') === $role)>{{ ucwords(str_replace('_', ' ', $role)) }}</option>
                    @endforeach
                </select>
            </div>
            @error('role')<div class="field-error">{{ $message }}</div>@enderror

            <div class="field">
                <label for="user-district">District</label>
                <select id="user-district" name="district">
                    <option value="">Unassigned</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district }}" @selected(old('district') === $district)>{{ $district }}</option>
                    @endforeach
                </select>
            </div>
            @error('district')<div class="field-error">{{ $message }}</div>@enderror

            <button type="submit" class="modal-submit">Add user</button>
        </form>
    </section>
</div>

<script>
    const modal = document.querySelector('[data-user-modal]');
    const closeButton = document.querySelector('[data-close-user-modal]');
    const accountModal = document.querySelector('[data-account-modal]');
    const accountForm = document.querySelector('[data-account-form]');
    const accountName = document.querySelector('[data-account-name]');
    const accountEmail = document.querySelector('[data-account-email]');
    const accountRole = document.querySelector('[data-account-role]');
    const accountDistrict = document.querySelector('[data-account-district]');
    const closeAccountButton = document.querySelector('[data-close-account-modal]');

    document.querySelectorAll('[data-open-user-modal]').forEach((openButton) => {
        openButton.addEventListener('click', () => modal?.classList.add('open'));
    });
    closeButton?.addEventListener('click', () => modal?.classList.remove('open'));
    modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.classList.remove('open');
        }
    });

    document.querySelectorAll('[data-open-account-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!accountModal || !accountForm || !accountDistrict) {
                return;
            }

            accountForm.action = button.dataset.updateUrl || '';
            accountName.textContent = button.dataset.userName || '';
            accountEmail.textContent = button.dataset.userEmail || '';
            accountRole.textContent = button.dataset.userRole || '';
            accountDistrict.value = button.dataset.userDistrict || '';
            accountModal.classList.add('open');
        });
    });

    closeAccountButton?.addEventListener('click', () => accountModal?.classList.remove('open'));
    accountModal?.addEventListener('click', (event) => {
        if (event.target === accountModal) {
            accountModal.classList.remove('open');
        }
    });
</script>
@include('partials.data-table-scripts')
</body>
</html>
