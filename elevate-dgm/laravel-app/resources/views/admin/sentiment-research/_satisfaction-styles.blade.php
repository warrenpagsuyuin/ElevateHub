.satisfaction-dashboard{margin:0 0 24px}
.sentiment-workspace .satisfaction-heading{margin-bottom:20px}
.satisfaction-heading p{margin:5px 0}
.satisfaction-filter-panel{display:grid;gap:12px;background:var(--select-bg)}
.satisfaction-filters{display:flex;flex-wrap:wrap;gap:12px;align-items:end}
.satisfaction-filters label{display:grid;gap:6px;flex:1 1 150px;font-size:13px;font-weight:700;color:var(--text-muted,#475467)}
.satisfaction-filters :is(select,input){min-height:44px;min-width:0;width:100%;padding:8px 12px;border:1px solid var(--select-border,#d0d5dd);border-radius:var(--radius-sm,8px);background:var(--select-bg,#fff);color:var(--select-text,#111827);font:inherit}
.satisfaction-filters :is(button,a){min-height:44px;display:inline-flex;align-items:center;justify-content:center}
.satisfaction-filters button.dark-action{border-radius:9999px}
.quick-date-pill:hover{border-color:#450d07!important;transform:translateY(-1px)}
.satisfaction-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:18px 0}
.satisfaction-card{display:flex;flex-direction:column;min-width:0;overflow-wrap:anywhere}
.satisfaction-count{color:var(--stat-card-label);font-size:13px}
.satisfaction-privacy-banner{display:flex;align-items:center;gap:10px;padding:12px 16px;margin:0 0 18px;background:#f8fafc;border:1px solid #cbd5e1;border-left:4px solid #450d07;border-radius:10px}
.satisfaction-privacy-icon{font-size:16px;line-height:1}
.satisfaction-privacy-note{margin:0;color:#334155;font-size:13px;line-height:1.5}
.satisfaction-results-layout{display:grid;grid-template-columns:minmax(290px,1fr) minmax(0,1.5fr);gap:16px;align-items:start}
.satisfaction-calendar h3{margin:0 0 4px}.satisfaction-calendar p{font-size:13px;color:var(--ui-muted,#667085);margin:0 0 10px;line-height:1.45}
.satisfaction-calendar-legend{display:flex;flex-wrap:wrap;gap:14px;margin:0 0 12px;font-size:12px;color:#475467;font-weight:600}
.satisfaction-calendar-legend span{display:inline-flex;align-items:center;gap:6px}
.legend-dot{display:inline-block;width:10px;height:10px;border-radius:3px}
.legend-dot.is-viewable{background:#ecfdf5;border:1.5px solid #059669}
.legend-dot.is-suppressed{background:#fffbeb;border:1.5px dashed #d97706}
.satisfaction-calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:5px}
.satisfaction-weekday{text-align:center;font-size:var(--ui-text-xs,12px);font-weight:700;color:#475467;padding-bottom:4px}
.satisfaction-day{display:grid;justify-items:center;align-content:center;min-height:52px;border:1px solid var(--select-border,#e2e8f0);border-radius:var(--radius-sm,8px);text-decoration:none;color:var(--select-text,#1e293b);transition:all .15s ease}
.satisfaction-day small{font-size:var(--ui-text-xs,11px);margin-top:2px}
.satisfaction-day.is-empty{border-color:#f1f5f9;background:#f8fafc;color:#64748b}
.satisfaction-day.is-empty small{color:#94a3b8}
.satisfaction-day.is-suppressed{border:1px dashed #f59e0b;background:#fffbeb;color:#78350f}
.satisfaction-day.is-suppressed small{color:#b45309;font-weight:600}
.satisfaction-day.has-data{border:1.5px solid #059669;background:#ecfdf5;color:#065f46;font-weight:700;box-shadow:0 1px 2px rgba(5,150,105,0.08)}
.satisfaction-day.has-data small{color:#047857;font-weight:700}
.satisfaction-day.has-data:hover{background:#d1fae5;transform:translateY(-1px)}
.satisfaction-day.is-selected{border:2px solid #450d07!important;background:#1A1A1A!important;color:#ffffff!important;font-weight:700;box-shadow:0 4px 10px rgba(0,0,0,0.16)}
.satisfaction-day.is-selected small{color:#fca5a5!important;font-weight:700}
.satisfaction-day.is-disabled{color:var(--ui-disabled-text,#94a3b8);background:var(--ui-disabled-surface,#f1f5f9);border-color:#e2e8f0}
.satisfaction-table{overflow:hidden;min-width:0}.satisfaction-table .elevate-table-heading{margin:0}.satisfaction-table table{width:100%;border-collapse:collapse}.satisfaction-table :is(th,td){padding:12px 14px;border-bottom:1px solid var(--ui-border,#e5e7eb);text-align:left}.satisfaction-table thead{background:#f8fafc;color:#475467;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
@media(max-width:1000px){.satisfaction-cards{grid-template-columns:repeat(2,minmax(0,1fr))}.satisfaction-results-layout{grid-template-columns:1fr}}
@media(max-width:600px){.satisfaction-cards{grid-template-columns:1fr}.satisfaction-calendar{display:none}.satisfaction-filters label:last-of-type{flex-basis:100%}}
