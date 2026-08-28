<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $satellite->display_name }} Seekers</title>
    <style>
        :root { --brand-red: #e33c24; --brand-red-dark: #c92f1b; --brand-red-soft: #fff1ee; --ink: #111; --muted: #667085; --line: #e8edf3; --surface: #fff; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f3f4f6; color: var(--ink); }
        .page { display: flex; min-height: 100vh; background: #f3f4f6; }
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
        .main { flex: 1; width: 100%; max-width: none; margin: 0; padding: 30px 32px 48px; }
        .seekers-toolbar { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; margin-bottom: 22px; }
        .title-group { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
        .title-group h1 { margin: 0; color: var(--ink); font-size: 32px; line-height: 1.1; font-weight: 900; }
        .page-kicker { color: var(--muted); font-size: 13px; font-weight: 800; }
        .toolbar-actions { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
        .sync-button, .notification-button { border: 1px solid #d8dde6; background: var(--surface); color: var(--ink); box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04); cursor: pointer; transition: border-color 0.15s ease, color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease; }
        .sync-button { display: inline-flex; align-items: center; gap: 9px; min-height: 38px; padding: 0 17px; border-radius: 999px; font-size: 13px; font-weight: 800; white-space: nowrap; }
        .notification-button { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 999px; }
        .sync-button:hover, .notification-button:hover { border-color: var(--brand-red); color: var(--brand-red); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(227, 60, 36, 0.16); }
        .button-icon { display: inline-flex; width: 14px; height: 14px; }
        .bell-icon { width: 18px; height: 18px; }
        .notification-badge { position: absolute; top: -7px; right: -5px; display: inline-flex; align-items: center; justify-content: center; min-width: 21px; height: 21px; padding: 0 6px; border-radius: 999px; background: var(--brand-red); color: #fff; border: 2px solid #f3f4f6; font-size: 11px; line-height: 1; font-weight: 900; }
        .seekers-card { width: 100%; overflow: hidden; background: var(--surface); border: 1px solid var(--line); border-radius: 16px; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08); }
        .seekers-table-wrap { width: 100%; overflow-x: auto; }
        .seekers-table { width: 100%; min-width: 900px; border-collapse: collapse; color: #374151; font-size: 15px; font-weight: 600; }
        .seekers-table th, .seekers-table td { padding: 18px 24px; text-align: left; border-bottom: 1px solid var(--line); vertical-align: middle; }
        .seekers-table th { color: #6b7280; background: var(--brand-red-soft); font-size: 14px; font-weight: 900; }
        .seekers-table tbody tr:last-child td { border-bottom: none; }
        .seekers-table tbody tr:hover { background: #fff9f7; }
        .seeker-name { color: var(--ink); font-weight: 900; }
        .leader-button { min-height: 40px; border: 1px solid #f0b4a8; border-radius: 8px; padding: 0 12px; background: #fff; color: var(--brand-red); font-size: 13px; font-weight: 900; cursor: pointer; }
        .leader-button:hover, .leader-button:focus-visible { background: var(--brand-red-soft); border-color: var(--brand-red); outline: none; }
        .leader-assigned { display: inline-flex; align-items: center; gap: 7px; min-height: 40px; border: 0; padding: 0; background: transparent; color: var(--ink); font-weight: 800; cursor: pointer; text-align: left; }
        .leader-assigned:hover, .leader-assigned:focus-visible { color: var(--brand-red); outline: none; }
        .leader-edit-icon { width: 14px; height: 14px; }
        .seeker-modal-backdrop { position: fixed; inset: 0; z-index: 30; display: none; align-items: center; justify-content: center; padding: 24px 16px; background: rgba(0, 0, 0, 0.48); }
        .seeker-modal-backdrop.open { display: flex; }
        .seeker-modal { position: relative; width: min(100%, 540px); max-height: calc(100vh - 48px); overflow-y: auto; border-radius: 14px; background: #fff; padding: 28px; box-shadow: 0 24px 70px rgba(15, 23, 42, 0.24); }
        .seeker-modal h2 { margin: 0; color: var(--ink); font-size: 22px; font-weight: 900; }
        .seeker-modal-copy { margin: 7px 0 20px; color: var(--muted); font-size: 14px; line-height: 1.5; }
        .seeker-modal-close { position: absolute; top: 18px; right: 20px; width: 34px; height: 34px; border: 0; border-radius: 7px; background: transparent; color: #98a2b3; font-size: 25px; line-height: 1; cursor: pointer; }
        .seeker-modal-close:hover, .seeker-modal-close:focus-visible { background: var(--brand-red-soft); color: var(--brand-red); outline: none; }
        .seeker-modal label { display: grid; gap: 6px; margin-bottom: 14px; color: #475467; font-size: 12px; font-weight: 900; }
        .seeker-modal input, .seeker-modal select { width: 100%; min-height: 42px; border: 1px solid #d8dde6; border-radius: 8px; padding: 0 12px; color: var(--ink); background: #fff; outline: none; }
        .seeker-modal input:focus, .seeker-modal select:focus { border-color: var(--brand-red); box-shadow: 0 0 0 3px rgba(227, 60, 36, 0.12); }
        .seeker-modal-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 14px; }
        .seeker-modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .seeker-modal-actions button { min-height: 42px; border-radius: 999px; padding: 0 17px; font-weight: 900; cursor: pointer; }
        .seeker-modal-cancel { border: 1px solid #d8dde6; background: #fff; color: var(--ink); }
        .seeker-modal-submit { border: 0; background: var(--brand-red); color: #fff; }
        .seeker-modal-submit:hover, .seeker-modal-submit:focus-visible { background: var(--brand-red-dark); outline: none; }
        .seeker-modal-submit:disabled { opacity: 0.6; cursor: wait; }
        .seeker-modal-error { display: none; margin-bottom: 14px; border: 1px solid #fecaca; border-radius: 8px; background: #fef2f2; color: #991b1b; padding: 10px 12px; font-size: 13px; font-weight: 800; }
        .seeker-modal-error.open { display: block; }
        .seeker-modal-seeker { margin: 0 0 18px; color: var(--ink); font-size: 14px; font-weight: 800; }
        @media (max-width: 520px) { .seeker-modal { padding: 24px 18px; } .seeker-modal-grid { grid-template-columns: 1fr; } .seeker-modal-actions { flex-direction: column-reverse; } .seeker-modal-actions button { width: 100%; } }
        .status-cell { width: 168px; text-align: right; }
        .status-control { position: relative; display: inline-flex; align-items: center; color: #344054; }
        .status-control::after { content: ""; position: absolute; right: 14px; top: 50%; width: 7px; height: 7px; border-right: 2px solid currentColor; border-bottom: 2px solid currentColor; transform: translateY(-64%) rotate(45deg); pointer-events: none; }
        .status-select { width: 126px; height: 34px; appearance: none; border-radius: 999px; padding: 0 34px 0 16px; border: 1px solid currentColor; background: #fff; color: inherit; font-family: inherit; font-size: 12px; font-weight: 900; cursor: pointer; outline: none; }
        .status-select:focus { box-shadow: 0 0 0 3px rgba(52, 64, 84, 0.10); }
        .status-form { margin: 0; display: inline-flex; }
        .status-successful { color: #159947; }
        .status-successful .status-select, .status-select.status-successful { background: #dcfce7; border-color: #86efac; }
        .status-pending { color: #c47700; }
        .status-pending .status-select, .status-select.status-pending { background: #fff4ce; border-color: #f8c94a; }
        .status-unsuccessful { color: var(--brand-red); }
        .status-unsuccessful .status-select, .status-select.status-unsuccessful { background: #ffe5e1; border-color: #ffb4a9; }
        .notice { margin: 0 0 18px; border: 1px solid rgba(21, 153, 71, 0.24); border-radius: 10px; background: #ecfdf3; color: #067647; padding: 12px 16px; font-size: 14px; font-weight: 800; }
        .empty-state { color: var(--muted); font-weight: 800; text-align: center; }
        .leader-modal-backdrop { position: fixed; inset: 0; z-index: 30; display: none; align-items: center; justify-content: center; padding: 20px; background: rgba(0, 0, 0, 0.48); }
        .leader-modal-backdrop.open { display: flex; }
        .leader-modal { position: relative; width: min(100%, 520px); max-height: calc(100vh - 40px); overflow-y: auto; padding: 28px; border-radius: 14px; background: #fff; box-shadow: 0 24px 70px rgba(15, 23, 42, 0.24); }
        .leader-modal h2 { margin: 0; color: var(--ink); font-size: 22px; font-weight: 900; }
        .leader-modal p { margin: 7px 0 20px; color: var(--muted); font-size: 14px; line-height: 1.5; }
        .leader-modal-close { position: absolute; top: 18px; right: 18px; width: 34px; height: 34px; border: 0; border-radius: 7px; background: transparent; color: #98a2b3; font-size: 25px; cursor: pointer; }
        .leader-modal-close:hover, .leader-modal-close:focus-visible { background: var(--brand-red-soft); color: var(--brand-red); outline: none; }
        .leader-modal label { display: grid; gap: 6px; color: #475467; font-size: 12px; font-weight: 900; }
        .leader-modal select, .leader-modal input { width: 100%; min-height: 42px; border: 1px solid #d8dde6; border-radius: 8px; padding: 0 12px; background: #fff; color: var(--ink); outline: none; }
        .leader-modal select:focus, .leader-modal input:focus { border-color: var(--brand-red); box-shadow: 0 0 0 3px rgba(227, 60, 36, 0.12); }
        .leader-modal-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .leader-modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px; }
        .leader-modal-actions button { min-height: 42px; border-radius: 999px; padding: 0 17px; font-weight: 900; cursor: pointer; }
        .leader-cancel { border: 1px solid #d8dde6; background: #fff; color: var(--ink); }
        .leader-submit { border: 0; background: var(--brand-red); color: #fff; }
        .leader-submit:hover, .leader-submit:focus-visible { background: var(--brand-red-dark); outline: none; }
        .leader-submit:disabled { opacity: 0.6; cursor: wait; }
        .leader-error { display: none; margin-bottom: 14px; border: 1px solid #fecaca; border-radius: 8px; background: #fef2f2; color: #991b1b; padding: 10px; font-size: 13px; font-weight: 800; }
        .leader-error.open { display: block; }
        @include('partials.table-pagination-styles')
        @media (max-width: 900px) {
            .page { flex-direction: column; }
            .sidebar { width: 100%; min-height: auto; }
            .sidebar .brand { height: 90px; }
            .menu { flex-direction: row; flex-wrap: wrap; }
            .menu a { padding: 12px 14px; font-size: 14px; }
            .signout { justify-content: flex-start; }
            .main { padding: 22px 18px 36px; }
            .seekers-toolbar { align-items: stretch; flex-direction: column; }
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
            <a href="{{ route('satellites.dashboard', $satellite) }}">Dashboard</a>
            <a href="{{ route('satellites.members', $satellite) }}">Members</a>
            <a href="{{ route('satellites.seekers', $satellite) }}" class="active">Seekers</a>
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
        <div class="seekers-toolbar">
            <div class="title-group">
                <h1>Seekers</h1>
                <span class="page-kicker">{{ $satellite->display_name }} Satellite</span>
            </div>

            <div class="toolbar-actions">
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

        <section class="seekers-card">
            <div class="seekers-table-wrap">
                <table class="seekers-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>DLeader</th>
                            <th class="status-cell">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($seekers as $seeker)
                            @php
                                $seekerPayload = json_encode([
                                    'last_name' => $seeker->last_name,
                                    'first_name' => $seeker->first_name,
                                    'gender' => $seeker->gender,
                                    'email_address' => $seeker->email,
                                    'contact_number' => $seeker->contact_number,
                                    'facebook_link' => $seeker->facebook_link,
                                    'birthdate' => $seeker->birthdate,
                                    'age' => $seeker->age,
                                    'district' => $seeker->assigned_district ?: $satellite->display_name,
                                    'area' => $seeker->area,
                                    'campus_school' => $seeker->school,
                                    'dgroup_leader_name' => $seeker->dgroup_leader_name,
                                    'active_disciples' => $seeker->active_disciples,
                                    'year_joined' => $seeker->year_joined,
                                    'ministry_involvement' => $seeker->ministry_involvement,
                                    'weekly_dgroups_led' => $seeker->weekly_dgroups_led,
                                    'generation' => $seeker->generation,
                                    'refresh_date' => $seeker->refresh_date,
                                    'year' => $seeker->year,
                                    'month' => $seeker->month,
                                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
                            @endphp
                            <tr>
                                <td class="seeker-name">{{ $seeker->name }}</td>
                                <td>{{ $seeker->email }}</td>
                                <td>
                                    <button type="button" class="{{ $seeker->dgroup_leader_name ? 'leader-assigned' : 'leader-button' }}" data-open-leader-modal data-seeker-name="{{ $seeker->name }}" data-leader="{{ $seeker->dgroup_leader_name }}" data-action="{{ route('satellites.seekers.leader', [$satellite, $seeker]) }}" aria-label="{{ $seeker->dgroup_leader_name ? 'Change DGroup Leader for '.$seeker->name : 'Assign DGroup Leader for '.$seeker->name }}">
                                        @if ($seeker->dgroup_leader_name)
                                            {{ $seeker->dgroup_leader_name }}
                                            <svg class="leader-edit-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 0 3 3L7 19l-4 1 1-4Z" /></svg>
                                        @else
                                            + Assign DLeader
                                        @endif
                                    </button>
                                </td>
                                <td class="status-cell">
                                    <form method="POST" action="{{ route('satellites.seekers.status', [$satellite, $seeker]) }}" class="status-form">
                                        @csrf
                                        @method('PATCH')
                                        <span class="status-control status-{{ $seeker->status_class }}" data-status-control>
                                            <select class="status-select status-{{ $seeker->status_class }}" name="status" data-status-select data-seeker-name="{{ $seeker->name }}" data-seeker-leader="{{ $seeker->dgroup_leader_name }}" data-convert-url="{{ route('satellites.seekers.convert', [$satellite, $seeker]) }}" data-current-status="{{ $seeker->status }}" data-seeker-payload="{{ $seekerPayload }}" aria-label="Status for {{ $seeker->name }}">
                                                @foreach ($statuses as $status)
                                                    <option value="{{ $status }}" @selected($seeker->status === $status)>{{ $status }}</option>
                                                @endforeach
                                            </select>
                                        </span>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-state">{{ $satellite->display_name }} has no seekers yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('partials.table-pagination', ['paginator' => $seekers, 'pageSizes' => [10], 'currentPageSize' => 10, 'recordLabel' => 'seekers', 'tableId' => 'satellite-seekers'])
        </section>
    </main>
</div>
<div class="leader-modal-backdrop" data-leader-modal aria-hidden="true">
    <section class="leader-modal" role="dialog" aria-modal="true" aria-labelledby="leader-modal-title">
        <button type="button" class="leader-modal-close" data-modal-close aria-label="Close dialog">&times;</button>
        <h2 id="leader-modal-title">Assign DGroup Leader</h2>
        <p>Choose the DGroup Leader who will handle this seeker.</p>
        <p class="seeker-modal-name">Seeker: <strong data-modal-seeker></strong></p>
        <div class="leader-error" data-leader-error></div>
        <form method="POST" data-leader-form>
            @csrf
            @method('PATCH')
            <label>DGroup Leader
                <select name="dgroup_leader_name" required data-leader-select>
                    <option value="">Search or select a DGroup Leader</option>
                    @foreach ($leaderOptions as $leader)
                        <option value="{{ $leader }}">{{ $leader }}</option>
                    @endforeach
                </select>
            </label>
            <div class="leader-modal-actions">
                <button type="button" class="leader-cancel" data-modal-close>Cancel</button>
                <button type="submit" class="leader-submit">Save Assignment</button>
            </div>
        </form>
    </section>
</div>
<div class="leader-modal-backdrop" data-convert-modal aria-hidden="true">
    <section class="leader-modal" role="dialog" aria-modal="true" aria-labelledby="convert-modal-title">
        <button type="button" class="leader-modal-close" data-modal-close aria-label="Close dialog">&times;</button>
        <h2 id="convert-modal-title">Complete Member Information</h2>
        <p>This seeker is ready to become a member. Complete the required information before adding them to Members.</p>
        <p class="seeker-modal-name">Seeker: <strong data-convert-seeker></strong></p>
        <div class="leader-error" data-convert-error></div>
        <form method="POST" data-convert-form>
            @csrf
            <div class="leader-modal-grid">
                <label>Status<input value="New" readonly></label>
                <label>Dgroup Role<input value="Member" readonly></label>
                <label>Last Name *<input name="last_name" required data-convert-last></label>
                <label>First Name *<input name="first_name" required data-convert-first></label>
                <label>Full Name<input name="full_name" readonly data-convert-full></label>
                <label>Gender<select name="gender"><option value="">Select gender</option><option>Male</option><option>Female</option></select></label>
                <label>Birthdate<input type="date" name="birthdate" data-birthdate></label>
                <label>Age<input name="age" type="number" readonly data-convert-age></label>
                <label>Email Address *<input type="email" name="email_address" required data-convert-email></label>
                <label>Contact Number<input name="contact_number"></label>
                <label>Facebook Link<input type="url" name="facebook_link"></label>
                <label>District *<input name="district" required></label>
                <label>Area<input name="area"></label>
                <label>Campus/School *<input name="campus_school" required></label>
                <label>Name of DLeader<input name="dgroup_leader_name" readonly data-convert-leader></label>
                <label>No. of Active Disciples<input name="active_disciples" type="number" min="0"></label>
                <label>Year Joined<input name="year_joined" type="number"></label>
                <label>Ministry Involvement<input name="ministry_involvement"></label>
                <label>How many Dgroups do you lead each week?<input name="weekly_dgroups_led" type="number" min="0"></label>
                <label>Generation<input name="generation"></label>
                <label>Refresh Date<input name="refresh_date" type="date"></label>
                <label>Year<input name="year" type="number"></label>
                <label>Month<input name="month"></label>
            </div>
            <div class="leader-modal-actions">
                <button type="button" class="leader-cancel" data-modal-close>Cancel</button>
                <button type="submit" class="leader-submit">Complete &amp; Add to Members</button>
            </div>
        </form>
    </section>
</div>
<script>
    const closeModal = (modal) => {
        modal?.classList.remove('open');
        modal?.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };
    const openModal = (modal) => { modal?.classList.add('open'); modal?.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden'; };
    const leaderModal = document.querySelector('[data-leader-modal]');
    const leaderForm = document.querySelector('[data-leader-form]');
    document.querySelectorAll('[data-open-leader-modal]').forEach((button) => button.addEventListener('click', () => {
        document.querySelector('[data-modal-seeker]').textContent = button.dataset.seekerName;
        leaderForm.action = button.dataset.action;
        document.querySelector('[data-leader-select]').value = button.dataset.leader || '';
        openModal(leaderModal);
    }));
    document.querySelectorAll('[data-modal-close]').forEach((button) => button.addEventListener('click', () => closeModal(button.closest('[data-leader-modal], [data-convert-modal]'))));
    document.querySelectorAll('[data-leader-modal], [data-convert-modal]').forEach((modal) => modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(modal); }));
    leaderForm?.addEventListener('submit', (event) => { event.submitter.disabled = true; event.submitter.textContent = 'Saving assignment...'; });
    const convertModal = document.querySelector('[data-convert-modal]');
    const convertForm = document.querySelector('[data-convert-form]');
    const convertField = (name) => convertForm?.elements.namedItem(name);
    const syncFullName = () => {
        const first = convertField('first_name')?.value.trim() || '';
        const last = convertField('last_name')?.value.trim() || '';
        if (convertField('full_name')) convertField('full_name').value = `${first} ${last}`.trim();
    };
    const syncAge = () => {
        const birthdate = convertField('birthdate')?.value;
        if (!birthdate || !convertField('age')) return;
        const today = new Date();
        const birth = new Date(`${birthdate}T00:00:00`);
        let age = today.getFullYear() - birth.getFullYear();
        if (today < new Date(today.getFullYear(), birth.getMonth(), birth.getDate())) age--;
        convertField('age').value = Math.max(0, age);
    };
    const populateConversionForm = (payload) => {
        Object.entries(payload).forEach(([name, value]) => {
            const field = convertField(name);
            if (field && value !== null && value !== undefined) field.value = value;
        });
        syncFullName();
        syncAge();
    };
    document.querySelectorAll('[data-status-select]').forEach((select) => {
        const statusClasses = ['status-successful', 'status-pending', 'status-unsuccessful'];
        const control = select.closest('[data-status-control]');
        const previous = select.value;
        const classForStatus = (status) => `status-${status.toLowerCase().replace(/\s+/g, '-')}`;
        const updateStatusClass = () => { select.classList.remove(...statusClasses); select.classList.add(classForStatus(select.value)); control?.classList.remove(...statusClasses); control?.classList.add(classForStatus(select.value)); };
        select.addEventListener('change', () => {
            if (select.value === 'Successful' && select.dataset.currentStatus !== 'Successful') {
                if (!select.dataset.seekerLeader) {
                    select.value = previous;
                    updateStatusClass();
                    const leaderButton = document.querySelector(`[data-open-leader-modal][data-seeker-name="${CSS.escape(select.dataset.seekerName)}"]`);
                    leaderButton?.click();
                    return;
                }
                document.querySelector('[data-convert-seeker]').textContent = select.dataset.seekerName;
                populateConversionForm(JSON.parse(select.dataset.seekerPayload || '{}'));
                convertForm.action = select.dataset.convertUrl;
                openModal(convertModal);
                select.value = previous; updateStatusClass(); return;
            }
            select.form?.submit();
        });
        updateStatusClass();
    });
    convertField('first_name')?.addEventListener('input', syncFullName);
    convertField('last_name')?.addEventListener('input', syncFullName);
    convertField('birthdate')?.addEventListener('change', syncAge);
    convertForm?.addEventListener('submit', (event) => { event.submitter.disabled = true; event.submitter.textContent = 'Saving...'; });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') document.querySelectorAll('[data-leader-modal].open, [data-convert-modal].open').forEach(closeModal); });

</script>
</body>
</html>
