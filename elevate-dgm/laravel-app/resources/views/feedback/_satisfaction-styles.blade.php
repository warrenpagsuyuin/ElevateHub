.satisfaction-form-section{margin:0 0 var(--ui-space-6);padding:var(--ui-space-6);background:var(--ui-surface);border:1px solid var(--ui-border);border-radius:var(--radius-lg)}
.satisfaction-form-section h2{margin:0 0 var(--ui-space-2)}
.satisfaction-form-section>p{color:var(--ui-muted)}
.satisfaction-satellite-picker{grid-template-columns:minmax(0,1fr) auto;align-items:end;max-width:480px;margin:var(--ui-space-4) 0}
.satisfaction-satellite-picker label{grid-column:1/-1}
.satisfaction-satellite-picker select{min-height:44px;padding:var(--ui-space-2);border:1px solid var(--select-border);border-radius:var(--radius-sm);background:var(--select-bg);color:var(--select-text);font:inherit}
.satisfaction-satellite-picker button{min-height:44px}
.satisfaction-form-section [type=date]{min-height:44px;padding:var(--ui-space-2);border:1px solid var(--select-border);border-radius:var(--radius-sm);font:inherit;color:var(--select-text);background:var(--select-bg)}
.satisfaction-category{margin-top:var(--ui-space-6)}
.satisfaction-category h3{margin:0 0 var(--ui-space-3)}
.satisfaction-question{min-width:0;margin:0 0 var(--ui-space-4);padding:var(--ui-space-4);border:1px solid var(--ui-border);border-radius:var(--radius-sm)}
.satisfaction-question legend{font-weight:700;color:var(--ui-text);padding:0 var(--ui-space-2)}
.satisfaction-options{display:flex;gap:var(--ui-space-2)}
.satisfaction-options label{flex:1;min-width:0;cursor:pointer}
.satisfaction-options input{position:absolute;width:1px;height:1px;opacity:0}
.satisfaction-options label>span{display:flex;align-items:center;justify-content:center;min-height:44px;gap:var(--ui-space-2);background:var(--select-bg);border:1px solid var(--select-border);border-radius:9999px;color:var(--select-text);font-weight:700}
.satisfaction-options input:checked+span{background:var(--btn-dark-bg);border-color:var(--btn-dark-border);color:var(--btn-dark-text)}
.satisfaction-check{display:none}.satisfaction-options input:checked+span .satisfaction-check{display:inline}
.satisfaction-options input:focus-visible+span,.satisfaction-form-section [type=date]:focus-visible{outline:2px solid var(--select-focus);outline-offset:2px}
.satisfaction-error{display:block;color:var(--ui-error);margin-top:var(--ui-space-2);font-weight:700}
.satisfaction-form-section .btn-primary{min-height:44px;border-radius:9999px}
