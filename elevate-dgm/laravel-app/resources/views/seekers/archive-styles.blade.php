.archive-bulk, .archive-undo, .archive-row-actions { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.archive-table { width:100%; min-width:1080px; border-collapse:collapse; }
.archive-table th, .archive-table td { padding:16px; text-align:left; border-bottom:1px solid #e5e7eb; vertical-align:top; }
.archive-table th { background:#fff7f5; font-size:13px; }
.archive-table th a { color:#344054; }
.archive-table td { font-size:14px; }
.archive-table summary { cursor:pointer; padding:10px 0; }
.archive-note { display:block; margin-top:6px; max-width:220px; overflow-wrap:anywhere; color:#475467; }
.archive-table dd { margin:4px 0 12px; overflow-wrap:anywhere; }
.archive-dialog { width:min(480px, calc(100vw - 32px)); border:1px solid #d7dce2; border-radius:16px; padding:24px; color:#1d2939; }
.archive-dialog::backdrop { background:rgba(16,24,40,.5); }
.archive-dialog form, .archive-dialog [data-restore-fields] { display:grid; gap:12px; }
.archive-dialog [hidden] { display:none; }
.archive-dialog label { font-weight:600; }
.archive-dialog select, .archive-dialog textarea { width:100%; padding:12px; border:1px solid #98a2b3; border-radius:8px; font:inherit; box-sizing:border-box; }
.archive-dialog-actions { display:flex; justify-content:flex-end; gap:12px; margin-top:12px; }
.archive-check { display:flex; gap:8px; align-items:center; }
[data-archive-select], [data-archive-select-all] { width:18px; height:18px; accent-color:#b42318; }
.archive-dialog button:focus-visible, .archive-table a:focus-visible { outline:3px solid #2459d3; outline-offset:3px; }

.seekers-page .status-successful, .archive-table .status-successful { color:#116b34; background:#dcfce7; }
.seekers-page .status-pending, .archive-table .status-pending { color:#805000; background:#fff4ce; }
.seekers-page .status-unsuccessful, .archive-table .status-unsuccessful { color:#a11a1a; background:#fee2e2; }
