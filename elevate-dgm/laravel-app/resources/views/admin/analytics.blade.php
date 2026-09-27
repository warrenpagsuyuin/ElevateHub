@extends('layouts.app')

@section('title', 'Analytics Dashboard | Elevate DGM')

@push('styles')
.header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 26px; }
        .header h1 { margin: 0; font-size: 32px; font-weight: 900; }
        .analytics-link { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; padding: 0 16px; border-radius: 8px; background: #111; color: #fff; text-decoration: none; font-size: 13px; font-weight: 900; }
        .analytics-link:hover { background: #e33c24; color: #fff; }
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
@endpush

@section('content')
<div data-analytics-url="{{ route('admin.analytics.data') }}">
<div class="header">
            <h1>Analytics Dashboard</h1>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a class="analytics-link" href="{{ route('admin.engagement.index') }}">Engagement Classification</a>
                <a class="analytics-link" href="{{ route('admin.recommendation-actions.index') }}">Recommendation Catalogue</a>
                <a class="analytics-link" href="{{ route('admin.analytics.sentiment') }}">Sentiment Analysis</a>
            </div>
        </div>
        <div class="analytics-status" id="analytics-status">Loading analytics data...</div>
        <div class="analytics-stack" id="analytics-sections"></div>
</div>
@endsection

@push('scripts')
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
@endpush
