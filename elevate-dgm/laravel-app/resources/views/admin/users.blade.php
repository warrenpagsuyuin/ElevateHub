@extends('layouts.app')

@section('title', 'Users Dashboard | Elevate DGM')

@push('styles')
@include('partials.table-pagination-styles')
@include('partials.data-table-styles')

        .admin-users-page {
            display: grid;
            gap: 24px;
        }

        .admin-users-header {
            align-items: center;
            margin-bottom: 0;
        }

        .admin-users-header h1 {
            font-size: 34px;
            font-weight: 800;
        }

        .new-button {
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: 1px solid var(--brand-red);
            border-radius: 8px;
            background: var(--brand-red);
            color: #ffffff;
            cursor: pointer;
            font-size: 15px;
            font-weight: 800;
            line-height: 1;
            padding: 0 18px;
            text-decoration: none;
            transition: background 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease;
        }

        .new-button:hover,
        .new-button:focus-visible {
            border-color: var(--brand-red-dark);
            background: var(--brand-red-dark);
            box-shadow: 0 0 0 3px rgba(239, 59, 36, 0.18);
            outline: none;
        }

        .new-button span {
            font-size: 22px;
            line-height: 1;
        }

        .user-summary-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 0;
        }

        .user-summary-card {
            min-height: 120px;
            align-items: flex-start;
            padding: 22px;
        }

        .user-summary-card .info {
            gap: 12px;
        }

        .user-summary-card .stat-label {
            color: #344054;
            font-size: 14px;
            font-weight: 700;
        }

        .user-summary-card .stat-value {
            font-size: 38px;
            font-weight: 800;
        }

        .directory-card {
            width: 100%;
            overflow: hidden;
            border-radius: 12px;
            background: #ffffff;
            color: #0f172a;
        }

        .directory-table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
            background: #ffffff;
            color: #344054;
            font-size: 15px;
            table-layout: auto;
        }

        .directory-table th,
        .directory-table td {
            padding: 16px 20px;
            border-bottom: 1px solid #edf0f4;
            text-align: left;
            vertical-align: middle;
        }

        .directory-table th {
            background: #fff7f5;
            color: #475467;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .directory-table tbody tr {
            min-height: 58px;
        }

        .directory-table tbody tr:hover {
            background: #fffaf8;
        }

        .directory-table tr:last-child td {
            border-bottom: 0;
        }

        .name-cell {
            color: #0f172a;
            font-weight: 800;
            line-height: 1.35;
        }

        .email-cell {
            color: #475569;
            font-weight: 500;
        }

        .role-badge,
        .district-pill,
        .unassigned {
            display: inline-flex;
            min-height: 28px;
            align-items: center;
            justify-content: center;
            border: 1px solid #d9dee7;
            border-radius: 999px;
            background: #f8fafc;
            color: #344054;
            font-size: 12px;
            font-weight: 800;
            line-height: 1;
            padding: 0 11px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .unassigned {
            border-color: #f1c3ba;
            background: #fff7f5;
            color: #b42318;
        }

        .empty-row {
            color: #64748b;
            font-weight: 700;
            text-align: center;
        }

        .modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: var(--z-modal-backdrop);
            display: none;
            align-items: flex-start;
            justify-content: center;
            background: rgba(15, 23, 42, 0.48);
            padding: 64px 16px 24px;
        }

        .modal-backdrop.open {
            display: flex;
        }

        .modal {
            width: min(100%, 470px);
            border-radius: 12px;
            background: #ffffff;
            padding: 26px 24px 24px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.24);
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 24px;
        }

        .modal-header h2 {
            margin: 0;
            color: #0f172a;
            font-size: 20px;
            font-weight: 800;
        }

        .modal-close {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 8px;
            background: transparent;
            color: #94a3b8;
            cursor: pointer;
            font-size: 24px;
            line-height: 1;
        }

        .modal-close:hover,
        .modal-close:focus-visible {
            background: #fff4f2;
            color: var(--brand-red);
            outline: none;
        }

        .field {
            display: grid;
            gap: 7px;
            margin-bottom: 18px;
        }

        .field label {
            color: #344054;
            font-size: 13px;
            font-weight: 700;
        }

        .field input,
        .field select {
            width: 100%;
            height: 44px;
            border: 1px solid #d9dee7;
            border-radius: 8px;
            background: #ffffff;
            color: #111827;
            font: inherit;
            padding: 0 14px;
            outline: none;
        }

        .field input:focus,
        .field select:focus {
            border-color: var(--brand-red);
            box-shadow: 0 0 0 3px rgba(227, 60, 36, 0.12);
        }

        .field-error {
            margin-top: -8px;
            margin-bottom: 14px;
            color: #b42318;
            font-size: 12px;
            font-weight: 700;
        }

        .modal-submit {
            width: 100%;
            min-height: 46px;
            border: 1px solid var(--brand-red);
            border-radius: 8px;
            background: var(--brand-red);
            color: #ffffff;
            cursor: pointer;
            font: inherit;
            font-weight: 800;
        }

        .modal-submit:hover,
        .modal-submit:focus-visible {
            background: var(--brand-red-dark);
            outline: none;
        }

        .account-modal {
            width: min(100%, 446px);
        }

        .account-summary {
            display: grid;
            gap: 4px;
            margin-bottom: 24px;
        }

        .account-name {
            color: #0f172a;
            font-size: 15px;
            font-weight: 800;
        }

        .account-email,
        .account-role {
            color: #475569;
            font-size: 13px;
            font-weight: 600;
        }

        @media (max-width: 1279px) {
            .user-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767px) {
            .admin-users-header {
                align-items: stretch;
            }

            .user-summary-grid {
                grid-template-columns: 1fr;
            }
        }
