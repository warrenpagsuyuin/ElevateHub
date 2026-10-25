@extends('layouts.app')
@section('title', 'Satisfaction | Elevate DGM')
@push('styles')
@include('admin.sentiment-research._styles')
{!! file_get_contents(resource_path('css/satellite-dashboard.css')) !!}
@include('admin.sentiment-research._satisfaction-styles')
@endpush
@section('content')
<div class="research sentiment-workspace"><section class="satisfaction-dashboard satellite-dashboard admin-satellites-period" data-satellite-dashboard data-refresh-interval="60000" data-live="{{ $isLiveDashboard ? 'true' : 'false' }}" data-year="{{ $dashboardCurrentYear }}" data-month="{{ $dashboardCurrentMonth }}">
    <div class="section-heading satisfaction-heading"><div><a href="{{ route('satellites.dashboard', $satellite) }}">← {{ $satellite->name }} dashboard</a><h1>Satisfaction</h1><p>Anonymous Yes/No answers for this satellite. Counts below five are withheld.</p><p data-dashboard-subtitle>@if($isLiveDashboard)<span data-dashboard-date>{{ $dashboardDate }}</span> · <span data-dashboard-updated>Updated {{ $dashboardUpdatedAt }}</span>@else Viewing {{ $selectedDashboardMonth }} {{ $selectedDashboardYear }} · <a href="{{ route('satellites.satisfaction', array_merge(['satellite' => $satellite], request()->except(['month','year']), ['month' => 'Live', 'year' => $dashboardCurrentYear])) }}" data-back-to-live>Back to Live</a>@endif</p></div></div>
    <div class="satisfaction-filter-panel panel"><div data-dashboard-controls>@include('components.satellite-period-controls')</div><form method="GET" action="{{ route('satellites.satisfaction', $satellite) }}" class="satisfaction-filters"><input type="hidden" name="month" value="{{ $selectedDashboardMonth }}" data-follow-period="month"><input type="hidden" name="year" value="{{ $selectedDashboardYear }}" data-follow-period="year"><label>Category<select name="category"><option value="">All categories</option>@foreach($satisfaction['categories'] as $category)<option value="{{ $category['id'] }}" @selected($satisfaction['filters']['category'] === $category['id'])>{{ $category['name'] }}</option>@endforeach</select></label><label>Selected day<input type="date" name="day" value="{{ $satisfaction['filters']['day'] }}" max="{{ now(config('app.timezone'))->toDateString() }}"></label><button type="submit" class="dark-action">Apply filters</button></form></div>
    <p class="sr-only" aria-live="polite" data-period-announce>{{ $isLiveDashboard ? 'Showing live satisfaction' : 'Showing satisfaction for '.$selectedDashboardMonth.' '.$selectedDashboardYear }}</p><div class="dashboard-refresh-status" data-dashboard-error role="alert" hidden>Unable to update. Showing previous data. <button type="button" data-dashboard-retry>Retry</button></div>
    <div data-dashboard-content>@include('admin.sentiment-research._satisfaction-results')</div>
</section></div>
@endsection
@push('scripts')<script>{!! file_get_contents(resource_path('js/satellite-dashboard.js')) !!}</script>@endpush
