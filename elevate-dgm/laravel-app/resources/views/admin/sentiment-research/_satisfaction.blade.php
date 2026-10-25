<section class="panel satisfaction-dashboard" aria-labelledby="satisfaction-heading">
    <div class="section-heading satisfaction-heading elevate-panel-heading">
        <div>
            <h2 id="satisfaction-heading">Satisfaction Overview</h2>
            <p>Member feedback trends and satisfaction ratings from Sunday services.</p>
        </div>
        @if(!empty($satisfaction['filters']['day']) || !empty($satisfaction['filters']['satellite']) || !empty($satisfaction['filters']['hub']) || !empty($satisfaction['filters']['category']))
            <a class="soft-badge" href="{{ route('admin.analytics.sentiment') }}" style="text-decoration: none; background: rgba(255,255,255,0.14); color: #ffffff; border: 1px solid rgba(255,255,255,0.24);">Filtered view · Reset</a>
        @endif
    </div>

    {{-- Plain-Language Summary Panel --}}
    @php
        $overall = $satisfaction['overall'] ?? ['responses' => 0, 'yes' => 0, 'total' => 0, 'show' => false];
        $totalAnswers = (int) ($overall['responses'] ?? 0);
        $overallShow = (bool) ($overall['show'] ?? false) && $totalAnswers >= \App\Services\SatisfactionService::MIN_RESPONSES && ($overall['total'] ?? 0) > 0;
        $overallPct = $overallShow ? round($overall['yes'] / $overall['total'] * 100) : null;
        $sortedCategories = collect($satisfaction['categories'] ?? [])
            ->filter(fn ($c) => ($c['show'] ?? false) && ($c['responses'] ?? 0) >= \App\Services\SatisfactionService::MIN_RESPONSES && ($c['total'] ?? 0) > 0)
            ->map(fn ($c) => [...$c, 'percentage' => round($c['yes'] / $c['total'] * 100)])
            ->sortByDesc('percentage')
            ->values();
        $validDates = collect($satisfaction['calendar'] ?? [])
            ->filter(fn ($cell) => (int) ($cell->responses ?? 0) >= \App\Services\SatisfactionService::MIN_RESPONSES && !($cell->suppressed ?? false))
            ->sortByDesc('service_date')
            ->take(6);
        $unreviewedCount = \Illuminate\Support\Facades\Schema::hasTable('satisfaction_comments')
            ? \Illuminate\Support\Facades\DB::table('satisfaction_comments')
                ->whereNull('final_sentiment')
                ->whereNotExists(function ($q) {
                    $q->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('satisfaction_comment_reviews')
                        ->whereColumn('satisfaction_comment_reviews.comment_id', 'satisfaction_comments.id')
                        ->where('satisfaction_comment_reviews.reviewer_id', auth()->id());
                })->count()
            : 0;
    @endphp

    <div class="summary-panel" style="background: #f8fafc; border: 1px solid var(--line, #e5e7eb); border-radius: var(--radius-md, 12px); padding: 18px 20px; margin-bottom: 20px;">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
            <div style="flex: 1 1 480px; min-width: 0;">
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 6px;">
                    <h3 style="margin: 0; font-size: 1.0625rem; font-weight: 700; color: var(--text-primary, #111111);">Summary at a Glance</h3>
                    @if(!empty($satisfaction['filters']['day']))
                        <span class="soft-badge" style="background: #1A1A1A; color: #ffffff;">{{ \Carbon\Carbon::parse($satisfaction['filters']['day'])->format('M j, Y') }}</span>
                    @endif
                </div>
                <p style="margin: 0; color: var(--text-muted, #475467); line-height: 1.6; font-size: 0.9375rem;">
                    @if($overallPct !== null)
                        Overall member satisfaction is at <strong style="color: var(--text-primary, #111111); font-weight: 700;">{{ $overallPct }}%</strong> ({{ number_format($overall['yes']) }} of {{ number_format($overall['total']) }} Yes answers across {{ number_format($totalAnswers) }} responses).
                        @if($sortedCategories->isNotEmpty())
                            Members rated <strong style="color: #059669;">{{ $sortedCategories->first()['name'] }}</strong> highest ({{ $sortedCategories->first()['percentage'] }}% Yes)@if($sortedCategories->count() > 1), while <strong style="color: #d97706;">{{ $sortedCategories->last()['name'] }}</strong> is at {{ $sortedCategories->last()['percentage'] }}% Yes.@else.@endif
                        @endif
                    @elseif($totalAnswers > 0)
                        <strong style="color: var(--text-primary, #111111);">{{ number_format($totalAnswers) }} {{ \Illuminate\Support\Str::plural('response', $totalAnswers) }}</strong> recorded in this view. Combined percentages are hidden when any included date or satellite group has fewer than {{ \App\Services\SatisfactionService::MIN_RESPONSES }} responses to protect member anonymity.
                    @else
                        No responses have been recorded yet for the selected filter.
                    @endif
                </p>

                @if($validDates->isNotEmpty())
                    <div class="quick-date-bar" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0;">
                        <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b;">Available dates (5+ responses):</span>
                        @foreach($validDates as $dateCell)
                            @php($isCurrentDate = ($satisfaction['filters']['day'] ?? null) === $dateCell->service_date)
                            <a href="{{ route('admin.analytics.sentiment', array_merge(request()->query(), ['day' => $dateCell->service_date])) }}"
                               class="quick-date-pill {{ $isCurrentDate ? 'is-active' : '' }}"
                               style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 9999px; font-size: 0.8125rem; font-weight: 600; text-decoration: none; transition: all 0.15s ease; {{ $isCurrentDate ? 'background: #1A1A1A; color: #ffffff; border: 2px solid #450d07;' : 'background: #ffffff; color: #1e293b; border: 1px solid #cbd5e1;' }}">
                                <span>{{ \Carbon\Carbon::parse($dateCell->service_date)->format('M j') }}</span>
                                <small style="opacity: 0.8; font-weight: 500;">({{ $dateCell->responses }})</small>
                            </a>
                        @endforeach
                        @if(!empty($satisfaction['filters']['day']))
                            <a href="{{ route('admin.analytics.sentiment', request()->except('day')) }}" style="font-size: 0.8125rem; font-weight: 600; color: #b42318; text-decoration: none; margin-left: 4px;">Show full month</a>
                        @endif
                    </div>
                @endif
            </div>
            @if($unreviewedCount > 0)
                <div style="display: flex; align-items: center; gap: 12px; background: #fffbeb; border: 1px solid #fde68a; padding: 12px 16px; border-radius: var(--radius-md, 10px);">
                    <span style="font-size: 1.25rem;" aria-hidden="true">📝</span>
                    <div>
                        <div style="font-size: 0.8125rem; font-weight: 700; color: #92400e;">Action Needed</div>
                        <div style="font-size: 0.875rem; color: #78350f;"><strong>{{ $unreviewedCount }}</strong> {{ \Illuminate\Support\Str::plural('comment', $unreviewedCount) }} awaiting review</div>
                    </div>
                    <a href="{{ route('admin.satisfaction-comments.review') }}" class="dark-action" style="padding: 8px 16px; font-size: 0.8125rem; margin-left: 8px; border-radius: 9999px; text-decoration: none;">Review Now</a>
                </div>
            @endif
        </div>
    </div>

    {{-- Filter Toolbar --}}
    <div class="satisfaction-filter-panel" style="margin-bottom: 20px; padding: 16px 20px; background: #ffffff; border: 1px solid var(--line, #e5e7eb); border-radius: var(--radius-md, 12px);">
        <form method="GET" action="{{ route('admin.analytics.sentiment') }}" class="satisfaction-filters">
            <label>
                Category
                <select name="category">
                    <option value="">All categories</option>
                    @foreach($satisfaction['categories'] as $category)
                        <option value="{{ $category['id'] }}" @selected($satisfaction['filters']['category'] === $category['id'])>{{ $category['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Satellite
                <select name="satellite">
                    <option value="">All satellites</option>
                    @foreach($satisfaction['satellites'] as $satellite)
                        <option value="{{ $satellite->id }}" @selected($satisfaction['filters']['satellite'] === $satellite->id)>{{ $satellite->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Hub
                <select name="hub">
                    <option value="">All hubs</option>
                    @foreach($satisfaction['hubs'] as $hub)
                        <option value="{{ $hub }}" @selected($satisfaction['filters']['hub'] === $hub)>{{ $hub }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Selected day
                <input type="date" name="day" value="{{ $satisfaction['filters']['day'] }}" max="{{ now(config('app.timezone'))->toDateString() }}">
            </label>
            <div style="display: flex; gap: 8px; align-items: center;">
                <button type="submit" class="dark-action" style="padding: 0 20px;">Apply filters</button>
                <a class="button btn-secondary" href="{{ route('admin.analytics.sentiment') }}" style="padding: 0 16px; border-radius: 9999px; border: 1px solid #d0d5dd; text-decoration: none; color: var(--text-primary, #111111); background: #fff;">Clear filters</a>
            </div>
        </form>
    </div>

    {{-- Stat Cards & Calendar/Table --}}
    <div>@include('admin.sentiment-research._satisfaction-results')</div>
</section>