@endpush

@section('content')
<div class="admin-users-page">
        <div class="page-header admin-users-header">
            <div>
                <h1>Users</h1>
                <p class="page-subtitle">Manage users, roles and district assignments.</p>
            </div>
            <button type="button" class="new-button" data-open-user-modal><span aria-hidden="true">+</span> New User</button>
        </div>

        @if (session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif

        @php
            $sortUrl = fn (string $column) => route('admin.users', array_merge(request()->except('page'), [
                'sort' => $column,
                'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
            ]));
            $sortLabel = fn (string $column) => $sort === $column ? ($direction === 'asc' ? 'Ascending' : 'Descending') : 'Not sorted';
            $sortGlyph = fn (string $column) => $sort === $column ? ($direction === 'asc' ? '&uarr;' : '&darr;') : '&#8597;';
            $hasActiveFilters = collect($filters)->contains(fn ($value) => $value !== '' && $value !== null);
            $filterChipUrl = fn (string $filterName) => route('admin.users', request()->except([$filterName, 'page']));
        @endphp

        <form method="GET" action="{{ route('admin.users') }}" class="data-table-toolbar" data-table-filter-form>
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <div class="data-table-field search">
                <label for="users-search">Search</label>
                <div class="data-table-search">
                    <input id="users-search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Search users by name or email" data-debounced-submit>
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
                <a class="data-table-link-button tertiary {{ $hasActiveFilters ? '' : 'disabled' }}" href="{{ route('admin.users') }}" aria-disabled="{{ $hasActiveFilters ? 'false' : 'true' }}" @unless($hasActiveFilters) tabindex="-1" @endunless>Clear Filters</a>
            </div>
        </form>

        @if ($hasActiveFilters)
            <div class="data-table-filter-chips" aria-label="Active user filters">
                @if ($filters['search'] !== '')
                    <a class="data-table-chip" href="{{ $filterChipUrl('search') }}">Search: {{ $filters['search'] }} <span aria-hidden="true">&times;</span></a>
                @endif
                @if ($filters['role'] !== '')
                    <a class="data-table-chip" href="{{ $filterChipUrl('role') }}">Role: {{ ucwords(str_replace('_', ' ', $filters['role'])) }} <span aria-hidden="true">&times;</span></a>
                @endif
                @if ($filters['district'] !== '')
                    <a class="data-table-chip" href="{{ $filterChipUrl('district') }}">District: {{ $filters['district'] === 'unassigned' ? 'Unassigned' : $filters['district'] }} <span aria-hidden="true">&times;</span></a>
                @endif
                <a class="data-table-link-button tertiary" href="{{ route('admin.users') }}">Clear all</a>
            </div>
        @endif

        <section class="directory-card">
            <div class="data-table-scroll">
            <table class="directory-table" id="admin-users-table" data-enhanced-table data-table-id="admin-users">
                <thead>
                    <tr>
                        <th data-column="name" data-label="Name" aria-sort="{{ $sort === 'name' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="data-table-sort-link" href="{{ $sortUrl('name') }}" aria-label="Sort by name. {{ $sortLabel('name') }}">Name <span class="data-table-sort-indicator" aria-hidden="true">{!! $sortGlyph('name') !!}</span></a>
                        </th>
                        <th data-column="email" data-label="Email" aria-sort="{{ $sort === 'email' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="data-table-sort-link" href="{{ $sortUrl('email') }}" aria-label="Sort by email. {{ $sortLabel('email') }}">Email <span class="data-table-sort-indicator" aria-hidden="true">{!! $sortGlyph('email') !!}</span></a>
                        </th>
                        <th data-column="role" data-label="Role" aria-sort="{{ $sort === 'role' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="data-table-sort-link" href="{{ $sortUrl('role') }}" aria-label="Sort by role. {{ $sortLabel('role') }}">Role <span class="data-table-sort-indicator" aria-hidden="true">{!! $sortGlyph('role') !!}</span></a>
                        </th>
                        <th data-column="district" data-label="District" aria-sort="{{ $sort === 'district' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="data-table-sort-link" href="{{ $sortUrl('district') }}" aria-label="Sort by district. {{ $sortLabel('district') }}">District <span class="data-table-sort-indicator" aria-hidden="true">{!! $sortGlyph('district') !!}</span></a>
                        </th>
                        <th class="data-table-action-col" data-locked-column><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td class="name-cell">{{ $user->name }}</td>
                            <td class="email-cell">{{ $user->email }}</td>
                            <td><span class="role-badge">{{ str_replace('_', ' ', $user->role ?: 'member') }}</span></td>
                            <td>
                                @php $assignedDistrict = $user->district; @endphp

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
                                        <a href="{{ route('admin.users.show', $user) }}" role="menuitem">View</a>
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
                                            Update District
                                        </button>
                                        @if ($assignedDistrict)
                                            <form method="POST" action="{{ route('admin.users.update', $user) }}" data-confirm-message="This will unassign {{ $user->name }} from {{ $assignedDistrict }}.">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="district" value="">
                                                <button type="submit" role="menuitem" aria-label="Remove district assignment for {{ $user->name }}">Remove District Assignment</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty-row" colspan="5">
                                @if (array_filter($filters))
                                    <div class="data-table-empty-card">
                                        <strong>No users found.</strong>
                                        <span>No users match your current filters.</span>
                                        <a class="data-table-link-button tertiary" href="{{ route('admin.users') }}">Clear Filters</a>
                                    </div>
                                @else
                                    <div class="data-table-empty-card">
                                        <strong>No users yet.</strong>
                                        <span>Create your first user to start managing accounts.</span>
                                        <button type="button" class="data-table-button primary" data-open-user-modal>New User</button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            @include('partials.table-pagination', ['paginator' => $users, 'pageSizes' => $pageSizes, 'currentPageSize' => $perPage, 'recordLabel' => 'users', 'tableId' => 'admin-users'])
        </section>
</div>
@endsection

@push('scripts')
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
                <label for="account-district">Select district</label>
                <select id="account-district" name="district" data-account-district>
                    <option value="">Remove District Assignment</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district }}">{{ $district }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="modal-submit">Update District</button>
        </form>
    </section>
