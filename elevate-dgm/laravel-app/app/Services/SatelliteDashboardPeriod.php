<?php

namespace App\Services;

use App\Models\{FollowUp, Satellite};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SatelliteDashboardPeriod
{
    public const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    public function resolve(Request $request, Satellite $satellite): array
    {
        $now = now(config('app.timezone'));
        $dates = array_filter([
            $satellite->members()->min('members.created_at'),
            $satellite->seekers()->min('created_at'),
            FollowUp::where('satellite_id', $satellite->id)->min('follow_up_date'),
            FollowUp::where('satellite_id', $satellite->id)->min('created_at'),
        ]);
        return $this->resolveForDates($request, $dates, $now);
    }

    public function resolveMovementWide(Request $request): array
    {
        $now = now(config('app.timezone'));
        $activeSatellites = Satellite::active()->select('id');
        $dates = array_filter([
            \App\Models\Member::active()->whereIn('satellite_id', $activeSatellites)->min('members.created_at'),
        ]);

        return $this->resolveForDates($request, $dates, $now);
    }

    public function resolveAdminSatellites(Request $request): array
    {
        $now = now(config('app.timezone'));
        $firstFollowUpDate = FollowUp::min('follow_up_date');
        $dates = array_filter([
            Satellite::withoutGlobalScope('active')->min('created_at'),
            \App\Models\Member::withoutGlobalScopes()->min('created_at'),
            $firstFollowUpDate ? Carbon::parse($firstFollowUpDate, config('app.timezone'))->utc()->toDateTimeString() : null,
            FollowUp::min('created_at'),
        ]);

        return $this->resolveForDates($request, $dates, $now);
    }

    private function resolveForDates(Request $request, array $dates, Carbon $now): array
    {
        $earliest = $now->year;
        foreach ($dates as $date) $earliest = min($earliest, Carbon::parse($date, 'UTC')->timezone(config('app.timezone'))->year);
        $month = is_string($request->query('month')) ? ucfirst(strtolower(trim($request->query('month')))) : 'Live';
        $yearInput = $request->query('year');
        $year = is_scalar($yearInput) && preg_match('/^\d{4}$/', (string) $yearInput) ? (int) $yearInput : null;
        if (!in_array($month, self::MONTHS, true) || !$year || $year < $earliest || $year > $now->year) {
            $month = 'Live';
            $year = $now->year;
        }
        if ($month === 'Live') $year = $now->year;
        if ($month !== 'Live' && $year === $now->year && array_search($month, self::MONTHS, true) + 1 > $now->month) $month = self::MONTHS[$now->month - 1];
        $live = $month === 'Live';
        $start = $live ? null : Carbon::create($year, array_search($month, self::MONTHS, true) + 1, 1, 0, 0, 0, config('app.timezone'));
        $manilaNow = $now->copy()->timezone('Asia/Manila');
        return [
            'selectedDashboardMonth' => $month, 'selectedDashboardYear' => $year,
            'isLiveDashboard' => $live, 'dashboardPeriods' => ['Live', ...self::MONTHS],
            'dashboardYears' => range($earliest, $now->year), 'dashboardCurrentYear' => $now->year,
            'dashboardCurrentMonth' => $now->month, 'dashboardDate' => $manilaNow->format('F j, Y'),
            'dashboardUpdatedAt' => $manilaNow->format('g:i A'), 'dashboardStart' => $start?->copy()->utc(),
            'dashboardEnd' => $start?->copy()->addMonth()->utc(),
        ];
    }
}
