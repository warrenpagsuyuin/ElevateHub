@extends('layouts.app')

@section('content')
@push('styles')
    .sentiment-explanation > summary { width: fit-content; list-style: none; cursor: pointer; }
    .sentiment-explanation > summary::-webkit-details-marker { display: none; }
@endpush

@php
    $formatMetric = fn ($value): string => $value === null ? 'No labels yet' : number_format($value * 100, 1).'%';
@endphp

<div style="padding: 0;">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 24px; flex-wrap: wrap;">
        <div>
            <a href="{{ route('admin.analytics') }}" style="display: inline-block; color: #e33c24; text-decoration: none; font-weight: 800; margin-bottom: 12px;">Back to Analytics</a>
            <h1 style="margin: 0; font-size: 32px; font-weight: 900;">Sentiment Analytics</h1>
            <p style="margin: 8px 0 0; color: #6b7280; font-weight: 700;">Evaluate system-user feedback sentiment against human reference labels.</p>
        </div>
        <form method="POST" action="{{ route('admin.analytics.sentiment.analyze') }}" style="margin: 0;">
            @csrf
            <button type="submit" class="btn-primary" style="display: inline-flex; align-items: center; padding: 12px 18px; border-radius: 8px; font-weight: 900; cursor: pointer;">Analyze Feedback</button>
        </form>
    </div>

    @if (session('status'))
        <div class="notice" style="border-color: #bbf7d0; color: #166534;">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="notice" role="alert" tabindex="-1" data-validation-summary>
            Please correct the reference label below.
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <div class="stats-grid metric-card-grid" style="display: grid; grid-template-columns: repeat(4, minmax(160px, 1fr)); gap: 14px; margin-bottom: 18px;">
        <div class="card metric-card elevate-stat-card">
            <span class="label stat-card-label">Accuracy</span>
            <span class="value stat-card-value">{{ $formatMetric($evaluation['accuracy']) }}</span>
        </div>
        <div class="card metric-card elevate-stat-card">
            <span class="label stat-card-label">Precision</span>
            <span class="value stat-card-value">{{ $formatMetric($evaluation['macro_precision']) }}</span>
        </div>
        <div class="card metric-card elevate-stat-card">
            <span class="label stat-card-label">Recall</span>
            <span class="value stat-card-value">{{ $formatMetric($evaluation['macro_recall']) }}</span>
        </div>
        <div class="card metric-card elevate-stat-card">
            <span class="label stat-card-label">F1 Score</span>
            <span class="value stat-card-value">{{ $formatMetric($evaluation['macro_f1']) }}</span>
        </div>
    </div>

    <div class="stats-grid metric-card-grid" style="display: grid; grid-template-columns: repeat(4, minmax(160px, 1fr)); gap: 14px; margin-bottom: 18px;">
        <div class="card metric-card elevate-stat-card">
            <span class="label stat-card-label">Positive</span>
            <span class="value stat-card-value">{{ $summary['counts']['Positive'] ?? 0 }}</span>
        </div>
        <div class="card metric-card elevate-stat-card">
            <span class="label stat-card-label">Neutral</span>
            <span class="value stat-card-value">{{ $summary['counts']['Neutral'] ?? 0 }}</span>
        </div>
        <div class="card metric-card elevate-stat-card">
            <span class="label stat-card-label">Negative</span>
            <span class="value stat-card-value">{{ $summary['counts']['Negative'] ?? 0 }}</span>
        </div>
        <div class="card metric-card elevate-stat-card">
            <span class="label stat-card-label">Labeled</span>
            <span class="value stat-card-value">{{ $summary['labeled'] ?? 0 }} / {{ $summary['total'] ?? 0 }}</span>
        </div>
    </div>

    <div class="responsive-panel-grid" style="display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(280px, 0.65fr); gap: 18px; align-items: start; margin-bottom: 18px;">
        <form method="GET" action="{{ route('admin.analytics.sentiment') }}" class="filter-toolbar" style="background: #fff; border-radius: 8px; padding: 18px; box-shadow: 0 12px 26px rgba(17, 24, 39, 0.10); display: grid; grid-template-columns: repeat(3, minmax(150px, 1fr)); gap: 14px; align-items: end;">
            <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 900; color: #374151;">
                Predicted Label
                <select name="sentiment" style="padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
                    <option value="">All predicted labels</option>
                    @foreach ($sentiments as $sentiment)
                        <option value="{{ $sentiment }}" {{ $filters['sentiment'] === $sentiment ? 'selected' : '' }}>{{ $sentiment }}</option>
                    @endforeach
                </select>
            </label>
            <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 900; color: #374151;">
                Human Reference
                <select name="human_sentiment_label" style="padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
                    <option value="">All reference labels</option>
                    @foreach ($sentiments as $sentiment)
                        <option value="{{ $sentiment }}" {{ $filters['human_sentiment_label'] === $sentiment ? 'selected' : '' }}>{{ $sentiment }}</option>
                    @endforeach
                </select>
            </label>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn-primary" style="display: inline-flex; align-items: center; padding: 10px 18px; border-radius: 8px; font-weight: 900; cursor: pointer;">Apply Filters</button>
                <a href="{{ route('admin.analytics.sentiment') }}" style="padding: 10px 18px; background: #f3f4f6; color: #111; text-decoration: none; border-radius: 8px; font-weight: 900;">Clear Filters</a>
            </div>
        </form>

        <div style="background: #fff; border-radius: 8px; padding: 18px; box-shadow: 0 12px 26px rgba(17, 24, 39, 0.10);">
            <h2 style="margin: 0 0 12px; font-size: 16px; font-weight: 900;">Six-Month Trend</h2>
            @forelse ($summary['trend'] as $trend)
                <div style="display: grid; grid-template-columns: 82px 1fr; gap: 10px; align-items: center; margin-bottom: 10px;">
                    <strong style="font-size: 13px;">{{ $trend['period'] }}</strong>
                    <div style="display: flex; height: 12px; border-radius: 999px; overflow: hidden; background: #f3f4f6;" aria-label="Sentiment trend for {{ $trend['period'] }}">
                        @php
                            $total = max(1, $trend['total']);
                            $positiveWidth = (($trend['Positive'] ?? 0) / $total) * 100;
                            $neutralWidth = (($trend['Neutral'] ?? 0) / $total) * 100;
                            $negativeWidth = (($trend['Negative'] ?? 0) / $total) * 100;
                        @endphp
                        <span title="Positive {{ $trend['Positive'] ?? 0 }}" style="display: block; width: {{ $positiveWidth }}%; background: #e33c24;"></span>
                        <span title="Neutral {{ $trend['Neutral'] ?? 0 }}" style="display: block; width: {{ $neutralWidth }}%; background: #111;"></span>
                        <span title="Negative {{ $trend['Negative'] ?? 0 }}" style="display: block; width: {{ $negativeWidth }}%; background: #7f1d1d;"></span>
                    </div>
                </div>
            @empty
                <p style="margin: 0; color: #6b7280; font-size: 14px;">No sentiment trend yet. Analyze submitted feedback to populate this view.</p>
            @endforelse
        </div>
    </div>

    <div class="table-card" style="background: #fff; border-radius: 8px; box-shadow: 0 16px 34px rgba(17, 24, 39, 0.12); overflow: hidden; margin-bottom: 18px;">
        <div class="table-scroll">
        <table style="width: 100%; min-width: 1120px; border-collapse: collapse;">
            <thead>
                <tr style="background: #111; color: #fff;">
                    <th style="padding: 15px; text-align: left; font-weight: 900; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">Evaluator</th>
                    <th style="padding: 15px; text-align: left; font-weight: 900; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">Predicted Label</th>
                    <th style="padding: 15px; text-align: left; font-weight: 900; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">Human Reference Label</th>
                    <th style="padding: 15px; text-align: left; font-weight: 900; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">Original Feedback</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($feedbackItems as $feedback)
                    @php
                        $sentiment = $feedback->sentiment;
                        $sentimentBg = match($sentiment) {
                            'Positive' => '#ffe5df',
                            'Negative' => '#fee2e2',
                            'Neutral' => '#f3f4f6',
                            default => '#f9fafb',
                        };
                        $sentimentColor = match($sentiment) {
                            'Positive' => '#c92f1b',
                            'Negative' => '#7f1d1d',
                            'Neutral' => '#111',
                            default => '#6b7280',
                        };
                        $decisionPath = $feedback->sentiment_metrics['decision_path'] ?? [];
                    @endphp
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 15px; font-weight: 800;">
                            {{ $feedback->evaluator?->name ?? 'Unknown evaluator' }}
                            <small style="display: block; margin-top: 5px; color: #6b7280;">{{ $feedback->created_at?->format('M d, Y g:i A') }}</small>
                        </td>
                        <td style="padding: 15px;">
                            @if ($decisionPath)
                                <details class="sentiment-explanation">
                                    <summary aria-label="Show explanation for {{ $sentiment ?? 'unanalyzed' }} sentiment">
                                        <span style="display: inline-block; padding: 5px 10px; background: {{ $sentimentBg }}; color: {{ $sentimentColor }}; border-radius: 4px; font-size: 12px; font-weight: 900;">{{ $sentiment ?? 'Unanalyzed' }}</span>
                                    </summary>
                                    <ul style="margin: 8px 0 0; padding-left: 18px; font-size: 13px;">
                                        @foreach ($decisionPath as $step)
                                            <li style="margin-bottom: 5px;">{{ $step }}</li>
                                        @endforeach
                                    </ul>
                                </details>
                            @else
                                <span style="display: inline-block; padding: 5px 10px; background: {{ $sentimentBg }}; color: {{ $sentimentColor }}; border-radius: 4px; font-size: 12px; font-weight: 900;">{{ $sentiment ?? 'Unanalyzed' }}</span>
                            @endif
                            @if ($feedback->sentiment_confidence !== null)
                                <small style="display: block; margin-top: 6px; color: #6b7280; font-weight: 700;">{{ number_format($feedback->sentiment_confidence * 100, 0) }}% confidence</small>
                            @endif
                        </td>
                        <td style="padding: 15px; min-width: 210px;">
                            <form method="POST" action="{{ route('admin.analytics.sentiment.reference-label', $feedback) }}" style="display: flex; gap: 8px; align-items: center;">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="_feedback_id" value="{{ $feedback->id }}">
                                <label class="sr-only" for="feedback-reference-{{ $feedback->id }}">Human reference label for feedback {{ $feedback->id }} from {{ $feedback->evaluator?->name ?? 'Unknown evaluator' }}</label>
                                <select id="feedback-reference-{{ $feedback->id }}" name="human_sentiment_label" @if (old('_feedback_id') == $feedback->id && $errors->has('human_sentiment_label')) aria-invalid="true" aria-describedby="feedback-reference-{{ $feedback->id }}-error" @endif style="min-width: 122px; padding: 9px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px;">
                                    <option value="">Unlabeled</option>
                                    @foreach ($sentiments as $sentimentOption)
                                        <option value="{{ $sentimentOption }}" {{ $feedback->human_sentiment_label === $sentimentOption ? 'selected' : '' }}>{{ $sentimentOption }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" aria-label="Save label for feedback {{ $feedback->id }}" style="padding: 9px 11px; background: #111; color: #fff; border: none; border-radius: 8px; font-size: 12px; font-weight: 900; cursor: pointer;">Save Label</button>
                            </form>
                            @if (old('_feedback_id') == $feedback->id && $errors->has('human_sentiment_label'))
                                <p id="feedback-reference-{{ $feedback->id }}-error" class="field-error">{{ $errors->first('human_sentiment_label') }}</p>
                            @endif
                            @if ($feedback->humanLabeler)
                                <small style="display: block; margin-top: 6px; color: #6b7280;">By {{ $feedback->humanLabeler->name }}</small>
                            @endif
                        </td>
                        <td style="padding: 15px; color: #374151; line-height: 1.45;">{{ Str::limit($feedback->message, 190) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="padding: 34px; text-align: center; color: #6b7280; font-weight: 700;">No system feedback is available for sentiment analysis.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @include('partials.table-pagination', ['paginator' => $feedbackItems, 'pageSizes' => $pageSizes, 'currentPageSize' => $perPage, 'recordLabel' => 'feedback entries', 'tableId' => 'sentiment-feedback'])
    </div>

    <div class="table-card" style="background: #fff; border-radius: 8px; box-shadow: 0 12px 28px rgba(17, 24, 39, 0.10); overflow: hidden;">
        <div class="table-scroll">
        <table style="width: 100%; min-width: 760px; border-collapse: collapse;">
            <thead>
                <tr style="background: #e33c24; color: #fff;">
                    <th style="padding: 14px; text-align: left; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">Reference Label</th>
                    <th style="padding: 14px; text-align: left; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">True Positive</th>
                    <th style="padding: 14px; text-align: left; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">False Positive</th>
                    <th style="padding: 14px; text-align: left; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">False Negative</th>
                    <th style="padding: 14px; text-align: left; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">Precision</th>
                    <th style="padding: 14px; text-align: left; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">Recall</th>
                    <th style="padding: 14px; text-align: left; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">F1</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($evaluation['per_label'] as $label => $metrics)
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 14px; font-weight: 900;">{{ $label }}</td>
                        <td style="padding: 14px;">{{ $metrics['true_positive'] }}</td>
                        <td style="padding: 14px;">{{ $metrics['false_positive'] }}</td>
                        <td style="padding: 14px;">{{ $metrics['false_negative'] }}</td>
                        <td style="padding: 14px;">{{ $formatMetric($metrics['precision']) }}</td>
                        <td style="padding: 14px;">{{ $formatMetric($metrics['recall']) }}</td>
                        <td style="padding: 14px;">{{ $formatMetric($metrics['f1']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        <p style="margin: 0; padding: 14px 16px; color: #6b7280; font-size: 13px; font-weight: 700;">Validation metrics use only feedback rows that have both a predicted label and a human reference label.</p>
    </div>
</div>
@endsection
