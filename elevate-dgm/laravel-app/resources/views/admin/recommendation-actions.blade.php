@extends('layouts.app')

@section('title', 'Recommendation Catalogue | Elevate DGM')

@push('styles')
.catalogue-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; margin-bottom: 22px; flex-wrap: wrap; }
.catalogue-header h1 { margin: 0; font-size: 34px; font-weight: 900; color: #111; }
.catalogue-header p { margin: 8px 0 0; color: #667085; font-size: 15px; font-weight: 700; line-height: 1.5; max-width: 760px; }
.catalogue-stats { display: grid; grid-template-columns: repeat(3, minmax(180px, 1fr)); gap: 16px; margin-bottom: 22px; }
.catalogue-stat { background: #fff; border: 1px solid #eef0f3; border-radius: 14px; box-shadow: 0 12px 28px rgba(17, 24, 39, .08); padding: 18px; }
.catalogue-stat strong { display: block; color: #111; font-size: 30px; font-weight: 900; line-height: 1; }
.catalogue-stat span { display: block; color: #667085; font-size: 13px; font-weight: 900; margin-top: 8px; text-transform: uppercase; }
.catalogue-card { background: #fff; border: 1px solid #eef0f3; border-radius: 14px; box-shadow: 0 12px 28px rgba(17, 24, 39, .08); margin-bottom: 22px; padding: 20px; }
.catalogue-card h2 { color: #111; font-size: 20px; font-weight: 900; margin: 0 0 8px; }
.catalogue-note { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 12px; color: #9a3412; font-size: 14px; font-weight: 800; line-height: 1.5; margin: 14px 0 18px; padding: 14px; }
.catalogue-form { display: grid; gap: 14px; }
.form-grid { display: grid; grid-template-columns: minmax(220px, .85fr) minmax(280px, 1.15fr); gap: 14px; }
.field { display: grid; gap: 7px; }
.field label, .checkbox-field span { color: #111; font-size: 13px; font-weight: 900; text-transform: uppercase; }
.field input, .field select, .field textarea { width: 100%; min-width: 0; border: 1px solid #d0d5dd; border-radius: 10px; color: #111; font: inherit; padding: 11px 12px; }
.field textarea { min-height: 110px; resize: vertical; }
.field small { color: #667085; font-size: 12px; font-weight: 700; line-height: 1.4; }
.checkbox-row { display: grid; grid-template-columns: repeat(2, minmax(220px, 1fr)); gap: 12px; }
.checkbox-field { align-items: flex-start; background: #f9fafb; border: 1px solid #eef0f3; border-radius: 12px; display: flex; gap: 10px; padding: 12px; }
.checkbox-field input { margin-top: 3px; }
.checkbox-field p { color: #667085; font-size: 12px; font-weight: 700; line-height: 1.4; margin: 3px 0 0; }
.button-row { display: flex; gap: 10px; flex-wrap: wrap; }
.primary-button, .secondary-button { align-items: center; border-radius: 10px; display: inline-flex; font-weight: 900; justify-content: center; min-height: 42px; padding: 0 16px; text-decoration: none; }
.primary-button { background: #ef3b24; border: 0; color: #fff; cursor: pointer; }
.secondary-button { background: #fff; border: 1px solid #d0d5dd; color: #111; }
.status-message { background: #dcfce7; border-left: 4px solid #22c55e; border-radius: 12px; color: #166534; font-weight: 800; margin-bottom: 18px; padding: 14px 16px; }
.error-box { background: #fef2f2; border-left: 4px solid #ef4444; border-radius: 12px; color: #991b1b; font-weight: 800; margin-bottom: 18px; padding: 14px 16px; }
.filter-toolbar { align-items: end; background: #fff; border: 1px solid #eef0f3; border-radius: 14px; box-shadow: 0 12px 28px rgba(17, 24, 39, .08); display: grid; gap: 12px; grid-template-columns: minmax(220px, 1fr) 180px auto; margin-bottom: 18px; padding: 16px; }
.table-card h2 { color: #111; font-size: 20px; font-weight: 900; margin: 0 0 14px; }
.catalogue-table { width: 100%; min-width: 900px; border-collapse: collapse; }
.catalogue-table th, .catalogue-table td { border-bottom: 1px solid #eef0f3; padding: 15px; text-align: left; vertical-align: top; }
.catalogue-table th { background: #111; color: #fff; font-size: 12px; font-weight: 900; text-transform: uppercase; }
.catalogue-table td { color: #111; font-size: 14px; }
.catalogue-title { color: #ef3b24; display: block; font-size: 15px; font-weight: 900; margin-bottom: 5px; }
.catalogue-description { color: #667085; display: block; line-height: 1.5; max-width: 520px; }
.status-pill { border-radius: 999px; display: inline-flex; font-size: 12px; font-weight: 900; padding: 7px 10px; white-space: nowrap; }
.status-pill.active { background: #dcfce7; color: #166534; }
.status-pill.draft { background: #e5e7eb; color: #4b5563; }
.row-editor summary { color: #ef3b24; cursor: pointer; font-weight: 900; }
.row-editor form { background: #f9fafb; border: 1px solid #eef0f3; border-radius: 12px; display: grid; gap: 12px; margin-top: 12px; min-width: 360px; padding: 14px; }
.metadata-preview { color: #667085; font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 12px; white-space: pre-wrap; }
.empty-cell { color: #667085; padding: 34px !important; text-align: center !important; }
@include('partials.table-pagination-styles')
@media (max-width: 900px) {
    .catalogue-stats, .form-grid, .checkbox-row, .filter-toolbar { grid-template-columns: 1fr; }
    .button-row .primary-button, .button-row .secondary-button { width: 100%; }
}
@endpush

@section('content')
@php
    $metadataValue = fn ($action = null) => $action?->condition_metadata
        ? json_encode($action->condition_metadata, JSON_PRETTY_PRINT)
        : '';
    $sortUrl = fn (string $column) => route('admin.recommendation-actions.index', array_merge(request()->except('page'), [
        'sort' => $column,
        'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
    ]));
    $sortGlyph = fn (string $column) => $sort === $column ? ($direction === 'asc' ? 'up' : 'down') : '-';
    $hasActiveFilters = collect($filters)->contains(fn ($value) => $value !== '' && $value !== null);
    $createWasSubmitted = old('_form_context', 'create') === 'create';
@endphp

<div class="catalogue-header">
    <div>
        <h1>Recommendation Catalogue</h1>
        <p>Maintain the authorized follow-up action catalogue used after engagement classification and human review.</p>
    </div>
    <a class="secondary-button" href="{{ route('admin.analytics') }}">Back to Analytics</a>
</div>

@if (session('status'))
    <div class="status-message">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="error-box" role="alert" tabindex="-1" data-validation-summary>
        <strong>Unable to save recommendation action.</strong>
        <ul>
            @foreach ($errors->getMessages() as $field => $messages)
                <li id="recommendation-error-{{ $field }}">{{ implode(' ', $messages) }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="catalogue-stats">
    <div class="catalogue-stat"><strong>{{ $totalActions }}</strong><span>Total Actions</span></div>
    <div class="catalogue-stat"><strong>{{ $activeActions }}</strong><span>Approved / Active</span></div>
    <div class="catalogue-stat"><strong>{{ $draftActions }}</strong><span>Draft / Inactive</span></div>
</div>

<section class="catalogue-card">
    <h2>Add Recommendation Action</h2>
    <div class="catalogue-note">
        Add only action wording that has been reviewed with the beneficiary or supervising adviser before activating it. Draft actions can be saved inactive for later review.
    </div>
    <form method="POST" action="{{ route('admin.recommendation-actions.store') }}" class="catalogue-form">
        @csrf
        <input type="hidden" name="_form_context" value="create">
        <div class="form-grid">
            <div class="field">
                <label for="title">Action Title</label>
                <input id="title" name="title" value="{{ $createWasSubmitted ? old('title') : '' }}" maxlength="140" required @if ($createWasSubmitted && $errors->has('title')) aria-invalid="true" aria-describedby="recommendation-error-title" @endif>
            </div>
            <div class="field">
                <label for="description">Action Description</label>
                <textarea id="description" name="description" required @if ($createWasSubmitted && $errors->has('description')) aria-invalid="true" aria-describedby="recommendation-error-description" @endif>{{ $createWasSubmitted ? old('description') : '' }}</textarea>
            </div>
        </div>
        <div class="field">
            <label for="condition_metadata">Condition Metadata JSON</label>
            <textarea id="condition_metadata" name="condition_metadata" aria-describedby="condition-metadata-help{{ $createWasSubmitted && $errors->has('condition_metadata') ? ' recommendation-error-condition_metadata' : '' }}" @if ($createWasSubmitted && $errors->has('condition_metadata')) aria-invalid="true" @endif placeholder='{"classification":"Possible Need for Follow-Up"}'>{{ $createWasSubmitted ? old('condition_metadata') : '' }}</textarea>
            <small id="condition-metadata-help">Optional. Store structured matching notes only; assignment logic is handled in the next phase.</small>
        </div>
        <div class="checkbox-row">
            <label class="checkbox-field">
                <input type="checkbox" name="is_active" value="1" @checked($createWasSubmitted && old('is_active')) @if ($createWasSubmitted && $errors->has('is_active')) aria-invalid="true" aria-describedby="recommendation-error-is_active" @endif>
                <span>
                    Activate action
                    <p>Only active actions can be used later in recommendation review.</p>
                </span>
            </label>
            <label class="checkbox-field">
                <input type="checkbox" name="approval_confirmed" value="1" @checked($createWasSubmitted && old('approval_confirmed')) @if ($createWasSubmitted && $errors->has('approval_confirmed')) aria-invalid="true" aria-describedby="recommendation-error-approval_confirmed" @endif>
                <span>
                    Approval confirmed
                    <p>Required before activation; this does not create automatic contact or status changes.</p>
                </span>
            </label>
        </div>
        <div class="button-row">
            <button type="submit" class="primary-button">Save Recommendation Action</button>
        </div>
    </form>
</section>

<form method="GET" action="{{ route('admin.recommendation-actions.index') }}" class="filter-toolbar">
    <input type="hidden" name="sort" value="{{ $sort }}">
    <input type="hidden" name="direction" value="{{ $direction }}">
    <div class="field">
        <label for="catalogue-search">Search</label>
        <input id="catalogue-search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Search title or description">
    </div>
    <div class="field">
        <label for="catalogue-status">Status</label>
        <select id="catalogue-status" name="status">
            <option value="">All statuses</option>
            <option value="active" @selected($filters['status'] === 'active')>Approved / Active</option>
            <option value="draft" @selected($filters['status'] === 'draft')>Draft / Inactive</option>
        </select>
    </div>
    <div class="button-row">
        <button type="submit" class="primary-button">Filter Recommendations</button>
        <a class="secondary-button" href="{{ route('admin.recommendation-actions.export', request()->query()) }}">Export CSV</a>
        <a class="secondary-button" href="{{ route('admin.recommendation-actions.index') }}" @unless($hasActiveFilters) aria-disabled="true" tabindex="-1" @endunless>Clear Filters</a>
    </div>
</form>

<section class="table-card">
    <h2>Catalogue Actions</h2>
    <div class="table-scroll">
        <table class="catalogue-table">
            <thead>
                <tr>
                    <th><a href="{{ $sortUrl('title') }}" style="color: inherit; text-decoration: none;">Action {{ $sortGlyph('title') }}</a></th>
                    <th><a href="{{ $sortUrl('status') }}" style="color: inherit; text-decoration: none;">Status {{ $sortGlyph('status') }}</a></th>
                    <th>Approval</th>
                    <th>Condition Metadata</th>
                    <th><a href="{{ $sortUrl('updated') }}" style="color: inherit; text-decoration: none;">Updated {{ $sortGlyph('updated') }}</a></th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($actions as $action)
                    @php($actionWasSubmitted = old('_form_context') === 'action-'.$action->id)
                    <tr>
                        <td>
                            <span class="catalogue-title">{{ $action->title }}</span>
                            <span class="catalogue-description">{{ $action->description }}</span>
                        </td>
                        <td>
                            <span class="status-pill {{ $action->is_active ? 'active' : 'draft' }}">{{ $action->status_label }}</span>
                        </td>
                        <td>
                            {{ $action->approver?->name ?? 'Not approved' }}<br>
                            <span class="catalogue-description">{{ $action->approved_at?->format('M d, Y g:i A') ?? 'Activation pending' }}</span>
                        </td>
                        <td>
                            <span class="metadata-preview">{{ $metadataValue($action) ?: 'No conditions recorded' }}</span>
                        </td>
                        <td>{{ $action->updated_at?->format('M d, Y g:i A') }}</td>
                        <td>
                            <details class="row-editor" @if ($actionWasSubmitted && $errors->any()) open @endif>
                                <summary aria-label="Edit recommendation {{ $action->title }}">Edit Recommendation</summary>
                                <form method="POST" action="{{ route('admin.recommendation-actions.update', $action) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="_form_context" value="action-{{ $action->id }}">
                                    <div class="field">
                                        <label for="title-{{ $action->id }}">Action Title</label>
                                        <input id="title-{{ $action->id }}" name="title" value="{{ $actionWasSubmitted ? old('title', $action->title) : $action->title }}" maxlength="140" required @if ($actionWasSubmitted && $errors->has('title')) aria-invalid="true" aria-describedby="recommendation-error-title" @endif>
                                    </div>
                                    <div class="field">
                                        <label for="description-{{ $action->id }}">Action Description</label>
                                        <textarea id="description-{{ $action->id }}" name="description" required @if ($actionWasSubmitted && $errors->has('description')) aria-invalid="true" aria-describedby="recommendation-error-description" @endif>{{ $actionWasSubmitted ? old('description', $action->description) : $action->description }}</textarea>
                                    </div>
                                    <div class="field">
                                        <label for="metadata-{{ $action->id }}">Condition Metadata JSON</label>
                                        <textarea id="metadata-{{ $action->id }}" name="condition_metadata" @if ($actionWasSubmitted && $errors->has('condition_metadata')) aria-invalid="true" aria-describedby="recommendation-error-condition_metadata" @endif>{{ $actionWasSubmitted ? old('condition_metadata', $metadataValue($action)) : $metadataValue($action) }}</textarea>
                                    </div>
                                    <label class="checkbox-field">
                                        <input type="checkbox" name="is_active" value="1" @checked($actionWasSubmitted ? old('is_active') : $action->is_active) @if ($actionWasSubmitted && $errors->has('is_active')) aria-invalid="true" aria-describedby="recommendation-error-is_active" @endif>
                                        <span>Activate action</span>
                                    </label>
                                    <label class="checkbox-field">
                                        <input type="checkbox" name="approval_confirmed" value="1" @checked($actionWasSubmitted && old('approval_confirmed')) @if ($actionWasSubmitted && $errors->has('approval_confirmed')) aria-invalid="true" aria-describedby="recommendation-error-approval_confirmed" @endif>
                                        <span>Approval confirmed</span>
                                    </label>
                                    <button type="submit" class="primary-button" aria-label="Update recommendation {{ $action->title }}">Update Recommendation</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty-cell">
                            No recommendation actions yet. Add draft actions only after the proposed wording is ready for review.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.table-pagination', ['paginator' => $actions, 'pageSizes' => $pageSizes, 'currentPageSize' => $perPage])
</section>
@endsection
