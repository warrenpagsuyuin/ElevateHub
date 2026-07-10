<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elevate Administrator Dashboard</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f3f4f6;
            color: #111;
        }

        .page {
            display: flex;
            min-height: 100vh;
            background: #f3f4f6;
        }

        /* SIDEBAR */
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

        .sidebar .brand-icon svg {
            width: 190px;
            height: 70px;
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
            transition: 0.15s ease;
        }

        .menu a .menu-icon {
            width: 28px;
            height: 28px;
            display: inline-grid;
            place-items: center;
        }

        .menu a .menu-icon svg {
            width: 20px;
            height: 20px;
            display: block;
            fill: currentColor;
        }

        .menu a:hover {
            background: rgba(227, 60, 36, 0.12);
            color: #ff6b53;
        }

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

        .signout form {
            margin: 0;
        }

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

        .signout button:hover {
            background: #c92f1b;
        }

        /* MAIN CONTENT */
        .main {
            flex: 1;
            width: 100%;
            max-width: none;
            margin: 0;
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

        /* CARDS */
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

        .card.red {
            background: #e33c24;
        }

        .card.black {
            background: #111;
        }

        .card .info {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .card .label {
            font-size: 14px;
            font-weight: 800;
            opacity: 0.95;
        }

        .card .value {
            font-size: 30px;
            font-weight: 900;
            line-height: 1;
        }

        .card .icon {
            width: 58px;
            height: 58px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.14);
        }

        .card .icon svg {
            width: 30px;
            height: 30px;
            fill: #fff;
        }

        /* TABLE */
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
            display: block;
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
            font-weight: 900;
            color: #fff;
            opacity: 0.95;
        }

        .table-card tbody tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }

        .table-card tbody tr:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        /* RESPONSIVE */
        @media (max-width: 1200px) {
            .stats {
                grid-template-columns: repeat(2, minmax(220px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .page {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                min-height: auto;
            }

            .sidebar .brand {
                height: 90px;
            }

            .menu {
                flex-direction: row;
                flex-wrap: wrap;
            }

            .menu a {
                padding: 12px 14px;
                font-size: 14px;
            }

            .signout {
                justify-content: flex-start;
            }

            .main {
                padding: 20px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 26px;
            }

            .table-card {
                overflow-x: auto;
            }
        }
    </style>
</head>

<body>

<div class="page">

    <aside class="sidebar">

        <div class="brand">
            <div class="brand-icon">
                <svg viewBox="0 0 140 64" xmlns="http://www.w3.org/2000/svg" aria-label="Elevate Administrator logo">
                    <text x="0" y="33" fill="#fff" font-family="Arial Black, Segoe UI, sans-serif" font-size="28" font-style="italic" font-weight="900">ELEV</text>
                    <path d="M73 35L88 9h10l5 26H93l-2-12-7 12H73z" fill="#e33c24" />
                    <text x="101" y="33" fill="#fff" font-family="Arial Black, Segoe UI, sans-serif" font-size="28" font-style="italic" font-weight="900">TE</text>
                    <text x="20" y="50" fill="#fff" font-family="Segoe UI, sans-serif" font-size="13" font-weight="800">ADMINISTRATOR</text>
                </svg>
            </div>
        </div>

        <nav class="menu">
            <a href="/admin/dashboard" class="active">Dashboard</a>
            <a href="/admin/SatellitesDashboard.php">Satellites</a>
            <a href="/admin/MembersDashboard.php">Members</a>
            <a href="/admin/SeekersDashboard.php">Seekers</a>
            <a href="/admin/UsersDashboard.php">Users</a>
            <a href="/admin/ReportsDashboard.php">Reports</a>
            <a href="/admin/AnalyticsDashboard.php">Analytics</a>
        </nav>

        <div class="signout">
            <form method="POST" action="<?php echo route('logout'); ?>">
                <?php echo csrf_field(); ?>
                <button type="submit">Sign out</button>
            </form>
        </div>

    </aside>

    <main class="main">

        <div class="header">
            <h1>Dashboard</h1>
        </div>

        <?php
            $totalSatellites = 6;
            $totalMembers = 429;
            $totalLeaders = 39;
            $totalUsers = 469;
        ?>

        <div class="stats">

            <div class="card red">
                <div class="info">
                    <span class="label">Total Satellites</span>
                    <span class="value"><?php echo $totalSatellites; ?></span>
                </div>

                <div class="icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22.54 5.46l-4-4a1 1 0 0 0-1.42 0L13.5 4.08a1 1 0 0 0 0 1.42l.7.7-6.93 6.93a1 1 0 0 0 0 1.42l1.42 1.42 6.93-6.93.7.7a1 1 0 0 0 1.42 0l3.62-3.62a1 1 0 0 0 0-1.42zM8.83 15.5a3.5 3.5 0 1 1 4.95-4.95 3.5 3.5 0 0 1-4.95 4.95z"/>
                    </svg>
                </div>
            </div>

            <div class="card black">
                <div class="info">
                    <span class="label">Total Members</span>
                    <span class="value"><?php echo $totalMembers; ?></span>
                </div>

                <div class="icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7 8c0-1.66-1.34-3-3-3S1 6.34 1 8s1.34 3 3 3 3-1.34 3-3zm16 0c0-1.66-1.34-3-3-3s-3 1.34-3 3 1.34 3 3 3 3-1.34 3-3zM12 6c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3zm-8 9c0-2.21 1.79-4 4-4h8c2.21 0 4 1.79 4 4v1H4v-1zm4-1c-1.1 0-2 .9-2 2h12c0-1.1-.9-2-2-2H8z"/>
                    </svg>
                </div>
            </div>

            <div class="card red">
                <div class="info">
                    <span class="label">Total Leaders</span>
                    <span class="value"><?php echo $totalLeaders; ?></span>
                </div>

                <div class="icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                </div>
            </div>

            <div class="card black">
                <div class="info">
                    <span class="label">Total Users</span>
                    <span class="value"><?php echo $totalUsers; ?></span>
                </div>

                <div class="icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2a5 5 0 0 1 5 5c0 2.76-2.24 5-5 5s-5-2.24-5-5a5 5 0 0 1 5-5zm0 12c4.97 0 9 2.49 9 5.5V22H3v-2.5C3 16.49 7.03 14 12 14zm0 2c-3.31 0-6 1.33-6 3.5V20h12v-.5c0-2.17-2.69-3.5-6-3.5z"/>
                    </svg>
                </div>
            </div>

        </div>

        <section class="table-card">
            <h2>Satellite Summary</h2>

            <table>
                <thead>
                    <tr>
                        <th>Satellite</th>
                        <th>Leaders</th>
                        <th>Members</th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td>Rosario</td>
                        <td>6</td>
                        <td>67</td>
                    </tr>

                    <tr>
                        <td>Maybunga</td>
                        <td>7</td>
                        <td>76</td>
                    </tr>

                    <tr>
                        <td>Kapasigan</td>
                        <td>6</td>
                        <td>67</td>
                    </tr>

                    <tr>
                        <td>Manggahan</td>
                        <td>7</td>
                        <td>76</td>
                    </tr>

                    <tr>
                        <td>Pinagbuhatan</td>
                        <td>6</td>
                        <td>67</td>
                    </tr>

                    <tr>
                        <td>Biringan</td>
                        <td>7</td>
                        <td>76</td>
                    </tr>
                </tbody>
            </table>
        </section>

    </main>

</div>

</body>
</html>
