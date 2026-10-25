@extends('layouts.app')

@section('title', $satellite->display_name.' Seekers | Elevate DGM')

@push('styles')
@include('seekers.archive-styles')
@include('seekers.details-modal-styles')
@include('seekers.leader-assignment-modal-styles')
@include('partials.data-table-styles')
:root { --brand-red: #ef3b24; --brand-red-dark: #c92f1b; --brand-red-soft: #fff1ee; --ink: #111; --muted: #667085; --line: #e8edf3; --surface: #fff; }
        .seekers-toolbar { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; margin-bottom: 22px; }
        .title-group { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
        .title-group h1 { margin: 0; color: var(--ink); font-size: 32px; line-height: 1.1; font-weight: 900; }
        .page-kicker { color: var(--muted); font-size: 13px; font-weight: 800; }
        .toolbar-actions { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
        .sync-button, .notification-button { border: 1px solid #d8dde6; background: var(--surface); color: var(--ink); box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04); cursor: pointer; transition: border-color 0.15s ease, color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease; }
        .sync-button { display: inline-flex; align-items: center; gap: 9px; min-height: 38px; padding: 0 17px; border-radius: 999px; font-size: 13px; font-weight: 800; white-space: nowrap; }
        .notification-button { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 999px; }
        .sync-button:hover, .notification-button:hover { border-color: var(--brand-red); color: var(--brand-red); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(227, 60, 36, 0.16); }
        .button-icon { display: inline-flex; width: 14px; height: 14px; }
        .bell-icon { width: 18px; height: 18px; }
        .notification-badge { position: absolute; top: -7px; right: -5px; display: inline-flex; align-items: center; justify-content: center; min-width: 21px; height: 21px; padding: 0 6px; border-radius: 999px; background: var(--brand-red); color: #fff; border: 2px solid #f3f4f6; font-size: 11px; line-height: 1; font-weight: 900; }
        .seekers-card { width: 100%; overflow: hidden; background: var(--surface); border: 1px solid var(--line); border-radius: 16px; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08); }
        .seekers-table-wrap { width: 100%; overflow-x: auto; }
        .seekers-table { width: 100%; min-width: 1020px; table-layout: fixed; border-collapse: collapse; color: #374151; font-size: 15px; font-weight: 600; }
        .seekers-table th:nth-child(1), .seekers-table td:nth-child(1) { width: 24%; }
        .seekers-table th:nth-child(2), .seekers-table td:nth-child(2) { width: 32%; overflow-wrap: anywhere; }
        .seekers-table th:nth-child(3), .seekers-table td:nth-child(3) { width: 25%; }
        .seekers-table th:nth-child(4), .seekers-table td:nth-child(4) { width: 19%; }
        .seekers-table th, .seekers-table td { padding: 18px 24px; text-align: left; border-bottom: 1px solid var(--line); vertical-align: middle; }
        .seekers-table th.status-cell, .seekers-table td.status-cell { padding: 12px 16px; }
        .seekers-table th { color: #6b7280; background: var(--brand-red-soft); font-size: 14px; font-weight: 900; }
        .seekers-table tbody tr:last-child td { border-bottom: none; }
        .seekers-table tbody tr:hover { background: #fff9f7; }
        .seeker-name { color: var(--ink); font-weight: 900; }
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
<div class="notice" data-seeker-status-notice role="status" aria-live="polite" hidden></div>
<div class="seekers-toolbar">
            <div class="title-group">
                <h1>Seekers</h1>
                <span class="page-kicker">{{ $satellite->display_name }} Satellite</span>
            </div>

            <div class="toolbar-actions">
                <button class="notification-button" type="button" aria-label="Notifications">
                    <svg class="bell-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
                        <path d="M13.7 21a2 2 0 0 1-3.4 0" />
                    </svg>
                    <span class="notification-badge" data-seeker-count="pending" @if(!$notificationCount) hidden @endif>{{ $notificationCount }}</span>
                </button>
            </div>
        </div>

        @if (session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="notice error">{{ session('error') }}</div>
        @endif

        @if (auth()->user()->normalizedRole() === 'leader')
        <x-status-tabs :current="$archive ? 'archive' : 'active'" :tabs="[
            ['key' => 'active', 'label' => 'Active', 'count' => $activeCount, 'url' => route('satellites.seekers', $satellite)],
            ['key' => 'archive', 'label' => 'Archived', 'count' => $archiveCount, 'url' => route('satellites.seekers', [$satellite, 'view' => 'archive'])],
        ]" />
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
                            <th>Name</th>
                            <th>Email</th>
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
                                </td>
                                <td>{{ $seeker->email }}</td>
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
                                <td colspan="4" class="empty-state">{{ $satellite->display_name }} has no seekers yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('partials.table-pagination', ['paginator' => $seekers, 'pageSizes' => [10], 'currentPageSize' => 10, 'recordLabel' => 'seekers', 'tableId' => 'satellite-seekers'])
        </section>
        @endif
@endsection

@push('scripts')
@include('seekers.details-modal')
@include('seekers.leader-assignment-modal')
@include('seekers.status-controls')
@endpush
