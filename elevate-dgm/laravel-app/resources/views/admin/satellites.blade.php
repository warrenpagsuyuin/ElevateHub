@extends('layouts.app')

@section('title', 'Satellites Dashboard | Elevate DGM')

@push('styles')
.header { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 22px; }
        .header h1 { margin: 0; font-size: 32px; font-weight: 900; }
        .new-button { display: inline-flex; align-items: center; gap: 10px; border: none; border-radius: 999px; cursor: pointer; font-size: 15px; font-weight: 900; padding: 14px 24px; }
        .new-button span { font-size: 22px; line-height: 0; }
        .notice { margin-bottom: 18px; border: 1px solid rgba(227, 60, 36, 0.18); border-radius: 10px; background: #fff; color: #111; padding: 12px 16px; font-weight: 700; }
        .notice.error { color: #b42318; }
        .stats { display: grid; grid-template-columns: repeat(4, minmax(220px, 1fr)); gap: 18px; margin-bottom: 24px; }
        .card .info { display: flex; flex-direction: column; gap: 8px; width: 100%; }
        .card .label { font-size: 14px; font-weight: 800; }
        .card .value { font-size: 36px; font-weight: 900; line-height: 1.2; }
        .satellites-stat-card { position: relative; text-decoration: none !important; cursor: pointer; }
        .satellites-stat-card:hover { border-color: var(--stat-card-border-hover, #3A3A3A) !important; border-top-color: var(--stat-card-accent, #450d07) !important; }
        .satellites-stat-card .stat-card-value-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; }
        .satellites-stat-card .card-chevron { flex-shrink: 0; color: var(--stat-card-chevron, #B8B8B8); font-size: 20px; line-height: 1; transition: color .15s ease; }
        .satellites-stat-card:hover .card-chevron { color: var(--stat-card-chevron-hover, #F2F2F2); }
@include('partials.admin-summary-table-styles')
        .satellite-name { color: inherit; font: inherit; font-weight: 800; text-decoration: none; }
        a.satellite-name:hover, a.satellite-name:focus-visible { color: #ef3b24; text-decoration: underline; outline: none; }
        .satellite-count-link { color: inherit; font-weight: 800; text-decoration: none; }
        .satellite-count-link:hover, .satellite-count-link:focus-visible { color: #ef3b24; text-decoration: underline; outline: none; }
        .hub-badge { display: inline-flex; align-items: center; min-height: 24px; padding: 0 10px; border-radius: 999px; background: #f3f4f6; color: #344054; font-size: 11.5px; font-weight: 850; letter-spacing: 0.02em; }
        .hub-badge.hub-main { background: #fff1ee; color: #c92f1b; }
        .hub-badge.hub-ncp { background: #eff8ff; color: #175cd3; }
        .hub-badge.hub-icp { background: #ecfdf3; color: #067647; }
        .unassigned-pill { display: inline-flex; align-items: center; min-height: 20px; margin-left: 6px; padding: 0 7px; border-radius: 999px; background: #fef3c7; color: #92400e; font-size: 11px; font-weight: 800; }
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
        .modal-submit { width: 100%; min-height: 45px; border: none; border-radius: 999px; cursor: pointer; font: inherit; font-weight: 900; }
        @media (max-width: 1200px) { .stats { grid-template-columns: repeat(2, minmax(220px, 1fr)); } }
@endpush

@section('content')
<div class="satellite-dashboard admin-satellites-period" data-satellite-dashboard data-live="{{ $isLiveDashboard ? 'true' : 'false' }}" data-year="{{ $dashboardCurrentYear }}" data-month="{{ $dashboardCurrentMonth }}">
    <div class="header">
        <div>
            <h1>Satellites Dashboard</h1>
            <p class="page-subtitle" data-dashboard-subtitle>
                @if($isLiveDashboard)
                    <span data-dashboard-date>{{ $dashboardDate }}</span> · <span data-dashboard-updated>Updated {{ $dashboardUpdatedAt }}</span>
                @else
                    Viewing {{ $selectedDashboardMonth }} {{ $selectedDashboardYear }} · <a href="{{ route('admin.satellites', ['month' => 'Live', 'year' => $dashboardCurrentYear]) }}" data-back-to-live>Back to Live</a>
                @endif
            </p>
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
            <div data-dashboard-controls>
                @include('components.satellite-period-controls', ['periodFormAction' => route('admin.satellites')])
            </div>
            <button type="button" class="new-button" data-open-satellite-modal><span>+</span> New satellite</button>
        </div>
    </div>

    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="notice error" role="alert">{{ session('error') }}</div>
    @endif

    <div class="sr-only" aria-live="polite" data-period-announce></div>
    <div class="dashboard-network-error" data-dashboard-error role="alert" hidden>Unable to update. Showing previous data. <button type="button" data-dashboard-retry>Retry</button></div>

    <div data-dashboard-content>
        @include('admin.satellites-content')
    </div>
</div>
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
<script>{!! file_get_contents(resource_path('js/satellite-dashboard.js')) !!}</script>
@include('partials.data-table-scripts')
@endpush
