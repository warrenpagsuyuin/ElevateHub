@extends('layouts.app')
@section('title', 'Analytics Dashboard | Elevate DGM')
@push('styles')
@include('admin.movement-analytics.styles')
@endpush
@section('content')
<div class="movement" id="movement-app" data-endpoint="{{ route('admin.analytics.movement-data') }}" data-resolution-url="{{ route('admin.analytics.resolve') }}" data-csrf="{{ csrf_token() }}" data-default-checkpoint="{{ $defaultCheckpoint }}" data-members-url="{{ route('admin.members') }}" data-satellites-url="{{ route('admin.satellites') }}" data-active-satellites="{{ $activeSatelliteCounts['totalSatellites'] }}">
    <header class="mv-header">
        <div><p class="mv-eyebrow">ELEVATE / MOVEMENT INTELLIGENCE</p><h1>Analytics Dashboard</h1><p class="mv-subtitle">See the movement. Understand the change. Know where to act.</p></div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">@include('admin.movement-analytics.checkpoint-workflow')<a class="mv-button mv-dark" id="mv-export" aria-disabled="true">Export CSV <span aria-hidden="true">&#8599;</span></a></div>
    </header>
    <section class="analytics-current-stats" aria-label="Current active satellite totals">
        <p class="mv-note">Current active records, matching the Members and Satellites dashboards. Checkpoint metrics below retain their dated definitions and coverage.</p>
        @php
            $analyticsTopCards = [
                'totalSatellites' => [
                    'label' => 'Total Satellites',
                    'value' => $activeSatelliteCounts['totalSatellites'] ?? 0,
                    'url' => route('admin.satellites'),
                ],
                'activeLeaders' => [
                    'label' => 'Active Leaders',
                    'value' => $activeSatelliteCounts['activeLeaders'] ?? 0,
                    'url' => route('admin.members', ['dgroup_role' => 'Dgroup Leader']),
                ],
                'activeMembers' => [
                    'label' => 'Total Members',
                    'value' => $activeSatelliteCounts['activeMembers'] ?? 0,
                    'url' => route('admin.members'),
                ],
                'openReports' => [
                    'label' => 'Open Reports',
                    'value' => $activeSatelliteCounts['openReports'] ?? 0,
                    'url' => route('admin.satellites'),
                ],
            ];
        @endphp
        <div class="stats metric-card-grid">
        @foreach ($analyticsTopCards as $key => $card)
            <a href="{{ $card['url'] }}" class="card metric-card elevate-stat-card stat-card-link mv-top-stat-card" aria-label="{{ $card['label'] }}: {{ number_format((int) $card['value']) }}">
                <div class="info">
                    <span class="label stat-card-label">{{ $card['label'] }}</span>
                    <div class="stat-card-value-row">
                        <span class="value stat-card-value">{{ number_format((int) $card['value']) }}</span>
                        <span class="card-chevron" aria-hidden="true">›</span>
                    </div>
                </div>
            </a>
        @endforeach
        </div>
    </section>
    @if(app()->environment('local') && config('movement_analytics.sample_enabled'))
        <p class="mv-note"><a href="{{ route('admin.analytics', ['sample'=>1]) }}">Open isolated sample preview</a> · <a href="{{ route('admin.analytics') }}">Return to real data</a></p>
    @endif
    <p id="mv-sample-banner" class="mv-state" hidden>SAMPLE DATA · local preview only. No real records are shown and export is disabled.</p><form class="mv-filters" id="mv-filters" aria-label="Analytics filters">
        <label>Checkpoint<select aria-label="Checkpoint" name="checkpoint" id="mv-checkpoint">@forelse($checkpoints as $checkpoint)<option value="{{ $checkpoint->key }}" @selected($checkpoint->key === $defaultCheckpoint)>{{ $checkpoint->label }} · {{ ucfirst($checkpoint->status) }}</option>@empty<option value="">No checkpoints configured</option>@endforelse</select><span class="mv-filter-help">Choose the reporting month. Open checkpoints can receive reports; closed checkpoints keep a saved snapshot.</span></label>
        <label>Compare with<select aria-label="Compare with" name="compare"><option value="baseline">{{ $checkpoints->firstWhere('kind', 'baseline')?->label ?? 'Baseline' }} baseline</option><option value="previous">Previous checkpoint</option></select><span class="mv-filter-help">Compare with the baseline, the starting checkpoint, or with the previous checkpoint.</span></label>
        <label>Movement group<select aria-label="Movement group" name="group"><option value="">All groups</option>@foreach($groups as $group)<option value="{{ $group->group }}">{{ $group->name }}</option>@endforeach</select><span class="mv-filter-help">Limit the view to one movement group. A unit is a DGroup used to organize satellites.</span></label>
        <label>Count source<select aria-label="Count source" name="mode"><option value="auto">Auto</option><option value="reported">Reported only</option><option value="live">Live only</option></select><span class="mv-filter-help">Auto uses reports when available and live member records when a report is missing.</span></label>
        <button class="mv-button" type="submit">Apply filters</button>
    </form>
    <details class="mv-data-definitions"><summary>How these numbers work</summary><dl><dt>Live dashboard totals</dt><dd>Active satellites, DGroup leaders, and members come from current active records, matching the Satellites and Members dashboards.</dd><dt>Checkpoint analytics</dt><dd>Reported data is the count submitted for the selected checkpoint. A unit is a DGroup. Open checkpoints can receive reports; closed checkpoints retain their saved counts. The baseline is the starting checkpoint used for comparison.</dd></dl></details>
    <div class="mv-context"><span id="mv-context">Preparing your movement overview</span><span id="mv-updated">Data last updated: </span></div>
    <section id="mv-state-card" class="mv-panel mv-state-card" role="status" aria-live="polite"><div id="mv-status" class="mv-state"><span class="mv-loader" aria-hidden="true"></span> Loading movement analytics...</div><div id="mv-state-actions" class="mv-state-actions" hidden></div><button id="mv-retry" class="mv-button" type="button" hidden>Try again</button></section>
    <noscript><div class="mv-state">Enable JavaScript to explore the analytics dashboard.</div></noscript>
    <p id="mv-completeness" class="mv-note" role="status"></p><div id="mv-content" hidden>
        <nav class="mv-jump-nav" aria-label="Analytics section jump links">
            <a href="#mv-kpis">Overview KPIs</a>
            <a href="#mv-groups-title">01 Group comparison</a>
            <a href="#mv-attention">02 Needs attention</a>
            <a href="#mv-units-title">03 Unit performance</a>
            <a href="#mv-pipeline-title">04 People &amp; pipeline</a>
            <a href="#mv-forecast-title">05 Next checkpoint outlook</a>
            <a href="#mv-full-data">Full data table</a>
        </nav>
        <section class="mv-verdict" aria-labelledby="mv-verdict-title"><div><p class="mv-eyebrow" id="mv-verdict-kicker">MOVEMENT PULSE</p><h2 id="mv-verdict-title"></h2><p id="mv-verdict-copy"></p></div><div class="mv-verdict-actions"><button type="button" class="mv-button" id="mv-switch-latest-reported" hidden>Switch to latest reported checkpoint</button><a href="#mv-attention" class="mv-verdict-link">Review attention items <span aria-hidden="true">&#8599;</span></a></div></section>
        <section class="mv-kpis" id="mv-kpis" aria-label="Movement key performance indicators"></section>
        <div class="mv-grid">
            <section class="mv-panel mv-comparison" aria-labelledby="mv-groups-title"><div class="mv-section-head"><div><p class="mv-eyebrow">01 / THE BIG PICTURE</p><h2 id="mv-groups-title">Group comparison</h2></div><span class="mv-chip">Across checkpoints</span></div><div id="mv-metrics" class="mv-tabs" role="group" aria-label="Chart metric"></div><div id="mv-group-chart"></div><p class="mv-note">Available counts are shown with coverage. Missing values are never plotted as zero.</p></section>
            <section class="mv-panel mv-attention" id="mv-attention" aria-labelledby="mv-attention-title"><div class="mv-section-head"><div><p class="mv-eyebrow">02 / WHERE TO ACT</p><h2 id="mv-attention-title">Needs attention</h2></div><span class="mv-chip" id="mv-issue-count"></span></div><div id="mv-issues"></div><details id="mv-all-issues"><summary>View all attention items</summary><div id="mv-more-issues"></div></details></section>
        </div>
        <section class="mv-panel" aria-labelledby="mv-units-title"><div class="mv-section-head"><div><p class="mv-eyebrow">03 / LOCAL MOMENTUM</p><h2 id="mv-units-title">Unit performance</h2><p class="mv-note">Select a unit to explore its satellites, history and reporting evidence.</p></div><label class="mv-sort">Rank by<select id="mv-sort"><option value="count">Current count</option><option value="growth">Growth percentage</option></select></label></div><div class="mv-rank-heading"><span>Unit / <span id="mv-ranked-metric">DMembers</span></span><span>Checkpoint history</span><span>Status</span></div><div id="mv-ranking"></div><button type="button" class="mv-button" id="mv-more-units" style="margin-top:16px" aria-expanded="false">Show all units</button></section>
        <div class="mv-section-head mv-live-heading"><div><p class="mv-eyebrow">04 / PEOPLE & FOLLOW-THROUGH</p><h2>From connection to community</h2></div><span class="mv-chip" id="mv-current-scope">Current records</span></div>
        <div class="mv-grid mv-equal">
            <section class="mv-panel" aria-labelledby="mv-pipeline-title"><div class="mv-section-head"><h3 id="mv-pipeline-title">Seeker pipeline</h3><a href="{{ route('admin.seekers') }}">View seekers &#8599;</a></div><div id="mv-pipeline"></div><details><summary>Pipeline by unit & dated joining trend</summary><div id="mv-pipeline-details"></div></details></section>
            <section class="mv-panel" aria-labelledby="mv-engagement-title"><div class="mv-section-head"><h3 id="mv-engagement-title">Engagement mix</h3><a href="{{ route('admin.engagement.index') }}">Engagement Classification &#8599;</a></div><div id="mv-engagement"></div><p class="mv-note">Model classifications support review. Active membership is decided by leaders.</p><details><summary>Engagement by unit</summary><div id="mv-engagement-details"></div></details><p class="mv-note">Historical sentiment includes archived satellites. Active totals exclude archived satellites; saved checkpoint evidence is retained.</p><div id="mv-sentiment" hidden></div></section>
        </div>
        <section class="mv-panel" aria-labelledby="mv-forecast-title"><div class="mv-section-head"><div><p class="mv-eyebrow">05 / LOOKING AHEAD</p><h2 id="mv-forecast-title">Next checkpoint outlook</h2></div><button class="mv-button" type="button" id="mv-method">How this works</button></div><div id="mv-forecasts"></div><p class="mv-note">Indicative ranges, not targets or guaranteed outcomes. At least three reported checkpoints are required.</p></section>
        <details class="mv-panel mv-full-data" id="mv-full-data"><summary>Full data <span>All units, checkpoints and variances · matches CSV export</span></summary><div class="mv-table-wrap" tabindex="0" role="region" aria-label="Full movement data"><table id="mv-pivot"></table></div></details>
        <footer class="mv-footer"><span id="mv-unit-scope">Every number has a source.</span><a href="{{ route('admin.analytics.sentiment') }}">Open Sentiment Analysis &#8599;</a></footer>
    </div>
    <dialog class="mv-drawer" id="mv-drawer" aria-labelledby="mv-drawer-title"><div class="mv-drawer-head"><div><p class="mv-eyebrow" id="mv-drawer-kicker">UNIT DETAIL</p><h2 id="mv-drawer-title"></h2></div><button class="mv-button" type="button" id="mv-close" aria-label="Close details">&times;</button></div><div id="mv-drawer-body"></div></dialog>
</div>
@endsection
@push('scripts')
<script>{!! file_get_contents(resource_path('js/movement-analytics.js')) !!}</script>
@endpush
