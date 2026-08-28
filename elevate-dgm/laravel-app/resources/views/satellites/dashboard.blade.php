<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $satellite->display_name }} Dashboard</title>
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
        .header { position: relative; display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; margin-bottom: 14px; }
        .header h1 { margin: 0; font-size: 32px; font-weight: 900; }
        .month-dropdown { position: relative; flex-shrink: 0; }
        .month-dropdown summary { position: relative; min-width: 136px; display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 11px 38px 11px 16px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; color: #111; box-shadow: 0 8px 18px rgba(17, 24, 39, 0.08); cursor: pointer; font-size: 14px; font-weight: 900; list-style: none; }
        .month-dropdown summary::-webkit-details-marker { display: none; }
        .month-dropdown summary::after { content: ""; position: absolute; right: 16px; top: 50%; width: 8px; height: 8px; border-right: 2px solid #111; border-bottom: 2px solid #111; transform: translateY(-65%) rotate(45deg); transition: transform 0.15s ease; }
        .month-dropdown[open] summary::after { transform: translateY(-30%) rotate(225deg); }
        .month-menu { position: absolute; right: 0; top: calc(100% + 8px); z-index: 5; width: 184px; max-height: 260px; overflow-y: auto; padding: 8px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; box-shadow: 0 18px 40px rgba(17, 24, 39, 0.16); }
        .month-option { display: block; padding: 10px 12px; border-radius: 6px; color: #111; font-size: 14px; font-weight: 800; }
        .month-option.active { background: #fff1ee; color: #e33c24; }
        .dashboard-intro { margin-bottom: 26px; }
        .today-date { margin: 0 0 8px; color: #4b5563; font-size: 16px; font-weight: 700; }
        .summary-title { margin: 0; color: #111; font-size: 18px; font-weight: 900; letter-spacing: 0.08em; text-transform: uppercase; }
        .summary-grid { display: grid; grid-template-columns: repeat(2, minmax(280px, 1fr)); gap: 24px; }
        .summary-card { min-height: 238px; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; padding: 34px; background: #fff; border-radius: 16px; box-shadow: 0 16px 34px rgba(17, 24, 39, 0.12); }
        .summary-main-number { display: block; color: #e33c24; font-size: 54px; line-height: 1; font-weight: 900; }
        .summary-label { display: block; margin-top: 12px; color: #111; font-size: 14px; line-height: 1.25; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
        @media (max-width: 900px) { .summary-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .page { flex-direction: column; } .sidebar { width: 100%; min-height: auto; } .sidebar .brand { height: 90px; } .menu { flex-direction: row; flex-wrap: wrap; } .menu a { padding: 12px 14px; font-size: 14px; } .signout { justify-content: flex-start; } .main { padding: 20px; } .header { align-items: stretch; flex-direction: column; } .header h1 { font-size: 26px; } .month-dropdown { align-self: flex-start; } .summary-card { min-height: 190px; padding: 28px; } .summary-main-number { font-size: 48px; } }
        @media (max-width: 440px) { .summary-card { padding: 24px 20px; } .summary-label { font-size: 13px; } .summary-title { font-size: 15px; } }
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
            <a href="{{ route('satellites.dashboard', $satellite) }}" class="active">Dashboard</a>
            <a href="{{ route('satellites.members', $satellite) }}">Members</a>
            <a href="{{ route('satellites.seekers', $satellite) }}">Seekers</a>
            <a href="{{ route('satellites.dashboard', $satellite) }}">Analytics</a>
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
            <h1>{{ $satellite->display_name }} Dashboard</h1>
            <details class="month-dropdown">
                <summary>{{ $selectedDashboardMonth }}</summary>
                <div class="month-menu">
                    @foreach ($dashboardMonths as $month)
                        <span @class(['month-option', 'active' => $month === $selectedDashboardMonth])>{{ $month }}</span>
                    @endforeach
                </div>
            </details>
        </div>

        <section class="dashboard-intro" aria-labelledby="satellite-summary-title">
            <p class="today-date">{{ now()->format('F j, Y') }}</p>
            <p class="summary-title" id="satellite-summary-title">{{ strtoupper($satellite->display_name) }} SATELLITE SUMMARY</p>
        </section>

        <div class="summary-grid">
            @foreach ($satelliteSummaryCards as $card)
                <article class="summary-card">
                    <div>
                        <span class="summary-main-number">{{ number_format($card['total']) }}</span>
                        <span class="summary-label">{{ $card['label'] }}</span>
                    </div>
                </article>
            @endforeach
        </div>
    </main>
</div>
</body>
</html>
