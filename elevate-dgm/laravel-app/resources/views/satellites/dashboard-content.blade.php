@php($emptyPeriod = !$isLiveDashboard && !$dashboardHasData)
<section class="dashboard-intro" aria-labelledby="satellite-summary-title">
    <h2 class="summary-title" id="satellite-summary-title">{{ strtoupper($satellite->display_name) }} SATELLITE SUMMARY</h2>
    @if($emptyPeriod)<p class="dashboard-empty-period">No data for {{ $selectedDashboardMonth }} {{ $selectedDashboardYear }}</p>@endif
</section>
<div class="stats-grid compact-dashboard-grid">
    @foreach($satelliteSummaryCards as $index => $card)
        @include('components.stat-card', ['card' => $card, 'index' => $index, 'emptyPeriod' => $emptyPeriod])
    @endforeach
</div>
@if($emptyPeriod)
    <div class="dashboard-panels">
        @foreach(['engagement-summary-title' => 'Engagement summary', 'seeker-summary-title' => 'Seeker summary'] as $id => $title)
            <section class="summary-panel" aria-labelledby="{{ $id }}"><h3 id="{{ $id }}">{{ $title }}</h3><p class="dashboard-empty-period">No data for {{ $selectedDashboardMonth }} {{ $selectedDashboardYear }}</p></section>
        @endforeach
    </div>
@else
    <div class="dashboard-panels">
        @include('components.summary-panel', ['id' => 'engagement-summary-title', 'title' => 'Engagement summary', 'items' => $engagementSummary])
        @include('components.summary-panel', ['id' => 'seeker-summary-title', 'title' => 'Seeker summary', 'items' => $seekerSummary])
    </div>
@endif
<template data-activity-history>@include('satellites.activity-history')</template>
