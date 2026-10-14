@php
    $sidebarUser = auth()->user();
    $sidebarRole = str_replace([' ', '-'], '_', strtolower((string) ($sidebarUser?->role ?: 'member')));
    $sidebarRouteSatellite = request()->route('satellite');
    $sidebarActiveSatellite = $sidebarRouteSatellite instanceof \App\Models\Satellite
        ? $sidebarRouteSatellite
        : $sidebarUser?->assignedSatellites()->latest()->first();
    $sidebarIsAdmin = $sidebarRole === 'admin';
    $sidebarHasSatelliteShell = ! $sidebarIsAdmin && $sidebarActiveSatellite !== null;

    $sidebarLinkClass = fn (array|string $patterns): string => 'sidebar-link'.(request()->routeIs(...(array) $patterns) ? ' active' : '');
    $sidebarCurrentPage = fn (array|string $patterns): ?string => request()->routeIs(...(array) $patterns) ? 'page' : null;
@endphp

<nav class="menu sidebar-nav" aria-label="Primary navigation">
    @if ($sidebarIsAdmin)
        <a href="{{ route('admin.dashboard') }}" class="{{ $sidebarLinkClass('admin.dashboard') }}" @if($sidebarCurrentPage('admin.dashboard')) aria-current="page" @endif>Dashboard</a>
        <a href="{{ route('admin.satellites') }}" class="{{ $sidebarLinkClass('admin.satellites*') }}" @if($sidebarCurrentPage('admin.satellites*')) aria-current="page" @endif>Satellites</a>
        <a href="{{ route('admin.members') }}" class="{{ $sidebarLinkClass('admin.members*') }}" @if($sidebarCurrentPage('admin.members*')) aria-current="page" @endif>Members</a>
        <a href="{{ route('admin.seekers') }}" class="{{ $sidebarLinkClass('admin.seekers*') }}" @if($sidebarCurrentPage('admin.seekers*')) aria-current="page" @endif>Seekers</a>
        <a href="{{ route('admin.users') }}" class="{{ $sidebarLinkClass('admin.users*') }}" @if($sidebarCurrentPage('admin.users*')) aria-current="page" @endif>Users</a>
        <span class="sidebar-section-label">Analytics</span>
        <a href="{{ route('admin.analytics') }}" class="{{ $sidebarLinkClass('admin.analytics') }}" @if($sidebarCurrentPage('admin.analytics')) aria-current="page" @endif>Overview</a>
        <a href="{{ route('admin.analytics.sentiment') }}" class="{{ $sidebarLinkClass('admin.analytics.sentiment*') }}" @if($sidebarCurrentPage('admin.analytics.sentiment*')) aria-current="page" @endif>Sentiment Analysis</a>
    @elseif ($sidebarHasSatelliteShell)
        <a href="{{ route('satellites.dashboard', $sidebarActiveSatellite) }}" class="{{ $sidebarLinkClass('satellites.dashboard') }}" @if($sidebarCurrentPage('satellites.dashboard')) aria-current="page" @endif>Dashboard</a>
        <a href="{{ route('satellites.members', $sidebarActiveSatellite) }}" class="{{ $sidebarLinkClass('satellites.members*') }}" @if($sidebarCurrentPage('satellites.members*')) aria-current="page" @endif>Members</a>
        <a href="{{ route('satellites.seekers', $sidebarActiveSatellite) }}" class="{{ $sidebarLinkClass('satellites.seekers*') }}" @if($sidebarCurrentPage('satellites.seekers*')) aria-current="page" @endif>Seekers</a>
        <a href="{{ route('satellites.follow-ups.index', $sidebarActiveSatellite) }}" class="{{ $sidebarLinkClass('satellites.follow-ups*') }}" @if($sidebarCurrentPage('satellites.follow-ups*')) aria-current="page" @endif>Follow-Ups</a>
        <a href="{{ route('satellites.engagement.index', $sidebarActiveSatellite) }}" class="{{ $sidebarLinkClass('satellites.engagement*') }}" @if($sidebarCurrentPage('satellites.engagement*')) aria-current="page" @endif>Engagement</a>
        <a href="{{ route('feedback.create') }}" class="{{ $sidebarLinkClass('feedback*') }}" @if($sidebarCurrentPage('feedback*')) aria-current="page" @endif>Feedback</a>
    @else
        <a href="{{ route($sidebarRole === 'district' || $sidebarRole === 'district_user' ? 'district.dashboard' : 'home') }}" class="{{ $sidebarLinkClass(['home', 'district.dashboard']) }}" @if($sidebarCurrentPage(['home', 'district.dashboard'])) aria-current="page" @endif>Dashboard</a>
        <a href="{{ route('feedback.create') }}" class="{{ $sidebarLinkClass('feedback*') }}" @if($sidebarCurrentPage('feedback*')) aria-current="page" @endif>Feedback</a>
    @endif
    @if ($sidebarRole === 'leader')
        <a href="{{ route('analytics.checkpoints.index') }}" class="{{ $sidebarLinkClass('analytics.checkpoints.*') }}">Checkpoint review</a>
    @endif
</nav>

@if (auth()->check())
    <div class="signout sidebar-footer">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Sign Out</button>
        </form>
    </div>
@endif
