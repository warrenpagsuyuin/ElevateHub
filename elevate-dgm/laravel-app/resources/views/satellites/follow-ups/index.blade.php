@extends('layouts.app')

@section('title', 'Follow-Up History | Elevate DGM')

@push('styles')
    .app-main:has(.follow-up-history) { padding: clamp(16px, 2vw, 32px) !important; }
    .follow-up-history { min-width: 0; max-width: 1600px; margin: 0 auto; }
    .follow-up-history .page-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 22px; flex-wrap: wrap; }
    .follow-up-header-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; align-items: center; }

    .follow-up-stat-card { position: relative; text-decoration: none; cursor: pointer; }
    .follow-up-stat-card:hover { border-color: var(--stat-card-border-hover, #3A3A3A) !important; border-top-color: var(--stat-card-accent, #450d07) !important; }
    .follow-up-stat-card.is-active { border-color: var(--stat-card-focus, #FF5A4D) !important; border-top-color: var(--stat-card-accent, #450d07) !important; }
    .follow-up-stat-card .stat-card-value-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; min-width: 0; }
    .follow-up-stat-card .card-chevron { flex-shrink: 0; color: var(--stat-card-chevron, #B8B8B8); font-size: 20px; line-height: 1; transition: color 0.15s ease; }
    .follow-up-stat-card:hover .card-chevron, .follow-up-stat-card.is-active .card-chevron { color: var(--stat-card-chevron-hover, #F2F2F2); }

    .follow-up-filters { display: flex; flex-direction: column; gap: 12px; margin-bottom: 18px; padding: 16px 18px; background: var(--ui-surface); border: 1px solid #e5e7eb; border-radius: 14px; box-shadow: 0 8px 24px rgba(17, 24, 39, 0.06); }
    .follow-up-filter-row-top { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .follow-up-search-wrap { position: relative; flex: 1 1 280px; min-width: 0; }
    .follow-up-search-wrap input { width: 100%; min-height: 42px; padding: 0 14px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; font-weight: 600; color: #111827; background: #fff; }
    .follow-up-filter-row-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)) auto; gap: 12px; align-items: end; }
    .follow-up-filters label, .follow-up-filters select { min-width: 0; width: 100%; }
    .follow-up-filters select { min-height: 42px; padding: 0 12px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 13px; font-weight: 700; color: #111827; background: #fff; }
    .follow-up-filter-actions { display: flex; gap: 10px; justify-content: flex-end; }
    .follow-up-filter-actions > * { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; white-space: nowrap; }

    .follow-up-history .table-scroll { overflow-x: auto; }
    .follow-up-table { width: 100%; min-width: 960px; table-layout: fixed; border-collapse: collapse; }
    .follow-up-table .follow-up-target { color: var(--ui-text); font-weight: 800; }
    .follow-up-table th, .follow-up-table td { overflow-wrap: anywhere; vertical-align: middle; padding: 14px 16px; }
    .follow-up-table th:nth-child(1) { width: 19%; }
    .follow-up-table th:nth-child(2) { width: 13%; }
    .follow-up-table th:nth-child(3) { width: 12%; }
    .follow-up-table th:nth-child(4) { width: 13%; }
    .follow-up-table th:nth-child(5) { width: 17%; }
    .follow-up-table th:nth-child(6) { width: 26%; }
    .follow-up-table td:nth-child(4) > span, .follow-up-table td:nth-child(5) > span { white-space: nowrap; }

    .notes-cell-wrap { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
    .notes-text { flex: 1 1 auto; min-width: 0; color: #374151; font-size: 13.5px; line-height: 1.45; }
    .notes-system-badge { display: inline-block; padding: 3px 8px; border-radius: 6px; background: #f3f4f6; color: #6b7280; font-size: 11.5px; font-weight: 700; }
    .row-update-btn { flex-shrink: 0; display: inline-flex; align-items: center; gap: 5px; padding: 6px 11px; border-radius: 8px; border: 1px solid #d1d5db; background: #fff; color: #111827; font-size: 12px; font-weight: 800; cursor: pointer; transition: all 0.15s ease; }
    .row-update-btn:hover { border-color: #450d07; background: #1A1A1A; color: #fff; }
    .row-update-btn.is-pending { background: #1A1A1A; border-color: #450d07; color: #fff; }
    .row-update-btn.is-pending:hover { background: #2d120e; }

    .followup-dialog { border: 0; border-radius: 16px; padding: 0; width: min(540px, calc(100vw - 32px)); box-shadow: 0 24px 48px rgba(15, 23, 42, 0.22); background: #fff; color: #111827; }
    .followup-dialog::backdrop { background: rgba(17, 24, 39, 0.55); }
    .followup-dialog-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 22px; background: #1A1A1A; color: #fff; border-bottom: 2px solid #450d07; }
    .followup-dialog-header h2 { margin: 0; font-size: 18px; font-weight: 900; color: #fff; }
    .followup-dialog-close { background: transparent; border: 0; color: #d1d5db; font-size: 22px; line-height: 1; cursor: pointer; padding: 4px 8px; border-radius: 6px; }
    .followup-dialog-close:hover { color: #fff; background: rgba(255,255,255,0.1); }
    .followup-dialog-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 16px; }
    .followup-dialog-field { display: flex; flex-direction: column; gap: 6px; }
    .followup-dialog-field label { font-size: 12.5px; font-weight: 800; color: #374151; text-transform: uppercase; letter-spacing: 0.03em; }
    .followup-dialog-field input, .followup-dialog-field select, .followup-dialog-field textarea { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; font-weight: 600; color: #111827; background: #fff; box-sizing: border-box; }
    .followup-dialog-row-2col { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .followup-dialog-footer { display: flex; align-items: center; justify-content: flex-end; gap: 10px; padding-top: 8px; border-top: 1px solid #eef0f3; }

    @media (max-width: 1023px) {
        .follow-up-filter-row-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .follow-up-filter-actions { grid-column: 1 / -1; }
    }
    @media (max-width: 767px) {
        .follow-up-table th:first-child, .follow-up-table td:first-child:not([colspan]) { position: sticky; left: 0; z-index: 1; background: var(--ui-surface); box-shadow: 1px 0 0 var(--ui-divider); }
        .follow-up-table th:first-child { background: var(--ui-error-surface); color: var(--ui-text); }
    }
    @media (max-width: 639px) {
        .follow-up-filter-row-grid, .followup-dialog-row-2col { grid-template-columns: minmax(0, 1fr); }
        .follow-up-filter-actions { flex-direction: column; }
        .follow-up-filter-actions > * { width: 100%; }
    }
@endpush

@section('content')
@php
    $summaryCards = [
        [
            'label' => 'Positive Notes',
            'sentiment' => 'Positive',
            'value' => $sentimentSummary['counts']['Positive'] ?? 0,
        ],
        [
            'label' => 'Neutral Notes',
            'sentiment' => 'Neutral',
            'value' => $sentimentSummary['counts']['Neutral'] ?? 0,
        ],
        [
            'label' => 'Negative Notes',
            'sentiment' => 'Negative',
            'value' => $sentimentSummary['counts']['Negative'] ?? 0,
        ],
        [
            'label' => 'Unanalyzed',
            'sentiment' => 'Unanalyzed',
            'value' => $sentimentSummary['unanalyzed'] ?? 0,
        ],
    ];
    $hasActiveFilters = ($filters['search'] ?? '') !== ''
        || ($filters['target_type'] ?? '') !== ''
        || ($filters['status'] ?? '') !== ''
        || ($filters['method'] ?? '') !== ''
        || ($filters['sentiment'] ?? '') !== '';
@endphp

<div class="follow-up-history">
    <div class="page-header">
        <div>
            <h1 style="margin: 0; font-size: 32px; font-weight: 900;">Follow-Up History</h1>
            <p style="margin: 8px 0 0; color: #6b7280; font-weight: 600;">Track follow-up notes, status, and sentiment for {{ $satellite->display_name }}.</p>
        </div>
        @if ($canManageSatellite)
        <div class="follow-up-header-actions">
            <form method="POST" action="{{ route('satellites.follow-ups.analyze-sentiment', $satellite) }}" style="margin: 0;">
                @csrf
                <button type="submit" class="btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; font-weight: 800; cursor: pointer;">
                    Analyze Sentiment
                </button>
            </form>
            <a href="{{ route('satellites.follow-ups.create', $satellite) }}" class="btn-primary" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; text-decoration: none; border-radius: 10px; font-weight: 800;">
                + New Follow-Up
            </a>
        </div>
        @endif
    </div>

    @if (session('status'))
        <div role="status" style="padding: 12px 16px; background: #dcfce7; color: #166534; border-radius: 8px; margin-bottom: 16px; border-left: 4px solid #16a34a; font-weight: 700;">
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

    <div class="follow-up-stats metric-card-grid">
        @foreach ($summaryCards as $index => $card)
            @php
                $isActiveCard = ($filters['sentiment'] ?? '') === $card['sentiment'];
                $cardUrl = $isActiveCard
                    ? route('satellites.follow-ups.index', array_filter([
                        'satellite' => $satellite,
                        'search' => $filters['search'] ?? null,
                        'target_type' => $filters['target_type'] ?? null,
                        'status' => $filters['status'] ?? null,
                        'method' => $filters['method'] ?? null,
                        'per_page' => $perPage !== 10 ? $perPage : null,
                    ]))
                    : route('satellites.follow-ups.index', array_filter([
                        'satellite' => $satellite,
                        'sentiment' => $card['sentiment'],
                        'search' => $filters['search'] ?? null,
                        'target_type' => $filters['target_type'] ?? null,
                        'status' => $filters['status'] ?? null,
                        'method' => $filters['method'] ?? null,
                        'per_page' => $perPage !== 10 ? $perPage : null,
                    ]));
                $formattedValue = number_format((int) $card['value']);
            @endphp
            <a
                href="{{ $cardUrl }}"
                class="stat-card elevate-stat-card stat-card-link follow-up-stat-card {{ $isActiveCard ? 'is-active' : '' }}"
                data-focus-key="followup-card-{{ $index }}"
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

    <form method="GET" action="{{ route('satellites.follow-ups.index', $satellite) }}" class="follow-up-filters">
        @if ($perPage !== 10)
            <input type="hidden" name="per_page" value="{{ $perPage }}">
        @endif
        <div class="follow-up-filter-row-top">
            <div class="follow-up-search-wrap">
                <input
                    type="search"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Search by seeker, member, leader, or notes..."
                    aria-label="Search follow-ups"
                >
            </div>
        </div>
        <div class="follow-up-filter-row-grid">
            <label style="display: flex; flex-direction: column; gap: 6px; font-size: 12px; font-weight: 800; color: #374151; text-transform: uppercase;">
                Target Type
                <select name="target_type" onchange="this.form.submit()">
                    <option value="">All targets</option>
                    <option value="seeker" @selected(($filters['target_type'] ?? '') === 'seeker')>Seekers</option>
                    <option value="member" @selected(($filters['target_type'] ?? '') === 'member')>Members</option>
                </select>
            </label>
            <label style="display: flex; flex-direction: column; gap: 6px; font-size: 12px; font-weight: 800; color: #374151; text-transform: uppercase;">
                Status
                <select name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
            <label style="display: flex; flex-direction: column; gap: 6px; font-size: 12px; font-weight: 800; color: #374151; text-transform: uppercase;">
                Method
                <select name="method" onchange="this.form.submit()">
                    <option value="">All methods</option>
                    @foreach ($methods as $method)
                        <option value="{{ $method }}" {{ ($filters['method'] ?? '') === $method ? 'selected' : '' }}>{{ $method }}</option>
                    @endforeach
                </select>
            </label>
            <label style="display: flex; flex-direction: column; gap: 6px; font-size: 12px; font-weight: 800; color: #374151; text-transform: uppercase;">
                Sentiment
                <select name="sentiment" onchange="this.form.submit()">
                    <option value="">All sentiments</option>
                    @foreach ($sentiments as $sentiment)
                        <option value="{{ $sentiment }}" {{ ($filters['sentiment'] ?? '') === $sentiment ? 'selected' : '' }}>{{ $sentiment }}</option>
                    @endforeach
                    <option value="Unanalyzed" {{ ($filters['sentiment'] ?? '') === 'Unanalyzed' ? 'selected' : '' }}>Unanalyzed</option>
                </select>
            </label>
            <div class="follow-up-filter-actions">
                <button type="submit" class="btn-primary" style="padding: 0 18px; border-radius: 10px; font-weight: 800; cursor: pointer;">Apply Filters</button>
                <a href="{{ route('satellites.follow-ups.index', $satellite) }}" class="btn-secondary" style="padding: 0 16px; text-decoration: none; border-radius: 10px; font-weight: 800;">Clear Filters</a>
            </div>
        </div>
    </form>

    <div class="table-card" style="background: #fff; border-radius: 14px; box-shadow: 0 16px 34px rgba(17, 24, 39, 0.12); overflow: hidden;">
        <div class="table-scroll" role="region" aria-label="Follow-up history table" tabindex="0">
        <table class="follow-up-table">
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
                    @php
                        $targetName = $followUp->seeker?->name ?? $followUp->member?->display_name ?? ($followUp->leader ? $followUp->leader->display_name : 'Unassigned Target');
                        $targetType = $followUp->seeker ? 'Seeker' : ($followUp->member ? 'Member' : ($followUp->leader ? 'Leader Log' : 'General'));
                        $isSeedNote = is_string($followUp->notes) && str_starts_with(trim($followUp->notes), 'seed:');
                        $cleanNotes = $isSeedNote ? '' : (string) ($followUp->notes ?? '');
                    @endphp
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 16px;">
                            @if ($followUp->seeker)
                                <strong class="follow-up-target">{{ $followUp->seeker->name }}</strong><br>
                                <small style="color: #6b7280; font-weight: 600;">Seeker</small>
                            @elseif ($followUp->member)
                                <strong class="follow-up-target">{{ $followUp->member->display_name }}</strong><br>
                                <small style="color: #6b7280; font-weight: 600;">Member</small>
                            @elseif ($followUp->leader)
                                <strong class="follow-up-target">{{ $followUp->leader->display_name }}</strong><br>
                                <small style="color: #6b7280; font-weight: 600;">Leader Log</small>
                            @else
                                <small style="color: #999;">—</small>
                            @endif
                        </td>
                        <td style="padding: 16px; font-weight: 600;">
                            {{ $followUp->follow_up_date?->format('M d, Y') ?? '—' }}
                        </td>
                        <td style="padding: 16px; font-weight: 600;">
                            {{ $followUp->method ?? '—' }}
                        </td>
                        <td style="padding: 16px;">
                            <span style="display: inline-block; padding: 4px 10px; background: {{ $followUp->status === 'Completed' ? '#dcfce7' : ($followUp->status === 'Cancelled' ? '#fee2e2' : '#fef3c7') }}; color: {{ $followUp->status === 'Completed' ? '#166534' : ($followUp->status === 'Cancelled' ? '#7f1d1d' : '#92400e') }}; border-radius: 999px; font-size: 12px; font-weight: 800;">{{ $followUp->status }}</span>
                        </td>
                        <td style="padding: 16px; min-width: 170px;">
                            @include('components.sentiment-result', [
                                'showPrediction' => true,
                                'sentiment' => $followUp->sentiment_label,
                                'confidence' => $followUp->sentiment_confidence,
                                'decisionPath' => $followUp->sentiment_metrics['decision_path'] ?? [],
                            ])
                        </td>
                        <td style="padding: 16px; color: #666; font-size: 14px;">
                            <div class="notes-cell-wrap">
                                <div class="notes-text">
                                    @if ($isSeedNote)
                                        <span class="notes-system-badge" title="{{ $followUp->notes }}">Historical monthly log</span>
                                    @else
                                        {{ $followUp->notes ?? '—' }}
                                    @endif
                                </div>
                                @if ($canManageSatellite)
                                    <button
                                        type="button"
                                        class="row-update-btn {{ $followUp->status === 'Pending' ? 'is-pending' : '' }}"
                                        data-open-followup-edit
                                        data-action="{{ route('satellites.follow-ups.update', [$satellite, $followUp]) }}"
                                        data-target-name="{{ $targetName }} ({{ $targetType }})"
                                        data-date="{{ $followUp->follow_up_date?->format('Y-m-d') ?? now()->format('Y-m-d') }}"
                                        data-method="{{ $followUp->method ?? 'Phone' }}"
                                        data-status="{{ $followUp->status ?? 'Pending' }}"
                                        data-notes="{{ $cleanNotes }}"
                                    >
                                        {{ $followUp->status === 'Pending' ? 'Follow Up' : 'Edit' }}
                                    </button>
                                @endif
                            </div>
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

@if ($canManageSatellite)
<dialog class="followup-dialog" id="followup-edit-dialog" aria-labelledby="followup-edit-title">
    <div class="followup-dialog-header">
        <h2 id="followup-edit-title">Log / Update Follow-Up</h2>
        <button type="button" class="followup-dialog-close" data-close-followup-edit aria-label="Close modal">&times;</button>
    </div>
    <form method="POST" id="followup-edit-form" class="followup-dialog-body">
        @csrf
        @method('PATCH')
        <div class="followup-dialog-field">
            <label>Person</label>
            <input type="text" id="followup-edit-target" readonly style="background: #f3f4f6; color: #4b5563;">
        </div>
        <div class="followup-dialog-row-2col">
            <div class="followup-dialog-field">
                <label for="followup-edit-date">Follow-Up Date</label>
                <input type="date" id="followup-edit-date" name="follow_up_date" required>
            </div>
            <div class="followup-dialog-field">
                <label for="followup-edit-method">Method</label>
                <select id="followup-edit-method" name="method" required>
                    @foreach ($methods as $method)
                        <option value="{{ $method }}">{{ $method }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="followup-dialog-field">
            <label for="followup-edit-status">Status</label>
            <select id="followup-edit-status" name="status" required>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}">{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div class="followup-dialog-field">
            <label for="followup-edit-notes">Follow-Up Notes</label>
            <textarea id="followup-edit-notes" name="notes" rows="4" maxlength="1000" placeholder="Write follow-up conversation summary or pastoral notes..."></textarea>
            <small style="color: #6b7280; font-size: 12px;">Sentiment is automatically analyzed when notes are saved.</small>
        </div>
        <div class="followup-dialog-footer">
            <button type="button" class="btn-secondary" data-close-followup-edit style="padding: 0 16px; border-radius: 10px; font-weight: 800; cursor: pointer;">Cancel</button>
            <button type="submit" class="btn-primary" style="padding: 0 20px; border-radius: 10px; font-weight: 800; cursor: pointer;">Save Follow-Up</button>
        </div>
    </form>
</dialog>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dialog = document.getElementById('followup-edit-dialog');
    const form = document.getElementById('followup-edit-form');
    if (!dialog || !form) return;

    const targetInput = document.getElementById('followup-edit-target');
    const dateInput = document.getElementById('followup-edit-date');
    const methodSelect = document.getElementById('followup-edit-method');
    const statusSelect = document.getElementById('followup-edit-status');
    const notesInput = document.getElementById('followup-edit-notes');

    document.querySelectorAll('[data-open-followup-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.action = btn.getAttribute('data-action') || '';
            targetInput.value = btn.getAttribute('data-target-name') || '';
            dateInput.value = btn.getAttribute('data-date') || '';
            methodSelect.value = btn.getAttribute('data-method') || 'Phone';
            statusSelect.value = btn.getAttribute('data-status') === 'Pending' ? 'Completed' : (btn.getAttribute('data-status') || 'Completed');
            notesInput.value = btn.getAttribute('data-notes') || '';
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', 'open');
            }
        });
    });

    document.querySelectorAll('[data-close-followup-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (typeof dialog.close === 'function') {
                dialog.close();
            } else {
                dialog.removeAttribute('open');
            }
        });
    });
});
</script>
@endpush
@endif
@endsection
