@php
    $role = auth()->user()?->role ?? 'member';
@endphp

<nav class="menu">
    @if ($role === 'admin')
        <a href="{{ route('admin.dashboard') }}" class="active">Dashboard</a>
        <a href="{{ route('admin.satellites') }}">Satellites</a>
        <a href="{{ route('admin.members') }}">Members</a>
        <a href="{{ route('admin.seekers') }}">Seekers</a>
        <a href="{{ route('admin.users') }}">Users</a>
        <a href="{{ route('admin.reports') }}">Reports</a>
        <a href="{{ route('admin.analytics') }}">Analytics</a>
    @else
        <a href="{{ route('dashboard') }}" class="active">Dashboard</a>
        <a href="{{ route('home') }}">Home</a>
        @if (auth()->check())
            <a href="{{ route('district.dashboard') }}">District</a>
        @endif
    @endif
</nav>

@if (auth()->check())
    <div class="signout">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Sign out</button>
        </form>
    </div>
@endif
