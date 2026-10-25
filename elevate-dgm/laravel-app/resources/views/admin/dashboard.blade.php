@extends('layouts.app')

@section('title', 'Admin Dashboard | Elevate DGM')

@section('content')
<div class="admin-dashboard-page satellite-dashboard satellite-overview" data-satellite-dashboard data-refresh-interval="30000" data-live="{{ $isLiveDashboard ? 'true' : 'false' }}" data-year="{{ $dashboardCurrentYear }}" data-month="{{ $dashboardCurrentMonth }}">
    <div class="page-header admin-dashboard-header">
        <div>
            <h1>Dashboard</h1>
            <p class="page-subtitle" data-dashboard-subtitle @if(!$isLiveDashboard) hidden @endif>
                @if($isLiveDashboard)
                    <span data-dashboard-date>{{ now()->format('F j, Y') }}</span> · <span data-dashboard-updated>Updated {{ $dashboardUpdatedAt }}</span>
                @else
                    Viewing {{ $selectedDashboardMonth }} {{ $selectedDashboardYear }} · <a href="{{ route('admin.dashboard', ['month' => 'Live', 'year' => $dashboardCurrentYear]) }}" data-back-to-live>Back to Live</a>
                @endif
            </p>
        </div>
        <div class="dashboard-header-actions">
            <div data-dashboard-controls>
                @include('components.satellite-period-controls', ['periodFormAction' => route('admin.dashboard')])
            </div>
            <button type="button" class="history-button" data-history-open aria-label="Activity history" title="Activity history" aria-expanded="false" aria-controls="activity-history">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 11a9 9 0 1 1 2.6 7M3 4v7h7M12 7v5l3 2"/></svg>
            </button>
        </div>
    </div>

    <div class="sr-only" aria-live="polite" data-period-announce></div>
    <div class="dashboard-network-error" data-dashboard-error role="alert" hidden>Unable to update. Showing previous data. <button type="button" data-dashboard-retry>Retry</button></div>

    <div data-dashboard-content>
        @include('admin.dashboard-content')
    </div>

    <dialog class="mv-drawer activity-history-drawer" id="activity-history" aria-labelledby="activity-history-title">
        <header class="activity-history-head"><h2 id="activity-history-title">Activity history</h2><button type="button" class="btn-secondary" data-history-close aria-label="Close activity history">&times;</button></header>
        <div data-history-error role="alert" hidden>Unable to update. Showing previous data. <button type="button" class="btn-secondary" data-dashboard-retry>Retry</button></div>
        <div data-history-body></div>
        <a class="touch-target" href="{{ route('admin.satellites') }}">View all satellites</a>
    </dialog>
</div>
@endsection

@push('styles')
        .admin-summary-card a { text-decoration:none; cursor:pointer; border-radius:4px; }
        .admin-summary-card a:hover { text-decoration:underline; text-underline-offset:5px; }

        .admin-dashboard-page {
            min-height: 0;
        }

        .admin-dashboard-header {
            margin-bottom: 14px;
        }

        .dashboard-intro {
            margin-bottom: 22px;
        }

        .summary-title {
            margin: 0;
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .stats-grid.admin-summary-grid {
            grid-template-columns: repeat(2, minmax(280px, 1fr)) !important;
            gap: 24px;
            margin-bottom: 0;
            max-width: 100%;
            width: 100%;
            align-items: stretch;
        }

        .admin-summary-card {
            display: flex;
            align-items: stretch;
            flex-direction: column;
            justify-content: flex-start;
            min-height: 220px;
        }

        .admin-summary-card .info {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin: 0;
        }

        .admin-summary-card .stat-value {
            line-height: 1;
            font-weight: 900;
        }

        .admin-summary-card .stat-label {
            font-weight: 900;
        }

        .summary-breakdown {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-top: auto;
            padding-top: 18px;
        }

        .summary-segment {
            display: grid;
            gap: 6px;
            min-width: 0;
            align-content: start;
        }

        .summary-segment strong {
            font-size: clamp(1.35rem, 2vw, 2.1rem);
            line-height: 1;
            font-weight: 900;
            letter-spacing: 0;
            overflow-wrap: anywhere;
        }

        .summary-segment span {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        @media (max-width: 1279px) {
            .stats-grid.admin-summary-grid {
                gap: 20px;
            }

            .admin-summary-card {
                min-height: 220px;
            }

            .summary-segment strong {
                font-size: 26px;
            }
        }

        @media (max-width: 900px) {
            .stats-grid.admin-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 620px) {
            .stats-grid.admin-summary-grid {
                grid-template-columns: 1fr !important;
            }

            .admin-summary-card {
                min-height: 0;
            }

            .summary-breakdown {
                gap: 12px;
                padding-top: 18px;
            }

            .summary-segment strong {
                font-size: 24px;
            }

            .summary-segment span {
                font-size: 12px;
            }
        }

        @media (max-width: 599px) {
            .stats-grid.admin-summary-grid {
                grid-template-columns: 1fr !important;
            }
        }

        @media (max-height: 760px) and (min-width: 768px) {
            .admin-dashboard-header {
                margin-bottom: 10px;
            }

            .dashboard-intro {
                margin-bottom: 16px;
            }

            .admin-summary-card {
                min-height: 206px;
            }

            .summary-breakdown {
                gap: 12px;
                padding-top: 16px;
            }

            .summary-segment strong {
                font-size: 24px;
            }

            .summary-segment span {
                font-size: 12px;
            }
        }

        @media (max-height: 680px) and (min-width: 768px) {
            .admin-dashboard-header .page-subtitle {
                margin-top: 4px;
            }

            .dashboard-intro {
                margin-bottom: 12px;
            }

            .admin-summary-card {
                min-height: 180px;
            }

            .summary-breakdown {
                gap: 10px;
                padding-top: 14px;
            }

            .summary-segment strong {
                font-size: 22px;
            }

            .summary-segment span {
                font-size: 11px;
            }
        }
@endpush

@push('scripts')
    <script>{!! file_get_contents(resource_path('js/satellite-dashboard.js')) !!}</script>
@endpush
