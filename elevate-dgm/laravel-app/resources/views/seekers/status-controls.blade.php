<dialog class="archive-dialog" data-seeker-status-dialog aria-labelledby="seeker-status-title">
    <form method="dialog">
        <h2 id="seeker-status-title">Confirm status change</h2>
        <p data-seeker-status-message></p>
        <div class="archive-dialog-actions">
            <button type="submit" value="cancel" class="data-table-link-button" autofocus>Cancel</button>
            <button type="submit" value="confirm" class="data-table-button primary">Confirm</button>
        </div>
    </form>
</dialog>
<script>{!! file_get_contents(resource_path('js/seeker-status.js')) !!}</script>
