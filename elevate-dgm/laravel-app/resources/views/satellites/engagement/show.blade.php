@extends('layouts.app')

@section('title', $member->display_name.' Engagement | Elevate DGM')

@section('content')
@php
    $activeLabel = \App\Services\EngagementClassificationService::CLASSIFICATION_ACTIVE;
    $moderateLabel = \App\Services\EngagementClassificationService::CLASSIFICATION_MODERATELY_ACTIVE;
    $needsFollowUpLabel = \App\Services\EngagementClassificationService::CLASSIFICATION_NEEDS_FOLLOW_UP;
    $insufficientLabel = \App\Services\EngagementClassificationService::CLASSIFICATION_INSUFFICIENT;
    $cartModel = \App\Services\EngagementClassificationService::MODEL_CART;
    $classification = $result?->classification;
    $displayClassification = $result?->classification_label ?? 'Unclassified';
    $confidence = $result?->confidence;
    $normalizedConfidence = $confidence !== null && $confidence > 0
        ? round($confidence > 1 ? $confidence : $confidence * 100) . '%'
        : 'N/A';
    $generatedAt = $result?->generated_at ?? $result?->updated_at;
    $metrics = is_array($result?->metrics) ? $result->metrics : [];
    $decisionPath = is_array($result?->decision_path) ? $result->decision_path : ($metrics['decision_path'] ?? []);
    $usedFeatures = is_array($result?->used_features) ? $result->used_features : ($metrics['used_features'] ?? []);
    $modelRun = $result?->modelRun;
    $splitSummary = is_array($modelRun?->split_summary) ? $modelRun->split_summary : [];
    $validationMetrics = is_array($modelRun?->validation_metrics) ? $modelRun->validation_metrics : [];
    $holdoutMetrics = is_array($modelRun?->holdout_metrics) ? $modelRun->holdout_metrics : [];
    $isCart = $result?->model_name === $cartModel;
    $badge = match ($classification) {
        'High', $activeLabel => ['bg' => '#dcfce7', 'text' => '#166534', 'panel' => '#f0fdf4'],
        'Medium', $moderateLabel => ['bg' => '#fef3c7', 'text' => '#92400e', 'panel' => '#fffbeb'],
        'Low', $needsFollowUpLabel => ['bg' => '#fee2e2', 'text' => '#991b1b', 'panel' => '#fef2f2'],
        'Inactive', $insufficientLabel => ['bg' => '#e5e7eb', 'text' => '#4b5563', 'panel' => '#f9fafb'],
        default => ['bg' => '#f3f4f6', 'text' => '#374151', 'panel' => '#f9fafb'],
    };
@endphp

