.satisfaction-form-section{margin:0 0 var(--ui-space-6,24px);padding:24px;background:var(--ui-surface,#ffffff);border:1px solid var(--ui-border,#e5e7eb);border-radius:var(--radius-lg,16px);box-shadow:0 4px 16px rgba(15,23,42,0.04)}
.satisfaction-top-bar{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:16px 20px;margin-bottom:20px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px}
.satisfaction-privacy-pill{display:inline-flex;align-items:center;gap:8px;font-size:13px;color:#475467;line-height:1.45;margin:4px 0 0}
.satisfaction-meta-group{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.satisfaction-satellite-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#1A1A1A;color:#ffffff;border-radius:9999px;font-size:13px;font-weight:600;border:1px solid #450d07;margin:0}
.satisfaction-progress-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#ffffff;color:#334155;border:1px solid #cbd5e1;border-radius:9999px;font-size:13px;font-weight:700;transition:all .2s ease}
.satisfaction-progress-badge.is-complete{background:#ecfdf5;color:#065f46;border-color:#059669}
.satisfaction-satellite-picker{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:end;gap:10px;max-width:480px;margin:0 0 16px}
.satisfaction-satellite-picker label{grid-column:1/-1;font-size:13px;font-weight:700;color:#344054}
.satisfaction-satellite-picker select{min-height:44px;padding:8px 12px;border:1px solid var(--select-border,#d0d5dd);border-radius:var(--radius-sm,8px);background:var(--select-bg,#fff);color:var(--select-text,#111827);font:inherit}
.satisfaction-satellite-picker button{min-height:44px}
.satisfaction-date-field{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0}
.satisfaction-date-field label{font-size:13px;font-weight:700;color:#344054;margin:0}
.satisfaction-form-section [type=date]{min-height:44px;padding:6px 12px;border:1px solid var(--select-border,#d0d5dd);border-radius:var(--radius-sm,8px);font:inherit;font-size:14px;font-weight:600;color:var(--select-text,#111827);background:var(--select-bg,#fff)}
.satisfaction-category{margin-top:20px;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;background:#ffffff}
.satisfaction-category-header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 18px;background:#1A1A1A;color:#ffffff;border-bottom:2px solid #450d07}
.satisfaction-category-header h3{margin:0;font-size:15px;font-weight:700;color:#ffffff;letter-spacing:-0.01em}
.satisfaction-category-count{font-size:12px;font-weight:600;color:#cbd5e1;background:rgba(255,255,255,0.1);padding:3px 10px;border-radius:9999px}
.satisfaction-question{min-width:0;margin:0;padding:14px 18px;border:0;border-bottom:1px solid #f1f5f9;display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:16px;transition:background-color .15s ease}
.satisfaction-question:last-child{border-bottom:0}
.satisfaction-question.is-answered{background:#f8fafc}
.satisfaction-question legend{float:left;width:100%;font-weight:600;font-size:14.5px;line-height:1.45;color:var(--ui-text,#1e293b);padding:0;margin:0}
.satisfaction-options{display:flex;gap:10px;flex-shrink:0}
.satisfaction-options label{min-width:104px;cursor:pointer;margin:0}
.satisfaction-options input{position:absolute;width:1px;height:1px;opacity:0}
.satisfaction-options label>span{display:flex;align-items:center;justify-content:center;min-height:44px;padding:0 18px;gap:6px;background:#ffffff;border:1.5px solid #cbd5e1;border-radius:9999px;color:#334155;font-weight:700;font-size:14px;transition:all .15s ease}
.satisfaction-options label:hover>span{border-color:#64748b;background:#f8fafc}
.satisfaction-options input:checked+span{background:var(--btn-dark-bg,#1A1A1A);border:2px solid var(--btn-dark-border,#450d07);color:var(--btn-dark-text,#ffffff);box-shadow:0 2px 6px rgba(0,0,0,0.14)}
.satisfaction-check{display:none}.satisfaction-options input:checked+span .satisfaction-check{display:inline}
.satisfaction-options input:focus-visible+span,.satisfaction-form-section [type=date]:focus-visible{outline:2px solid var(--select-focus,#450d07);outline-offset:2px}
.satisfaction-error{grid-column:1/-1;display:block;color:var(--ui-error,#dc2626);margin-top:4px;font-weight:700;font-size:13px}
.satisfaction-form-section .btn-primary{min-height:44px;border-radius:9999px;padding:0 28px}
.feedback-actions{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-top:20px;padding-top:16px;border-top:1px solid #e2e8f0}
.feedback-help{margin:0;font-size:13.5px;color:#475467;font-weight:500}
@media(max-width:680px){
    .satisfaction-form-section{padding:16px}
    .satisfaction-question{grid-template-columns:1fr;gap:10px}
    .satisfaction-options{width:100%}
    .satisfaction-options label{flex:1;min-width:0}
    .feedback-actions button{width:100%}
}
