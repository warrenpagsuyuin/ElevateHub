.seeker-name-cell { display: flex; align-items: center; gap: 10px; min-width: 0; }
.seeker-detail-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    flex: 0 0 20px;
    padding: 0;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #9aa4b2;
    cursor: pointer;
    transition: color 0.15s ease, background-color 0.15s ease, transform 0.15s ease;
}
.seeker-detail-button:hover, .seeker-detail-button:focus-visible {
    color: var(--brand-red, #ef3b24);
    background: rgba(227, 60, 36, 0.08);
    outline: none;
    transform: translateY(-1px);
}
.seeker-detail-button:focus-visible { box-shadow: 0 0 0 3px rgba(227, 60, 36, 0.18); }
.seeker-detail-button svg { display: block; width: 15px; height: 15px; }
.seeker-detail-name { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.seeker-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 23, 42, 0.46);
}
.seeker-modal-backdrop.open { display: flex; }
.seeker-modal {
    position: relative;
    width: min(100%, 520px);
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    border: 1px solid rgba(15, 23, 42, 0.06);
    border-radius: 12px;
    background: #fff;
    padding: 28px 24px 20px;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.2);
}
.seeker-modal-close {
    position: absolute;
    top: 14px;
    right: 14px;
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #98a2b3;
    font-size: 28px;
    line-height: 1;
    cursor: pointer;
}
.seeker-modal-close:hover, .seeker-modal-close:focus-visible {
    background: rgba(227, 60, 36, 0.08);
    color: var(--brand-red, #ef3b24);
    outline: none;
}
.seeker-modal-header { margin: 0 0 18px; padding-right: 30px; }
.seeker-modal-title {
    margin: 0;
    color: var(--text-primary, var(--ink, #111));
    font-size: 26px;
    font-weight: 800;
    overflow-wrap: anywhere;
}
.seeker-modal-subtitle {
    margin: 8px 0 0;
    color: var(--text-muted, var(--muted, #667085));
    font-size: 14px;
    line-height: 1.5;
}
.seeker-modal-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 16px; }
.seeker-detail-card {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 5px;
    padding: 14px 12px;
    border: 1px solid var(--line, #e8edf3);
    border-radius: 8px;
    background: #fafbfc;
}
.seeker-detail-label {
    color: var(--text-muted, var(--muted, #667085));
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
}
.seeker-detail-value {
    color: var(--text-primary, var(--ink, #111));
    font-size: 15px;
    font-weight: 700;
    overflow-wrap: anywhere;
}
.seeker-detail-value a { color: var(--brand-red, #ef3b24); text-decoration: none; overflow-wrap: anywhere; }
.seeker-detail-value a:hover, .seeker-detail-value a:focus-visible { text-decoration: underline; outline: none; }
.seeker-detail-card.full-span { grid-column: 1 / -1; }
@media (max-width: 640px) {
    .seeker-modal { padding: 26px 18px 18px; }
    .seeker-modal-grid { grid-template-columns: 1fr; }
}