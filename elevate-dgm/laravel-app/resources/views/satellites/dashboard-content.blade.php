<section class="dashboard-intro" aria-labelledby="satellite-summary-title">
    <h2 class="summary-title" id="satellite-summary-title">{{ strtoupper($satellite->display_name) }} SATELLITE SUMMARY</h2>
    <p class="page-subtitle">{{ $isLiveDashboard ? 'Overall registrations and their current statuses.' : $selectedDashboardMonth.' '.$selectedDashboardYear.' registrations and their current statuses, not a historical status snapshot. Card links open the current directories.' }}</p>
</section>
@php($emptyPeriod = !$isLiveDashboard && !$dashboardHasData)
@if($emptyPeriod)<p class="dashboard-empty-period">No data for {{ $selectedDashboardMonth }} {{ $selectedDashboardYear }}</p>@endif
<div class="stats-grid compact-dashboard-grid">
    @foreach($satelliteSummaryCards as $index => $card)
        @include('components.stat-card', ['card' => $card, 'index' => $index, 'emptyPeriod' => $emptyPeriod])
    @endforeach
</div>
@if($emptyPeriod)
    <div class="dashboard-panels">@foreach(['Engagement summary', 'Seeker summary'] as $title)<section class="summary-panel"><h2>{{ $title }}</h2><p class="dashboard-empty-period">No data for {{ $selectedDashboardMonth }} {{ $selectedDashboardYear }}</p></section>@endforeach</div>
@else
    <div class="dashboard-panels">
        @include('components.summary-panel', ['id' => 'engagement-summary-title', 'title' => 'Engagement summary', 'items' => $engagementSummary])
        @include('components.summary-panel', ['id' => 'seeker-summary-title', 'title' => 'Seeker summary', 'items' => $seekerSummary])
    </div>
@endif
<section class="data-card recent-activity" aria-labelledby="recent-activity-title">
    <h2 class="section-title" id="recent-activity-title">Recent Activity</h2>
    <div class="activity-list" role="list">
        @forelse($recentActivity as $activity)
            <div class="activity-row" role="listitem">
                <strong>{{ $activity['target'] }}</strong>
                <span class="activity-type">{{ $activity['method'] }} follow-up</span>
                <span class="activity-status" data-status="{{ $activity['status'] }}" aria-label="{{ $activity['statusLabel'] }}: {{ $activity['status'] }}"><span aria-hidden="true">{{ match ($activity['status']) { 'Successful', 'Completed' => '✓', 'Pending' => '…', 'Unsuccessful', 'Cancelled' => '×', default => '•' } }}</span>{{ $activity['status'] }}</span>
                <small class="activity-date">{{ $activity['date'] }}</small>
            </div>
        @empty<p class="empty-state">{{ $isLiveDashboard ? 'No follow-ups yet.' : 'No follow-ups for '.$selectedDashboardMonth.' '.$selectedDashboardYear.'.' }}</p>@endforelse
    </div>
    <div class="activity-footer"><a class="touch-target" href="{{ route('satellites.follow-ups.index', $satellite) }}">View all</a></div>
</section>
