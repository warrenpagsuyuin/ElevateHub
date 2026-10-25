<?php

namespace App\Services;

use App\Models\FollowUp;
use App\Models\Member;
use App\Models\Satellite;
use Illuminate\Support\Carbon;

class AdminSatellitesPeriodData
{
    public function resolve(array $period): array
    {
        if ($period['isLiveDashboard']) {
            return ['counts' => Satellite::dashboardCounts(), 'rows' => [], 'hasData' => true];
        }

        $start = $period['dashboardStart'];
        $end = $period['dashboardEnd'];
        $satellites = Satellite::withoutGlobalScope('active')->get(['id', 'created_at', 'archived_at']);
        $members = Member::active()
            ->where('members.created_at', '>=', $start)
            ->where('members.created_at', '<', $end)
            ->selectRaw('satellite_id, COUNT(*) as members_count')
            ->selectRaw('SUM(CASE WHEN dgroup_role = ? THEN 1 ELSE 0 END) as leaders_count', [Member::ROLE_LEADER])
            ->groupBy('satellite_id')->get()->keyBy('satellite_id');
        $localStart = $start->copy()->timezone(config('app.timezone'))->toDateString();
        $localEnd = $end->copy()->timezone(config('app.timezone'))->toDateString();
        $reportQuery = FollowUp::query()->where(function ($query) use ($start, $end, $localStart, $localEnd): void {
            $query->where(fn ($dated) => $dated->whereNotNull('follow_up_date')->whereDate('follow_up_date', '>=', $localStart)->whereDate('follow_up_date', '<', $localEnd))
                ->orWhere(fn ($undated) => $undated->whereNull('follow_up_date')->where('created_at', '>=', $start)->where('created_at', '<', $end));
        });
        $hasData = $members->isNotEmpty() || (clone $reportQuery)->exists();
        $rows = [];
        $activeIds = [];
        foreach ($satellites as $satellite) {
            $existed = $satellite->created_at instanceof Carbon
                ? $satellite->created_at->lessThan($end)
                : Carbon::parse($satellite->created_at, 'UTC')->lessThan($end);
            $entry = $members->get($satellite->id);
            $rows[$satellite->id] = $hasData && $existed
                ? ['leaders' => (int) ($entry?->leaders_count ?? 0), 'members' => (int) ($entry?->members_count ?? 0)]
                : ['leaders' => null, 'members' => null];
            if ($satellite->archived_at === null) $activeIds[] = $satellite->id;
        }
        $activeRows = array_intersect_key($rows, array_flip($activeIds));
        return [
            'counts' => [
                // Archive/restore history is unavailable, so a month-end satellite total cannot be reconstructed.
                'totalSatellites' => null,
                'activeLeaders' => $hasData ? array_sum(array_column($activeRows, 'leaders')) : null,
                'activeMembers' => $hasData ? array_sum(array_column($activeRows, 'members')) : null,
                'openReports' => $hasData ? (clone $reportQuery)->whereIn('satellite_id', $activeIds)->where('status', FollowUp::STATUS_PENDING)->count() : null,
            ],
            'rows' => $rows,
            'hasData' => $hasData,
        ];
    }
}
