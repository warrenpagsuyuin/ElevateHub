<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Elevate DGM')</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        @include('partials.sidebar-styles')
        @include('partials.table-pagination-styles')
        @include('partials.status-tabs-styles')
        {{-- The styles stack contains raw CSS; the layout owns its style element. --}}
        @stack('styles')
        @include('partials.sidebar-responsive-overrides')
        @include('partials.accessibility-styles')
    </style>
    @vite('resources/css/shared-theme.css')
</head>
<body class="antialiased">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    @auth
        <div class="page app-shell">
            <aside class="sidebar app-sidebar" id="app-sidebar" aria-label="Site navigation">
                <div class="brand sidebar-logo">
                    <div class="brand-icon">
                        <img src="{{ asset('assets/images/elevate-logo.png') }}" alt="Elevate logo">
                    </div>
                </div>
                @include('layouts.sidebar')
            </aside>

            <main class="main app-main" id="main-content" tabindex="-1">
                <div class="page-container">
                    @yield('content')
                </div>
            </main>
        </div>

        @include('partials.sidebar-scripts')
    @else
        <main class="guest-main" id="main-content" tabindex="-1">
            <div class="page-container">
                @yield('content')
            </div>
        </main>
    @endauth
    @stack('scripts')
    <script>{!! file_get_contents(resource_path('js/accessibility.js')) !!}</script>
</body>
</html>
