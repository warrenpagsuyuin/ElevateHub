@extends('layouts.app')

@section('title', 'Engagement Analytics | Elevate DGM')

@section('content')
@php
    $activeLabel = \App\Services\EngagementClassificationService::CLASSIFICATION_ACTIVE;
    $moderateLabel = \App\Services\EngagementClassificationService::CLASSIFICATION_MODERATELY_ACTIVE;
    $needsFollowUpLabel = \App\Services\EngagementClassificationService::CLASSIFICATION_NEEDS_FOLLOW_UP;
    $insufficientLabel = \App\Services\EngagementClassificationService::CLASSIFICATION_INSUFFICIENT;
    $summaryCards = [
        [
            'label' => $activeLabel,
            'filter' => $activeLabel,
            'value' => $classifications[$activeLabel] ?? 0,
            'sub' => 'Consistent ministry indicators',
            'accent' => '#166534',
        ],
        [
            'label' => $moderateLabel,
            'filter' => $moderateLabel,
            'value' => $classifications[$moderateLabel] ?? 0,
            'sub' => 'Steady participation level',
            'accent' => '#92400e',
        ],
        [
            'label' => $needsFollowUpLabel,
            'filter' => $needsFollowUpLabel,
            'value' => $classifications[$needsFollowUpLabel] ?? 0,
            'sub' => 'Prioritize pastoral check-in',
            'accent' => '#991b1b',
        ],
        [
            'label' => 'Insufficient Information',
            'filter' => 'Insufficient Information',
            'value' => $classifications[$insufficientLabel] ?? 0,
            'sub' => 'Needs profile data validation',
            'accent' => '#4b5563',
        ],
    ];

    $firstResult = $members->getCollection()->first(fn ($m) => $m->engagementResult !== null)?->engagementResult;
    $activeModelSummary = $firstResult
        ? trim(($firstResult->model_name ?: 'CART Engagement Classifier') . ($firstResult->model_version ? ' (' . $firstResult->model_version . ')' : ''))
        : 'CART Engagement Classifier';
    $hasFilters = ($search ?? '') !== '' || ($classificationFilter ?? '') !== '' || ($roleFilter ?? '') !== '';
@endphp

