@extends('layouts.app')
@section('title','Sentiment Analytics | Elevate DGM')
@push('styles')
@include('admin.engagement-research._styles')
@include('admin.sentiment-research._styles')
@endpush
@section('content')
<p class="muted">Historical research includes feedback from archived satellites. Archive status does not change research eligibility or results.</p>
@php
    $labels = \App\Services\SentimentResearchMetrics::LABELS;
    $distribution = collect($labels)->mapWithKeys(fn($label) => [$label => array_sum(array_column($report['table11'], $label))]);
    $total = $distribution->sum();
    $positive = $total ? $distribution['Positive'] / $total * 100 : 0;
    $neutral = $total ? $distribution['Neutral'] / $total * 100 : 0;
    $metric = fn($value) => $value === null ? '—' : number_format($value * 100, 1).'%';
@endphp
<div class="research sentiment-workspace">
    <header class="research-head">
        <div><a class="sentiment-back" href="{{ route('admin.analytics') }}">Analytics / Sentiment</a><h1>Sentiment Analytics</h1><p class="subtitle">Understand feedback. Review with confidence. Turn responses into insight.</p></div>
        <a class="button" href="{{ route('admin.sentiment-research.export') }}">Export evaluation <span aria-hidden="true">↗</span></a>
    </header>
    <nav class="sentiment-tabs" aria-label="Sentiment workspace">
        @foreach(['overview'=>['Overview',route('admin.analytics.sentiment')], 'review'=>['Human Feedback Review',route('admin.sentiment-research.review')]] as $key=>$tab)
            <a href="{{ $tab[1] }}" @if($section === $key) aria-current="page" @endif>{{ $tab[0] }}</a>
        @endforeach
    </nav>
    @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    @if($section === 'overview')
    <section class="panel distribution-panel"><div class="section-heading distribution-heading"><div><h2>Sentiment at a glance</h2><p>Human Reference Label · eligible feedback</p></div><span class="soft-badge">{{ $total }} labeled</span></div>
            <div class="distribution-body">
                <div class="sentiment-donut" style="--positive:{{ $positive }}%;--neutral:{{ $positive + $neutral }}%;{{ !$total ? 'background:#e9edf2;' : '' }}" role="img" aria-label="Human sentiment distribution: {{ $distribution['Positive'] }} positive, {{ $distribution['Neutral'] }} neutral, {{ $distribution['Negative'] }} negative">
                    <div><strong>{{ $total }}</strong><span>eligible labels</span></div>
                </div>
                <div class="sentiment-legend">@foreach($distribution as $label=>$value)<div><span><i class="sentiment-dot {{ strtolower($label) }}"></i>{{ $label }}</span><strong>{{ $value }} <small>{{ $total ? round($value / $total * 100) : 0 }}%</small></strong></div>@endforeach</div>
            </div>
            <p class="muted distribution-footnote">Distribution reflects eligible human references, not automated predictions.</p>
    </section>
    <section class="panel evaluation-snapshot"><div class="section-heading quality-heading"><div><h2>Evaluation snapshot</h2><p>Quality against eligible human references · {{ $report['table19']['n'] }} observations</p></div></div>
        <div class="quality-grid">@foreach(['Accuracy'=>$report['table19']['accuracy'],'Precision'=>$report['table19']['macro']['precision'],'Recall'=>$report['table19']['macro']['recall'],'F1 Score'=>$report['table19']['macro']['f1']] as $label=>$value)<div><span>{{ $label }}</span><strong>{{ $metric($value) }}</strong></div>@endforeach</div>
        @if(!$report['table19']['n'])<p class="muted">UNTESTED · No eligible observations with current predictions yet.</p>@endif
    </section>
    @endif

    @if($section === 'review')
    <div class="section-heading workspace-heading"><div><h2>Human Feedback Review</h2><p>Read the response, check eligibility, and record an independent reference.</p></div><form method="POST" action="{{ route('admin.sentiment-research.sync') }}">@csrf<button type="submit">Import new feedback</button></form></div>
    <form class="feedback-filters" method="GET" action="{{ route('admin.sentiment-research.review') }}"><label>Search feedback<input type="search" name="search" value="{{ $search }}" placeholder="Search by code or feedback text"></label><label>Review status<select name="status"><option value="">All statuses</option><option value="pending" @selected($status === 'pending')>Needs review</option><option value="reviewed" @selected($status === 'reviewed')>Reviewed</option></select></label><button type="submit" class="primary">Filter feedback</button><a href="{{ route('admin.sentiment-research.review') }}">Reset</a></form>
    <p class="muted">{{ $units->total() }} matching feedback units · Predictions stay hidden until the human reference is finalized.</p>
    <div class="feedback-cards">
    @forelse($units as $unit)
        <article class="panel feedback-card"><div class="section-heading"><strong class="feedback-code">{{ $unit->feedback_code }}</strong>@if ($unit->feedback?->archived_satellite)<span class="soft-badge">{{ $unit->feedback->archived_satellite->display_name }} (archived)</span>@endif<span class="soft-badge">{{ $unit->human_review_status ?? 'Needs review' }}</span></div><p class="feedback-excerpt">{{ \Illuminate\Support\Str::limit($unit->feedback_text,320) }}</p><dl class="feedback-meta"><div><dt>Language</dt><dd>{{ $unit->language_group ?? 'Unreviewed' }}</dd></div><div><dt>Human Reference Label</dt><dd>{{ $unit->human_reference_sentiment ?? 'Unlabeled' }}</dd></div><div><dt>System prediction</dt><dd>{{ $service->finalHuman($unit) ? ($unit->system_prediction ?? 'Not predicted') : 'Hidden until independent review' }}</dd></div></dl><div class="feedback-footer"><span class="muted">{{ $unit->reviewer?->name ?? 'No reviewer assigned' }}</span><a class="button" href="{{ route('admin.sentiment-research.show',$unit) }}">Open review →</a></div></article>
    @empty
        <div class="panel empty-state"><h2>{{ $search || $status ? 'No matching feedback' : 'Your review queue is empty' }}</h2><p>{{ $search || $status ? 'Try a different search or reset the filters.' : 'Import stored feedback to start reviewing responses.' }}</p></div>
    @endforelse
    </div>
    <nav class="research-actions" aria-label="Feedback pages">@if($units->previousPageUrl())<a class="button" href="{{ $units->previousPageUrl() }}">Previous</a>@endif<span>Page {{ $units->currentPage() }} of {{ $units->lastPage() }}</span>@if($units->nextPageUrl())<a class="button" href="{{ $units->nextPageUrl() }}">Next</a>@endif</nav>
    <details class="panel"><summary>Eligibility checklist and missing requirements</summary>@include('admin.sentiment-research._eligibility')</details>
    @endif

    <details class="panel engine-details"><summary>Engine information & evaluation notes <span class="soft-badge">{{ count($report['warnings']) }} notes</span></summary><p><strong>Current sentiment engine is lexicon/rule-based.</strong> {{ $report['engine']['name'] }} · {{ $report['engine']['version'] }}</p><p>{{ $report['engine']['partition_method'] }}</p>@foreach($report['warnings'] as $warning)<p class="muted">{{ $warning }}</p>@endforeach</details>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const tabs = document.querySelector('.sentiment-tabs');
    const activeTab = tabs?.querySelector('[aria-current="page"]');
    if (!tabs || !activeTab) return;

    const tabBounds = activeTab.getBoundingClientRect();
    const tabsBounds = tabs.getBoundingClientRect();
    if (tabBounds.left < tabsBounds.left) tabs.scrollLeft -= tabsBounds.left - tabBounds.left;
    if (tabBounds.right > tabsBounds.right) tabs.scrollLeft += tabBounds.right - tabsBounds.right;
})();
</script>
@endpush
