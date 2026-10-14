@extends('layouts.app')

@section('title', 'Follow-Up History | Elevate DGM')

@push('styles')
    .app-main:has(.follow-up-history) { padding: clamp(16px, 2vw, 32px) !important; }
    .follow-up-history { min-width: 0; }
    .follow-up-history .page-header { flex-wrap: wrap; }
    .follow-up-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
    .follow-up-stats > div { min-width: 0; overflow-wrap: anywhere; }
    .follow-up-filters { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)) auto; gap: 14px; align-items: end; margin-bottom: 18px; padding: 18px; background: var(--ui-surface); border-radius: 10px; box-shadow: 0 12px 26px rgba(17, 24, 39, 0.10); }
    .follow-up-filters label, .follow-up-filters select { min-width: 0; width: 100%; }
    .follow-up-filters select { min-height: var(--ui-target); }
    .follow-up-filter-actions { display: flex; gap: 10px; justify-content: flex-end; }
    .follow-up-filter-actions > * { display: inline-flex; align-items: center; justify-content: center; min-height: var(--ui-target); white-space: nowrap; }
    .follow-up-history .table-scroll { overflow-x: auto; }
    .follow-up-table { min-width: 900px; table-layout: fixed; }
    .follow-up-table th, .follow-up-table td { overflow-wrap: anywhere; vertical-align: top; }
    .follow-up-table th:nth-child(1) { width: 17%; }
    .follow-up-table th:nth-child(2) { width: 13%; }
    .follow-up-table th:nth-child(3) { width: 12%; }
    .follow-up-table th:nth-child(4) { width: 13%; }
    .follow-up-table th:nth-child(5) { width: 20%; }
    .follow-up-table th:nth-child(6) { width: 25%; }
    .follow-up-table td:nth-child(4) > span, .follow-up-table td:nth-child(5) > span { white-space: nowrap; }
    @media (max-width: 1023px) {
        .follow-up-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .follow-up-filters { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .follow-up-filter-actions { grid-column: 1 / -1; }
    }
    @media (max-width: 767px) {
        .follow-up-table th:first-child, .follow-up-table td:first-child:not([colspan]) { position: sticky; left: 0; z-index: 1; background: var(--ui-surface); box-shadow: 1px 0 0 var(--ui-divider); }
        .follow-up-table th:first-child { background: var(--ui-error-surface); color: var(--ui-text); }
    }
    @media (max-width: 639px) {
        .follow-up-filters { grid-template-columns: minmax(0, 1fr); }
        .follow-up-filter-actions { flex-direction: column; }
        .follow-up-filter-actions > * { width: 100%; }
    }
    @media (max-width: 479px) {
        .follow-up-stats { grid-template-columns: minmax(0, 1fr); }
    }
@endpush

@section('content')
<div class="follow-up-history">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 26px;">
        <div>
            <h1 style="margin: 0; font-size: 32px; font-weight: 900;">Follow-Up History</h1>
            <p style="margin: 8px 0 0; color: #6b7280; font-weight: 600;">Track follow-up notes, status, and sentiment for {{ $satellite->display_name }}.</p>
        </div>
        @if ($canManageSatellite)
        <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end;">
            <form method="POST" action="{{ route('satellites.follow-ups.analyze-sentiment', $satellite) }}" style="margin: 0;">
                @csrf
                <button type="submit" style="display: inline-block; padding: 12px 18px; background: #111; color: #fff; border: none; text-decoration: none; border-radius: 8px; font-weight: 800; cursor: pointer;">Analyze Sentiment</button>
            </form>
            <a href="{{ route('satellites.follow-ups.create', $satellite) }}" style="display: inline-block; padding: 12px 24px; background: #e33c24; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 800;">New Follow-Up</a>
        </div>
        @endif
    </div>

    @if (session('status'))
        <div role="status" style="padding: 12px 16px; background: #dcfce7; color: #166534; border-radius: 8px; margin-bottom: 16px; border-left: 4px solid #16a34a;">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" tabindex="-1" data-validation-summary style="padding: 16px; background: #fee2e2; color: #7f1d1d; border-radius: 8px; margin-bottom: 16px;">
            <strong>Please correct the follow-up fields below.</strong>
            <ul>
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="follow-up-stats">
        <div style="background: #e33c24; color: #fff; border-radius: 10px; padding: 16px 18px; box-shadow: 0 12px 26px rgba(17, 24, 39, 0.10);">
            <div style="font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.08em;">Positive Notes</div>
            <div style="font-size: 30px; font-weight: 900; margin-top: 8px;">{{ $sentimentSummary['counts']['Positive'] ?? 0 }}</div>
        </div>
        <div style="background: #111; color: #fff; border-radius: 10px; padding: 16px 18px; box-shadow: 0 12px 26px rgba(17, 24, 39, 0.10);">
            <div style="font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.08em;">Neutral Notes</div>
            <div style="font-size: 30px; font-weight: 900; margin-top: 8px;">{{ $sentimentSummary['counts']['Neutral'] ?? 0 }}</div>
        </div>
        <div style="background: #fff; color: #111; border-radius: 10px; padding: 16px 18px; box-shadow: 0 12px 26px rgba(17, 24, 39, 0.10); border-left: 4px solid #b42318;">
            <div style="font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280;">Negative Notes</div>
            <div style="font-size: 30px; font-weight: 900; margin-top: 8px; color: #b42318;">{{ $sentimentSummary['counts']['Negative'] ?? 0 }}</div>
        </div>
        <div style="background: #fff; color: #111; border-radius: 10px; padding: 16px 18px; box-shadow: 0 12px 26px rgba(17, 24, 39, 0.10);">
            <div style="font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280;">Unanalyzed</div>
            <div style="font-size: 30px; font-weight: 900; margin-top: 8px;">{{ $sentimentSummary['unanalyzed'] ?? 0 }}</div>
        </div>
    </div>

    <form method="GET" action="{{ route('satellites.follow-ups.index', $satellite) }}" class="follow-up-filters">
        <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 800; color: #374151;">
            Status
            <select name="status" style="padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" {{ $filters['status'] === $status ? 'selected' : '' }}>{{ $status }}</option>
                @endforeach
            </select>
        </label>
        <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 800; color: #374151;">
            Method
            <select name="method" style="padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
                <option value="">All methods</option>
                @foreach ($methods as $method)
                    <option value="{{ $method }}" {{ $filters['method'] === $method ? 'selected' : '' }}>{{ $method }}</option>
                @endforeach
            </select>
        </label>
        <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 800; color: #374151;">
            Sentiment
            <select name="sentiment" style="padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;">
                <option value="">All sentiments</option>
                @foreach ($sentiments as $sentiment)
                    <option value="{{ $sentiment }}" {{ $filters['sentiment'] === $sentiment ? 'selected' : '' }}>{{ $sentiment }}</option>
                @endforeach
            </select>
        </label>
        <div class="follow-up-filter-actions">
            <button type="submit" style="padding: 10px 18px; background: #e33c24; color: #fff; border: none; border-radius: 8px; font-weight: 800; cursor: pointer;">Apply Filters</button>
            <a href="{{ route('satellites.follow-ups.index', $satellite) }}" style="padding: 10px 18px; background: #f3f4f6; color: #111; text-decoration: none; border-radius: 8px; font-weight: 800;">Clear Filters</a>
        </div>
    </form>

    <div class="table-card" style="background: #fff; border-radius: 14px; box-shadow: 0 16px 34px rgba(17, 24, 39, 0.12); overflow: hidden;">
        <div class="table-scroll" role="region" aria-label="Follow-up history table" tabindex="0">
        <table class="follow-up-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #111; color: #fff;">
                    <th style="padding: 16px; text-align: left; font-weight: 900; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid #e5e7eb;">Target</th>
                    <th style="padding: 16px; text-align: left; font-weight: 900; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid #e5e7eb;">Date</th>
                    <th style="padding: 16px; text-align: left; font-weight: 900; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid #e5e7eb;">Method</th>
                    <th style="padding: 16px; text-align: left; font-weight: 900; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid #e5e7eb;">Status</th>
                    <th style="padding: 16px; text-align: left; font-weight: 900; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid #e5e7eb;">Sentiment</th>
                    <th style="padding: 16px; text-align: left; font-weight: 900; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid #e5e7eb;">Notes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($followUps as $followUp)
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 16px;">
                            @if ($followUp->seeker)
                                <strong style="color: #e33c24;">{{ $followUp->seeker->name }}</strong><br>
                                <small style="color: #6b7280;">Seeker</small>
                            @elseif ($followUp->member)
                                <strong style="color: #e33c24;">{{ $followUp->member->display_name }}</strong><br>
                                <small style="color: #6b7280;">Member</small>
                            @else
                                <small style="color: #999;">—</small>
                            @endif
                        </td>
                        <td style="padding: 16px;">
                            {{ $followUp->follow_up_date?->format('M d, Y') ?? '—' }}
                        </td>
                        <td style="padding: 16px;">
                            {{ $followUp->method ?? '—' }}
                        </td>
                        <td style="padding: 16px;">
                            <span style="display: inline-block; padding: 4px 10px; background: {{ $followUp->status === 'Completed' ? '#dcfce7' : ($followUp->status === 'Cancelled' ? '#fee2e2' : '#fef3c7') }}; color: {{ $followUp->status === 'Completed' ? '#166534' : ($followUp->status === 'Cancelled' ? '#7f1d1d' : '#92400e') }}; border-radius: 4px; font-size: 12px; font-weight: 700;">{{ $followUp->status }}</span>
                        </td>
                        <td style="padding: 16px; min-width: 190px;">
                            @php
                                $sentiment = $followUp->sentiment_label;
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
                                $decisionPath = $followUp->sentiment_metrics['decision_path'] ?? [];
                            @endphp
                            <span style="display: inline-block; padding: 4px 10px; background: {{ $sentimentBg }}; color: {{ $sentimentColor }}; border-radius: 4px; font-size: 12px; font-weight: 800;">{{ $sentiment ?? 'Unanalyzed' }}</span>
                            @if ($followUp->sentiment_confidence !== null)
                                <small style="display: block; margin-top: 6px; color: #6b7280;">{{ number_format($followUp->sentiment_confidence * 100, 0) }}% confidence</small>
                            @endif
                            @if ($decisionPath)
                                <details style="margin-top: 8px; color: #4b5563; font-size: 12px;">
                                    <summary style="cursor: pointer; font-weight: 800; color: #e33c24;">Why this sentiment?</summary>
                                    <ul style="margin: 8px 0 0; padding-left: 18px;">
                                        @foreach ($decisionPath as $step)
                                            <li style="margin-bottom: 4px;">{{ $step }}</li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        </td>
                        <td style="padding: 16px; color: #666; font-size: 14px;">
                            {{ $followUp->notes ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 32px; text-align: center; color: #999;">No follow-ups yet. @if ($canManageSatellite)<a href="{{ route('satellites.follow-ups.create', $satellite) }}" style="color: #e33c24; text-decoration: none; font-weight: 700;">Create Follow-Up</a>@endif</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @include('partials.table-pagination', ['paginator' => $followUps, 'pageSizes' => $pageSizes, 'currentPageSize' => $perPage, 'recordLabel' => 'follow-ups', 'tableId' => 'satellite-follow-ups'])
    </div>
</div>
@endsection
