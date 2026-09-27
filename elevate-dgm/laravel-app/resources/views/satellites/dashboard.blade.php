@extends('layouts.app')

@section('title', $satellite->display_name.' Dashboard | Elevate DGM')

@section('content')
<div class="page-header">
    <div>
        <h1>{{ $satellite->display_name }} Dashboard</h1>
        <p class="page-subtitle">{{ now()->format('F j, Y') }}</p>
    </div>
    <details class="month-dropdown">
        <summary>{{ $selectedDashboardMonth }}</summary>
        <div class="month-menu">
            @foreach ($dashboardMonths as $month)
                <span @class(['month-option', 'active' => $month === $selectedDashboardMonth])>{{ $month }}</span>
            @endforeach
        </div>
    </details>
</div>

<section class="dashboard-intro" aria-labelledby="satellite-summary-title">
    <p class="summary-title" id="satellite-summary-title">{{ strtoupper($satellite->display_name) }} SATELLITE SUMMARY</p>
</section>

<div class="stats-grid compact-dashboard-grid">
    @foreach ($satelliteSummaryCards as $card)
        <article class="stat-card">
            <div class="info">
                <span class="stat-value">{{ number_format($card['total']) }}</span>
                <span class="stat-label">{{ $card['label'] }}</span>
            </div>
        </article>
    @endforeach
</div>

<div class="dashboard-panels">
    <section class="data-card quick-actions" aria-labelledby="quick-actions-title">
        <h2 class="section-title" id="quick-actions-title">Quick Actions</h2>
        <div class="quick-action-grid">
            @if ($canManageSatellite)
                <a class="btn-primary" href="{{ route('satellites.members', $satellite) }}">Add Member</a>
                <a class="btn-secondary" href="{{ route('satellites.seekers', $satellite) }}">Review Seekers</a>
                <a class="btn-primary" href="{{ route('satellites.follow-ups.create', $satellite) }}">Add Follow-Up</a>
                <a class="btn-secondary" href="{{ route('satellites.engagement.index', $satellite) }}">View Engagement</a>
            @else
                <a class="btn-secondary" href="{{ route('satellites.members', $satellite) }}">View Members</a>
                <a class="btn-secondary" href="{{ route('satellites.seekers', $satellite) }}">View Seekers</a>
                <a class="btn-secondary" href="{{ route('satellites.follow-ups.index', $satellite) }}">View Follow-Ups</a>
                <a class="btn-secondary" href="{{ route('satellites.engagement.index', $satellite) }}">View Engagement</a>
            @endif
        </div>
    </section>

    <section class="data-card assigned-accounts" aria-labelledby="assigned-accounts-title">
        <h2 class="section-title" id="assigned-accounts-title">Assigned Accounts</h2>
        <div class="assigned-account-list">
            @forelse ($satelliteUsers as $satelliteUser)
                <div class="assigned-account-row">
                    <div>
                        <strong>{{ $satelliteUser->name }}</strong>
                        <span>{{ $satelliteUser->email }}</span>
                    </div>
                    <span class="role-pill">{{ \Illuminate\Support\Str::headline($satelliteUser->role ?: 'member') }}</span>
                </div>
            @empty
                <div class="empty-state">
                    No user accounts are assigned to this satellite district yet.
                </div>
            @endforelse
        </div>
    </section>

    <section class="data-card" aria-labelledby="engagement-summary-title">
        <h2 class="section-title" id="engagement-summary-title">Engagement Summary</h2>
        <div class="mini-stat-list">
            @foreach ($engagementSummary as $item)
                <div class="mini-stat">
                    <span>{{ $item['label'] }}</span>
                    <strong>{{ number_format($item['value']) }}</strong>
                </div>
            @endforeach
        </div>
    </section>

    <section class="data-card" aria-labelledby="seeker-summary-title">
        <h2 class="section-title" id="seeker-summary-title">Seeker Summary</h2>
        <div class="mini-stat-list">
            @foreach ($seekerSummary as $item)
                <div class="mini-stat">
                    <span>{{ $item['label'] }}</span>
                    <strong>{{ number_format($item['value']) }}</strong>
                </div>
            @endforeach
        </div>
    </section>

    <section class="data-card recent-activity" aria-labelledby="recent-activity-title">
        <h2 class="section-title" id="recent-activity-title">Recent Activity</h2>
        @forelse ($recentActivity as $activity)
            <div class="activity-row">
                <div>
                    <strong>{{ $activity['target'] }}</strong>
                    <span>{{ $activity['method'] }} follow-up</span>
                </div>
                <div>
                    <span class="activity-status">{{ $activity['status'] }}</span>
                    <small>{{ $activity['date'] }}</small>
                </div>
            </div>
        @empty
            <div class="empty-state">
                No recent activity yet. Activity will appear here when members, seekers, or follow-ups are updated.
            </div>
        @endforelse
    </section>
