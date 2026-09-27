@extends('layouts.app')
@section('title', 'Recommendations | Elevate DGM')
@push('styles')
@include('satellites.recommendations.styles')
@endpush
@section('content')
<div class="recommendation-page">
    <h1>Recommendations</h1>
    <p>{{ $satellite->display_name }} — review assigned actions and decisions.</p>
    <a class="recommendation-button" href="{{ route('satellites.engagement.index', $satellite) }}">Engagement Analytics</a>
    <p class="recommendation-notice">Human review is required. Recommendations provide decision support only. The system does not automatically contact, discipline, or exclude members.</p>
    <form method="get" class="recommendation-card recommendation-form">
        <label>Search <input name="search" value="{{ $filters['search'] }}" placeholder="Member, action, or reviewer"></label>
        <label>Status <select name="status"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }} ({{ $summary[$status] ?? 0 }})</option>@endforeach</select></label>
        <button class="recommendation-button" type="submit">Apply filters</button>
    </form>
    <section class="recommendation-card">
        <h2>Assigned recommendations</h2>
        <div class="recommendation-scroll"><table class="recommendation-table">
            <thead><tr><th>Member</th><th>Action</th><th>Status</th><th>Assigned reviewer</th><th>Created</th></tr></thead>
            <tbody>@forelse ($recommendations as $recommendation)
                <tr><td>{{ $recommendation->member?->display_name }}</td><td><a href="{{ route('satellites.recommendations.show', [$satellite, $recommendation]) }}">{{ $recommendation->action_title }}</a></td><td>{{ $recommendation->effective_status }}</td><td>{{ $recommendation->assignee?->name ?? 'Not recorded' }}</td><td>{{ $recommendation->created_at?->format('M d, Y g:i A') }}</td></tr>
            @empty
                <tr><td colspan="5">No recommendations found. Open a member's engagement result to assign an eligible approved action for review.</td></tr>
            @endforelse</tbody>
        </table></div>
        {{ $recommendations->links() }}
    </section>
</div>
@endsection
