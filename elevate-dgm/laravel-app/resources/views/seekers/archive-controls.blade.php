@if ($errors->any())
    <div class="notice error" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
@if (session('archive_undo'))
    <form method="POST" action="{{ route('seekers.archive.undo') }}" class="archive-undo">
        @csrf <input type="hidden" name="token" value="{{ session('archive_undo') }}">
        <button class="data-table-link-button" type="submit">Undo</button>
        <span>Return to Active with the current status unchanged.</span>
    </form>
@endif
@unless ($hideArchiveBulk ?? false)
<div class="archive-bulk" aria-label="Bulk archive actions">
    <span data-archive-selected aria-live="polite">0 selected</span>
    <button type="button" class="data-table-link-button" data-archive-bulk="{{ $filters['view'] === 'archive' ? 'restore' : 'archive' }}" data-action-url="{{ route('seekers.archive.bulk') }}" disabled>{{ $filters['view'] === 'archive' ? 'Restore selected' : 'Archive selected' }}</button>
</div>
@endunless
<dialog id="archive-dialog" class="archive-dialog" aria-labelledby="archive-dialog-title">
    <form method="POST" id="archive-dialog-form">
        @csrf
        <h2 id="archive-dialog-title">Archive seeker</h2>
        <p data-archive-description></p>
        <div data-archive-ids></div>
        <div data-restore-fields hidden>
            <label for="archive-next-status">Status after restore</label>
            <select name="status" id="archive-next-status">
                <option value="">Keep current status</option>
                @foreach (array_diff(\App\Models\Seeker::STATUSES, config('seekers.final_statuses')) as $nonFinal)<option value="{{ $nonFinal }}">{{ $nonFinal }}</option>@endforeach
            </select>
            <p>If the status is final, choose an active status or explicitly confirm below.</p>
            <label class="archive-check"><input type="checkbox" name="keep_final" value="1"> Keep final status, stay active</label>
        </div>
        <label for="archive-note">Reason / note <span data-note-required>(required)</span></label>
        <textarea id="archive-note" name="note" maxlength="1000" rows="3"></textarea>
        <div class="archive-dialog-actions"><button type="button" class="data-table-link-button tertiary" data-archive-cancel>Cancel</button><button type="submit" class="data-table-button primary" data-archive-submit>Archive</button></div>
    </form>
</dialog>
@push('scripts')
<script>
(() => {
    const dialog = document.getElementById('archive-dialog');
    const form = document.getElementById('archive-dialog-form');
    const selected = () => [...document.querySelectorAll('[data-archive-select]:checked')];
    const refresh = () => {
        document.querySelector('[data-archive-selected]').textContent = selected().length + ' selected';
        document.querySelector('[data-archive-bulk]').disabled = selected().length === 0;
    };
    document.querySelectorAll('[data-archive-select]').forEach(el => el.addEventListener('change', refresh));
    document.querySelectorAll('[data-archive-select-all]').forEach(el => el.addEventListener('change', () => {
        document.querySelectorAll('[data-archive-select]').forEach(box => box.checked = el.checked); refresh();
    }));
    document.querySelectorAll('[data-archive-action], [data-archive-bulk]').forEach(button => button.addEventListener('click', () => {
        form.reset();
        const action = button.dataset.archiveAction || button.dataset.archiveBulk;
        const restore = action === 'restore';
        form.action = button.dataset.actionUrl;
        document.getElementById('archive-dialog-title').textContent = restore ? 'Restore seekers' : 'Archive seekers';
        form.querySelector('[data-archive-description]').textContent = button.dataset.name || selected().length + ' selected seekers';
        form.querySelector('[data-restore-fields]').hidden = !restore;
        form.querySelectorAll('[data-restore-fields] input, [data-restore-fields] select').forEach(el => el.disabled = !restore);
        form.querySelector('[data-note-required]').hidden = restore;
        form.querySelector('[name=note]').required = !restore;
        const submit = form.querySelector('[data-archive-submit]'); submit.textContent = restore ? 'Restore' : 'Archive'; submit.disabled = false;
        const ids = form.querySelector('[data-archive-ids]'); ids.replaceChildren();
        if (button.dataset.archiveBulk) {
            for (const [name,value] of [['action',action], ...selected().map(el => ['ids[]',el.value])]) {
                const input = document.createElement('input'); input.type='hidden'; input.name=name; input.value=value; ids.append(input);
            }
        }
        dialog.showModal();
    }));
    form.querySelector('[data-archive-cancel]').addEventListener('click', () => dialog.close());
    form.addEventListener('submit', () => { const button=form.querySelector('[data-archive-submit]'); button.disabled=true; button.textContent='Saving?'; });
})();
</script>
@endpush
