@extends('layouts.app')

@section('title', 'Engagement Analytics | Elevate DGM')

@section('content')
@php
    $activeLabel = \App\Services\EngagementClassificationService::CLASSIFICATION_ACTIVE;
    $moderateLabel = \App\Services\EngagementClassificationService::CLASSIFICATION_MODERATELY_ACTIVE;
    $needsFollowUpLabel = \App\Services\EngagementClassificationService::CLASSIFICATION_NEEDS_FOLLOW_UP;
    $insufficientLabel = \App\Services\EngagementClassificationService::CLASSIFICATION_INSUFFICIENT;
    $summaryCards = [
        ['label' => $activeLabel, 'value' => $classifications[$activeLabel] ?? 0],
        ['label' => $moderateLabel, 'value' => $classifications[$moderateLabel] ?? 0],
        ['label' => $needsFollowUpLabel, 'value' => $classifications[$needsFollowUpLabel] ?? 0],
        ['label' => 'Insufficient Information', 'value' => $classifications[$insufficientLabel] ?? 0],
    ];
@endphp

<div class="page-inner">
    <div class="page-header">
        <div>
            <h1>Engagement Analytics</h1>
            <p>{{ $satellite->display_name }} - CART-ready member engagement classification and review.</p>
        </div>

        @if ($canManageSatellite)
            <form method="POST" action="{{ route('satellites.engagement.classify', $satellite) }}">
                @csrf
                <button type="submit" class="primary-action">
                    Generate Classifications
                </button>
            </form>
        @endif
    </div>

    @if (session('success'))
        <div class="feedback-banner feedback-banner-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="metric-card-grid engagement-summary-grid">
        @foreach ($summaryCards as $card)
            @include('components.metric-card', ['label' => $card['label'], 'value' => $card['value']])
        @endforeach
    </div>

    <div class="table-card">
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Classification</th>
                        <th>Confidence</th>
                        <th>Model</th>
                        <th>Generated</th>
                        <th>Role</th>
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
                            </td>
                            <td>{{ $confidence !== null && $confidence > 0 ? round($confidence * 100) . '%' : 'N/A' }}</td>
                            <td>
                                <strong>{{ $result?->model_name ?: 'Not generated' }}</strong>
                                <span>{{ $result?->model_version ?: 'No version' }}</span>
                            </td>
                            <td>{{ $result?->generated_at?->format('M d, Y g:i A') ?? $result?->updated_at?->format('M d, Y g:i A') ?? 'N/A' }}</td>
                            <td>{{ $member->display_role }}</td>
                            <td>
                                <a href="{{ route('satellites.engagement.show', [$satellite, $member]) }}" class="table-action">View Result</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-cell">
                                No members found. <a href="{{ route('satellites.members', $satellite) }}">{{ $canManageSatellite ? 'Add members' : 'View members' }}</a>
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

    .primary-action {
        border: 0;
        border-radius: 10px;
        background: #ef3b24;
        color: #fff;
        cursor: pointer;
        font-weight: 900;
        min-height: 44px;
        padding: 0 18px;
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
        font-size: 13px;
        font-weight: 900;
        text-transform: uppercase;
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
        padding: 7px 10px;
    }

    .table-action {
        color: var(--ui-text);
        font-weight: 900;
        text-decoration: none;
        white-space: nowrap;
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

@endpush
