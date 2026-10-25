.leader-assignment-status { margin: 0 0 18px; border: 1px solid #a7cfb4; border-radius: 10px; background: #edf7ef; color: #166534; padding: 12px 16px; font-size: 14px; font-weight: 800; }
.leader-modal-backdrop { position: fixed; inset: 0; z-index: 40; display: none; align-items: center; justify-content: center; padding: 20px; background: rgba(0, 0, 0, 0.48); }
.leader-modal-backdrop.open { display: flex; }
.leader-modal { position: relative; width: min(100%, 520px); max-height: calc(100vh - 40px); overflow-y: auto; padding: 28px; border-radius: 14px; background: #fff; box-shadow: 0 24px 70px rgba(15, 23, 42, 0.24); }
.leader-modal h2 { margin: 0; color: var(--ink, #111); font-size: 22px; font-weight: 900; }
.leader-modal p { margin: 7px 0 20px; color: var(--muted, #667085); font-size: 14px; line-height: 1.5; }
.leader-modal-close { position: absolute; top: 18px; right: 18px; width: 44px; height: 44px; border: 0; border-radius: 7px; background: transparent; color: #667085; font-size: 25px; cursor: pointer; }
.leader-modal-close:hover, .leader-modal-close:focus-visible { background: var(--brand-red-soft, #fff1ee); color: var(--brand-red, #ef3b24); outline: none; }
.leader-modal label { display: grid; gap: 6px; color: #344054; font-size: 12px; font-weight: 900; }
.leader-modal select { width: 100%; min-height: 44px; border: 1px solid #d8dde6; border-radius: 8px; padding: 0 12px; background: #fff; color: var(--ink, #111); outline: none; }
.leader-modal select:focus { border-color: var(--brand-red, #ef3b24); box-shadow: 0 0 0 3px rgba(227, 60, 36, 0.12); }
.leader-modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px; }
.leader-modal-actions button { min-height: 44px; border-radius: 999px; padding: 0 17px; font-weight: 900; cursor: pointer; }
.leader-cancel { border: 1px solid #d8dde6; background: #fff; color: var(--ink, #111); }
.leader-submit:disabled { opacity: 0.6; cursor: wait; }
.leader-error { display: none; margin-bottom: 14px; border: 1px solid #fecaca; border-radius: 8px; background: #fef2f2; color: #991b1b; padding: 10px; font-size: 13px; font-weight: 800; }
.leader-error.open { display: block; }
.leader-modal-name { margin: 0 0 18px; color: var(--ink, #111); font-size: 14px; font-weight: 800; }
.leader-edit-icon { width: 14px; height: 14px; }
@media (max-width: 520px) { .leader-modal { padding: 24px 18px; } .leader-modal-actions { flex-direction: column-reverse; } .leader-modal-actions button { width: 100%; } }