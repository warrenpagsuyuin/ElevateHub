<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Satellite;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    private const HUBS = ['MAIN', 'NCP', 'ICP'];

    public function index(): View
    {
        return view('admin.dashboard', [
            'movementSummaryCards' => $this->movementSummaryCards(),
        ]);
    }

    private function movementSummaryCards(): array
    {
        $hubBreakdown = $this->hubBreakdown();
        $leaderTotal = (int) array_sum(array_map(function (string $hub) use ($hubBreakdown): int {
            $item = $hubBreakdown->get($hub, new \stdClass());
            $groups = max(1, (int) ($item->groups_total ?? 0));
            $rawLeaders = (int) ($item->leaders_total ?? 0);
            $normalizedLeaders = max($rawLeaders, $groups * 3);

            return min($normalizedLeaders, $groups * 10);
        }, self::HUBS));
        $groupTotal = Satellite::query()->count();
        $memberTotal = Member::query()->count();
        $campusTotal = Satellite::query()->count();

        return [
            [
                'label' => 'DGROUP LEADERS',
                'total' => $leaderTotal,
                'segments' => $this->segmentsForHubValues('leaders', $hubBreakdown),
            ],
            [
                'label' => 'DGROUPS',
                'total' => $groupTotal,
                'segments' => $this->segmentsForHubValues('groups', $hubBreakdown),
            ],
            [
                'label' => 'DGROUP MEMBERS',
                'total' => $memberTotal,
                'segments' => $this->segmentsForHubValues('members', $hubBreakdown),
            ],
            [
                'label' => 'CAMPUSES',
                'total' => $campusTotal,
                'segments' => $this->segmentsForHubValues('campuses', $hubBreakdown),
            ],
        ];
    }

    private function hubBreakdown(): Collection
    {
        return Satellite::query()
            ->selectRaw('hub, SUM(leaders_count) as leaders_total, COUNT(*) as groups_total, SUM(members_count) as members_total, COUNT(*) as campuses_total')
            ->whereIn('hub', self::HUBS)
            ->groupBy('hub')
            ->get()
            ->keyBy('hub');
    }

    private function segmentsForHubValues(string $field, Collection $hubBreakdown): array
    {
        return array_map(function (string $hub) use ($field, $hubBreakdown): array {
            $item = $hubBreakdown->get($hub, new \stdClass());
            $groups = max(1, (int) ($item->groups_total ?? 0));

            $value = match ($field) {
                'leaders' => min(max((int) ($item->leaders_total ?? 0), $groups * 3), $groups * 10),
                'groups' => (int) ($item->groups_total ?? 0),
                'members' => (int) ($item->members_total ?? 0),
                'campuses' => (int) ($item->campuses_total ?? 0),
                default => 0,
            };

            return ['label' => $hub, 'value' => $value];
        }, self::HUBS);
    }
}
