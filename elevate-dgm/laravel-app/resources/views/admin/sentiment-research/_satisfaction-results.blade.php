@php
    $result = $satisfaction;
    $display = function ($row, $show = true) {
        if ($row['responses'] === 0) return ['value' => '—', 'count' => 'No responses yet'];
        if (!$show || $row['responses'] < \App\Services\SatisfactionService::MIN_RESPONSES) return ['value' => '—', 'count' => 'Not enough responses yet'];
        return ['value' => round($row['yes'] / $row['total'] * 100).'%', 'count' => number_format($row['yes']).' of '.number_format($row['total'])];
    };
    $cards = collect([['name' => 'Overall satisfaction', ...$result['overall']]])->concat($result['categories']->map(fn ($row) => ['name' => $row['name'], ...$row]));
    $calendarStart = $isLiveDashboard ? now(config('app.timezone'))->startOfMonth() : \Illuminate\Support\Carbon::create($selectedDashboardYear, array_search($selectedDashboardMonth, \App\Services\SatelliteDashboardPeriod::MONTHS, true) + 1, 1, 0, 0, 0, config('app.timezone'));
    $calendarEnd = $calendarStart->copy()->endOfMonth();
    $calendarPadding = $calendarStart->dayOfWeek;
@endphp
<div class="satisfaction-cards">
@foreach($cards as $card)
    @php($metric = $display($card, $card['show'] ?? true))
    <div class="elevate-stat-card satisfaction-card" aria-label="{{ $card['name'] }}: {{ $metric['value'] }}{{ $metric['count'] ? ', '.$metric['count'] : '' }}"><span class="stat-card-label">{{ $card['name'] }}</span><strong class="stat-card-value">{{ $metric['value'] }}</strong>@if($metric['count'])<span class="satisfaction-count">{{ $metric['count'] }}</span>@endif</div>
@endforeach
</div>
<div class="satisfaction-results-layout">
    <section class="panel satisfaction-calendar" aria-label="Responses by day">
        <div class="satisfaction-calendar-head">
            <h3>{{ $calendarStart->format('F Y') }}</h3>
            <p>Select a highlighted date (5+ responses) to view daily satisfaction ratings. Days with fewer than {{ \App\Services\SatisfactionService::MIN_RESPONSES }} responses are shown as &lt;{{ \App\Services\SatisfactionService::MIN_RESPONSES }}.</p>
            <div class="satisfaction-calendar-legend" aria-hidden="true">
                <span><i class="legend-dot is-viewable"></i> 5+ responses (viewable)</span>
                <span><i class="legend-dot is-suppressed"></i> &lt;5 (privacy hidden)</span>
            </div>
        </div>
        @if($result['calendar']->filter(fn ($item) => str_starts_with($item->service_date, $calendarStart->format('Y-m')))->isEmpty())<p>No responses for this month.</p>@endif
        <div class="satisfaction-calendar-grid">@foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $weekday)<span class="satisfaction-weekday">{{ $weekday }}</span>@endforeach
            @for($i = 0; $i < $calendarPadding; $i++)<span aria-hidden="true"></span>@endfor
            @for($day = 1; $day <= $calendarEnd->day; $day++)
                @php($date = $calendarStart->copy()->day($day)->toDateString())
                @php($calendarCell = $result['calendar']->get($date))
                @php($responses = (int) ($calendarCell->responses ?? 0))
                @php($suppressed = (bool) ($calendarCell->suppressed ?? false))
                @php($future = $date > now(config('app.timezone'))->toDateString())
                @php($cellState = $responses === 0 ? 'is-empty' : (($responses < \App\Services\SatisfactionService::MIN_RESPONSES || $suppressed) ? 'is-suppressed' : 'has-data'))
                @if($future)<span class="satisfaction-day is-disabled" aria-label="{{ $date }} future">{{ $day }}</span>
                @else<a class="satisfaction-day {{ $cellState }} {{ $result['filters']['day'] === $date ? 'is-selected' : '' }}" href="{{ route($satisfactionRoute, array_merge(isset($satellite) ? ['satellite' => $satellite] : [], request()->query(), ['day' => $date])) }}" @if($result['filters']['day'] === $date) aria-current="date" @endif aria-label="{{ $date }}, {{ $responses === 0 ? 'no responses' : ($responses < \App\Services\SatisfactionService::MIN_RESPONSES ? 'fewer than five responses' : ($suppressed ? 'response count withheld' : $responses.' responses')) }}"><span>{{ $day }}</span><small>{{ $responses === 0 ? '—' : ($responses < \App\Services\SatisfactionService::MIN_RESPONSES ? '<5' : ($suppressed ? '—' : number_format($responses))) }}</small></a>@endif
            @endfor
        </div>
        @if($result['filters']['day'])<div style="margin-top: 12px;"><a class="button btn-secondary" href="{{ route($satisfactionRoute, array_merge(isset($satellite) ? ['satellite' => $satellite] : [], request()->except('day'))) }}">Clear selected day</a></div>@endif
    </section>
    <section class="elevate-table-card satisfaction-table" aria-label="Satisfaction by category"><h3 class="elevate-table-heading">Satisfaction results</h3><div class="table-scroll"><table><thead><tr><th scope="col">Category</th><th scope="col">Satisfaction</th><th scope="col">Yes of answers</th></tr></thead><tbody>
        @foreach($result['categories'] as $row)
            @php($metric = $display($row, $row['show']))
            <tr><th scope="row">{{ $row['name'] }}</th><td>{{ $metric['value'] }}</td><td>{{ $metric['count'] ?: '—' }}</td></tr>
        @endforeach
    </tbody></table></div></section>
</div>
