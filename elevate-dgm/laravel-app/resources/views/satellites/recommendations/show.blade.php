@extends('layouts.app')
@section('title', 'Recommendation Review | Elevate DGM')
@push('styles')
@include('satellites.recommendations.styles')
@endpush
@section('content')
<div class="recommendation-page">
    <a href="{{ route('satellites.recommendations.index', $satellite) }}">Back to Recommendations</a>
    <h1>Recommendation Review</h1>
    <p>{{ $recommendation->member?->display_name }} · {{ $satellite->display_name }}</p>
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if (session('error'))<p class="recommendation-error" role="alert">{{ session('error') }}</p>@endif
    @if ($errors->any())<div class="recommendation-error" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <p class="recommendation-notice">Human review is required. Recommendations provide decision support only. Approval records a human decision; the system does not automatically contact, discipline, or exclude members.</p>
    <section class="recommendation-card">
        <h2>{{ $recommendation->action_title }}</h2>
        <p class="recommendation-copy">{{ $recommendation->action_description }}</p>
        <dl class="recommendation-metadata">
            <div><dt>Status</dt><dd>{{ $recommendation->effective_status }}</dd></div>
            <div><dt>Assigned by</dt><dd>{{ $recommendation->assigner?->name ?? 'Not recorded' }}</dd></div>
            <div><dt>Assigned reviewer</dt><dd>{{ $recommendation->assignee?->name ?? 'Not recorded' }}</dd></div>
            <div><dt>Created</dt><dd>{{ $recommendation->created_at?->format('M d, Y g:i A') }}</dd></div>
            <div><dt>Reviewed by</dt><dd>{{ $recommendation->reviewer?->name ?? 'Awaiting review' }}</dd></div>
            <div><dt>Reviewed at</dt><dd>{{ $recommendation->reviewed_at?->format('M d, Y g:i A') ?? 'Awaiting review' }}</dd></div>
            <div><dt>Expires</dt><dd>{{ $recommendation->expires_at?->format('M d, Y g:i A') ?? 'No expiry set' }}</dd></div>
            <div><dt>Classification</dt><dd>{{ $recommendation->engagementResult?->classification_label ?? 'Not available' }}</dd></div>
        </dl>
        @if ($recommendation->assignment_notes)<h3>Assignment notes</h3><p class="recommendation-copy">{{ $recommendation->assignment_notes }}</p>@endif
        @if ($recommendation->review_notes)<h3>Review notes</h3><p class="recommendation-copy">{{ $recommendation->review_notes }}</p>@endif
        <a class="recommendation-button" href="{{ route('satellites.engagement.show', [$satellite, $recommendation->member]) }}">Review engagement explanation</a>
    </section>
    @if ($recommendation->is_reviewable)
    <section class="recommendation-card">
        <h2>Record human decision</h2>
        @if ($recommendation->status === \App\Models\Recommendation::STATUS_MODIFIED)<p>Modified wording awaits a final approval or rejection.</p>@endif
        <form method="post" action="{{ route('satellites.recommendations.review', [$satellite, $recommendation]) }}" class="recommendation-form">
            @csrf @method('PATCH')
            <label>Review notes (required for modification or rejection)<textarea name="review_notes" maxlength="3000">{{ old('review_notes') }}</textarea></label>
            <label>Modified action wording (required when choosing Modify)<textarea name="modified_action_description" maxlength="2000">{{ old('modified_action_description', $recommendation->action_description) }}</textarea></label>
            <div class="recommendation-buttons">
                <button class="recommendation-button primary" type="submit" name="status" value="Approved">Approve</button>
                <button class="recommendation-button" type="submit" name="status" value="Modified">Modify</button>
                <button class="recommendation-button" type="submit" name="status" value="Rejected">Reject</button>
            </div>
        </form>
    </section>
    @else
        <p>This recommendation is finalized or expired. Its review history is retained.</p>
    @endif
    <section class="recommendation-card">
        <h2>Review history</h2>
        <div class="recommendation-scroll"><table class="recommendation-table">
            <thead><tr><th>Action</th><th>Reviewer / actor</th><th>Date and time</th><th>Recorded decision</th></tr></thead>
            <tbody>@forelse ($history as $entry)
                <tr><td>{{ ucfirst(str_replace('recommendation.', '', $entry->event)) }}</td><td>{{ $reviewerNames[$entry->actor_id] ?? 'Not recorded' }}</td><td>{{ $entry->created_at?->format('M d, Y g:i:s A') }}</td><td>{{ $entry->after_state['status'] ?? '' }}<p class="recommendation-copy">{{ $entry->after_state['review_notes'] ?? $entry->after_state['assignment_notes'] ?? '' }}</p>@if ($entry->event === 'recommendation.modified')<p class="recommendation-copy">{{ $entry->after_state['action_description_snapshot'] ?? '' }}</p>@endif</td></tr>
            @empty
                <tr><td colspan="4">No separate history entries were recorded for this recommendation.</td></tr>
            @endforelse</tbody>
        </table></div>
    </section>
</div>
@endsection
