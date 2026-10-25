@extends('layouts.app')

@section('title', $satellite->display_name.' Seekers | Elevate DGM')

@push('styles')
@include('seekers.archive-styles')
@include('seekers.details-modal-styles')
@include('seekers.leader-assignment-modal-styles')
@include('partials.data-table-styles')
:root { --brand-red: #ef3b24; --brand-red-dark: #c92f1b; --brand-red-soft: #fff1ee; --ink: #111; --muted: #667085; --line: #e8edf3; --surface: #fff; }
        .seekers-toolbar { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; margin-bottom: 20px; }
        .title-group { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
        .title-group h1 { margin: 0; color: var(--ink); font-size: 32px; line-height: 1.1; font-weight: 900; }
        .page-kicker { color: var(--muted); font-size: 13px; font-weight: 800; }
        .toolbar-actions { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
        .sync-button, .notification-button { border: 1px solid #d8dde6; background: var(--surface); color: var(--ink); box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04); cursor: pointer; transition: border-color 0.15s ease, color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease; }
        .sync-button { display: inline-flex; align-items: center; gap: 9px; min-height: 38px; padding: 0 17px; border-radius: 999px; font-size: 13px; font-weight: 800; white-space: nowrap; }
        .notification-button { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 999px; text-decoration: none; }
        .sync-button:hover, .notification-button:hover { border-color: var(--brand-red); color: var(--brand-red); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(227, 60, 36, 0.16); }
        .button-icon { display: inline-flex; width: 14px; height: 14px; }
        .bell-icon { width: 18px; height: 18px; }
        .notification-badge { position: absolute; top: -7px; right: -5px; display: inline-flex; align-items: center; justify-content: center; min-width: 21px; height: 21px; padding: 0 6px; border-radius: 999px; background: var(--brand-red); color: #fff; border: 2px solid #f3f4f6; font-size: 11px; line-height: 1; font-weight: 900; }

        .seekers-stat-card { position: relative; text-decoration: none; cursor: pointer; }
        .seekers-stat-card:hover { border-color: var(--stat-card-border-hover, #3A3A3A) !important; border-top-color: var(--stat-card-accent, #450d07) !important; }
        .seekers-stat-card.is-active { border-color: var(--stat-card-focus, #FF5A4D) !important; border-top-color: var(--stat-card-accent, #450d07) !important; }
        .seekers-stat-card .stat-card-value-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; min-width: 0; }
        .seekers-stat-card .card-chevron { flex-shrink: 0; color: var(--stat-card-chevron, #B8B8B8); font-size: 20px; line-height: 1; transition: color 0.15s ease; }
        .seekers-stat-card:hover .card-chevron, .seekers-stat-card.is-active .card-chevron { color: var(--stat-card-chevron-hover, #F2F2F2); }

        .seekers-filters-card { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; margin-bottom: 18px; background: var(--surface); border: 1px solid var(--line); border-radius: 14px; box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05); }
        .seekers-search-wrap { flex: 1 1 280px; min-width: 0; }
        .seekers-search-wrap input { width: 100%; min-height: 42px; padding: 0 14px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; font-weight: 600; color: var(--ink); background: #fff; box-sizing: border-box; }
        .seekers-filter-controls { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        .seekers-filter-controls select { min-height: 42px; padding: 0 12px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 13px; font-weight: 700; color: var(--ink); background: #fff; }

        .seekers-card { width: 100%; overflow: hidden; background: var(--surface); border: 1px solid var(--line); border-radius: 16px; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08); }
        .seekers-table-wrap { width: 100%; overflow-x: auto; }
        .seekers-table { width: 100%; min-width: 1020px; table-layout: fixed; border-collapse: collapse; color: #374151; font-size: 15px; font-weight: 600; }
        .seekers-table th:nth-child(1), .seekers-table td:nth-child(1) { width: 26%; }
        .seekers-table th:nth-child(2), .seekers-table td:nth-child(2) { width: 30%; overflow-wrap: anywhere; }
        .seekers-table th:nth-child(3), .seekers-table td:nth-child(3) { width: 25%; }
        .seekers-table th:nth-child(4), .seekers-table td:nth-child(4) { width: 19%; }
        .seekers-table th, .seekers-table td { padding: 16px 22px; text-align: left; border-bottom: 1px solid var(--line); vertical-align: middle; }
        .seekers-table th.status-cell, .seekers-table td.status-cell { padding: 12px 16px; }
        .seekers-table th { color: #6b7280; background: var(--brand-red-soft); font-size: 14px; font-weight: 900; }
        .seekers-table tbody tr:last-child td { border-bottom: none; }
        .seekers-table tbody tr:hover { background: #fff9f7; }
        .seeker-name { color: var(--ink); font-weight: 900; }
        .seeker-submeta { display: block; margin-top: 3px; padding-left: 28px; color: #667085; font-size: 12.5px; font-weight: 600; }
        .seeker-contact-sub { display: block; margin-top: 3px; color: #667085; font-size: 12.5px; font-weight: 600; }
        .leader-button { min-height: var(--ui-target); border: 1px solid #f0b4a8; border-radius: 8px; padding: 0 12px; background: #fff; color: var(--ui-text); font-size: 13px; font-weight: 900; cursor: pointer; }
        .leader-button:hover, .leader-button:focus-visible { background: var(--brand-red-soft); border-color: var(--brand-red); outline: none; }
        .leader-assigned { display: inline-flex; align-items: center; gap: 7px; min-height: 40px; border: 0; padding: 0; background: transparent; color: var(--ink); font-weight: 800; cursor: pointer; text-align: left; }
        .leader-assigned:hover, .leader-assigned:focus-visible { color: var(--brand-red); outline: none; }
        .leader-edit-icon { width: 14px; height: 14px; }
        .status-cell { width: 200px; min-width: 190px; text-align: left; vertical-align: middle; }
        .status-form { width: 100%; margin: 0; }
        .status-control { position: relative; display: block; width: 100%; max-width: 160px; height: 44px; color: #344054; }
        .status-control::after { content: ""; position: absolute; right: 14px; top: 50%; width: 7px; height: 7px; border-right: 2px solid currentColor; border-bottom: 2px solid currentColor; transform: translateY(-50%) rotate(45deg); pointer-events: none; }
        .status-select { display: block; width: 100%; min-height: 44px; appearance: none; border-radius: 999px; padding: 0 40px 0 16px; border: 1px solid currentColor; background: #fff; color: inherit; font-family: inherit; font-size: 12px; font-weight: 900; cursor: pointer; outline: none; }
        .status-leader-help { display: block; width: 100%; max-width: 160px; height: 17px; margin: 4px 0 0; color: #475467; font-size: 12px; font-weight: 600; line-height: 17px; text-align: left; }
        .status-leader-help.is-hidden { visibility: hidden; }
        .status-select:focus-visible { outline: 3px solid var(--ui-focus); outline-offset: 2px; }
        .status-successful { color: #166534; }
        .status-successful .status-select, .status-select.status-successful { background: #dcfce7; border-color: #86efac; }
        .status-pending { color: #854d0e; }
        .status-pending .status-select, .status-select.status-pending { background: #fff4ce; border-color: #f8c94a; }
        .status-unsuccessful { color: #b42318; }
        .status-unsuccessful .status-select, .status-select.status-unsuccessful { background: #ffe5e1; border-color: #ffb4a9; }
        .notice { margin: 0 0 18px; border: 1px solid rgba(21, 153, 71, 0.24); border-radius: 10px; background: #ecfdf3; color: #067647; padding: 12px 16px; font-size: 14px; font-weight: 800; }
        .empty-state { color: var(--muted); font-weight: 800; text-align: center; }
        @include('partials.table-pagination-styles')
        @media (max-width: 900px) {
            .seekers-toolbar { align-items: stretch; flex-direction: column; }
            .toolbar-actions { justify-content: space-between; }
            .sync-button { min-width: 0; }
        }
        @media (max-width: 767px) {
            .status-control,
            .status-leader-help { max-width: 100%; }
        }
@endpush

@section('content')
@php
    $summaryCards = [
        [
            'label' => 'Active Seekers',
            'active' => ! $archive && ($assignmentFilter ?? '') === '',
            'url' => route('satellites.seekers', array_filter(['satellite' => $satellite, 'search' => $search ?? null, 'per_page' => ($perPage ?? 10) !== 10 ? $perPage : null])),
            'value' => $activeCount ?? 0,
            'countKey' => 'active',
        ],
        [
            'label' => 'Needs DLeader',
            'active' => ! $archive && ($assignmentFilter ?? '') === 'unassigned',
            'url' => ($assignmentFilter ?? '') === 'unassigned'
                ? route('satellites.seekers', array_filter(['satellite' => $satellite, 'search' => $search ?? null, 'per_page' => ($perPage ?? 10) !== 10 ? $perPage : null]))
                : route('satellites.seekers', array_filter(['satellite' => $satellite, 'assignment' => 'unassigned', 'search' => $search ?? null, 'per_page' => ($perPage ?? 10) !== 10 ? $perPage : null])),
            'value' => $unassignedCount ?? 0,
            'countKey' => null,
        ],
        [
            'label' => 'Assigned to DLeader',
            'active' => ! $archive && ($assignmentFilter ?? '') === 'assigned',
            'url' => ($assignmentFilter ?? '') === 'assigned'
                ? route('satellites.seekers', array_filter(['satellite' => $satellite, 'search' => $search ?? null, 'per_page' => ($perPage ?? 10) !== 10 ? $perPage : null]))
                : route('satellites.seekers', array_filter(['satellite' => $satellite, 'assignment' => 'assigned', 'search' => $search ?? null, 'per_page' => ($perPage ?? 10) !== 10 ? $perPage : null])),
            'value' => $assignedCount ?? 0,
            'countKey' => null,
        ],
        [
            'label' => 'Archived Seekers',
            'active' => $archive,
            'url' => ($canManageSeekers ?? false)
                ? route('satellites.seekers', [$satellite, 'view' => $archive ? null : 'archive'])
                : route('satellites.seekers', $satellite),
            'value' => $archiveCount ?? 0,
            'countKey' => 'archived',
        ],
    ];
    $hasSearchOrFilters = ($search ?? '') !== '' || ($assignmentFilter ?? '') !== '' || ($leaderFilter ?? '') !== '';
@endphp

<div class="notice" data-seeker-status-notice role="status" aria-live="polite" hidden></div>
<div class="seekers-toolbar">
            <div class="title-group">
                <h1>Seekers</h1>
                <span class="page-kicker">{{ $satellite->display_name }} Satellite — Assign a DGroup Leader first, then update seeker status.</span>
            </div>

            <div class="toolbar-actions">
                @if ($canManageFollowUps ?? false)
                    <a href="{{ route('satellites.follow-ups.index', $satellite) }}" class="btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; min-height: 40px; padding: 0 16px; border-radius: 10px; font-size: 13px; font-weight: 800; text-decoration: none;">
                        Follow-Ups
                    </a>
                @endif
                <a href="{{ route('satellites.seekers', [$satellite, 'assignment' => 'unassigned']) }}" class="notification-button" aria-label="Notifications" title="Pending seekers needing follow-up or leader assignment">
                    <svg class="bell-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
                        <path d="M13.7 21a2 2 0 0 1-3.4 0" />
                    </svg>
                    <span class="notification-badge" data-seeker-count="pending" @if(!$notificationCount) hidden @endif>{{ $notificationCount }}</span>
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="notice error">{{ session('error') }}</div>
        @endif

        <div class="metric-card-grid">
            @foreach ($summaryCards as $index => $card)
                @php
                    $formattedValue = number_format((int) $card['value']);
                @endphp
                <a
                    href="{{ $card['url'] }}"
                    class="stat-card elevate-stat-card stat-card-link seekers-stat-card {{ $card['active'] ? 'is-active' : '' }}"
                    data-focus-key="seeker-card-{{ $index }}"
                    aria-label="{{ $card['label'] }}, {{ $formattedValue }}"
                    title="Filter by {{ $card['label'] }}"
                >
                    <span class="stat-label stat-card-label" title="{{ $card['label'] }}">{{ $card['label'] }}</span>
                    <div class="stat-card-value-row">
                        <strong class="stat-value stat-card-value" @if($card['countKey']) data-seeker-count="{{ $card['countKey'] }}" @endif>{{ $formattedValue }}</strong>
                        <span class="card-chevron" aria-hidden="true">{{ $card['active'] && ($assignmentFilter ?? '') !== '' ? '✕' : '›' }}</span>
                    </div>
                </a>
            @endforeach
        </div>

        @if ($canManageSeekers ?? (auth()->user()->normalizedRole() === 'leader'))
        <x-status-tabs :current="$archive ? 'archive' : 'active'" :tabs="[
            ['key' => 'active', 'label' => 'Active', 'count' => $activeCount, 'url' => route('satellites.seekers', $satellite)],
            ['key' => 'archive', 'label' => 'Archived', 'count' => $archiveCount, 'url' => route('satellites.seekers', [$satellite, 'view' => 'archive'])],
        ]" />
        @endif

        @if (! $archive)
        <form method="GET" action="{{ route('satellites.seekers', $satellite) }}" class="seekers-filters-card">
            @if (($perPage ?? 10) !== 10)
                <input type="hidden" name="per_page" value="{{ $perPage }}">
            @endif
            <div class="seekers-search-wrap">
                <input
                    type="search"
                    name="search"
                    value="{{ $search ?? '' }}"
                    placeholder="Search seeker by name, email, school/campus, contact, or DLeader..."
                    aria-label="Search seekers"
                >
            </div>
            <div class="seekers-filter-controls">
                <select name="assignment" aria-label="Filter by leader assignment" onchange="this.form.submit()">
                    <option value="">All Assignment States</option>
                    <option value="unassigned" @selected(($assignmentFilter ?? '') === 'unassigned')>Needs DLeader ({{ $unassignedCount ?? 0 }})</option>
                    <option value="assigned" @selected(($assignmentFilter ?? '') === 'assigned')>Assigned to DLeader ({{ $assignedCount ?? 0 }})</option>
                </select>
                <select name="leader" aria-label="Filter by DGroup Leader" onchange="this.form.submit()">
                    <option value="">All DGroup Leaders</option>
                    @foreach ($leaderOptions as $leaderId => $leaderName)
                        <option value="{{ $leaderName }}" @selected(($leaderFilter ?? '') === $leaderName)>{{ $leaderName }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn-primary" style="min-height: 42px; padding: 0 18px; border-radius: 10px; font-size: 13px; font-weight: 800; cursor: pointer;">Search</button>
                @if ($hasSearchOrFilters)
                    <a href="{{ route('satellites.seekers', $satellite) }}" class="btn-secondary" style="display: inline-flex; align-items: center; min-height: 42px; padding: 0 14px; border-radius: 10px; font-size: 13px; font-weight: 800; text-decoration: none;">Reset</a>
                @endif
            </div>
        </form>
        @endif

        @php
            $filters = ['view' => $archive ? 'archive' : 'active']; $hasActiveFilters = false;
            $sortUrl = fn ($key) => route('satellites.seekers', [$satellite, 'view' => 'archive', 'sort' => $key, 'direction' => $sort === $key && $direction === 'asc' ? 'desc' : 'asc']);
            $sortGlyph = fn ($key) => $sort === $key ? ($direction === 'asc' ? '&#8593;' : '&#8595;') : '&#8597;';
        @endphp
        @if (auth()->user()->normalizedRole() === 'leader')
            @include('seekers.archive-controls')
        @endif
        @if ($archive)
            @include('seekers.archive-table', ['satelliteArchive' => true])
            {{ $seekers->links() }}
        @else
        <section class="seekers-card">
            <div class="seekers-table-wrap">
                <table class="seekers-table">
                    <thead>
                        <tr>
                            <th>Name &amp; School</th>
                            <th>Email &amp; Contact</th>
                            <th>DLeader</th>
                            <th class="status-cell">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($seekers as $seeker)
                            @php
                                $leaderOptionsJson = json_encode($leaderOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
                                $currentLeaderId = array_search($seeker->dgroup_leader_name, $leaderOptions, true);
                            @endphp
                            <tr
                                data-seeker-modal-row
                                data-seeker-name="{{ $seeker->name }}"
                                data-seeker-contact="{{ $seeker->contact_number }}"
                                data-seeker-email="{{ $seeker->email }}"
                                data-seeker-facebook="{{ $seeker->facebook_link }}"
                                data-seeker-age="{{ $seeker->age }}"
                                data-seeker-gender="{{ $seeker->gender }}"
                                data-seeker-school="{{ $seeker->school }}"
                            >
                                <td class="seeker-name">
                                    @if(auth()->user()->normalizedRole() === 'leader')<input type="checkbox" data-archive-select value="{{ $seeker->id }}" aria-label="Select {{ $seeker->name }}"> @endif
                                    <div class="seeker-name-cell">
                                        <button type="button" class="seeker-detail-button" data-open-seeker-modal aria-label="View seeker details">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>
                                        <span class="seeker-detail-name">{{ $seeker->name }}</span>
                                    </div>
                                    @if ($seeker->school)
                                        <span class="seeker-submeta">{{ $seeker->school }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span>{{ $seeker->email }}</span>
                                    @if ($seeker->contact_number)
                                        <span class="seeker-contact-sub">{{ $seeker->contact_number }}</span>
                                    @endif
                                </td>
                                <td>
                                    <button type="button" class="{{ $seeker->dgroup_leader_name ? 'leader-assigned' : 'leader-button' }}" data-open-leader-modal data-seeker-name="{{ $seeker->name }}" data-leader="{{ $seeker->dgroup_leader_name }}" data-leader-id="{{ $currentLeaderId ?: '' }}" data-leader-options="{{ $leaderOptionsJson }}" data-action="{{ route('satellites.seekers.leader', [$satellite, $seeker]) }}" aria-label="{{ $seeker->dgroup_leader_name ? 'Change DGroup Leader for '.$seeker->name : 'Assign DGroup Leader for '.$seeker->name }}">
                                        @if ($seeker->dgroup_leader_name)
                                            {{ $seeker->dgroup_leader_name }}
                                            <svg class="leader-edit-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 0 3 3L7 19l-4 1 1-4Z" /></svg>
                                        @else
                                            + Assign DLeader
                                        @endif
                                    </button>
                                </td>
                                <td class="status-cell">
                                    @if(auth()->user()->normalizedRole() === 'leader')<button type="button" class="data-table-link-button" data-archive-action="archive" data-action-url="{{ route('seekers.archive', $seeker) }}" data-name="{{ $seeker->name }}">Archive</button>@endif
                                    <form method="POST" action="{{ route('satellites.seekers.status', [$satellite, $seeker]) }}" class="status-form">
                                        @csrf
                                        @method('PATCH')
                                        <span class="status-control status-{{ $seeker->status_class }}" data-status-control>
                                            <select class="status-select status-{{ $seeker->status_class }}" name="status" @disabled(!$canChangeSeekerStatus) data-status-select data-has-leader="{{ $currentLeaderId ? 'true' : 'false' }}" data-seeker-name="{{ $seeker->name }}" data-current-status="{{ $seeker->status }}" aria-label="Status for {{ $seeker->name }}" @if(!$currentLeaderId) aria-describedby="status-hint-{{ $seeker->id }}" @endif title="{{ $currentLeaderId ? '' : 'Assign a DGroup Leader first' }}">
                                                @foreach ($statuses as $status)
                                                    <option value="{{ $status }}" @selected($seeker->status === $status) @disabled(!$currentLeaderId && $status !== \App\Models\Seeker::STATUS_PENDING)>{{ $status }}</option>
                                                @endforeach
                                            </select>
                                        </span>
                                    </form>
                                    <p class="status-leader-help{{ $currentLeaderId ? ' is-hidden' : '' }}" id="status-hint-{{ $seeker->id }}" data-status-leader-help @if($currentLeaderId) aria-hidden="true" @endif>
                                        Assign a DGroup Leader first
                                    </p>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-state">
                                    @if ($hasSearchOrFilters)
                                        No seekers matched your search or filter criteria. <a href="{{ route('satellites.seekers', $satellite) }}" style="color: #ef3b24; font-weight: 900; text-decoration: none;">Reset filters</a>
                                    @else
                                        {{ $satellite->display_name }} has no seekers yet.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('partials.table-pagination', ['paginator' => $seekers, 'pageSizes' => $pageSizes ?? [10, 25, 50, 100], 'currentPageSize' => $perPage ?? 10, 'recordLabel' => 'seekers', 'tableId' => 'satellite-seekers'])
        </section>
        @endif
@endsection

@push('scripts')
@include('seekers.details-modal')
@include('seekers.leader-assignment-modal')
@include('seekers.status-controls')
@endpush
