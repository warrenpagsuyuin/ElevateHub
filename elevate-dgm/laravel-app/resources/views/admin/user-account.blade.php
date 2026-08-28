<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $user->name }} Account</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f3f4f6; color: #111; }
        .page { display: flex; min-height: 100vh; background: #f3f4f6; }
        .sidebar { width: 260px; background: #000; color: #fff; display: flex; flex-direction: column; flex-shrink: 0; padding: 18px 14px; }
        .sidebar .brand { display: flex; justify-content: center; align-items: center; height: 110px; background: #111; padding: 14px; border-radius: 8px; margin-bottom: 18px; }
        .sidebar .brand-icon img { display: block; width: 228px; max-width: 100%; height: auto; object-fit: contain; }
        .menu { display: flex; flex-direction: column; gap: 8px; flex: 1; }
        .menu a { display: flex; align-items: center; color: #e33c24; text-decoration: none; padding: 15px 18px; border-radius: 8px; font-weight: 800; font-size: 16px; }
        .menu a.active { background: #fff; color: #111; }
        .signout { margin-top: auto; padding-top: 20px; display: flex; justify-content: center; }
        .signout button { width: 170px; border: none; padding: 12px 20px; border-radius: 999px; background: #e33c24; color: #fff; font-weight: 800; cursor: pointer; }
        .main { flex: 1; padding: 30px 32px; }
        .header { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 22px; }
        .header h1 { margin: 0; font-size: 32px; font-weight: 900; }
        .back-link { color: #e33c24; font-weight: 900; text-decoration: none; }
        .account-card { max-width: 720px; border-radius: 16px; background: #fff; padding: 28px; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08); }
        .account-grid { display: grid; grid-template-columns: 170px 1fr; gap: 16px; font-size: 16px; }
        .label { color: #64748b; font-weight: 800; }
        .value { color: #111827; font-weight: 900; }
        @media (max-width: 768px) { .page { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .main { padding: 20px; } .account-grid { grid-template-columns: 1fr; } }
        @include('partials.sidebar-styles')
    </style>
</head>
<body>
<div class="page">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-icon">
                <img src="{{ asset('assets/images/elevate-logo.png') }}" alt="Elevate logo">
            </div>
        </div>
        <nav class="menu">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <a href="{{ route('admin.satellites') }}">Satellites</a>
            <a href="{{ route('admin.members') }}">Members</a>
            <a href="{{ route('admin.seekers') }}">Seekers</a>
            <a href="{{ route('admin.users') }}" class="active">Users</a>
            <a href="{{ route('admin.reports') }}">Reports</a>
            <a href="{{ route('admin.analytics') }}">Analytics</a>
        </nav>
        <div class="signout">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Sign out</button>
            </form>
        </div>
    </aside>
    <main class="main">
        <div class="header">
            <h1>{{ $user->name }}</h1>
            <a class="back-link" href="{{ route('admin.users') }}">Back to Users</a>
        </div>

        <section class="account-card">
            <div class="account-grid">
                <div class="label">Name</div><div class="value">{{ $user->name }}</div>
                <div class="label">Email</div><div class="value">{{ $user->email }}</div>
                <div class="label">Role</div><div class="value">{{ ucwords(str_replace('_', ' ', $user->role ?: 'member')) }}</div>
                <div class="label">District</div><div class="value">{{ $user->district ?: 'Unassigned' }}</div>
                <div class="label">Created</div><div class="value">{{ $user->created_at?->format('M d, Y') }}</div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
