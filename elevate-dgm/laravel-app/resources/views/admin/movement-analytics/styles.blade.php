.movement { --mv-red:#b42318; --mv-ink:#171717; --mv-muted:#62646b; --mv-line:#e5e5e7; color:var(--mv-ink); max-width:1600px; margin:auto; }
.movement h1,.movement h2,.movement h3,.movement p { margin:0; }
.movement h1 { font-size:clamp(26px,2.5vw,36px); font-weight:800; letter-spacing:-1.2px; line-height:1.2; }
.movement h2 { font-size:21px; font-weight:750; letter-spacing:-.5px; }
.movement h3 { font-size:17px; font-weight:750; }
.movement a { color:var(--mv-red); font-size:13px; font-weight:650; }
.movement .mv-eyebrow { color:var(--mv-muted); font-size:10px; letter-spacing:1.8px; font-weight:750; margin-bottom:9px; }
.movement .mv-subtitle { color:var(--mv-muted); margin-top:9px; font-size:14px; }
.mv-header,.mv-section-head,.mv-context,.mv-footer { display:flex; justify-content:space-between; align-items:center; gap:18px; }
.mv-header { margin:8px 0 28px; }
.movement .mv-button { display:inline-flex; justify-content:center; align-items:center; gap:14px; min-height:42px; padding:10px 16px; border:1px solid #b6b7bd; border-radius:7px; background:white; color:var(--mv-ink); font-size:13px; font-weight:650; cursor:pointer; text-decoration:none; white-space:nowrap; }
.movement .mv-button:hover { background:#f4f4f5; }
.movement .mv-dark { background:var(--mv-ink); border-color:var(--mv-ink); color:white; }
.movement .mv-dark:hover { background:var(--mv-red); }
.movement [aria-disabled=true] { opacity:.5; pointer-events:none; }
.mv-filters { display:flex; align-items:end; gap:16px; padding:18px 20px; background:white; border:1px solid var(--mv-line); border-radius:10px; }
.mv-filters label { display:grid; gap:7px; flex:1; font-size:11px; font-weight:700; color:var(--mv-muted); }
.mv-filter-help { color:var(--mv-muted); font-size:10px; font-weight:500; line-height:1.4; }
.mv-data-definitions { margin:12px 0 18px; padding:12px 16px; border:1px solid var(--mv-line); border-radius:8px; background:#fff; color:var(--mv-muted); font-size:12px; }
.mv-data-definitions summary { padding:0; color:var(--mv-ink); }
.mv-data-definitions dl { display:grid; grid-template-columns:max-content 1fr; gap:8px 14px; margin:12px 0 0; line-height:1.5; }
.mv-data-definitions dt { color:var(--mv-ink); font-weight:700; }
.mv-data-definitions dd { margin:0; }
.movement select { min-width:0; width:100%; height:42px; border:1px solid #acadb5; border-radius:6px; padding:0 28px 0 10px; color:var(--mv-ink); background:#fff; font-size:13px; }
.mv-context { margin:14px 0 23px; font-size:11px; color:var(--mv-muted); }
.mv-verdict { display:flex; justify-content:space-between; align-items:center; gap:28px; padding:27px 30px; background:var(--mv-ink); color:#fff; border-radius:10px; border-left:5px solid var(--mv-red); }
.mv-verdict .mv-eyebrow { color:#f4a79f; }
.mv-verdict h2 { font-size:24px; letter-spacing:-.7px; }
.mv-verdict p:not(.mv-eyebrow) { color:#d5d5d7; font-size:13px; margin-top:8px; max-width:690px; }
.movement .mv-verdict-link { color:#fff; flex-shrink:0; border-bottom:1px solid #777; padding-bottom:5px; text-decoration:none; }
.mv-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:15px; margin:18px 0 25px; }
.mv-kpi { background:#fff; border:1px solid var(--mv-line); border-radius:9px; padding:20px; }
.mv-kpi-label { font-size:12px; font-weight:700; display:flex; justify-content:space-between; gap:6px; }
.mv-kpi-value { font-size:38px; line-height:1.25; font-weight:750; letter-spacing:-1.5px; margin:12px 0 4px; font-variant-numeric:tabular-nums; }
.mv-kpi-state { color:var(--mv-muted); font-size:11px; min-height:18px; }
.mv-change { display:flex; justify-content:space-between; gap:8px; font-size:11px; margin-top:7px; color:var(--mv-muted); }
.mv-change strong { color:var(--mv-ink); font-weight:600; }
.mv-change strong.mv-decline { color:var(--mv-red); }
.mv-grid { display:grid; grid-template-columns:minmax(0,1.5fr) minmax(290px,1fr); gap:22px; align-items:start; }
.mv-grid.mv-equal { grid-template-columns:repeat(2,minmax(0,1fr)); }
.mv-panel { background:#fff; border:1px solid var(--mv-line); border-radius:10px; padding:24px; margin-bottom:22px; min-width:0; }
.mv-section-head { align-items:flex-start; margin-bottom:20px; }
.mv-chip { font-size:10px; font-weight:650; background:#f3f3f5; color:#50515a; border:1px solid #e1e1e5; border-radius:5px; padding:4px 8px; white-space:nowrap; }
.mv-note { color:var(--mv-muted); font-size:11px; line-height:1.6; margin-top:12px!important; }
.mv-tabs { display:flex; gap:5px; flex-wrap:wrap; margin:0 0 20px; }
.mv-tabs button { border:0; background:#f4f4f6; border-radius:5px; padding:9px 12px; font-size:11px; font-weight:650; cursor:pointer; min-height:36px; color:#54555d; }
.mv-tabs button[aria-pressed=true] { background:var(--mv-ink); color:#fff; }
.mv-chart-groups { display:flex; gap:24px; margin-top:15px; }
.mv-chart-group { flex:1; min-width:0; text-align:center; }
.mv-chart-bars { height:175px; display:flex; align-items:end; justify-content:center; gap:7px; border-bottom:1px solid #a5a5ad; background:repeating-linear-gradient(to top,transparent 0,transparent 43px,#f0f0f2 43px,#f0f0f2 44px); }
.mv-chart-column { height:100%; flex:1; max-width:36px; display:flex; flex-direction:column; justify-content:end; align-items:center; }
.mv-chart-column b { font-size:9px; white-space:nowrap; font-weight:600; margin-bottom:4px; }
.mv-chart-bar { width:100%; border-radius:3px 3px 0 0; min-height:2px; }
.mv-chart-group h3 { font-size:12px; margin-top:12px; }
.mv-legend { display:flex; justify-content:center; gap:12px; flex-wrap:wrap; font-size:10px; margin:20px 0 0; color:#62646b; }
.mv-swatch { display:inline-block; width:9px; height:9px; border-radius:2px; margin-right:4px; }
.mv-empty { min-height:150px; padding:28px 15px; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; border:1px dashed #d4d4d9; background:#fbfbfc; border-radius:8px; color:var(--mv-muted); font-size:12px; gap:9px; }
.mv-empty strong { color:var(--mv-ink); font-size:15px; font-weight:650; }
.mv-issue { padding:13px 0; border-bottom:1px solid #ededf0; display:flex; gap:12px; align-items:flex-start; }
.mv-issue-mark { color:var(--mv-red); background:#fff1ee; font-weight:800; font-size:11px; border-radius:4px; width:22px; height:22px; display:grid; place-items:center; flex-shrink:0; }
.mv-issue strong { display:block; font-size:12px; }
.mv-issue p { color:var(--mv-muted); font-size:11px; line-height:1.5; margin:4px 0 6px; }
.mv-issue button { background:none; border:0; padding:0; color:var(--mv-red); font-size:11px; font-weight:700; cursor:pointer; }
.movement summary { cursor:pointer; font-size:12px; font-weight:650; padding:15px 0; }
.mv-sort { display:flex; align-items:center; gap:9px; font-size:11px; white-space:nowrap; }
.mv-sort select { width:160px; }
.mv-rank-heading { display:grid; grid-template-columns:1fr 135px 116px; gap:20px; font-size:10px; color:var(--mv-muted); border-bottom:1px solid var(--mv-line); padding:0 12px 10px; }
.mv-rank-row { width:100%; display:grid; grid-template-columns:minmax(0,1fr) 135px 116px; gap:20px; align-items:center; text-align:left; padding:13px 12px; border:0; border-bottom:1px solid #efeff2; background:#fff; cursor:pointer; color:var(--mv-ink); }
.mv-rank-row:hover { background:#faf6f5; }
.mv-rank-main { display:grid; grid-template-columns:145px minmax(60px,1fr) 70px; align-items:center; gap:15px; font-size:12px; }
.mv-rank-main strong { font-size:12px; font-weight:650; }
.mv-rank-main small { color:var(--mv-muted); font-size:10px; display:block; }
.mv-track { background:#f0f0f3; height:8px; border-radius:3px; overflow:hidden; }
.mv-track span { display:block; height:100%; background:#29292d; border-radius:3px; }
.mv-rank-value { text-align:right; font-size:13px; font-weight:700; }
.mv-sparkline { width:125px; height:30px; overflow:visible; }
.mv-status-chip { display:inline-block; border-radius:5px; padding:5px 8px; background:#f2f2f4; font-size:10px; font-weight:650; color:#585861; text-align:center; }
.mv-status-chip.alert { color:#9b241a; background:#fff0ed; }
.mv-live-heading { margin:10px 0 18px; }
.mv-pipeline-numbers { display:grid; grid-template-columns:repeat(3,1fr); gap:15px; margin-bottom:20px; }
.mv-pipeline-numbers strong { font-size:29px; display:block; font-variant-numeric:tabular-nums; }
.mv-pipeline-numbers span { font-size:11px; color:var(--mv-muted); }
.mv-meter { height:10px; border-radius:4px; background:#ededf0; overflow:hidden; margin:14px 0 8px; display:flex; }
.mv-meter span { display:block; min-width:0; }
.mv-split-row { display:flex; justify-content:space-between; gap:12px; font-size:12px; margin:12px 0; }
.mv-mini-unit { border-top:1px solid #eee; padding:10px 0; font-size:11px; }
.mv-mini-unit strong { display:block; margin-bottom:5px; }
.mv-forecast-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; }
.mv-forecast-card { padding:16px; border:1px solid var(--mv-line); border-radius:7px; }
.mv-forecast-card strong { font-size:25px; display:block; margin:8px 0; }
.mv-forecast-card p { font-size:11px; color:var(--mv-muted); }
.mv-full-data summary { display:flex; justify-content:space-between; gap:12px; font-size:15px; padding:0; list-style:disclosure-closed; }
.mv-full-data summary::before { content:'+'; margin-right:6px; }
.mv-full-data[open] summary::before { content:'-'; }
.mv-full-data summary span { font-size:11px; color:var(--mv-muted); font-weight:400; flex:1; text-align:right; }
.mv-table-wrap { overflow:auto; max-height:470px; margin-top:20px; }
.movement table { border-collapse:collapse; width:100%; font-size:11px; }
.movement th,.movement td { padding:10px 12px; text-align:left; border-bottom:1px solid #e6e6e9; white-space:nowrap; }
.movement th { background:#f4f4f6; font-weight:650; position:sticky; top:0; }
.mv-footer { color:var(--mv-muted); font-size:11px; margin:3px 0 20px; }
.mv-state { background:#fff; padding:45px; border:1px solid var(--mv-line); border-radius:10px; text-align:center; color:var(--mv-muted); font-size:14px; }
.mv-support-id { display:block; margin-top:8px; font-size:11px; color:var(--mv-muted); }
.analytics-current-stats { margin:0 0 24px; }
.analytics-current-stats .mv-note { margin:0 0 12px; }
.analytics-current-stats .stats { margin-bottom:0; }
.mv-state-card { margin:0 0 20px; }
.mv-state-card[hidden] { display:none; }
.mv-state-actions { display:flex; justify-content:center; gap:12px; flex-wrap:wrap; margin-top:16px; }
.mv-state-actions .mv-button { min-height:44px; }
.mv-loader { display:inline-block; width:15px; height:15px; border:2px solid #ddd; border-top-color:var(--mv-red); border-radius:50%; animation:mv-spin 1s linear infinite; margin-right:8px; }
@keyframes mv-spin { to { transform:rotate(360deg); } }
.movement .mv-drawer { position:fixed; margin:0 0 0 auto; inset:0 0 0 auto; width:560px; max-width:100%; height:100dvh; max-height:100dvh; border:0; border-left:1px solid #ddd; padding:28px; color:var(--mv-ink); box-shadow:-15px 0 55px #0002; background:white; }
.mv-drawer::backdrop { background:#1118; }
.mv-drawer-head { display:flex; justify-content:space-between; gap:20px; align-items:center; border-bottom:1px solid #ddd; padding-bottom:22px; margin-bottom:24px; }
.mv-drawer h3 { margin:24px 0 12px; }
.mv-drawer p { font-size:13px; line-height:1.7; color:var(--mv-muted); }
.mv-drawer .mv-detail-row { display:flex; justify-content:space-between; gap:16px; padding:12px 0; font-size:12px; border-bottom:1px solid #eee; }
.mv-drawer .mv-detail-row span { color:var(--mv-muted); }
.mv-drawer button[data-unit] { border:1px solid #ddd; background:#fafafa; padding:8px; margin:5px; border-radius:5px; cursor:pointer; }
@media (max-width:1200px) { .mv-filters { flex-wrap:wrap; } .mv-filters label { flex:1 1 150px; } .mv-grid { grid-template-columns:1fr; } .mv-grid.mv-equal { grid-template-columns:1fr 1fr; } .mv-attention { max-height:410px; overflow:auto; } .mv-verdict { align-items:start; flex-direction:column; gap:16px; } .mv-rank-main { grid-template-columns:125px minmax(30px,1fr) 65px; } }
@media (max-width:850px) { .mv-kpis { grid-template-columns:1fr 1fr; } .mv-grid.mv-equal { grid-template-columns:1fr; } .mv-header { align-items:start; } .mv-header .mv-dark { padding:10px; } .mv-panel { padding:18px; } .mv-rank-heading { grid-template-columns:1fr 100px; } .mv-rank-heading span:nth-child(2) { display:none; } .mv-rank-row { grid-template-columns:1fr 100px; gap:12px; } .mv-rank-row > .mv-sparkline { display:none; } .mv-context,.mv-footer { align-items:start; flex-direction:column; gap:6px; } .mv-rank-main { grid-template-columns:minmax(90px,1fr) 65px; gap:8px; } .mv-rank-main .mv-track { display:none; } .mv-full-data summary span { display:none; } .mv-section-head { flex-wrap:wrap; } }
@media (max-width:560px) { .mv-data-definitions dl { grid-template-columns:1fr; gap:4px; } }
@media (prefers-reduced-motion:reduce) { .mv-loader { animation:none; } }
.mv-no-data { display:block; padding:6px 2px; font-size:10px; border:1px dashed #777; background:repeating-linear-gradient(45deg,#fff,#fff 4px,#eee 4px,#eee 6px); }
.mv-resolution { display:grid; gap:12px; margin-top:15px; }
.mv-resolution label { display:block; font-size:12px; }
.mv-resolution input:not([type=radio]) { display:block; width:100%; padding:9px; border:1px solid #777; }
.movement .mv-attention { max-height:650px; overflow:auto; }
.mv-rank-row > .mv-note { grid-column:1 / -1; text-align:left; }
#mv-status:has(.mv-loader)::after { content:''; display:block; width:100%; height:50px; border-radius:8px; background:linear-gradient(90deg,#eee,#fafafa,#eee); margin-top:15px; }
#mv-sample-banner { position:sticky; top:0; z-index:10; border:3px solid #b42318; background:#fff; font-weight:700; }
.movement .mv-verdict { background:#fff; color:var(--mv-ink); padding:16px 20px; border-left:3px solid #b42318; }
.movement .mv-verdict h2 { font-size:16px; line-height:1.5; }
.movement .mv-verdict p,.movement .mv-verdict .mv-verdict-link { color:var(--mv-muted); }
.mv-resolution { grid-template-columns:repeat(2,minmax(0,1fr)); }
.mv-resolution > label:nth-of-type(-n+2) { padding:10px; border:1px solid #ccc; border-radius:6px; }
.mv-resolution > br { display:none; }
.mv-resolution > label:nth-of-type(n+3),.mv-resolution > button,.mv-resolution > p { grid-column:1 / -1; }
