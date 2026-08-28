<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; overflow: hidden; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f3f4f6; color: #111; }
        .page { display: flex; min-height: 100vh; height: 100vh; overflow: hidden; background: #f3f4f6; }
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
        .main { flex: 1; width: 100%; max-width: none; height: 100vh; margin: 0; overflow-y: auto; padding: 30px 32px 44px; }
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 26px; }
        .header h1 { margin: 0; font-size: 32px; font-weight: 900; }
        .analytics-stack { display: flex; flex-direction: column; gap: 34px; }
        .analytics-section { display: flex; flex-direction: column; gap: 18px; }
        .section-title { margin: 0; color: #111; font-size: 23px; line-height: 1.2; font-weight: 900; letter-spacing: 0.05em; text-transform: uppercase; }
        .chart-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; }
        .chart-card, .data-card { min-width: 0; background: #fff; border-radius: 14px; box-shadow: 0 16px 34px rgba(17, 24, 39, 0.12); }
        .chart-card { padding: 18px 18px 14px; }
        .chart-card h3, .data-card h3 { margin: 0; color: #111; font-size: 13px; font-weight: 900; letter-spacing: 0.08em; text-transform: uppercase; }
        .plotly-chart { width: 100%; height: 310px; margin-top: 10px; }
        .data-card { padding: 18px; }
        .table-wrap { max-height: 350px; overflow: auto; margin-top: 14px; border: 1px solid #e5e7eb; border-radius: 10px; }
        .analytics-table { width: 100%; min-width: 620px; border-collapse: collapse; color: #111; background: #fff; font-size: 14px; }
        .analytics-table th, .analytics-table td { padding: 13px 14px; text-align: left; border-bottom: 1px solid #edf0f3; }
        .analytics-table th { position: sticky; top: 0; z-index: 1; background: #111; color: #fff; font-size: 12px; font-weight: 900; letter-spacing: 0.08em; text-transform: uppercase; }
        .analytics-table tbody tr:hover { background: #f9fafb; }
        .analytics-table td:first-child { color: #e8402b; font-weight: 900; letter-spacing: 0.05em; }
        .analytics-table td:nth-child(3), .analytics-table td:nth-child(4) { font-weight: 800; }
        .analytics-status { padding: 24px; border-radius: 14px; background: #fff; color: #4b5563; box-shadow: 0 16px 34px rgba(17, 24, 39, 0.10); font-weight: 800; }
        .analytics-status.error { color: #b42318; }
        @media (max-width: 1200px) { .chart-grid { grid-template-columns: 1fr; } .plotly-chart { height: 340px; } }
        @media (max-width: 900px) { body { overflow: auto; } .page { height: auto; min-height: 100vh; overflow: visible; } .main { height: auto; min-height: 0; overflow: visible; } }
        @media (max-width: 768px) { .main { padding: 20px; } .header h1 { font-size: 26px; } .section-title { font-size: 20px; } .plotly-chart { height: 285px; } .chart-card, .data-card { border-radius: 12px; } }
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
            <a href="{{ route('admin.users') }}">Users</a>
            <a href="{{ route('admin.reports') }}">Reports</a>
            <a href="{{ route('admin.analytics') }}" class="active">Analytics</a>
        </nav>
        <div class="signout"><form method="POST" action="<?php echo route('logout'); ?>"><?php echo csrf_field(); ?><button type="submit">Sign out</button></form></div>
    </aside>
    <main class="main" data-analytics-url="{{ route('admin.analytics.data') }}">
        <div class="header"><h1>Analytics Dashboard</h1></div>
        <div class="analytics-status" id="analytics-status">Loading analytics data...</div>
        <div class="analytics-stack" id="analytics-sections"></div>
    </main>
</div>
<script src="https://cdn.plot.ly/plotly-2.35.2.min.js"></script>
<script>
    (() => {
        const main = document.querySelector('[data-analytics-url]');
        const status = document.getElementById('analytics-status');
        const sections = document.getElementById('analytics-sections');
        const metrics = ['DLEADERS', 'DGROUPS', 'DMEMBERS'];
        const sectionTitles = {
            MAIN: 'ELEVATE MAIN',
            NCP: 'ELEVATE NCP',
            ICP: 'ELEVATE ICP',
        };
        const metricTitles = {
            DLEADERS: 'DLEADERS',
            DGROUPS: 'DGROUPS',
            DMEMBERS: 'DMEMBERS',
        };
        const colors = {
            2024: '#2563EB',
            2025: '#E8402B',
        };
        const numberFormatter = new Intl.NumberFormat();
        const plotConfig = {
            responsive: true,
            displaylogo: false,
            modeBarButtonsToRemove: ['lasso2d', 'select2d'],
        };

        const escapeHtml = (value) => String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const chartId = (category, metric) => `analytics-${category.toLowerCase()}-${metric.toLowerCase()}`;

        const tableRows = (categoryData) => metrics.map((metric) => {
            const metricData = categoryData[metric] || { labels: [], 2024: [], 2025: [] };

            return metricData.labels.map((label, index) => `
                <tr>
                    <td>${escapeHtml(metricTitles[metric])}</td>
                    <td>${escapeHtml(label)}</td>
                    <td>${numberFormatter.format(metricData[2024][index] ?? 0)}</td>
                    <td>${numberFormatter.format(metricData[2025][index] ?? 0)}</td>
                </tr>
            `).join('');
        }).join('');

        const sectionTemplate = (category, categoryData) => `
            <section class="analytics-section">
                <h2 class="section-title">${sectionTitles[category]}</h2>
                <div class="chart-grid">
                    ${metrics.map((metric) => `
                        <article class="chart-card">
                            <h3>${metricTitles[metric]}</h3>
                            <div class="plotly-chart" id="${chartId(category, metric)}"></div>
                        </article>
                    `).join('')}
                </div>
                <article class="data-card">
                    <h3>${sectionTitles[category]} DATA</h3>
                    <div class="table-wrap">
                        <table class="analytics-table">
                            <thead>
                                <tr>
                                    <th>Metric</th>
                                    <th>Satellite/Region</th>
                                    <th>2024</th>
                                    <th>2025</th>
                                </tr>
                            </thead>
                            <tbody>${tableRows(categoryData)}</tbody>
                        </table>
                    </div>
                </article>
            </section>
        `;

        const plotChart = (category, metric, metricData) => {
            if (!window.Plotly) {
                return;
            }

            const traces = [
                {
                    x: metricData.labels,
                    y: metricData[2024],
                    name: '2024',
                    mode: 'lines+markers',
                    line: { color: colors[2024], width: 3 },
                    marker: { size: 7, color: colors[2024] },
                    hovertemplate: '%{x}<br>2024: %{y:,}<extra></extra>',
                },
                {
                    x: metricData.labels,
                    y: metricData[2025],
                    name: '2025',
                    mode: 'lines+markers',
                    line: { color: colors[2025], width: 3 },
                    marker: { size: 7, color: colors[2025] },
                    hovertemplate: '%{x}<br>2025: %{y:,}<extra></extra>',
                },
            ];
            const layout = {
                autosize: true,
                margin: { t: 8, r: 10, b: 78, l: 52 },
                paper_bgcolor: '#ffffff',
                plot_bgcolor: '#ffffff',
                font: {
                    family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif",
                    color: '#111111',
                },
                hovermode: 'x unified',
                legend: {
                    orientation: 'h',
                    x: 0,
                    y: 1.16,
                    xanchor: 'left',
                    font: { size: 12 },
                },
                xaxis: {
                    automargin: true,
                    tickangle: -28,
                    gridcolor: '#f1f5f9',
                    linecolor: '#d1d5db',
                    zeroline: false,
                },
                yaxis: {
                    automargin: true,
                    rangemode: 'tozero',
                    gridcolor: '#e5e7eb',
                    linecolor: '#d1d5db',
                    tickformat: ',d',
                    zerolinecolor: '#e5e7eb',
                },
            };

            Plotly.newPlot(chartId(category, metric), traces, layout, plotConfig);
        };

        const renderAnalytics = (data) => {
            sections.innerHTML = Object.keys(sectionTitles)
                .map((category) => sectionTemplate(category, data[category] || {}))
                .join('');

            if (!window.Plotly) {
                status.textContent = 'Charts are unavailable because Plotly.js could not be loaded.';
                status.classList.add('error');
                return;
            }

            status.hidden = true;
            requestAnimationFrame(() => {
                Object.keys(sectionTitles).forEach((category) => {
                    metrics.forEach((metric) => {
                        const metricData = (data[category] || {})[metric] || { labels: [], 2024: [], 2025: [] };

                        plotChart(category, metric, metricData);
                    });
                });
            });
        };

        fetch(main.dataset.analyticsUrl, { headers: { Accept: 'application/json' } })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`Analytics request failed with status ${response.status}`);
                }

                return response.json();
            })
            .then(renderAnalytics)
            .catch(() => {
                status.textContent = 'Unable to load analytics data.';
                status.classList.add('error');
            });
    })();
</script>
</body>
</html>
