@extends('layouts.app')
@section('title', $title.' | Elevate DGM')
@section('content')
<div class="directory-page">
    <a href="{{ route('admin.dashboard') }}">&larr; Back to dashboard</a>
    <div class="page-header"><h1>{{ $title }}</h1></div>
    @if ($directory === 'dgroups')
        <p>A DGroup is a leader with at least {{ config('dashboard_directory.DGROUP_MIN_MEMBERS') }} linked members in the same district.</p>
        <p>Leader distribution: @foreach ($distribution as $band => $count)<span class="directory-badge">{{ $band }} members: <strong>{{ $count }}</strong></span> @endforeach</p>
    @elseif ($directory === 'campuses')
        <p>Distinct campus/school names recorded in districts. Shared campuses count once; placeholders are excluded.</p>
    @elseif ($directory === 'dgroup-members')
        <p>Member-role records, including inactive members. Leaders are counted separately.</p>
    @endif
    <form method="get" class="directory-filters">
        <label>Search<input type="search" name="search" value="{{ $search }}" maxlength="200" placeholder="Search {{ strtolower($title) }}"></label>
        <label>Category<select name="category"><option value="">All categories</option>@foreach (config('dashboard_directory.categories') as $hub)<option value="{{ strtolower($hub) }}" @selected($category === $hub)>{{ $hub }}</option>@endforeach</select></label>
        <label>District<select name="satellite"><option value="">All districts</option>@foreach ($satellites as $satellite)<option value="{{ $satellite->id }}" @selected($selectedSatellite === $satellite->id)>{{ $satellite->name }}</option>@endforeach</select></label>
        <label>Per page<select name="per_page">@foreach ($pageSizes as $size)<option @selected($size === $perPage)>{{ $size }}</option>@endforeach</select></label>
        <input type="hidden" name="sort" value="{{ $sort }}"><input type="hidden" name="direction" value="{{ $direction }}">
        <button type="submit">Apply filters</button><a href="{{ route('admin.dashboard.directory', $directory) }}">Reset</a>
    </form>
    <p role="status"><strong>Showing {{ number_format($records->total()) }} {{ $directory === 'dgroup-members' ? 'members' : strtolower($title) }}</strong>@if ($search !== '') of {{ number_format($scopeCount) }} in this scope @endif @if ($category) &middot; {{ $category }} @endif</p>
    <div class="directory-table" role="region" aria-label="{{ $title }} list" tabindex="0">
    <table>
        <thead><tr>
            @include('admin.dashboard-directory.sort-heading', ['column' => 'name', 'label' => $directory === 'campuses' ? 'Campus / school' : ($directory === 'dgroups' ? 'DGroup leader' : 'Full name')])
            @if ($directory === 'campuses')<th>Districts</th><th>Categories</th>
            @else
                @include('admin.dashboard-directory.sort-heading', ['column' => 'district', 'label' => 'District'])
                @if ($directory === 'dgroup-members')@include('admin.dashboard-directory.sort-heading', ['column' => 'leader', 'label' => 'DGroup leader'])
                @else @include('admin.dashboard-directory.sort-heading', ['column' => 'assigned', 'label' => 'Assigned members']) @endif
                @include('admin.dashboard-directory.sort-heading', ['column' => 'campus', 'label' => 'Campus / school'])
                @include('admin.dashboard-directory.sort-heading', ['column' => 'category', 'label' => 'Category'])
                <th>Contact info</th>
                @include('admin.dashboard-directory.sort-heading', ['column' => 'registered', 'label' => 'Date registered'])
            @endif
        </tr></thead>
        <tbody>
        @forelse ($records as $record)
            <tr>
            @if ($directory === 'campuses')
                <td>{{ $record['name'] }}</td><td>{{ implode(', ', $record['satellites']) }}</td><td>{{ implode(', ', $record['categories']) }}</td>
            @else
                <td><strong>{{ $record->display_name }}</strong></td><td>{{ $record->satellite?->name ?: 'Unassigned' }}</td>
                @if ($directory === 'dgroup-members')<td>{{ $record->leader?->display_name ?: 'Unassigned' }}@if (!$record->leader && $record->dgroup_leader_name)<small>Unresolved: {{ $record->dgroup_leader_name }}</small>@endif</td>
                @else <td>{{ $record->assigned_count }}</td> @endif
                <td>{{ $record->campus_school ?: '-' }}</td><td>{{ $record->satellite?->hub ?: 'Unassigned' }}</td>
                <td>{{ $record->email_address ?: $record->email ?: '-' }}<small>{{ $record->contact_number ?: $record->phone ?: '-' }}</small></td><td>{{ $record->created_at?->format('M j, Y') ?: '-' }}</td>
            @endif
            </tr>
        @empty
            <tr><td colspan="{{ $directory === 'campuses' ? 3 : 7 }}">{{ $search !== '' ? 'No results match your search.' : 'No '.($category ? $category.' ' : '').($directory === 'dgroup-members' ? 'members' : strtolower($title)).' yet' }}</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <nav class="directory-pagination" aria-label="Pagination">
        @if ($records->previousPageUrl())<a rel="prev" href="{{ $records->previousPageUrl() }}">Previous</a>@endif
        <span>Page {{ $records->currentPage() }} of {{ $records->lastPage() }} &middot; Rows {{ $records->firstItem() ?? 0 }}&ndash;{{ $records->lastItem() ?? 0 }}</span>
        @if ($records->nextPageUrl())<a rel="next" href="{{ $records->nextPageUrl() }}">Next</a>@endif
    </nav>
</div>
@endsection
@push('styles')
.directory-page { background:#fff; border:1px solid #e2e5eb; border-radius:16px; padding:24px; min-width:0; }
.directory-page a { color:#b3200f; text-underline-offset:3px; cursor:pointer; }
.directory-page a:hover { text-decoration-thickness:2px; }
.directory-page :is(a,button,input,select):focus-visible { outline:3px solid #2459d3; outline-offset:3px; }
.directory-filters { display:flex; gap:16px; flex-wrap:wrap; align-items:end; margin:24px 0; }
.directory-filters label { display:flex; flex-direction:column; gap:6px; font-weight:600; }
.directory-filters input,.directory-filters select { padding:10px; border:1px solid #aab1bd; border-radius:8px; max-width:100%; }
.directory-filters button { background:#b3200f; color:white; padding:12px 18px; border:0; border-radius:8px; cursor:pointer; }
.directory-table { overflow:auto; }
.directory-table table { border-collapse:collapse; width:100%; }
.directory-table th { text-align:left; text-transform:uppercase; font-size:12px; background:#faf6f5; }
.directory-table :is(th,td) { padding:16px 12px; border-bottom:1px solid #e6e9ef; }
.directory-table small { display:block; margin-top:5px; color:#5b6575; }
.directory-badge { display:inline-block; margin:4px 12px 4px 0; }
.directory-pagination { display:flex; flex-wrap:wrap; justify-content:space-between; gap:16px; padding-top:24px; }
@media(max-width:600px) { .directory-page { padding:16px; } .directory-filters label { width:100%; } }
@endpush
