<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f3f4f6; color: #111; }
        .page { display: flex; min-height: 100vh; background: #f3f4f6; }
        .sidebar { width: 260px; background: #000; color: #fff; display: flex; flex-direction: column; flex-shrink: 0; padding: 18px 14px; }
        .sidebar .brand { display: flex; justify-content: center; align-items: center; height: 110px; background: #111; padding: 14px; border-radius: 8px; margin-bottom: 18px; }
        .sidebar .brand-icon img { display: block; width: 228px; max-width: 100%; height: auto; object-fit: contain; }
        .menu { display: flex; flex-direction: column; gap: 8px; flex: 1; }
        .menu a { display: flex; align-items: center; gap: 12px; color: #e33c24; text-decoration: none; padding: 15px 18px; border-radius: 8px; font-weight: 800; font-size: 16px; transition: 0.15s ease; }
        .menu a:hover { background: rgba(227, 60, 36, 0.12); color: #ff6b53; }
        .menu a.active { background: #fff; color: #111; }
        .signout { margin-top: auto; padding-top: 20px; display: flex; justify-content: center; }
        .signout form { margin: 0; }
        .signout button { width: 170px; border: none; padding: 12px 20px; border-radius: 999px; background: #e33c24; color: #fff; font-weight: 800; cursor: pointer; }
        .signout button:hover { background: #c92f1b; }
        .main { flex: 1; width: 100%; max-width: none; margin: 0; padding: 30px 32px; }
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
        .header h1 { margin: 0; font-size: 32px; font-weight: 900; }
        .dashboard-intro { margin-bottom: 26px; }
        .today-date { margin: 0 0 8px; color: #4b5563; font-size: 16px; font-weight: 700; }
        .summary-title { margin: 0; color: #111; font-size: 18px; font-weight: 900; letter-spacing: 0.08em; text-transform: uppercase; }
        .summary-grid { display: grid; grid-template-columns: repeat(2, minmax(280px, 1fr)); gap: 24px; }
        .summary-card { min-height: 258px; display: flex; flex-direction: column; justify-content: space-between; gap: 24px; padding: 28px; background: #fff; border-radius: 14px; box-shadow: 0 16px 34px rgba(17, 24, 39, 0.12); }
        .summary-main-number { display: block; color: #e33c24; font-size: 54px; line-height: 1; font-weight: 900; }
        .summary-label { display: block; margin-top: 12px; color: #111; font-size: 14px; line-height: 1.25; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
        .summary-breakdown { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; padding-top: 22px; border-top: 1px solid #e5e7eb; }
        .summary-segment { min-width: 0; }
        .segment-number { display: block; color: #e33c24; font-size: 31px; line-height: 1; font-weight: 900; }
        .segment-label { display: block; margin-top: 9px; color: #111; font-size: 12px; line-height: 1.25; font-weight: 800; letter-spacing: 0.09em; text-transform: uppercase; }
        @media (max-width: 900px) { .summary-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .page { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .sidebar .brand { height: 90px; } .menu { flex-direction: row; flex-wrap: wrap; } .menu a { padding: 12px 14px; font-size: 14px; } .signout { justify-content: flex-start; } .main { padding: 20px; } .header h1 { font-size: 26px; } .summary-card { min-height: 230px; padding: 24px; } .summary-main-number { font-size: 48px; } .segment-number { font-size: 28px; } }
        @media (max-width: 440px) { .summary-breakdown { gap: 12px; } .summary-card { padding: 24px 20px; } .summary-label { font-size: 13px; } .segment-label { font-size: 11px; } }
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
            <a href="{{ route('admin.dashboard') }}" class="active">Dashboard</a>
            <a href="{{ route('admin.satellites') }}">Satellites</a>
            <a href="{{ route('admin.members') }}">Members</a>
            <a href="{{ route('admin.seekers') }}">Seekers</a>
            <a href="{{ route('admin.users') }}">Users</a>
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
        <div class="header"><h1>Dashboard</h1></div>
        <section class="dashboard-intro" aria-labelledby="movement-summary-title">
            <p class="today-date">{{ now()->format('F j, Y') }}</p>
            <p class="summary-title" id="movement-summary-title">ELEVATE MOVEMENT WIDE SUMMARY</p>
        </section>
        <div class="summary-grid">
            @foreach ($movementSummaryCards as $card)
                <article class="summary-card">
                    <div>
                        <span class="summary-main-number">{{ number_format($card['total']) }}</span>
                        <span class="summary-label">{{ $card['label'] }}</span>
                    </div>
                    <div class="summary-breakdown">
                        @foreach ($card['segments'] as $segment)
                            <div class="summary-segment">
                                <span class="segment-number">{{ $segment['value'] === null ? '-' : number_format($segment['value']) }}</span>
                                <span class="segment-label">{{ $segment['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>
    </main>
</div>
</body>
</html>
