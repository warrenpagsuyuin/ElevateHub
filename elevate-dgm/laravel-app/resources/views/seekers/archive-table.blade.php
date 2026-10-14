<div class="seekers-table-wrap data-table-scroll">
<table class="archive-table" aria-label="Archived seekers">
    <thead><tr>
        @unless($satelliteArchive ?? false)<th><input type="checkbox" data-archive-select-all aria-label="Select all seekers on this page"></th>@endunless
        @foreach (['name' => 'Name', 'school' => 'School', 'district' => 'District', 'status' => 'Status', 'archived_at' => 'Archived on'] as $key => $label)
            <th aria-sort="{{ $sort === $key ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}"><a href="{{ $sortUrl($key) }}">{{ $label }} {!! $sortGlyph($key) !!}</a></th>
        @endforeach
        <th>Archived by</th><th>Reason</th><th>Actions</th>
    </tr></thead>
    <tbody>
    @forelse ($seekers as $seeker)
        <tr>
            @unless($satelliteArchive ?? false)<td><input type="checkbox" name="ids[]" value="{{ $seeker->id }}" data-archive-select aria-label="Select {{ $seeker->name }}"></td>@endunless
            <td>{{ $seeker->name }}</td><td>{{ $seeker->school ?: 'Not provided' }}</td>
            <td>{{ $seeker->assigned_district_label }}</td><td><span class="status-pill status-{{ $seeker->status_class }}">{{ $seeker->status }}</span></td>
            <td>{{ $seeker->archived_at?->format('M d, Y H:i') }}</td>
            <td>{{ $seeker->archivedBy?->name ?: 'Not recorded' }}</td>
            <td>{{ match ($seeker->archive_reason) { 'auto_final_status' => 'Final status', 'manual' => 'Manual archive', default => 'Not recorded' } }}
                @if ($seeker->latestArchiveLog?->note)<small class="archive-note">{{ $seeker->latestArchiveLog->note }}</small>@endif
            </td>
            <td><div class="archive-row-actions"><details><summary>View</summary><dl><dt>Email</dt><dd>{{ $seeker->email }}</dd><dt>Leader</dt><dd>{{ $seeker->dgroup_leader_name ?: 'Unassigned' }}</dd><dt>Placement</dt><dd>{{ $seeker->converted_member_id ? 'Member linked' : 'Not joined' }}</dd></dl></details>
                @if($seeker->converted_member_id)
                    <span>Moved to Members</span>
                @elseif($satelliteArchive ?? false)
                    <form method="POST" action="{{ route('satellites.seekers.restore', [$satellite, $seeker]) }}" data-seeker-restore data-seeker-name="{{ $seeker->name }}">@csrf<button type="submit" class="data-table-link-button">Restore to Pending</button></form>
                @else
                    <button type="button" class="data-table-link-button" data-archive-action="restore" data-action-url="{{ route('seekers.restore', $seeker) }}" data-name="{{ $seeker->name }}">Restore</button>
                @endif
            </div></td>
        </tr>
    @empty
        <tr><td colspan="9" class="empty-state">{{ $hasActiveFilters ? 'No archived seekers match your filters.' : 'No archived seekers yet. Seekers moved here once marked with a final status.' }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
