<form class="dashboard-period-controls" method="GET" action="{{ $periodFormAction ?? route('satellites.dashboard', $satellite) }}" data-period-form data-live-mode="{{ $isLiveDashboard ? 'true' : 'false' }}" data-current-year="{{ $dashboardCurrentYear }}" data-current-month="{{ $dashboardCurrentMonth }}">
    <input type="hidden" name="month" value="{{ $selectedDashboardMonth }}">
    <div class="period-control-group">
        <label class="period-control-label sr-only" for="satellite-period-summary">Period</label>
        <details class="month-dropdown">
            <summary id="satellite-period-summary" aria-label="Period" aria-haspopup="listbox" aria-expanded="false">
                <span class="period-select-text">@if($isLiveDashboard)<i class="live-dot" aria-hidden="true"></i>@endif<span data-period-label>{{ $selectedDashboardMonth }}</span></span>
                <svg class="select-chevron" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </summary>
            <div class="month-menu" role="listbox" aria-label="Period">
                @foreach($dashboardPeriods as $index => $period)
                    @php($disabled = $index > 0 && $selectedDashboardYear === $dashboardCurrentYear && $index > $dashboardCurrentMonth)
                    <button type="button" role="option" tabindex="-1" data-period="{{ $period }}" aria-selected="{{ $period === $selectedDashboardMonth ? 'true' : 'false' }}" aria-disabled="{{ $disabled ? 'true' : 'false' }}" @disabled($disabled)>@if($period === 'Live')<i class="live-dot" aria-hidden="true"></i>@endif{{ $period }}</button>
                @endforeach
            </div>
        </details>
    </div>
    <div class="period-year" data-year-control {{ $isLiveDashboard ? 'hidden' : '' }}>
        <label class="period-control-label sr-only" for="satellite-period-year">Year</label>
        <div class="year-select-wrap">
            <select id="satellite-period-year" name="year" aria-label="Year">
                @foreach($dashboardYears as $year)<option value="{{ $year }}" @selected($selectedDashboardYear === $year)>{{ $year }}</option>@endforeach
            </select>
            <svg class="select-chevron" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
    </div>
    <noscript>@if($isLiveDashboard)<input type="hidden" name="year" value="{{ $dashboardCurrentYear }}">@endif<select name="month" aria-label="Period">@foreach($dashboardPeriods as $index => $period)<option @selected($period === $selectedDashboardMonth) @disabled($index > 0 && $selectedDashboardYear === $dashboardCurrentYear && $index > $dashboardCurrentMonth)>{{ $period }}</option>@endforeach</select><button type="submit">Apply period</button></noscript>
</form>
