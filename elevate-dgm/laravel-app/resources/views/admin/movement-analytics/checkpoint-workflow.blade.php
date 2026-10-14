<div id="qc-workflow" data-base="{{ route('analytics.checkpoints.index') }}" data-csrf="{{ csrf_token() }}">
    <button class="mv-button" type="button" id="qc-open">Review checkpoint close</button>
    <dialog class="mv-drawer" id="qc-dialog" aria-labelledby="qc-title">
        <div class="mv-drawer-head"><div><p class="mv-eyebrow">CHECKPOINT CONTROL</p><h2 id="qc-title">Review checkpoint</h2></div><button class="mv-button" type="button" id="qc-dismiss" aria-label="Close checkpoint review">&times;</button></div>
        <label for="qc-basis">Evidence source</label><select id="qc-basis"><option value="system">Dated system records</option><option value="legacy_import">Imported as-of reports</option></select>
        <button class="mv-button" type="button" id="qc-refresh" style="margin-top:10px">Refresh review</button>
        <p id="qc-message" role="status" aria-live="polite" style="margin:16px 0"></p>
        <div id="qc-results" hidden>
            <div id="qc-summary"></div>
            <details open><summary>Close blockers</summary><div id="qc-blockers"></div></details>
            <details><summary>Data quality and reconciliation</summary><div id="qc-issues"></div></details>
            <details><summary>Reviewed unit counts</summary><div class="mv-table-wrap"><table id="qc-counts"></table></div></details>
            <form id="qc-attest" class="qc-form">
                <h3>Confirm unit evidence</h3><p>Confirm only counts verified against evidence for the checkpoint date. Empty or conflicting counts cannot be confirmed.</p>
                <label for="qc-unit">Unit</label><select id="qc-unit" required></select>
                <label for="qc-attest-reason">Verification reason</label><textarea id="qc-attest-reason" required minlength="10" maxlength="2000" rows="2"></textarea>
                <label for="qc-attest-reference">As-of evidence reference</label><input id="qc-attest-reference" required minlength="10" maxlength="2000" placeholder="Dated source report or record reference">
                <button class="mv-button" type="submit" id="qc-attest-submit">Confirm this unit</button>
            </form>
            <form id="qc-finalize" class="qc-form" hidden>
                <h3>Finalize movement checkpoint</h3><p>All units must have complete, confirmed evidence. This freezes every unit and parent total. Later corrections create a new audited version.</p>
                <label for="qc-reason">Close reason</label><textarea id="qc-reason" required minlength="10" maxlength="2000" rows="2"></textarea>
                <label for="qc-reference">Evidence reference</label><input id="qc-reference" required minlength="10" maxlength="2000">
                <label class="qc-check"><input type="checkbox" id="qc-ack" required> I reviewed the reconciliation, quality issues and as-of evidence.</label>
                <label for="qc-confirm">Type the checkpoint key shown above to confirm</label><input id="qc-confirm" required autocomplete="off">
                <button class="mv-button mv-dark" type="submit" id="qc-finalize-submit">Close checkpoint permanently</button>
            </form>
            <form id="qc-correction" class="qc-form" hidden>
                <h3>Record an audited correction</h3><p>Original snapshots stay unchanged. This creates a new version and recalculates parent totals. A reason and evidence reference are required.</p>
                <label for="qc-correction-unit">Leaf unit</label><select id="qc-correction-unit" required></select>
                <div id="qc-correction-values"></div>
                <label for="qc-correction-reason">Correction reason</label><textarea id="qc-correction-reason" required minlength="10" maxlength="2000" rows="2"></textarea>
                <label for="qc-correction-reference">Correction evidence reference</label><input id="qc-correction-reference" required minlength="10" maxlength="2000">
                <button class="mv-button" type="submit">Record correction version</button>
            </form>
        </div>
    </dialog>
</div>
@push('styles')
.qc-form { display:grid; gap:10px; margin-top:24px; border-top:1px solid #ddd; padding-top:15px; }
.qc-form label,#qc-dialog > label { font-size:12px; font-weight:650; }
.qc-form input:not([type=checkbox]),.qc-form textarea { width:100%; border:1px solid #aaa; border-radius:6px; padding:10px; color:#171717; background:#fff; font:inherit; font-size:13px; }
.qc-form .qc-check { display:flex; align-items:start; gap:10px; font-weight:400; }
.qc-form .qc-check input { margin-top:4px; }
#qc-correction-values { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
#qc-correction-values label { display:grid; gap:5px; }
#qc-dialog button:disabled { opacity:.5; cursor:not-allowed; }
#qc-counts { min-width:520px; }
@endpush
@push('scripts')
<script>{!! file_get_contents(resource_path('js/checkpoint-close.js')) !!}</script>
@endpush
