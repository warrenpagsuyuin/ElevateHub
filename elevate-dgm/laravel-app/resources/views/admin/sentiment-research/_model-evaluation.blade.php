@php
    $pct = fn ($value) => $value === null ? '—' : number_format($value * 100, 1).'%';
    $scope = ($evaluation['from'] ?: 'all time').' to '.($evaluation['to'] ?: 'present');
    $currentDataset = $evaluation['dataset'] ?? 'comments';
@endphp
<div style="display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 20px;">
    <form method="GET" action="{{ route('admin.analytics.sentiment') }}" class="evaluation-scope" style="margin-bottom: 0;">
        <input type="hidden" name="view" value="evaluation">
        <label>Dataset
            <select name="dataset" onchange="this.form.submit()">
                <option value="comments" @selected($currentDataset === 'comments')>Questionnaire Comments (Live)</option>
                <option value="system_feedback" @selected($currentDataset === 'system_feedback')>System Feedback (Thesis Cohort)</option>
            </select>
        </label>
        <label>From <input type="date" name="from" value="{{ $evaluation['from'] }}"></label>
        <label>To <input type="date" name="to" value="{{ $evaluation['to'] }}"></label>
        <button type="submit" class="dark-action">Apply scope</button>
        <a class="touch-target" href="{{ route('admin.analytics.sentiment', ['view' => 'evaluation', 'dataset' => $currentDataset]) }}">All time</a>
    </form>

    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
        @if($currentDataset === 'comments')
            <a class="button" href="{{ route('admin.satisfaction-comments.export', request()->only(['from', 'to'])) }}" style="min-height: 44px; display: inline-flex; align-items: center; border-radius: 8px;">
                Export CSV <span aria-hidden="true" style="margin-left: 4px;">↓</span>
            </a>
            <form method="POST" action="{{ route('admin.satisfaction-comments.backfill') }}" onsubmit="return confirm('Back-fill predictions for comments that lack one?');" style="margin: 0;">
                @csrf
                <input type="hidden" name="from" value="{{ $evaluation['from'] }}">
                <input type="hidden" name="to" value="{{ $evaluation['to'] }}">
                <input type="hidden" name="dataset" value="{{ $currentDataset }}">
                <input type="hidden" name="confirm" value="1">
                <button type="submit" class="button" style="min-height: 44px; border-radius: 8px;">Back-fill predictions</button>
            </form>
        @endif

        <form method="POST" action="{{ route('admin.satisfaction-comments.recalculate') }}" onsubmit="return confirm('Recalculate metrics from stored records and clear the evaluation cache?');" style="margin: 0;">
            @csrf
            <input type="hidden" name="from" value="{{ $evaluation['from'] }}">
            <input type="hidden" name="to" value="{{ $evaluation['to'] }}">
            <input type="hidden" name="dataset" value="{{ $currentDataset }}">
            <input type="hidden" name="confirm" value="1">
            <button type="submit" class="dark-action" style="min-height: 44px; border-radius: 8px;">Recalculate metrics</button>
        </form>
    </div>
</div>

