@extends('layouts.app')

@section('title', $satellite->display_name.' Dashboard | Elevate DGM')

@section('content')
<div class="satellite-dashboard satellite-overview" data-satellite-dashboard data-refresh-interval="30000" data-live="{{ $isLiveDashboard ? 'true' : 'false' }}" data-year="{{ $dashboardCurrentYear }}" data-month="{{ $dashboardCurrentMonth }}">
    <div class="page-header">
        <div>
            <h1 title="{{ $satellite->display_name }} Dashboard">{{ $satellite->display_name }} Dashboard</h1>
            <p class="page-subtitle" data-dashboard-subtitle @if(!$isLiveDashboard) hidden @endif>
                @if($isLiveDashboard)
                    <span data-dashboard-date>{{ $dashboardDate }}</span> · <span data-dashboard-updated>Updated {{ $dashboardUpdatedAt }}</span>
                @endif
            </p>
        </div>
        <div class="dashboard-header-actions">
            <div data-dashboard-controls>
                @include('components.satellite-period-controls')
            </div>
            <button type="button" class="history-button" data-history-open aria-label="Activity history" title="Activity history" aria-expanded="false" aria-controls="activity-history">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 11a9 9 0 1 1 2.6 7M3 4v7h7M12 7v5l3 2"/></svg>
            </button>
        </div>
    </div>

    <div class="sr-only" aria-live="polite" data-period-announce></div>
    <div class="dashboard-network-error" data-dashboard-error role="alert" hidden>Unable to update. Showing previous data. <button type="button" data-dashboard-retry>Retry</button></div>

    <div data-dashboard-content>
        @include('satellites.dashboard-content')
    </div>
    <dialog class="mv-drawer activity-history-drawer" id="activity-history" aria-labelledby="activity-history-title">
        <header class="activity-history-head"><h2 id="activity-history-title">Activity history</h2><button type="button" class="btn-secondary" data-history-close aria-label="Close activity history">&times;</button></header>
        <div data-history-error role="alert" hidden>Unable to update. Showing previous data. <button type="button" class="btn-secondary" data-dashboard-retry>Retry</button></div>
        <div data-history-body></div>
        <a class="touch-target" href="{{ route('satellites.follow-ups.index', $satellite) }}">View all in Follow-Ups</a>
    </dialog>
</div>
@endsection

@push('scripts')
    <script>{!! file_get_contents(resource_path('js/satellite-dashboard.js')) !!}</script>
@endpush
