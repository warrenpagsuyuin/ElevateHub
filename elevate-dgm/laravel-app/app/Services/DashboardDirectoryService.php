<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardDirectoryService
{
    public const TYPES = ['dgroup-members' => 'DGroup Members', 'dgroup-leaders' => 'DGroup Leaders', 'dgroups' => 'DGroups', 'campuses' => 'Campuses'];

    public function query(string $type, ?string $category = null, ?int $satellite = null, ?\DateTimeInterface $start = null, ?\DateTimeInterface $end = null): Builder
    {
        $q = Member::active()->whereIn('satellite_id', \App\Models\Satellite::active()->select('id'))->where('dgroup_role', $type === 'dgroup-members' ? Member::ROLE_MEMBER : Member::ROLE_LEADER);
        if ($category) {
            $q->whereHas('satellite', fn ($s) => $s->where('hub', $category));
        }
        if ($satellite) {
            $q->where('satellite_id', $satellite);
        }
        if ($start && $end) {
            $q->where('members.created_at', '>=', $start)->where('members.created_at', '<', $end);
        }
        if ($type === 'dgroups') {
            $q->whereIn('members.id', DB::table('members as a')->join('members as l', 'l.id', '=', 'a.leader_member_id')
                ->whereNull('a.archived_at')->where('a.status', '!=', Member::STATUS_INACTIVE)
                ->whereNull('l.archived_at')->where('l.status', '!=', Member::STATUS_INACTIVE)
                ->where('a.dgroup_role', Member::ROLE_MEMBER)->whereColumn('a.satellite_id', 'l.satellite_id')
                ->when($start && $end, fn ($sub) => $sub->where('a.created_at', '>=', $start)->where('a.created_at', '<', $end))
                ->select('a.leader_member_id')->groupBy('a.leader_member_id')
                ->havingRaw('COUNT(*) >= ?', [max(1, (int) config('dashboard_directory.DGROUP_MIN_MEMBERS'))]));
        }

        return $q;
    }

    public function withAssignmentCount(Builder $q): Builder
    {
        return $q->select('members.*')->selectSub(DB::table('members as assigned')
            ->selectRaw('COUNT(*)')->whereColumn('assigned.leader_member_id', 'members.id')
            ->whereNull('assigned.archived_at')->where('assigned.status', '!=', Member::STATUS_INACTIVE)
            ->whereColumn('assigned.satellite_id', 'members.satellite_id')->where('assigned.dgroup_role', Member::ROLE_MEMBER), 'assigned_count');
    }

    public function normalizeCampus(?string $value): ?string
    {
        $key = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value)), 'UTF-8');
        $placeholders = array_map(fn ($p) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $p)), 'UTF-8'), config('dashboard_directory.CAMPUS_PLACEHOLDERS'));

        return $key === '' || in_array($key, $placeholders, true) ? null : $key;
    }

    public function campuses(?string $category = null, ?int $satellite = null): Collection
    {
        $rows = DB::table('members as m')->join('satellites as s', 's.id', '=', 'm.satellite_id')
            ->whereIn('s.id', \App\Models\Satellite::active()->select('id'))->whereNull('m.archived_at')->where('m.status', '!=', Member::STATUS_INACTIVE)
            ->when($category, fn ($q) => $q->where('s.hub', $category))->when($satellite, fn ($q) => $q->where('s.id', $satellite))
            ->select('m.campus_school', 's.id', 's.name', 's.hub')->distinct()->orderBy('m.campus_school')->get();
        $campuses = [];
        foreach ($rows as $row) {
            $key = $this->normalizeCampus($row->campus_school);
            if ($key === null) {
                continue;
            }
            $campuses[$key] ??= ['key' => $key, 'name' => trim(preg_replace('/\s+/u', ' ', $row->campus_school)), 'satellites' => [], 'categories' => []];
            $campuses[$key]['satellites'][$row->id] = $row->name;
            $campuses[$key]['categories'][$row->hub ?: 'Unassigned'] = $row->hub ?: 'Unassigned';
        }

        return collect($campuses)->sortKeys()->values();
    }

    public function summary(?array $period = null): array
    {
        $start = !empty($period) && empty($period['isLiveDashboard']) ? ($period['dashboardStart'] ?? null) : null;
        $end = !empty($period) && empty($period['isLiveDashboard']) ? ($period['dashboardEnd'] ?? null) : null;

        $cards = [];
        foreach (['dgroup-leaders', 'dgroups', 'dgroup-members', 'campuses'] as $type) {
            $count = fn ($category = null) => $type === 'campuses' ? $this->campuses($category)->count() : $this->query($type, $category, null, $start, $end)->count();
            $cards[] = ['key' => $type, 'label' => strtoupper(self::TYPES[$type]), 'title' => self::TYPES[$type], 'total' => $count(),
                'segments' => array_map(fn ($category) => ['label' => $category, 'value' => $count($category)], config('dashboard_directory.categories'))];
        }

        return $cards;
    }

    public function distribution(?string $category = null, ?int $satellite = null): array
    {
        $counts = ['0' => 0, '1-2' => 0, '3-4' => 0, '5+' => 0];
        foreach ($this->withAssignmentCount($this->query('dgroup-leaders', $category, $satellite))->get() as $leader) {
            $n = (int) $leader->assigned_count;
            $counts[$n === 0 ? '0' : ($n < 3 ? '1-2' : ($n < 5 ? '3-4' : '5+'))]++;
        }

        return $counts;
    }
}
