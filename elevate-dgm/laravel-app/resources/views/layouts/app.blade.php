<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Elevate DGM</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f3f4f6;
            color: #111;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .app-navbar {
            display: flex;
            min-height: 64px;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e5e7eb;
            padding: 0 24px;
            background: #fff;
        }

        .app-logo {
            display: block;
            width: min(220px, 52vw);
            height: auto;
        }

        .logout-form { margin: 0; }
        .logout-button {
            height: 40px;
            border: none;
            border-radius: 999px;
            background: #e33c24;
            color: #ffffff;
            cursor: pointer;
            font: inherit;
            font-size: 14px;
            font-weight: 800;
            padding: 0 16px;
        }
        .logout-button:hover { background: #c92f1b; }

        .page {
            display: flex;
            min-height: calc(100vh - 64px);
            background: #f3f4f6;
        }

        .sidebar {
            width: 260px;
            background: #000;
            color: #fff;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            padding: 18px 14px;
        }

        .sidebar .brand {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 110px;
            background: #111;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .sidebar .brand-icon img {
            display: block;
            width: 228px;
            max-width: 100%;
            height: auto;
            object-fit: contain;
        }

        .menu {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
        }

        .menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #e33c24;
            text-decoration: none;
            padding: 15px 18px;
            border-radius: 8px;
            font-weight: 800;
            font-size: 16px;
        }

        .menu a:hover,
        .menu a.active {
            background: #fff;
            color: #111;
        }

        .signout {
            margin-top: auto;
            padding-top: 20px;
            display: flex;
            justify-content: center;
        }

        .signout form { margin: 0; }
        .signout button {
            width: 170px;
            border: none;
            padding: 12px 20px;
            border-radius: 999px;
            background: #e33c24;
            color: #fff;
            font-weight: 800;
            cursor: pointer;
        }
        .signout button:hover { background: #c92f1b; }

        .main {
            flex: 1;
            padding: 30px 32px;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
        }

        .header h1 {
            margin: 0;
            font-size: 32px;
            font-weight: 900;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }

        .card {
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-height: 105px;
            color: #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.10);
        }

        .card.red { background: #e33c24; }
        .card.black { background: #111; }
        .card .info { display: flex; flex-direction: column; gap: 6px; }
        .card .label { font-size: 14px; font-weight: 800; opacity: 0.95; }
        .card .value { font-size: 30px; font-weight: 900; line-height: 1; }

        .table-card {
            width: 100%;
            max-width: none;
            background: #e33c24;
            border-radius: 16px;
            padding: 26px 28px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
            color: #fff;
        }

        .table-card h2 {
            margin: 0 0 16px;
            font-size: 20px;
            font-weight: 900;
            color: #fff;
        }

        .table-card table {
            width: 100%;
            border-collapse: collapse;
            color: #fff;
            font-size: 15px;
            font-weight: 600;
        }

        .table-card th,
        .table-card td {
            padding: 14px 12px;
            text-align: left;
        }

        .table-card th {
            border-bottom: 2px solid rgba(255, 255, 255, 0.18);
        }

        .table-card tbody tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }

        .table-card tbody tr:hover { background: rgba(255, 255, 255, 0.08); }

        .muted { color: rgba(255, 255, 255, 0.78); font-size: 13px; font-weight: 600; }
        .notice { margin-bottom: 18px; border: 1px solid rgba(227, 60, 36, 0.18); border-radius: 10px; background: #fff; color: #111; padding: 12px 16px; font-weight: 700; }

        @media (max-width: 1200px) { .stats { grid-template-columns: repeat(2, minmax(220px, 1fr)); } }
        @media (max-width: 768px) {
            .page { flex-direction: column; }
            .sidebar { width: 100%; min-height: auto; }
            .sidebar .brand { height: 90px; }
            .menu { flex-direction: row; flex-wrap: wrap; }
            .menu a { padding: 12px 14px; font-size: 14px; }
            .signout { justify-content: flex-start; }
            .main { padding: 20px; }
            .stats { grid-template-columns: 1fr; }
            .header h1 { font-size: 26px; }
            .table-card { overflow-x: auto; }
        }
        @include('partials.sidebar-styles')
    </style>
</head>
<body>
    @include('layouts.navbar')
    <div class="page">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-icon">
                    <img src="{{ asset('assets/images/elevate-logo.png') }}" alt="Elevate logo">
                </div>
            </div>
            @include('layouts.sidebar')
        </aside>
        <main class="main">
            @yield('content')
        </main>
    </div>
</body>
</html>
