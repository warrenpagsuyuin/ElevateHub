<section class="satisfaction-dashboard" aria-labelledby="satisfaction-heading">
    <div class="section-heading satisfaction-heading elevate-panel-heading">
        <div>
            <h2 id="satisfaction-heading">Satisfaction Overview</h2>
        </div>
    </div>

    {{-- Plain-Language Summary Panel --}}
    @php
        $totalAnswers = $satisfaction['total_responses'] ?? 0;
        $overallPct = $satisfaction['overall']['percentage'] ?? null;
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

    <div class="summary-panel" style="background: var(--surface-card, #ffffff); border: 1px solid var(--line, #e5e7eb); border-radius: var(--radius-lg, 16px); padding: 20px 24px; margin-bottom: 24px; box-shadow: var(--shadow-card, 0 8px 24px rgba(15, 23, 42, 0.06));">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
            <div style="max-width: 680px;">
                <h3 style="margin: 0 0 8px; font-size: 1.125rem; font-weight: 600; color: var(--text-primary, #111111);">Summary at a Glance</h3>
                <p style="margin: 0; color: var(--text-muted, #667085); line-height: 1.6; font-size: 0.9375rem;">
                    @if($overallPct !== null)
                        Overall member satisfaction is at <strong style="color: var(--text-primary, #111111); font-weight: 700;">{{ $overallPct }}%</strong> across recorded Sunday responses.
                        @php
                            $sortedCategories = collect($satisfaction['categories_with_results'] ?? [])->filter(fn($c) => ($c['results']['percentage'] ?? null) !== null)->sortByDesc('results.percentage');
                        @endphp
                        @if($sortedCategories->isNotEmpty())
                            Members rated <strong style="color: #059669;">{{ $sortedCategories->first()['name'] }}</strong> highest ({{ $sortedCategories->first()['results']['percentage'] }}% Yes), while <strong style="color: #d97706;">{{ $sortedCategories->last()['name'] }}</strong> is at {{ $sortedCategories->last()['results']['percentage'] }}% Yes.
                        @endif
                    @elseif($totalAnswers > 0)
                        Answers have been recorded, but privacy protection requires at least 5 responses per group before showing satisfaction percentages.
                    @else
                        No responses have been recorded yet for the selected filter.
                    @endif
                </p>
            </div>
            @if($unreviewedCount > 0)
                <div style="display: flex; align-items: center; gap: 12px; background: #fffbeb; border: 1px solid #fef3c7; padding: 10px 16px; border-radius: var(--radius-md, 10px);">
                    <span style="font-size: 1.25rem;">📝</span>
                    <div>
                        <div style="font-size: 0.8125rem; font-weight: 600; color: #92400e;">Action Needed</div>
                        <div style="font-size: 0.875rem; color: #78350f;"><strong>{{ $unreviewedCount }}</strong> {{ \Illuminate\Support\Str::plural('comment', $unreviewedCount) }} awaiting your review</div>
                    </div>
                    <a href="{{ route('admin.satisfaction-comments.review') }}" class="dark-action" style="padding: 6px 14px; font-size: 0.8125rem; margin-left: 8px;">Review Now</a>
                </div>
            @endif
        </div>
    </div>

    {{-- Filter Toolbar --}}
    <div class="satisfaction-filter-panel panel" style="margin-bottom: 24px; padding: 16px 20px; background: var(--surface-card, #ffffff); border: 1px solid var(--line, #e5e7eb); border-radius: var(--radius-lg, 16px);">
        <form method="GET" action="{{ route('admin.analytics.sentiment') }}" class="satisfaction-filters" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
            <label style="display: flex; flex-direction: column; gap: 6px; font-size: 0.8125rem; font-weight: 600; color: var(--text-muted, #667085);">
                Category
                <select name="category" style="height: 38px; border: 1px solid var(--line, #e5e7eb); border-radius: var(--radius-sm, 8px); padding: 0 12px; background: #fff;">
                    <option value="">All categories</option>
                    @foreach($satisfaction['categories'] as $category)
                        <option value="{{ $category['id'] }}" @selected($satisfaction['filters']['category'] === $category['id'])>{{ $category['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label style="display: flex; flex-direction: column; gap: 6px; font-size: 0.8125rem; font-weight: 600; color: var(--text-muted, #667085);">
                Satellite
                <select name="satellite" style="height: 38px; border: 1px solid var(--line, #e5e7eb); border-radius: var(--radius-sm, 8px); padding: 0 12px; background: #fff;">
                    <option value="">All satellites</option>
                    @foreach($satisfaction['satellites'] as $satellite)
                        <option value="{{ $satellite->id }}" @selected($satisfaction['filters']['satellite'] === $satellite->id)>{{ $satellite->name }}</option>
                    @endforeach
                </select>
            </label>
            <label style="display: flex; flex-direction: column; gap: 6px; font-size: 0.8125rem; font-weight: 600; color: var(--text-muted, #667085);">
                Hub
                <select name="hub" style="height: 38px; border: 1px solid var(--line, #e5e7eb); border-radius: var(--radius-sm, 8px); padding: 0 12px; background: #fff;">
                    <option value="">All hubs</option>
                    @foreach($satisfaction['hubs'] as $hub)
                        <option value="{{ $hub }}" @selected($satisfaction['filters']['hub'] === $hub)>{{ $hub }}</option>
                    @endforeach
                </select>
            </label>
            <label style="display: flex; flex-direction: column; gap: 6px; font-size: 0.8125rem; font-weight: 600; color: var(--text-muted, #667085);">
                Selected day
                <input type="date" name="day" value="{{ $satisfaction['filters']['day'] }}" max="{{ now(config('app.timezone'))->toDateString() }}" style="height: 38px; border: 1px solid var(--line, #e5e7eb); border-radius: var(--radius-sm, 8px); padding: 0 12px; background: #fff;">
            </label>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="dark-action" style="height: 38px; padding: 0 18px;">Apply filters</button>
                <a class="button" href="{{ route('admin.analytics.sentiment') }}" style="height: 38px; display: inline-flex; align-items: center; justify-content: center; padding: 0 16px; border: 1px solid var(--line, #e5e7eb); border-radius: var(--radius-sm, 8px); text-decoration: none; color: var(--text-primary, #111111); background: #fff;">Clear filters</a>
            </div>
        </form>
    </div>

    {{-- Stat Cards --}}
    <div>@include('admin.sentiment-research._satisfaction-results')</div>
</section>