<div class="page-inner">
    <div class="page-header">
        <div>
            <h1>Engagement Analytics</h1>
            <p>{{ $satellite->display_name }} — Member engagement review, pastoral prioritization, and indicator summary.</p>
        </div>

        <div class="header-actions">
            <span class="model-engine-badge" title="Active classification model engine">
                Engine: {{ $activeModelSummary }}
            </span>
            @if ($canManageSatellite)
                <form method="POST" action="{{ route('satellites.engagement.classify', $satellite) }}">
                    @csrf
                    <button type="submit" class="primary-action">
                        Generate Classifications
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if (session('success'))
        <div class="feedback-banner feedback-banner-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="metric-card-grid engagement-summary-grid">
        @foreach ($summaryCards as $index => $card)
            @php
                $isActiveCard = ($classificationFilter ?? '') === $card['filter'];
                $cardUrl = $isActiveCard
                    ? route('satellites.engagement.index', array_filter([
                        'satellite' => $satellite,
                        'search' => $search ?? null,
                        'role' => $roleFilter ?? null,
                        'per_page' => $perPage ?? null,
                    ]))
                    : route('satellites.engagement.index', array_filter([
                        'satellite' => $satellite,
                        'classification' => $card['filter'],
                        'search' => $search ?? null,
                        'role' => $roleFilter ?? null,
                        'per_page' => $perPage ?? null,
                    ]));
                $formattedValue = number_format((int) $card['value']);
            @endphp
            <a
                href="{{ $cardUrl }}"
                class="stat-card elevate-stat-card stat-card-link engagement-stat-card {{ $isActiveCard ? 'is-active' : '' }}"
                data-focus-key="card-{{ $index }}"
                aria-label="{{ $card['label'] }}, {{ $formattedValue }}"
                title="Filter by {{ $card['label'] }}"
            >
                <span class="stat-label stat-card-label" title="{{ $card['label'] }}">{{ $card['label'] }}</span>
                <div class="stat-card-value-row">
                    <strong class="stat-value stat-card-value">{{ $formattedValue }}</strong>
                    <span class="card-chevron" aria-hidden="true">{{ $isActiveCard ? '✕' : '›' }}</span>
                </div>
            </a>
        @endforeach
    </div>

    <div class="ui-card filter-toolbar-card">
        <form method="GET" action="{{ route('satellites.engagement.index', $satellite) }}" class="engagement-toolbar-form">
            @if (!empty($perPage) && $perPage !== 10)
                <input type="hidden" name="per_page" value="{{ $perPage }}">
            @endif

            <div class="toolbar-search-group">
                <label for="engagement-search" class="sr-only">Search member or campus</label>
                <input
                    id="engagement-search"
                    type="search"
                    name="search"
                    value="{{ $search ?? '' }}"
                    placeholder="Search by member name or campus/school..."
                    class="toolbar-input"
                >
            </div>

            <div class="toolbar-select-group">
                <label for="engagement-classification" class="sr-only">Filter by Classification</label>
                <select id="engagement-classification" name="classification" class="toolbar-select" onchange="this.form.submit()">
                    <option value="">All Classifications</option>
                    <option value="{{ $needsFollowUpLabel }}" @selected(($classificationFilter ?? '') === $needsFollowUpLabel)>{{ $needsFollowUpLabel }}</option>
                    <option value="{{ $moderateLabel }}" @selected(($classificationFilter ?? '') === $moderateLabel)>{{ $moderateLabel }}</option>
                    <option value="{{ $activeLabel }}" @selected(($classificationFilter ?? '') === $activeLabel)>{{ $activeLabel }}</option>
                    <option value="Insufficient Information" @selected(($classificationFilter ?? '') === 'Insufficient Information' || ($classificationFilter ?? '') === $insufficientLabel)>Insufficient Information</option>
                </select>

                <label for="engagement-role" class="sr-only">Filter by Role</label>
                <select id="engagement-role" name="role" class="toolbar-select" onchange="this.form.submit()">
                    <option value="">All Roles</option>
                    <option value="Dgroup Leader" @selected(($roleFilter ?? '') === 'Dgroup Leader')>Dgroup Leader</option>
                    <option value="Dgroup Member" @selected(($roleFilter ?? '') === 'Dgroup Member')>Dgroup Member</option>
                    <option value="Member" @selected(($roleFilter ?? '') === 'Member')>Member</option>
                </select>

                <button type="submit" class="toolbar-btn-apply">Search</button>

                @if ($hasFilters)
                    <a href="{{ route('satellites.engagement.index', $satellite) }}" class="toolbar-btn-clear">Reset</a>
                @endif
            </div>
        </form>
    </div>

    <div class="table-card">
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Member &amp; Campus</th>
                        <th>Classification</th>
                        <th>Role</th>
                        <th>Key Engagement Indicators</th>
                        <th>Last Updated</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        @php
                            $result = $member->engagementResult;
                            $classification = $result?->classification;
                            $displayClassification = $result?->classification_label ?? 'Unclassified';
                            $confidence = $result?->confidence;
                            $normalizedConfidence = $confidence !== null && $confidence > 0
                                ? round($confidence > 1 ? $confidence : $confidence * 100) . '%'
                                : null;
                            $usedFeatures = is_array($result?->used_features) ? $result->used_features : [];
                            $badge = match ($classification) {
                                'High', $activeLabel => ['bg' => '#dcfce7', 'text' => '#166534'],
                                'Medium', $moderateLabel => ['bg' => '#fef3c7', 'text' => '#92400e'],
                                'Low', $needsFollowUpLabel => ['bg' => '#fee2e2', 'text' => '#991b1b'],
                                'Inactive', $insufficientLabel => ['bg' => '#e5e7eb', 'text' => '#4b5563'],
                                default => ['bg' => '#f3f4f6', 'text' => '#374151'],
                            };
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $member->display_name }}</strong>
                                <span>{{ $member->campus_school ?: 'Campus not recorded' }}</span>
                            </td>
                            <td>
                                <span class="classification-pill" style="background: {{ $badge['bg'] }}; color: {{ $badge['text'] }};">
                                    {{ $displayClassification }}
                                </span>
                                @if ($normalizedConfidence)
                                    <span class="confidence-subtext">Confidence: {{ $normalizedConfidence }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="role-badge">{{ $member->display_role }}</span>
                            </td>
                            <td class="indicators-cell">
                                @if (!empty($usedFeatures))
                                    <div class="indicator-pills">
                                        @foreach (array_slice($usedFeatures, 0, 3) as $feature)
                                            <span class="indicator-pill">
                                                <strong>{{ $feature['label'] ?? 'Indicator' }}:</strong> {{ $feature['value'] ?? 'N/A' }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="indicator-fallback">
                                        <strong>Disciples:</strong> {{ $member->active_disciples ?? 0 }} &middot;
                                        <strong>Weekly Dgroups:</strong> {{ $member->weekly_dgroups_led ?? 0 }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $result?->generated_at?->format('M d, Y') ?? $result?->updated_at?->format('M d, Y') ?? 'Not classified' }}</strong>
                                @if ($result?->generated_at || $result?->updated_at)
                                    <span>{{ ($result?->generated_at ?? $result?->updated_at)?->format('g:i A') }}</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('satellites.engagement.show', [$satellite, $member]) }}" class="table-action-btn">View Result</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-cell">
                                @if ($hasFilters)
                                    No members matched your current filter criteria. <a href="{{ route('satellites.engagement.index', $satellite) }}">Clear filters</a>
                                @else
                                    No members found. <a href="{{ route('satellites.members', $satellite) }}">{{ $canManageSatellite ? 'Add members' : 'View members' }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.table-pagination', ['paginator' => $members, 'pageSizes' => $pageSizes, 'currentPageSize' => $perPage, 'recordLabel' => 'members', 'tableId' => 'satellite-engagement'])
    </div>
</div>
@endsection

@push('styles')
    .page-inner {
        width: 100%;
        max-width: 1600px;
        margin: 0 auto;
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .model-engine-badge {
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 999px;
        color: #4b5563;
        font-size: 12px;
        font-weight: 800;
        padding: 7px 12px;
        white-space: nowrap;
    }

    .primary-action {
        border: 1px solid #450d07;
        border-radius: 10px;
        background: #1A1A1A;
        color: #fff;
        cursor: pointer;
        font-weight: 900;
        min-height: 44px;
        padding: 0 18px;
        transition: background 0.15s ease;
    }

    .primary-action:hover {
        background: #2d120e;
    }

    .feedback-banner {
        border-radius: 12px;
        font-weight: 700;
        margin-bottom: 18px;
        padding: 14px 16px;
    }

    .feedback-banner-success {
        background: #dcfce7;
        border-left: 4px solid #22c55e;
        color: #166534;
    }

    .engagement-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .engagement-stat-card {
        position: relative;
        text-decoration: none;
        cursor: pointer;
    }

    .engagement-stat-card:hover {
        border-color: var(--stat-card-border-hover, #3A3A3A) !important;
        border-top-color: var(--stat-card-accent, #450d07) !important;
    }

    .engagement-stat-card.is-active {
        border-color: var(--stat-card-focus, #FF5A4D) !important;
        border-top-color: var(--stat-card-accent, #450d07) !important;
    }

    .engagement-stat-card .stat-card-value-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        width: 100%;
        min-width: 0;
    }

    .engagement-stat-card .card-chevron {
        flex-shrink: 0;
        color: var(--stat-card-chevron, #B8B8B8);
        font-size: 20px;
        line-height: 1;
        transition: color 0.15s ease;
    }

    .engagement-stat-card:hover .card-chevron,
    .engagement-stat-card.is-active .card-chevron {
        color: var(--stat-card-chevron-hover, #F2F2F2);
    }

    .filter-toolbar-card {
        padding: 16px;
        margin-bottom: 18px;
    }

    .engagement-toolbar-form {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .toolbar-search-group {
        flex: 1 1 280px;
    }

    .toolbar-input {
        width: 100%;
        min-height: 42px;
        border: 1px solid #d0d5dd;
        border-radius: 10px;
        padding: 0 14px;
        font-size: 14px;
        font-weight: 600;
        color: #111827;
        background: #fff;
    }

    .toolbar-select-group {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }

    .toolbar-select {
        min-height: 42px;
        border: 1px solid #d0d5dd;
        border-radius: 10px;
        padding: 0 12px;
        font-size: 13px;
        font-weight: 700;
        color: #111827;
        background: #fff;
    }

    .toolbar-btn-apply {
        min-height: 42px;
        padding: 0 16px;
        border-radius: 10px;
        border: 1px solid #450d07;
        background: #1A1A1A;
        color: #fff;
        font-size: 13px;
        font-weight: 900;
        cursor: pointer;
    }

    .toolbar-btn-clear {
        min-height: 42px;
        padding: 0 14px;
        border-radius: 10px;
        border: 1px solid #d0d5dd;
        background: #fff;
        color: #374151;
        font-size: 13px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        text-decoration: none;
    }

    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        border: 0;
    }

    .data-table th,
    .data-table td {
        border-bottom: 1px solid #eef0f3;
        padding: 16px;
        text-align: left;
        vertical-align: middle;
    }

    .data-table th {
        background: #f9fafb;
        color: #111;
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .data-table td span {
        color: #667085;
        display: block;
        font-size: 13px;
        margin-top: 4px;
    }

    .classification-pill {
        border-radius: 999px;
        display: inline-block !important;
        font-size: 12px !important;
        font-weight: 900;
        line-height: 1.25;
        margin-top: 0 !important;
        padding: 6px 11px;
    }

    .confidence-subtext {
        font-size: 11px !important;
        font-weight: 700;
        color: #6b7280 !important;
        margin-top: 4px !important;
    }

    .role-badge {
        display: inline-block !important;
        background: #f3f4f6;
        color: #1f2937 !important;
        border-radius: 8px;
        padding: 5px 10px;
        font-size: 12px !important;
        font-weight: 800;
        margin-top: 0 !important;
    }

    .indicator-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .indicator-pill {
        display: inline-block !important;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 4px 8px;
        font-size: 12px !important;
        color: #374151 !important;
        margin-top: 0 !important;
    }

    .indicator-fallback {
        font-size: 12px !important;
        color: #4b5563 !important;
        margin-top: 0 !important;
    }

    .table-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #1A1A1A;
        border: 1px solid #450d07;
        color: #ffffff;
        border-radius: 8px;
        padding: 7px 12px;
        font-size: 12px;
        font-weight: 900;
        text-decoration: none;
        white-space: nowrap;
        transition: background 0.15s ease;
    }

    .table-action-btn:hover {
        background: #2d120e;
    }

    .empty-cell {
        color: #667085;
        padding: 32px !important;
        text-align: center !important;
    }

    .empty-cell a {
        color: #ef3b24;
        font-weight: 900;
        text-decoration: none;
    }

    @media (max-width: 1100px) {
        .engagement-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .engagement-summary-grid {
            grid-template-columns: 1fr;
        }
    }
@endpush
