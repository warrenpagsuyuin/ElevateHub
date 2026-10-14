@extends('layouts.app')

@section('title', 'Admin Dashboard | Elevate DGM')

@section('content')
<div class="admin-dashboard-page">
    <div class="page-header admin-dashboard-header">
        <div>
            <h1>Dashboard</h1>
            <p class="page-subtitle">{{ now()->format('F j, Y') }}</p>
        </div>
    </div>

    <section class="dashboard-intro" aria-labelledby="movement-summary-title">
        <p class="summary-title" id="movement-summary-title">ELEVATE MOVEMENT WIDE SUMMARY</p>
    </section>

    <div class="stats-grid admin-summary-grid">
        @foreach ($movementSummaryCards as $card)
            @include('admin.dashboard-directory.card', ['card' => $card])
        @endforeach
    </div>
</div>
@endsection

@push('styles')
        .admin-summary-card a { text-decoration:none; cursor:pointer; width:fit-content; border-radius:4px; }
        .admin-summary-card a:hover { text-decoration:underline; text-underline-offset:5px; }
        .admin-summary-card a:focus-visible { outline:3px solid #2459d3; outline-offset:6px; }
        .admin-summary-card .stat-value, .admin-summary-card .summary-segment strong { color:#b3200f; }

        .admin-dashboard-page {
            min-height: 0;
        }

        .admin-dashboard-header {
            margin-bottom: 14px;
        }

        .dashboard-intro {
            margin-bottom: 22px;
        }

        .summary-title {
            margin: 0;
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .stats-grid.admin-summary-grid {
            grid-template-columns: repeat(2, minmax(280px, 1fr)) !important;
            gap: 24px;
            margin-bottom: 0;
            max-width: 100%;
            width: 100%;
            align-items: stretch;
        }

        .admin-summary-card {
            display: flex;
            align-items: stretch;
            flex-direction: column;
            justify-content: flex-start;
            gap: 18px;
            min-height: 220px;
            padding: 20px 24px 18px;
            background: #fff;
            border: 1px solid rgba(17, 24, 39, 0.08);
            border-radius: 14px;
            box-shadow: none;
        }

        .admin-summary-card .info {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin: 0;
        }

        .admin-summary-card .stat-value {
            color: #b3200f;
            font-size: clamp(2.2rem, 3.4vw, 4rem);
            line-height: 1;
            letter-spacing: 0;
            font-weight: 900;
        }

        .admin-summary-card .stat-label {
            color: var(--text-primary);
            font-size: 16px;
            font-weight: 900;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .summary-breakdown {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-top: auto;
            padding-top: 18px;
            border-top: 1px solid rgba(17, 24, 39, 0.12);
        }

        .summary-segment {
            display: grid;
            gap: 6px;
            min-width: 0;
            align-content: start;
        }

        .summary-segment strong {
            color: #b3200f;
            font-size: clamp(1.35rem, 2vw, 2.1rem);
            line-height: 1;
            font-weight: 900;
            letter-spacing: 0;
            overflow-wrap: anywhere;
        }

        .summary-segment span {
            color: var(--text-primary);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        @media (max-width: 1279px) {
            .stats-grid.admin-summary-grid {
                gap: 20px;
            }

            .admin-summary-card {
                min-height: 220px;
                padding: 24px;
            }

            .admin-summary-card .stat-value {
                font-size: 46px;
            }

            .summary-segment strong {
                font-size: 26px;
            }
        }

        @media (max-width: 900px) {
            .stats-grid.admin-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 620px) {
            .stats-grid.admin-summary-grid {
                grid-template-columns: 1fr !important;
            }

            .admin-summary-card {
                gap: 22px;
                min-height: 0;
                padding: 22px;
            }

            .admin-summary-card .stat-value {
                font-size: 40px;
            }

            .summary-breakdown {
                gap: 12px;
                padding-top: 18px;
            }

            .summary-segment strong {
                font-size: 24px;
            }

            .summary-segment span {
                font-size: 12px;
            }
        }

        @media (max-width: 599px) {
            .stats-grid.admin-summary-grid {
                grid-template-columns: 1fr !important;
            }
        }

        @media (max-height: 760px) and (min-width: 768px) {
            .admin-dashboard-header {
                margin-bottom: 10px;
            }

            .dashboard-intro {
                margin-bottom: 16px;
            }

            .admin-summary-card {
                gap: 18px;
                min-height: 206px;
                padding: 22px 24px;
            }

            .admin-summary-card .stat-value {
                font-size: 42px;
            }

            .admin-summary-card .stat-label {
                font-size: 14px;
            }

            .summary-breakdown {
                gap: 12px;
                padding-top: 16px;
            }

            .summary-segment strong {
                font-size: 24px;
            }

            .summary-segment span {
                font-size: 12px;
            }
        }

        @media (max-height: 680px) and (min-width: 768px) {
            .admin-dashboard-header .page-subtitle {
                margin-top: 4px;
            }

            .dashboard-intro {
                margin-bottom: 12px;
            }

            .admin-summary-card {
                gap: 14px;
                min-height: 180px;
                padding: 18px 20px;
            }

            .admin-summary-card .stat-value {
                font-size: 36px;
            }

            .admin-summary-card .stat-label {
                font-size: 13px;
            }

            .summary-breakdown {
                gap: 10px;
                padding-top: 14px;
            }

            .summary-segment strong {
                font-size: 22px;
            }

            .summary-segment span {
                font-size: 11px;
            }
        }
@endpush