<div class="page-inner engagement-detail">
    <div class="page-header">
        <div>
            <a href="{{ route('satellites.engagement.index', $satellite) }}" class="back-link">← Back to Analytics</a>
            <h1>{{ $member->display_name }}</h1>
            <p>{{ $satellite->display_name }} — Engagement classification result, recorded indicators, and pastoral review.</p>
        </div>

        <a href="{{ route('satellites.members', $satellite) }}" class="secondary-action">View Members</a>
    </div>

    @if (session('error'))<p class="recommendation-error" role="alert">{{ session('error') }}</p>@endif
    @if ($errors->any())<div class="recommendation-error" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if (! $result)
        <div class="ui-card empty-state">
            <h2>No Engagement Result Available</h2>
            <p>Generate a classification from Engagement Analytics before reviewing the explanation.</p>
        </div>
    @else
        <div class="detail-grid">
            <section class="ui-card result-card">
                <div class="eyebrow">Engagement Classification</div>
                <div class="classification-banner" style="background: {{ $badge['panel'] }}; border-color: {{ $badge['text'] }};">
                    <div class="banner-top-row">
                        <span class="classification-pill" style="background: {{ $badge['bg'] }}; color: {{ $badge['text'] }};">
                            {{ $displayClassification }}
                        </span>
                        <div class="confidence-text">Confidence: {{ $normalizedConfidence }}</div>
                    </div>
                </div>
                <dl class="metadata-list metadata-grid-2col">
                    <div>
                        <dt>Role</dt>
                        <dd>{{ $member->display_role }}</dd>
                    </div>
                    <div>
                        <dt>Campus / School</dt>
                        <dd>{{ $member->campus_school ?: 'Not recorded' }}</dd>
                    </div>
                    <div>
                        <dt>Generated</dt>
                        <dd>{{ $generatedAt?->format('M d, Y g:i A') ?? 'Not recorded' }}</dd>
                    </div>
                    <div>
                        <dt>Human Review</dt>
                        <dd>{{ $result->human_review_required ? 'Required' : 'Not required' }}</dd>
                    </div>
                </dl>
                <div class="metadata-footer">
                    <span><strong>Model:</strong> {{ $result->model_name ?: 'Not recorded' }} ({{ $result->model_version ?: 'Not recorded' }})</span>
                    <span>&middot;</span>
                    <span><strong>Data Sufficiency:</strong> {{ \Illuminate\Support\Str::of($result->data_sufficiency ?: 'not recorded')->replace('_', ' ')->title() }}</span>
                </div>
            </section>

            <section class="ui-card xai-card">
                <div class="eyebrow">Why This Result?</div>
                <h2>Explainable AI (XAI)</h2>
                <div class="eyebrow eyebrow-subtle">Plain-Language Explanation</div>
                <p>
                    @if ($isCart)
                        Based on the available approved records, the CART model reached this classification through the recorded engagement indicators shown below.
                    @else
                        {{ $result->explanation ?: 'The recorded indicators used for this classification are shown below for authorized review.' }}
                    @endif
                </p>

                @if (! empty($usedFeatures))
                    <div class="xai-summary-pills">
                        @foreach ($usedFeatures as $feature)
                            <div class="xai-summary-pill">
                                <span class="xai-pill-label">{{ $feature['label'] ?? 'Recorded Indicator' }}</span>
                                <strong class="xai-pill-value">{{ $feature['value'] ?? 'Not recorded' }}</strong>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="notice-box">
                    Classification is intended for authorized administrative review only. It does not measure faith, spiritual maturity, character, emotional condition, or personal worth.
                    Human review is required before any follow-up decision. The system does not automatically contact, discipline, or exclude members.
                </div>
            </section>
        </div>

        <div class="action-factors-grid {{ $canManageSatellite ? 'has-action-col' : '' }}">
            @if ($canManageSatellite)
                <section class="ui-card section-card action-review-card">
                    <div class="section-heading">
                        <div>
                            <div class="eyebrow">Pastoral &amp; Administrative Action</div>
                            <h2>Assign recommendation for human review</h2>
                        </div>
                        <a href="{{ route('satellites.recommendations.index', $satellite) }}" class="secondary-action secondary-action-sm">View recommendations</a>
                    </div>
                    <p class="muted-text" style="margin-bottom: 14px;">Choose an approved catalogue action that applies to this result. Insufficient information permits only an approved data-validation action.</p>
                    @if ($recommendationActions->isEmpty())
                        <div class="empty-catalogue-box">
                            <p>No eligible approved catalogue actions are available. An administrator must review and approve suitable wording before a recommendation can be assigned.</p>
                        </div>
                    @else
                        <form method="post" action="{{ route('satellites.engagement.recommendations.store', [$satellite, $member]) }}" class="recommendation-form">
                            @csrf
                            <label>Approved action<select name="recommended_action_id" required><option value="">Choose action</option>@foreach ($recommendationActions as $action)<option value="{{ $action->id }}" @selected((string) old('recommended_action_id') === (string) $action->id)>{{ $action->title }} — {{ $action->description }}</option>@endforeach</select></label>
                            <div class="form-row-2col">
                                <label>Assigned reviewer<select name="assigned_to" required>@foreach ($recommendationAssignees as $assignee)<option value="{{ $assignee->id }}" @selected((int) old('assigned_to', auth()->id()) === $assignee->id)>{{ $assignee->name }}</option>@endforeach</select></label>
                                <label>Expiry date (optional)<input type="date" name="expires_at" min="{{ now()->toDateString() }}" value="{{ old('expires_at') }}"></label>
                            </div>
                            <label>Assignment notes<textarea name="assignment_notes" maxlength="2000" rows="2" placeholder="Optional context for the reviewer...">{{ old('assignment_notes') }}</textarea></label>
                            <button class="recommendation-button primary" type="submit">Assign for review</button>
                        </form>
                    @endif
                </section>
            @endif

            <section class="ui-card section-card">
                <div class="section-heading">
                    <div>
                        <div class="eyebrow">Factors Used</div>
                        <h2>{{ $isCart ? 'Factors Used by CART' : 'Factors Used by Review Baseline' }}</h2>
                    </div>
                </div>

                @if (! empty($usedFeatures))
                    <div class="table-scroll">
                        <table class="data-table compact-table">
                            <thead>
                                <tr>
                                    <th>Indicator</th>
                                    <th>Recorded Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($usedFeatures as $feature)
                                    <tr>
                                        <td>{{ $feature['label'] ?? 'Recorded Indicator' }}</td>
                                        <td>{{ $feature['value'] ?? 'Not recorded' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="muted-text">No structured indicator list is available for this result.</p>
                @endif
            </section>
        </div>

        <section class="ui-card section-card technical-section-card">
            <div class="section-heading">
                <div>
                    <div class="eyebrow" title="The sequence of model rules followed before producing the classification.">{{ $isCart ? 'CART Decision Path' : 'Review Decision Path' }}</div>
                    <h2>{{ $isCart ? 'Actual CART Traversal' : 'Baseline Review Path' }}</h2>
                </div>
                <span class="model-chip" title="{{ $isCart ? 'A decision-tree model used to classify engagement records using approved recorded indicators.' : 'A review baseline used only when separate human reference labels are not sufficient for CART training.' }}">{{ $isCart ? 'CART' : 'Baseline' }}</span>
            </div>

            @if(!empty($result?->model_metadata['reference_notice']))
                <p class="muted-text" style="margin-bottom: 10px;">{{ $result->model_metadata['reference_notice'] }}</p>
            @endif
            @if($modelRun && !$modelRun->is_active)
                <p class="muted-text" style="margin-bottom: 10px;">This result uses a previous reference-dataset revision. Ask an administrator to run the current evaluation before relying on this prediction.</p>
            @endif
            @if (! empty($decisionPath))
                <details class="xai-disclosure">
                    <summary aria-label="{{ $isCart ? 'View CART Decision Path' : 'View Review Decision Path' }}">
                        {{ $isCart ? 'View CART Decision Path' : 'View Review Decision Path' }}
                    </summary>
                    <div class="decision-timeline">
                        @foreach ($decisionPath as $step)
                            @if (is_array($step))
                                <div class="decision-step">
                                    <div class="step-label">{{ $step['label'] ?? 'Decision Step' }}</div>
                                    <div class="step-rule">
                                        @if (($step['feature'] ?? null) === 'leaf')
                                            Final leaf predicts <strong>{{ $step['outcome'] ?? $displayClassification }}</strong>
                                        @else
                                            Recorded value <strong>{{ $step['value'] ?? 'Not recorded' }}</strong>
                                            @if(!empty($step['was_imputed']))
                                                (training-fitted replacement: <strong>{{ $step['imputed_value'] }}</strong>)
                                            @endif
                                            {{ $step['operator'] ?? '' }}
                                            <strong>{{ $step['threshold'] ?? 'threshold' }}</strong>
                                        @endif
                                    </div>
                                    <div class="step-meta">
                                        Node: {{ $step['node_id'] ?? 'N/A' }}
                                        @if (! empty($step['next_node_id']))
                                            | Next Node: {{ $step['next_node_id'] }}
                                        @endif
                                        | Branch: {{ ucfirst((string) ($step['branch_taken'] ?? $step['branch'] ?? 'review')) }}
                                        @if (! empty($step['outcome']))
                                            | Outcome: {{ $step['outcome'] }}
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="decision-step">
                                    <div class="step-label">Legacy Decision Step</div>
                                    <div class="step-rule">{{ $step }}</div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </details>
            @else
                <p class="muted-text">No decision path is available for this result.</p>
            @endif
        </section>

        @if (auth()->user()?->role === 'admin' && $modelRun)
            <section class="ui-card section-card technical-section-card">
                <div class="section-heading">
                    <div>
                        <div class="eyebrow">Model Evaluation</div>
                        <h2>Validation and Holdout Results</h2>
                    </div>
                    <span class="model-chip">{{ $modelRun->model_version }}</span>
                </div>

                <div class="evaluation-grid">
                    <div class="evaluation-tile">
                        <div class="tile-label">Training Samples</div>
                        <div class="tile-value">{{ $splitSummary['train']['sample_count'] ?? 'N/A' }}</div>
                    </div>
                    <div class="evaluation-tile">
                        <div class="tile-label">Validation Accuracy</div>
                        <div class="tile-value">
                            {{ isset($validationMetrics['accuracy']) ? round($validationMetrics['accuracy'] * 100) . '%' : 'N/A' }}
                        </div>
                    </div>
                    <div class="evaluation-tile">
                        <div class="tile-label">Holdout Accuracy</div>
                        <div class="tile-value">
                            {{ isset($holdoutMetrics['accuracy']) ? round($holdoutMetrics['accuracy'] * 100) . '%' : 'N/A' }}
                        </div>
                    </div>
                    <div class="evaluation-tile">
                        <div class="tile-label">Trained</div>
                        <div class="tile-value tile-value-small">{{ $modelRun->trained_at?->format('M d, Y g:i A') ?? 'N/A' }}</div>
                    </div>
                </div>
            </section>
        @endif

        <div class="action-row">
            <a href="{{ route('satellites.engagement.index', $satellite) }}" class="secondary-action">Back</a>
        </div>
    @endif
</div>
@endsection

@push('styles')
    @include('satellites.recommendations.styles')
    .page-inner {
        width: 100%;
        max-width: 1600px;
        margin: 0 auto;
    }

    .back-link {
        color: #ef3b24;
        display: inline-block;
        font-weight: 900;
        margin-bottom: 12px;
        text-decoration: none;
    }

    .secondary-action {
        align-items: center;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        color: #111;
        display: inline-flex;
        font-weight: 900;
        min-height: 44px;
        padding: 0 16px;
        text-decoration: none;
    }

    .secondary-action-sm {
        min-height: 36px;
        padding: 0 12px;
        font-size: 13px;
    }

    .detail-grid {
        display: grid;
        gap: 20px;
        grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr);
        margin-bottom: 20px;
    }

    .action-factors-grid {
        display: grid;
        gap: 20px;
        grid-template-columns: 1fr;
        margin-bottom: 20px;
    }

    .action-factors-grid.has-action-col {
        grid-template-columns: minmax(0, 1.05fr) minmax(0, .95fr);
    }

    .action-factors-grid .section-card {
        margin-top: 0;
    }

    .result-card,
    .xai-card,
    .section-card,
    .empty-state {
        padding: 24px;
    }

    .eyebrow {
        color: #667085;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: 0;
        margin-bottom: 8px;
        text-transform: uppercase;
    }

    .eyebrow-subtle {
        color: #111;
        margin-bottom: 6px;
        margin-top: 12px;
    }

    .classification-banner {
        border-left: 4px solid;
        border-radius: 12px;
        margin-bottom: 18px;
        padding: 16px;
    }

    .banner-top-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .classification-pill {
        border-radius: 999px;
        display: inline-block;
        font-size: 13px;
        font-weight: 900;
        padding: 8px 12px;
    }

    .confidence-text {
        color: #374151;
        font-size: 14px;
        font-weight: 800;
    }

    .metadata-list {
        display: grid;
        gap: 14px;
        margin: 0;
    }

    .metadata-grid-2col {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .metadata-list div {
        display: grid;
        gap: 4px;
        background: #f9fafb;
        border: 1px solid #eef0f3;
        border-radius: 10px;
        padding: 10px 12px;
    }

    .metadata-list dt {
        color: #667085;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .metadata-list dd {
        color: #111;
        font-size: 14px;
        font-weight: 800;
        margin: 0;
    }

    .metadata-footer {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 14px;
        padding-top: 12px;
        border-top: 1px solid #eef0f3;
        font-size: 12px;
        color: #667085;
    }

    .xai-card h2,
    .section-card h2,
    .empty-state h2 {
        font-size: 20px;
        font-weight: 900;
        margin: 0 0 8px;
    }

    .xai-card p,
    .empty-state p,
    .muted-text {
        color: #4b5563;
        font-size: 14px;
        line-height: 1.6;
        margin: 0;
    }

    .xai-summary-pills {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        margin-top: 14px;
    }

    .xai-summary-pill {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 10px 12px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .xai-pill-label {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        color: #667085;
    }

    .xai-pill-value {
        font-size: 15px;
        font-weight: 900;
        color: #111827;
    }

    .notice-box {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-left: 3px solid #9ca3af;
        border-radius: 10px;
        color: #4b5563;
        font-size: 12.5px;
        font-weight: 600;
        line-height: 1.5;
        margin-top: 16px;
        padding: 12px 14px;
    }

    .section-card {
        margin-top: 20px;
    }

    .action-review-card {
        border-top: 3px solid #450d07;
    }

    .empty-catalogue-box {
        background: #f9fafb;
        border: 1px dashed #d0d5dd;
        border-radius: 10px;
        padding: 14px;
    }

    .form-row-2col {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .section-heading {
        align-items: flex-start;
        display: flex;
        gap: 16px;
        justify-content: space-between;
        margin-bottom: 14px;
    }

    .model-chip {
        background: #1A1A1A;
        border: 1px solid #450d07;
        border-radius: 999px;
        color: #fff;
        font-size: 12px;
        font-weight: 900;
        padding: 6px 10px;
        white-space: nowrap;
    }

    .compact-table {
        width: 100%;
    }

    .compact-table th,
    .compact-table td {
        border-bottom: 1px solid #eef0f3;
        padding: 12px 14px;
        text-align: left;
    }

    .compact-table th {
        background: #f9fafb;
        color: #111;
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .decision-timeline {
        display: grid;
        gap: 12px;
    }

    .xai-disclosure {
        display: grid;
        gap: 14px;
    }

    .xai-disclosure summary {
        background: #1A1A1A;
        border: 1px solid #450d07;
        border-radius: 10px;
        color: #fff;
        cursor: pointer;
        font-size: 13px;
        font-weight: 900;
        list-style-position: inside;
        max-width: max-content;
        padding: 10px 14px;
    }

    .xai-disclosure summary:focus-visible {
        outline: 3px solid rgba(239, 59, 36, .35);
        outline-offset: 3px;
    }

    .xai-disclosure[open] summary {
        background: #2d120e;
    }

    .evaluation-grid {
        display: grid;
        gap: 14px;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }

    .evaluation-tile {
        background: #f9fafb;
        border: 1px solid #eef0f3;
        border-radius: 12px;
        padding: 14px;
    }

    .tile-label {
        color: #667085;
        font-size: 12px;
        font-weight: 900;
        margin-bottom: 6px;
        text-transform: uppercase;
    }

    .tile-value {
        color: #111;
        font-size: 24px;
        font-weight: 900;
    }

    .tile-value-small {
        font-size: 15px;
    }

    .decision-step {
        background: #f9fafb;
        border: 1px solid #eef0f3;
        border-radius: 12px;
        padding: 14px;
    }

    .step-label {
        color: #111;
        font-size: 14px;
        font-weight: 900;
        margin-bottom: 6px;
    }

    .step-rule {
        color: #374151;
        font-size: 14px;
        line-height: 1.5;
    }

    .step-meta {
        color: #667085;
        font-size: 12px;
        font-weight: 800;
        margin-top: 8px;
    }

    .action-row {
        margin-top: 20px;
    }

    @media (max-width: 960px) {
        .detail-grid,
        .action-factors-grid.has-action-col,
        .form-row-2col,
        .metadata-grid-2col {
            grid-template-columns: 1fr;
        }
    }
@endpush
