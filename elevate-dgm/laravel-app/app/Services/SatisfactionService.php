<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Satellite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SatisfactionService
{
    public const MIN_RESPONSES = 5;

    public function respondentContext(User $user, ?string $satelliteSlug = null): array
    {
        $satellite = $satelliteSlug !== null && $satelliteSlug !== ''
            ? Satellite::where('slug', $satelliteSlug)->firstOrFail()
            : ($user->normalizedRole() === 'admin' ? null : $user->satelliteForDistrict());
        if ($satellite && $user->normalizedRole() !== 'admin') {
            abort_unless($user->canAccessSatellite($satellite), 403);
        }
        if (! $satellite || ! $satellite->hub || ! $user->email) {
            throw ValidationException::withMessages(['satisfaction' => 'Choose an active satellite, or ask an administrator to confirm your satellite assignment.']);
        }
        $matches = Member::active()->where('satellite_id', $satellite->id)
            ->where(fn ($query) => $query->whereRaw('LOWER(email_address) = ?', [strtolower($user->email)])
                ->orWhereRaw('LOWER(email) = ?', [strtolower($user->email)]))
            ->limit(2)->get();
        if ($matches->count() > 1) {
            throw ValidationException::withMessages(['satisfaction' => 'Your member record needs to be confirmed before answering.']);
        }
        $member = $matches->first();
        return ['satellite' => $satellite, 'inDgroup' => $member && ($member->leader_member_id !== null || $member->dgroup_role === Member::ROLE_LEADER)];
    }

    public function activeCategories(bool $inDgroup)
    {
        return DB::table('satisfaction_categories')->where('active', true)->orderBy('position')->orderBy('id')->get()
            ->map(function ($category) use ($inDgroup) {
                $category->questions = DB::table('satisfaction_questions')->where('category_id', $category->id)->where('active', true)
                    ->when(! $inDgroup, fn ($query) => $query->where('dgroup_only', false))
                    ->orderBy('position')->orderBy('id')->get();
                return $category;
            })->filter(fn ($category) => $category->questions->isNotEmpty());
    }

    public function token(User $user, string $date): string
    {
        return hash_hmac('sha256', $user->getAuthIdentifier().'|'.$date, (string) config('app.key'));
    }

    public function submitted(User $user, string $date): bool
    {
        return DB::table('satisfaction_tokens')->where('token', $this->token($user, $date))->exists();
    }

    /** Saves answers and the optional comment in one transaction; returns the comment id when one was stored. */
    public function submit(User $user, string $date, array $answers, array $context, ?string $comment = null, bool $commentConsent = false): ?string
    {
        $token = $this->token($user, $date);
        if ($this->submitted($user, $date)) {
            throw ValidationException::withMessages(['service_date' => 'You already answered for this date.']);
        }
        try {
            return DB::transaction(function () use ($token, $date, $answers, $context, $comment, $commentConsent) {
                DB::table('satisfaction_tokens')->insert(['token' => $token]);
                $responseId = (string) Str::uuid();
                DB::table('satisfaction_responses')->insert([
                    'id' => $responseId, 'service_date' => $date,
                    'satellite_id' => $context['satellite']->id, 'hub' => $context['satellite']->hub,
                ]);
                $rows = [];
                foreach ($answers as $questionId => $value) {
                    $rows[] = ['response_id' => $responseId, 'question_id' => $questionId, 'yes' => $value === 'yes'];
                }
                DB::table('satisfaction_answers')->insert($rows);
                return $comment !== null ? app(SatisfactionCommentService::class)->insert($responseId, $comment, $commentConsent) : null;
            }, 3);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['service_date' => 'You already answered for this date.']);
        }
    }

    public function period(Request $request): array
    {
        $now = now(config('app.timezone'));
        $earliestDate = DB::table('satisfaction_responses')->min('service_date');
        $earliest = $earliestDate ? (int) substr($earliestDate, 0, 4) : $now->year;
        $month = is_string($request->query('month')) ? ucfirst(strtolower(trim($request->query('month')))) : 'Live';
        $year = $request->query('year');
        $year = is_scalar($year) && preg_match('/^\d{4}$/', (string) $year) ? (int) $year : null;
        if (! in_array($month, SatelliteDashboardPeriod::MONTHS, true) || ! $year || $year < $earliest || $year > $now->year) {
            $month = 'Live';
            $year = $now->year;
        }
        if ($month === 'Live') $year = $now->year;
        if ($month !== 'Live' && $year === $now->year && array_search($month, SatelliteDashboardPeriod::MONTHS, true) + 1 > $now->month) {
            $month = SatelliteDashboardPeriod::MONTHS[$now->month - 1];
        }
        $manilaNow = $now->copy()->timezone('Asia/Manila');
        return [
            'selectedDashboardMonth' => $month, 'selectedDashboardYear' => $year,
            'isLiveDashboard' => $month === 'Live', 'dashboardPeriods' => ['Live', ...SatelliteDashboardPeriod::MONTHS],
            'dashboardYears' => range($earliest, $now->year), 'dashboardCurrentYear' => $now->year,
            'dashboardCurrentMonth' => $now->month, 'dashboardDate' => $manilaNow->format('F j, Y'),
            'dashboardUpdatedAt' => $manilaNow->format('g:i A'),
        ];
    }

    public function results(Request $request, array $period, ?User $viewer = null): array
    {
        $category = $request->integer('category') ?: null;
        $satellite = $request->integer('satellite') ?: null;
        $hub = trim((string) $request->query('hub', ''));
        $rawDay = $request->query('day');
        $day = is_string($rawDay) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDay) && $rawDay <= now(config('app.timezone'))->toDateString() ? $rawDay : null;
        $query = DB::table('satisfaction_responses as r');
        if (! $period['isLiveDashboard']) {
            $start = \Illuminate\Support\Carbon::create($period['selectedDashboardYear'], array_search($period['selectedDashboardMonth'], SatelliteDashboardPeriod::MONTHS, true) + 1, 1, 0, 0, 0, config('app.timezone'));
            $query->where('r.service_date', '>=', $start->toDateString())->where('r.service_date', '<', $start->copy()->addMonth()->toDateString());
        }
        $own = null;
        if ($viewer && $viewer->normalizedRole() !== 'admin') {
            $own = $viewer->satelliteForDistrict();
            abort_unless($own, 403);
            $satellite = $own->id;
        }
        if ($satellite) $query->where('r.satellite_id', $satellite);
        if ($hub !== '') $query->where('r.hub', $hub);
        $calendarQuery = clone $query;
        if ($category) $calendarQuery->whereExists(fn ($sub) => $sub->selectRaw('1')->from('satisfaction_answers as ca')
            ->join('satisfaction_questions as cq', 'cq.id', '=', 'ca.question_id')
            ->whereColumn('ca.response_id', 'r.id')->where('cq.category_id', $category));
        $privacyPartitions = (clone $calendarQuery)->selectRaw('r.service_date, r.satellite_id, r.hub, COUNT(*) as responses')
            ->groupBy('r.service_date', 'r.satellite_id', 'r.hub')->get();
        $hiddenPartition = $privacyPartitions->contains(fn ($item) => $item->responses < self::MIN_RESPONSES);
        $hiddenDays = $privacyPartitions->groupBy('service_date')
            ->map(fn ($items) => $items->contains(fn ($item) => $item->responses < self::MIN_RESPONSES));
        $calendar = $calendarQuery->selectRaw('r.service_date, COUNT(*) as responses')
            ->groupBy('r.service_date')->orderBy('r.service_date')->get()->keyBy('service_date');
        foreach ($calendar as $item) $item->suppressed = (bool) ($hiddenDays[$item->service_date] ?? false);
        if ($day) $query->where('r.service_date', $day);
        $base = (clone $query)->join('satisfaction_answers as a', 'a.response_id', '=', 'r.id')
            ->join('satisfaction_questions as q', 'q.id', '=', 'a.question_id')
            ->join('satisfaction_categories as c', 'c.id', '=', 'q.category_id');
        if ($category) $base->where('c.id', $category);
        $groups = (clone $base)->selectRaw('c.id, c.name, COUNT(DISTINCT r.id) as responses, COUNT(*) as total, SUM(CASE WHEN a.yes = 1 THEN 1 ELSE 0 END) as yes')
            ->groupBy('c.id', 'c.name')->get()->keyBy('id');
        $overall = (clone $base)->selectRaw('COUNT(DISTINCT r.id) as responses, COUNT(*) as total, SUM(CASE WHEN a.yes = 1 THEN 1 ELSE 0 END) as yes')->first();
        $categories = DB::table('satisfaction_categories')->orderBy('position')->orderBy('id')->get();
        $visible = $categories->map(function ($item) use ($groups, $category) {
            $row = $category && $category !== $item->id ? null : $groups->get($item->id);
            return ['id' => $item->id, 'name' => $item->name, 'responses' => (int) ($row->responses ?? 0),
                'total' => (int) ($row->total ?? 0), 'yes' => (int) ($row->yes ?? 0)];
        });
        $overallCount = (int) ($overall->responses ?? 0);
        // A visible overall value alongside a single suppressed category permits subtraction.
        $hidden = $visible->filter(fn ($item) => $item['responses'] > 0 && $item['responses'] < self::MIN_RESPONSES);
        $hiddenDays = $calendar->contains(fn ($item) => $item->responses > 0 && ($item->responses < self::MIN_RESPONSES || $item->suppressed));
        $selectedCategory = $category ? $visible->firstWhere('id', $category) : null;
        $showOverall = $overallCount >= self::MIN_RESPONSES && $hidden->isEmpty() && ! $hiddenDays && ! $hiddenPartition
            && (! $category || ($selectedCategory && $selectedCategory['responses'] >= self::MIN_RESPONSES));
        return [
            'categories' => $visible->map(fn ($item) => $item + ['show' => ! $hiddenPartition]), 'overall' => ['responses' => $overallCount, 'total' => (int) ($overall->total ?? 0), 'yes' => (int) ($overall->yes ?? 0), 'show' => $showOverall],
            'calendar' => $calendar, 'filters' => ['category' => $category, 'satellite' => $satellite, 'hub' => $hub, 'day' => $day],
            'satellites' => Satellite::when($own, fn ($q) => $q->whereKey($own->id))->orderBy('name')->get(['id', 'name', 'hub']),
            'hubs' => Satellite::when($own, fn ($q) => $q->whereKey($own->id))->whereNotNull('hub')->distinct()->orderBy('hub')->pluck('hub'),
        ];
    }
}
