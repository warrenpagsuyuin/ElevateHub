.satisfaction-dashboard{margin:0 0 30px}
.sentiment-workspace .satisfaction-heading{margin-bottom:16px}
.satisfaction-heading p{margin:5px 0}
.satisfaction-filter-panel{display:grid;gap:12px;background:var(--select-bg)}
.satisfaction-filters{display:flex;flex-wrap:wrap;gap:12px;align-items:end}
.satisfaction-filters label{display:grid;gap:5px;flex:1 1 140px;font-size:13px;font-weight:700}
.satisfaction-filters :is(select,input){min-height:44px;min-width:0;width:100%;padding:8px;border:1px solid var(--select-border);border-radius:var(--radius-sm);background:var(--select-bg);color:var(--select-text);font:inherit}
.satisfaction-filters :is(button,a){min-height:44px;display:inline-flex;align-items:center;justify-content:center}
.satisfaction-filters button.dark-action{border-radius:9999px}
.satisfaction-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:18px 0}
.satisfaction-card{display:flex;flex-direction:column;min-width:0;overflow-wrap:anywhere}
.satisfaction-count{color:var(--stat-card-label);font-size:13px}
.satisfaction-privacy-note{margin:0 0 16px;color:var(--ui-muted);font-size:13px}
.satisfaction-results-layout{display:grid;grid-template-columns:minmax(280px,1fr) minmax(0,1.5fr);gap:16px;align-items:start}
.satisfaction-calendar h3{margin:0}.satisfaction-calendar p{font-size:13px;color:var(--ui-muted)}
.satisfaction-calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:4px}
.satisfaction-weekday{text-align:center;font-size:var(--ui-text-xs);font-weight:700}
.satisfaction-day{display:grid;justify-items:center;align-content:center;min-height:52px;border:1px solid var(--select-border);border-radius:var(--radius-sm);text-decoration:none;color:var(--select-text)}
.satisfaction-day small{font-size:var(--ui-text-xs)}.satisfaction-day.is-selected{border-color:var(--select-focus);background:var(--select-option-selected-bg);font-weight:700}.satisfaction-day.is-disabled{color:var(--ui-disabled-text);background:var(--ui-disabled-surface)}
.satisfaction-table{overflow:hidden;min-width:0}.satisfaction-table .elevate-table-heading{margin:0}.satisfaction-table table{width:100%;border-collapse:collapse}.satisfaction-table :is(th,td){padding:12px;border-bottom:1px solid var(--ui-border);text-align:left}.satisfaction-table thead{background:#f1f1f1;color:#474c58}
@media(max-width:1000px){.satisfaction-cards{grid-template-columns:repeat(2,minmax(0,1fr))}.satisfaction-results-layout{grid-template-columns:1fr}}
@media(max-width:600px){.satisfaction-cards{grid-template-columns:1fr}.satisfaction-calendar{display:none}.satisfaction-filters label:last-of-type{flex-basis:100%}}