@if($currentDataset === 'comments' && isset($evaluation['commentEvaluation']['agreement']['n']) && $evaluation['commentEvaluation']['agreement']['n'] > 0)
    @php($agr = $evaluation['commentEvaluation']['agreement'])
    <div class="panel" style="margin-bottom: 20px; padding: 14px 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
        <h3 style="margin: 0 0 6px; font-size: 14px; font-weight: 800;">Inter-Rater Agreement (Cohen's Kappa)</h3>
        <p style="margin: 0; font-size: 13px; color: #475569;">
            Double-labeled items: <strong>{{ $agr['n'] }}</strong> · Observed agreement: <strong>{{ $agr['observed'] !== null ? number_format($agr['observed'] * 100, 1).'%' : '—' }}</strong> ·
            Cohen's κ: <strong>{{ $agr['kappa'] !== null ? number_format($agr['kappa'], 3) : '—' }}</strong>
            <small style="color: #64748b; margin-left: 6px;">(Calculated across independent reviewer pairs prior to adjudication)</small>
        </p>
    </div>
@endif

@foreach([['title' => $currentDataset === 'comments' ? 'Sentiment analysis (Questionnaire comments)' : 'Sentiment analysis (System feedback research)', 'data' => $evaluation['sentiment'], 'version' => $evaluation['sentimentVersion'], 'coverage' => $evaluation['sentimentCoverage']['predicted'].' eligible predictions of '.$evaluation['sentimentCoverage']['eligible'].' eligible labels; '.$evaluation['sentimentCoverage']['eligible'].' eligible of '.$evaluation['sentimentCoverage']['total'].' feedback records'], ['title' => 'Engagement classification', 'data' => $evaluation['engagement'], 'version' => $evaluation['engagementRun']?->model_version, 'coverage' => $evaluation['engagementRun'] ? $evaluation['engagement']['n'].' evaluated holdout predictions of '.$evaluation['engagementTotal'].' holdout records' : 'No active evaluated model run']] as $component)
    @php($data = $component['data'])
    <section class="panel model-evaluation-section" aria-label="{{ $component['title'] }} model evaluation">
        <h2 class="elevate-panel-heading">{{ $component['title'] }}</h2>
        <p>Compared against human reference labels · {{ $data['n'] }} eligible observations · evaluated on {{ now()->format('M d, Y g:i A') }} · {{ str_contains($component['title'], 'Sentiment') ? 'feedback submitted' : 'model run trained' }} {{ $scope }} · model {{ $component['version'] ?: 'not available' }}</p>
        <p class="muted">{{ $component['coverage'] }}</p>
        @if($component['title'] === 'Engagement classification')<p class="muted">The CART model evaluates three classes. Insufficient Information / Not Yet Classified is a dashboard fallback, not a predicted CART class.</p>@endif
        @if(!$data['n'])
            <p>No evaluation data yet. Metrics appear once records have both a system prediction and a human reference label.</p>
        @else
            <div class="quality-grid">
                @foreach(['Accuracy' => $data['accuracy'], 'Precision' => $data['macro']['precision'], 'Recall' => $data['macro']['recall'], 'F1 Score' => $data['macro']['f1']] as $name => $value)
                    @php($key = strtolower(str_replace(' Score', '', $name)))
                    <div class="elevate-stat-card" aria-label="{{ $component['title'] }} {{ $name }} {{ $pct($value) }}{{ $name === 'Accuracy' ? ', 95 percent Wilson confidence interval '.$pct($data['accuracy_ci'][0]).' to '.$pct($data['accuracy_ci'][1]) : ', macro average; weighted average '.$pct($data['weighted'][$key]) }}">
                        <span class="stat-card-label">{{ $name }}</span><strong class="stat-card-value">{{ $pct($value) }}</strong>
                        <small>
                            @if($name === 'Accuracy')
                                95% CI {{ $pct($data['accuracy_ci'][0]) }} to {{ $pct($data['accuracy_ci'][1]) }}
                                @if(isset($data['majority_baseline']) && $data['majority_baseline'] !== null)
                                    · majority: {{ $pct($data['majority_baseline']) }}
                                @endif
                            @else
                                macro average · weighted {{ $pct($data['weighted'][$key]) }}
                            @endif
                        </small>
                    </div>
                @endforeach
            </div>
        @endif
        <details><summary>How these are calculated</summary><p>Accuracy is correct predictions divided by all evaluated predictions. Per-class precision is correct predictions of a class divided by all predictions of that class. Recall is correct predictions of a class divided by all actual records of that class. F1 is the harmonic mean of precision and recall. Macro averages weight classes equally; weighted averages use actual class support. Undefined class metrics contribute zero to averages. Only records with an eligible human reference and a stored system prediction are compared.</p></details>
    </section>
@endforeach
