@extends('layouts.app')

@section('title', 'Follow-Up History | Elevate DGM')

@push('styles')
    .follow-up-editor { margin-bottom: 12px; text-align: left; }
    .follow-up-editor[open] { min-width: 260px; max-width: 360px; }
    .follow-up-editor summary { color: #a92717; cursor: pointer; font-weight: 800; }
    .follow-up-edit-form { display: grid; gap: 14px; margin-top: 14px; padding: 16px; border: 1px solid #667085; border-radius: 8px; }
    .follow-up-edit-field { display: grid; gap: 6px; }
    .follow-up-edit-field label { color: #374151; font-size: 14px; font-weight: 800; }
    .follow-up-edit-field input, .follow-up-edit-field select, .follow-up-edit-field textarea { width: 100%; min-width: 0; padding: 10px; border: 1px solid #667085; border-radius: 6px; background: #fff; color: #111; font: inherit; }
    .follow-up-edit-field textarea { resize: vertical; }
    .follow-up-edit-help { color: #4b5563; font-size: 12px; line-height: 1.5; }
    .follow-up-edit-error { margin: 0; color: #b42318; font-size: 13px; font-weight: 700; }
    .follow-up-edit-save { padding: 10px 16px; border: 0; border-radius: 8px; background: #a92717; color: #fff; font-weight: 800; cursor: pointer; }
@endpush

@section('content')
<div>
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 26px;">
        <div>
            <h1 style="margin: 0; font-size: 32px; font-weight: 900;">Follow-Up History</h1>
            <p style="margin: 8px 0 0; color: #6b7280; font-weight: 600;">Track follow-up notes, status, and sentiment trends for {{ $satellite->display_name }}.</p>
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

    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(4, minmax(160px, 1fr)); gap: 14px; margin-bottom: 18px;">
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

    <div class="responsive-panel-grid" style="display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(280px, 0.6fr); gap: 18px; align-items: start; margin-bottom: 18px;">
        <form method="GET" action="{{ route('satellites.follow-ups.index', $satellite) }}" class="filter-toolbar" style="background: #fff; border-radius: 10px; padding: 18px; box-shadow: 0 12px 26px rgba(17, 24, 39, 0.10); display: grid; grid-template-columns: repeat(4, minmax(150px, 1fr)); gap: 14px; align-items: end;">
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
            <div style="display: flex; gap: 10px;">
                <button type="submit" style="padding: 10px 18px; background: #e33c24; color: #fff; border: none; border-radius: 8px; font-weight: 800; cursor: pointer;">Apply Filters</button>
                <a href="{{ route('satellites.follow-ups.index', $satellite) }}" style="padding: 10px 18px; background: #f3f4f6; color: #111; text-decoration: none; border-radius: 8px; font-weight: 800;">Clear Filters</a>
            </div>
        </form>

        <div style="background: #fff; border-radius: 10px; padding: 18px; box-shadow: 0 12px 26px rgba(17, 24, 39, 0.10);">
            <h2 style="margin: 0 0 12px; font-size: 16px; font-weight: 900;">Sentiment Trends</h2>
            @forelse ($sentimentSummary['trend'] as $trend)
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
                        <span title="Negative {{ $trend['Negative'] ?? 0 }}" style="display: block; width: {{ $negativeWidth }}%; background: #b42318;"></span>
                    </div>
                </div>
            @empty
                <p style="margin: 0; color: #6b7280; font-size: 14px;">No sentiment trend yet. Analyze follow-up notes to populate this view.</p>
            @endforelse
        </div>
    </div>

    <div class="table-card" style="background: #fff; border-radius: 14px; box-shadow: 0 16px 34px rgba(17, 24, 39, 0.12); overflow: hidden;">
        <div class="table-scroll">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #111; color: #fff;">
                    <th style="padding: 16px; text-align: left; font-weight: 900; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid #e5e7eb;">Target</th>
                    <th style="padding: 16px; text-align: left; font-weight: 900; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid #e5e7eb;">Date</th>
                    <th style="padding: 16px; text-align: left; font-weight: 900; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid #e5e7eb;">Method</th>
                    <th style="padding: 16px; text-align: left; font-weight: 900; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid #e5e7eb;">Status</th>
                    <th style="padding: 16px; text-align: left; font-weight: 900; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid #e5e7eb;">Sentiment</th>
                    <th style="padding: 16px; text-align: left; font-weight: 900; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid #e5e7eb;">Notes</th>
                    <th style="padding: 16px; text-align: center; font-weight: 900; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid #e5e7eb;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($followUps as $followUp)
                    @php
                        $followUpTarget = $followUp->seeker?->name ?? $followUp->member?->display_name ?? 'unassigned target';
                        $isEditingFollowUp = (string) old('_follow_up_id') === (string) $followUp->id;
                        $editMethod = $isEditingFollowUp ? old('method') : $followUp->method;
                        $editStatus = $isEditingFollowUp ? old('status') : $followUp->status;
                    @endphp
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
                            {{ $followUp->method }}
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
                            {{ Str::limit($followUp->notes, 50) ?? '—' }}
                        </td>
                        <td style="padding: 16px; text-align: center;">
                            @if ($canManageSatellite)
                            <details class="follow-up-editor" @if ($isEditingFollowUp && $errors->any()) open @endif>
                                <summary aria-label="Edit follow-up {{ $followUp->id }} for {{ $followUpTarget }}">Edit Follow-Up</summary>
                                <form class="follow-up-edit-form" method="POST" action="{{ route('satellites.follow-ups.update', [$satellite, $followUp]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="_follow_up_id" value="{{ $followUp->id }}">
                                    <div class="follow-up-edit-field">
                                        <label for="follow-up-{{ $followUp->id }}-date">Follow-Up Date</label>
                                        <input id="follow-up-{{ $followUp->id }}-date" type="date" name="follow_up_date" value="{{ $isEditingFollowUp ? old('follow_up_date') : $followUp->follow_up_date?->format('Y-m-d') }}" aria-describedby="follow-up-{{ $followUp->id }}-date-help{{ $isEditingFollowUp && $errors->has('follow_up_date') ? ' follow-up-'.$followUp->id.'-date-error' : '' }}" @if ($isEditingFollowUp && $errors->has('follow_up_date')) aria-invalid="true" @endif>
                                        <small id="follow-up-{{ $followUp->id }}-date-help" class="follow-up-edit-help">Leave blank to keep the current date.</small>
                                        @if ($isEditingFollowUp && $errors->has('follow_up_date'))<p id="follow-up-{{ $followUp->id }}-date-error" class="follow-up-edit-error">{{ $errors->first('follow_up_date') }}</p>@endif
                                    </div>
                                    <div class="follow-up-edit-field">
                                        <label for="follow-up-{{ $followUp->id }}-method">Method (required)</label>
                                        <select id="follow-up-{{ $followUp->id }}-method" name="method" required @if ($isEditingFollowUp && $errors->has('method')) aria-invalid="true" aria-describedby="follow-up-{{ $followUp->id }}-method-error" @endif>
                                            <option value="">Select method</option>
                                            @foreach ($methods as $editMethodOption)
                                                <option value="{{ $editMethodOption }}" @selected($editMethod === $editMethodOption)>{{ $editMethodOption }}</option>
                                            @endforeach
                                        </select>
                                        @if ($isEditingFollowUp && $errors->has('method'))<p id="follow-up-{{ $followUp->id }}-method-error" class="follow-up-edit-error">{{ $errors->first('method') }}</p>@endif
                                    </div>
                                    <div class="follow-up-edit-field">
                                        <label for="follow-up-{{ $followUp->id }}-status">Status (required)</label>
                                        <select id="follow-up-{{ $followUp->id }}-status" name="status" required @if ($isEditingFollowUp && $errors->has('status')) aria-invalid="true" aria-describedby="follow-up-{{ $followUp->id }}-status-error" @endif>
                                            <option value="">Select status</option>
                                            @foreach ($statuses as $editStatusOption)
                                                <option value="{{ $editStatusOption }}" @selected($editStatus === $editStatusOption)>{{ $editStatusOption }}</option>
                                            @endforeach
                                        </select>
                                        @if ($isEditingFollowUp && $errors->has('status'))<p id="follow-up-{{ $followUp->id }}-status-error" class="follow-up-edit-error">{{ $errors->first('status') }}</p>@endif
                                    </div>
                                    <div class="follow-up-edit-field">
                                        <label for="follow-up-{{ $followUp->id }}-notes">Notes</label>
                                        <textarea id="follow-up-{{ $followUp->id }}-notes" name="notes" rows="4" maxlength="1000" aria-describedby="follow-up-{{ $followUp->id }}-notes-help{{ $isEditingFollowUp && $errors->has('notes') ? ' follow-up-'.$followUp->id.'-notes-error' : '' }}" @if ($isEditingFollowUp && $errors->has('notes')) aria-invalid="true" @endif>{{ $isEditingFollowUp ? old('notes') : $followUp->notes }}</textarea>
                                        <small id="follow-up-{{ $followUp->id }}-notes-help" class="follow-up-edit-help">Maximum 1,000 characters. Leave blank to keep the current notes. Saving updates the sentiment analysis.</small>
                                        @if ($isEditingFollowUp && $errors->has('notes'))<p id="follow-up-{{ $followUp->id }}-notes-error" class="follow-up-edit-error">{{ $errors->first('notes') }}</p>@endif
                                    </div>
                                    <button type="submit" class="follow-up-edit-save" aria-label="Save follow-up {{ $followUp->id }} for {{ $followUpTarget }}">Save Follow-Up</button>
                                </form>
                            </details>
                            <form method="POST" action="{{ route('satellites.follow-ups.destroy', [$satellite, $followUp]) }}" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" aria-label="Delete follow-up {{ $followUp->id }} for {{ $followUp->seeker?->name ?? $followUp->member?->display_name ?? 'unassigned target' }}" style="background: none; border: none; color: #dc2626; text-decoration: none; font-weight: 700; cursor: pointer;" onclick="return confirm('Delete this follow-up?');">Delete Follow-Up</button>
                            </form>
                            @else
                                <span>View only</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding: 32px; text-align: center; color: #999;">No follow-ups yet. @if ($canManageSatellite)<a href="{{ route('satellites.follow-ups.create', $satellite) }}" style="color: #e33c24; text-decoration: none; font-weight: 700;">Create Follow-Up</a>@endif</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @include('partials.table-pagination', ['paginator' => $followUps, 'pageSizes' => $pageSizes, 'currentPageSize' => $perPage, 'recordLabel' => 'follow-ups', 'tableId' => 'satellite-follow-ups'])
    </div>
</div>
@endsection