</div>
@endsection

@push('styles')
        .dashboard-intro {
            margin-bottom: 18px;
        }

        .summary-title {
            margin: 0;
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .compact-dashboard-grid .stat-card {
            min-height: 168px;
            padding: 20px 24px 18px;
            background: #FFFFFF;
            border: 1px solid rgba(17, 24, 39, 0.08);
            border-radius: 14px;
            box-shadow: none;
        }

        .compact-dashboard-grid .stat-value {
            color: var(--brand-red);
            font-weight: 900;
        }

        .compact-dashboard-grid .stat-label {
            color: var(--text-primary);
            font-weight: 900;
        }

        .dashboard-panels {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .recent-activity {
            grid-column: 1 / -1;
        }

        .quick-action-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .mini-stat-list {
            display: grid;
            gap: 10px;
        }

        .assigned-account-list {
            display: grid;
            gap: 10px;
        }

        .mini-stat,
        .activity-row,
        .assigned-account-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
            background: #f9fafb;
            padding: 13px 14px;
        }

        .assigned-account-row > div {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .mini-stat span,
        .activity-row span,
        .activity-row small,
        .assigned-account-row span {
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 650;
        }

        .mini-stat strong,
        .activity-row strong,
        .assigned-account-row strong {
            color: var(--text-primary);
            font-weight: 850;
        }

        .assigned-account-row strong,
        .assigned-account-row span:not(.role-pill) {
            overflow-wrap: anywhere;
        }

        .role-pill {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            flex-shrink: 0;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3 !important;
            padding: 0 10px;
            font-weight: 800 !important;
        }

        .mini-stat strong {
            color: var(--brand-red);
            font-size: 22px;
        }

        .activity-row > div {
            display: grid;
            gap: 4px;
        }

        .activity-row > div:last-child {
            justify-items: end;
        }

        .activity-status {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            border-radius: 999px;
            background: var(--brand-red-soft);
            color: var(--brand-red) !important;
            padding: 0 10px;
            font-weight: 800 !important;
        }

        .month-dropdown {
            position: relative;
            flex-shrink: 0;
        }

        .month-dropdown summary {
            position: relative;
            min-width: 136px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 11px 38px 11px 16px;
            border: 1px solid var(--line);
            border-radius: var(--radius-sm);
            background: #fff;
            color: var(--text-primary);
            box-shadow: var(--shadow-card-soft);
            cursor: pointer;
            font-size: 14px;
            font-weight: 800;
            list-style: none;
        }

        .month-dropdown summary::-webkit-details-marker {
            display: none;
        }

        .month-dropdown summary::after {
            content: "";
            position: absolute;
            right: 16px;
            top: 50%;
            width: 8px;
            height: 8px;
            border-right: 2px solid var(--text-primary);
            border-bottom: 2px solid var(--text-primary);
            transform: translateY(-65%) rotate(45deg);
            transition: transform 0.15s ease;
        }

        .month-dropdown[open] summary::after {
            transform: translateY(-30%) rotate(225deg);
        }

        .month-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            z-index: 5;
            width: 184px;
            max-height: 260px;
            overflow-y: auto;
            padding: 8px;
            border: 1px solid var(--line);
            border-radius: var(--radius-sm);
            background: #fff;
            box-shadow: 0 18px 40px rgba(17, 24, 39, 0.16);
        }

        .month-option {
            display: block;
            padding: 10px 12px;
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 750;
        }

        .month-option.active {
            background: var(--brand-red-soft);
            color: var(--brand-red);
        }

        @media (max-width: 900px) {
            .dashboard-panels {
                grid-template-columns: 1fr;
            }

            .quick-action-grid {
                grid-template-columns: 1fr;
            }
        }
@endpush
