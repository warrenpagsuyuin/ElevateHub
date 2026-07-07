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
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #ffffff;
            color: #111827;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .app-navbar {
            display: flex;
            min-height: 64px;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e5e7eb;
            padding: 0 24px;
        }

        .logout-form {
            margin: 0;
        }

        .logout-button {
            height: 40px;
            border: 1px solid #dc2626;
            border-radius: 8px;
            background: #dc2626;
            color: #ffffff;
            cursor: pointer;
            font: inherit;
            font-size: 14px;
            font-weight: 600;
            padding: 0 16px;
            transition: background 150ms ease, border-color 150ms ease, box-shadow 150ms ease;
        }

        .logout-button:hover {
            border-color: #b91c1c;
            background: #b91c1c;
        }

        .logout-button:focus {
            outline: none;
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.16);
        }

        main {
            padding: 24px;
        }
    </style>
</head>
<body>
    @include('layouts.navbar')
    <main>
        @include('layouts.sidebar')
        @yield('content')
    </main>
</body>
</html>
