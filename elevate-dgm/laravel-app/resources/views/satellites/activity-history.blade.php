@php
    $historyItems = collect($recentActivity);
    $historyToday = now('Asia/Manila')->startOfDay();
    $historyGroups = $historyItems->groupBy(function ($activity) use ($historyToday) {
        if (empty($activity['date'])) return 'Undated';
        $date = \Carbon\Carbon::parse($activity['date'], 'Asia/Manila')->startOfDay();
        return match (true) {
            $date->equalTo($historyToday) => 'Today',
            $date->equalTo($historyToday->copy()->subDay()) => 'Yesterday',
            $date->greaterThanOrEqualTo($historyToday->copy()->subDays(6)) => $date->format('l'),
            $date->format('Y-m') === $historyToday->format('Y-m') => 'This month',
            default => $date->format('F Y'),
        };
    });
@endphp
<label class="history-filter">All activity
    <select data-history-filter aria-label="Filter activity history">
        <option value="">All activity</option>
        @foreach($historyItems->pluck('method')->filter()->unique() as $method)<option value="type:{{ $method }}">{{ $method }} follow-up</option>@endforeach
        @foreach($historyItems->pluck('status')->filter()->unique() as $status)<option value="status:{{ $status }}">{{ $status }}</option>@endforeach
    </select>
</label>
@forelse($historyGroups as $heading => $activities)
    <section class="history-date-group" data-history-group>
        <h3>{{ $heading }}</h3>
        @foreach($activities as $activity)
            <details class="history-entry" data-history-entry data-type="{{ $activity['method'] }}" data-status="{{ $activity['status'] }}">
                <summary>
                    <strong>{{ $activity['target'] }}</strong>
                    <span>{{ $activity['method'] }} follow-up</span>
                    <span class="activity-status" data-status="{{ $activity['status'] }}"><span aria-hidden="true">{{ match ($activity['status']) { 'Successful', 'Completed' => '✓', 'Pending' => '…', 'Unsuccessful', 'Cancelled' => '×', default => '•' } }}</span>{{ $activity['status'] }}</span>
                </summary>
                <dl><div><dt>Date</dt><dd>{{ $activity['date'] }}</dd></div><div><dt>Type</dt><dd>{{ $activity['method'] }} follow-up</dd></div><div><dt>Status</dt><dd>{{ $activity['status'] }}</dd></div></dl>
            </details>
        @endforeach
    </section>
@empty
    <p class="empty-state">No activity yet.</p>
@endforelse
<p data-history-no-matches hidden>No activity yet.</p>
