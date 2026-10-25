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
        .admin-summary-card a { text-decoration:none; cursor:pointer; border-radius:4px; }
        .admin-summary-card a:hover { text-decoration:underline; text-underline-offset:5px; }

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
            min-height: 220px;
        }

        .admin-summary-card .info {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin: 0;
        }

        .admin-summary-card .stat-value {
            line-height: 1;
            font-weight: 900;
        }

        .admin-summary-card .stat-label {
            font-weight: 900;
        }

        .summary-breakdown {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-top: auto;
            padding-top: 18px;
        }

        .summary-segment {
            display: grid;
            gap: 6px;
            min-width: 0;
            align-content: start;
        }

        .summary-segment strong {
            font-size: clamp(1.35rem, 2vw, 2.1rem);
            line-height: 1;
            font-weight: 900;
            letter-spacing: 0;
            overflow-wrap: anywhere;
        }

        .summary-segment span {
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
                min-height: 0;
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
                min-height: 206px;
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
                min-height: 180px;
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