</div>

<div class="modal-backdrop {{ $errors->any() ? 'open' : '' }}" data-user-modal>
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="new-user-title">
        <div class="modal-header">
            <h2 id="new-user-title">New User</h2>
            <button type="button" class="modal-close" aria-label="Close new user form" data-close-user-modal>x</button>
        </div>

        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            @if ($errors->any())
                <div class="field-error" role="alert" tabindex="-1" data-validation-summary>Please correct the user fields below.</div>
            @endif
            <div class="field">
                <label for="user-name">Name</label>
                <input id="user-name" @error('name') aria-invalid="true" aria-describedby="user-name-error" @enderror name="name" type="text" value="{{ old('name') }}" placeholder="e.g. Maria Santos" required>
            </div>
            @error('name')<div id="user-name-error" class="field-error">{{ $message }}</div>@enderror

            <div class="field">
                <label for="user-email">Email</label>
                <input id="user-email" @error('email') aria-invalid="true" aria-describedby="user-email-error" @enderror name="email" type="email" value="{{ old('email') }}" placeholder="name@elevate.org" required>
            </div>
            @error('email')<div id="user-email-error" class="field-error">{{ $message }}</div>@enderror

            <div class="field">
                <label for="user-role">Role</label>
                <select id="user-role" @error('role') aria-invalid="true" aria-describedby="user-role-error" @enderror name="role" required>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(old('role', 'member') === $role)>{{ ucwords(str_replace('_', ' ', $role)) }}</option>
                    @endforeach
                </select>
            </div>
            @error('role')<div id="user-role-error" class="field-error">{{ $message }}</div>@enderror

            <div class="field">
                <label for="user-district">District</label>
                <select id="user-district" @error('district') aria-invalid="true" aria-describedby="user-district-error" @enderror name="district">
                    <option value="">Unassigned</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district }}" @selected(old('district') === $district)>{{ $district }}</option>
                    @endforeach
                </select>
            </div>
            @error('district')<div id="user-district-error" class="field-error">{{ $message }}</div>@enderror

            <button type="submit" class="modal-submit">Create User</button>
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
@endpush
