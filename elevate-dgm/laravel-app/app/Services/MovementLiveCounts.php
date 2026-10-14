<?php

namespace App\Services;

use App\Models\Checkpoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Internal dated-registry reader. Consumers use MovementAnalyticsService. */
class MovementLiveCounts
{
    private ?array $records = null;

    public function at(Checkpoint $checkpoint, $previousDate, Collection $units): array
    {
        $math = new MovementAnalyticsService;
        $date = $checkpoint->as_of_date->toDateString();
        $end = $date.' 23:59:59';
        $result = [];
        $issues = [];
        foreach ($units as $unit) {
            foreach (config('movement_analytics.metrics') as $metric) {
                $result[$unit->id][$metric] = $math->cell(null, $metric === 'campus' && ! $unit->has_campus ? 'not_applicable' : 'not_reported', 'system');
            }
        }
        // Never project today's population into a future checkpoint.
        if ($checkpoint->as_of_date->startOfDay()->gt(now()->startOfDay())) {
            return ['units' => $result, 'issues' => []];
        }
        $this->records ??= [
            'assignments' => DB::table('analytics_member_assignments')->orderBy('effective_from')->get(),
            'statuses' => DB::table('member_status_history')->orderBy('effective_at')->orderBy('version')->get(),
            'groups' => DB::table('analytics_dgroups')->orderBy('effective_from')->get(),
            'memberships' => DB::table('analytics_dgroup_memberships')->orderBy('effective_from')->get(),
            'leadership' => DB::table('analytics_leadership_events')->orderBy('effective_at')->orderBy('id')->get(),
        ];
        $statuses = $this->records['statuses']->filter(fn ($r) => $r->effective_at <= $end)->keyBy('member_id');
        $assignments = $this->records['assignments']->filter(fn ($r) => $this->within($r, $date));
        $duplicates = $assignments->groupBy('member_id')->filter(fn ($rows) => $rows->count() > 1)->keys()->all();
        $active = config('movement_analytics.active_definition.active_value');
        foreach ($units as $unit) {
            $assigned = $assignments->where('unit_id', $unit->id);
            $history = $this->records['assignments']->where('unit_id', $unit->id)->filter(fn ($r) => $r->effective_from <= $date);
            $groupsHistory = $this->records['groups']->where('unit_id', $unit->id)->filter(fn ($r) => $r->effective_from <= $date);
            $groups = $groupsHistory->filter(fn ($r) => $this->within($r, $date));
            if ($history->isEmpty() && $groupsHistory->isEmpty() && ! $units->contains(fn ($child) => $child->parent_id === $unit->id)) {
                $issues[] = $this->issue('missing_system_evidence', $unit, $checkpoint, 'No dated operational registry evidence exists for this unit. Empty registries are not confirmed zero counts.');
            }
            $missing = $assigned->filter(fn ($r) => ! $statuses->has($r->member_id))->count();
            $overlap = $assigned->filter(fn ($r) => in_array($r->member_id, $duplicates, true))->count();
            $unassignedLeaders = $groups->pluck('leader_member_id')->unique()->filter(fn ($id) => ! $assigned->contains('member_id', $id))->count();
            if ($history->isNotEmpty()) {
                $count = $assigned->filter(fn ($r) => ($statuses->get($r->member_id)?->new_status) === $active)->pluck('member_id')->unique()->count();
                $result[$unit->id]['dmembers'] = $math->cell($missing || $overlap || $unassignedLeaders ? null : $count, $overlap ? 'conflict' : ($missing || $unassignedLeaders ? 'not_reported' : 'reported'), 'system');
                if ($missing || $overlap) {
                    $issues[] = $this->issue($overlap ? 'overlapping_assignments' : 'missing_status_history', $unit, $checkpoint,
                        'Live member count requires one dated assignment and a leader decision per member.', ['unverified' => $missing, 'overlapping' => $overlap, 'observed_active' => $count]);
                }
            }
            if ($unassignedLeaders) {
                $issues[] = $this->issue('missing_leader_assignment', $unit, $checkpoint, 'DMembers includes leaders, but group leaders lack dated member assignments in this unit.', ['count' => $unassignedLeaders]);
            }
            if ($groupsHistory->isNotEmpty()) {
                $result[$unit->id]['dgroups'] = $math->cell($groups->count(), 'reported', 'system');
                $leaders = $groups->pluck('leader_member_id')->unique();
                $unverified = $leaders->filter(fn ($id) => ! $statuses->has($id))->count();
                $count = $leaders->filter(fn ($id) => $statuses->get($id)?->new_status === $active)->count();
                $result[$unit->id]['dleaders'] = $math->cell($unverified ? null : $count, $unverified ? 'not_reported' : 'reported', 'system');
                if ($unverified) {
                    $issues[] = $this->issue('missing_leader_status_history', $unit, $checkpoint, 'Group leaders lack verified as-of Active decisions.', ['count' => $unverified]);
                }
                // Baseline is a stock checkpoint, not a known new-leader event interval.
                if ($previousDate !== null) {
                    $new = 0;
                    $unknown = false;
                    foreach ($groupsHistory->pluck('leader_member_id')->unique() as $leaderId) {
                        $events = $this->records['leadership']->where('member_id', $leaderId)->filter(fn ($event) => $event->effective_at <= $end);
                        $first = $this->firstQualification($leaderId, $date);
                        $groupIds = $this->records['groups']->where('leader_member_id', $leaderId)->pluck('id')->all();
                        $hasMembershipEvidence = $this->records['memberships']->contains(fn ($r) => in_array($r->dgroup_id, $groupIds, true) && $r->effective_from <= $date);
                        if ($events->isEmpty() || ! $hasMembershipEvidence) {
                            $unknown = true;

                            continue;
                        }
                        if (! $first) {
                            continue;
                        }
                        $event = $events->last(fn ($event) => substr($event->effective_at, 0, 10) <= $first);
                        if ($first > $previousDate->toDateString() && $first <= $date && $event?->unit_id === $unit->id) {
                            $new++;
                        }
                    }
                    $result[$unit->id]['new_leaders'] = $math->cell($unknown ? null : $new, $unknown ? 'not_reported' : 'reported', 'system');
                    if ($unknown) {
                        $issues[] = $this->issue('incomplete_leadership_evidence', $unit, $checkpoint, 'New Leader counts require dated qualification and appointment evidence.');
                    }
                }
            }
        }
        $updated = null;
        foreach ($this->records as $records) {
            foreach ($records as $record) {
                $updated = max($updated, $record->updated_at ?? $record->recorded_at ?? null);
            }
        }

        return ['units' => $result, 'issues' => $issues, 'data_last_updated' => $updated];
    }

    private function within(object $row, string $date): bool
    {
        return $row->effective_from <= $date && ($row->effective_to === null || $row->effective_to > $date);
    }

    /** First date with at least N distinct concurrent members under this leader, across groups. */
    private function firstQualification(int $leaderId, string $asOf): ?string
    {
        $groups = $this->records['groups']->where('leader_member_id', $leaderId)->keyBy('id');
        $memberships = $this->records['memberships']->filter(fn ($row) => $groups->has($row->dgroup_id) && $row->member_id !== $leaderId);
        $events = $this->records['leadership']->where('member_id', $leaderId);
        $dates = [];
        foreach ($memberships as $row) {
            $group = $groups[$row->dgroup_id];
            $date = max($row->effective_from, $group->effective_from);
            if ($date <= $asOf) {
                $dates[$date] = true;
            }
        }
        foreach ($events as $event) {
            $date = substr($event->effective_at, 0, 10);
            if ($date <= $asOf) {
                $dates[$date] = true;
            }
        }
        ksort($dates);
        foreach (array_keys($dates) as $date) {
            $event = $events->last(fn ($row) => substr($row->effective_at, 0, 10) <= $date);
            if (! $event || $event->event !== 'appointed') {
                continue;
            }
            if (! config('movement_analytics.definitions.new_leaders_include_reactivations') && $events->contains(fn ($row) => $row->event === 'reactivated' && substr($row->effective_at, 0, 10) <= $date)) {
                continue;
            }
            $members = $memberships->filter(fn ($row) => $this->within($row, $date) && $this->within($groups[$row->dgroup_id], $date))->pluck('member_id')->unique();
            if ($members->count() >= config('movement_analytics.definitions.new_leader_minimum_members')) {
                return $date;
            }
        }

        return null;
    }

    private function issue(string $code, object $unit, Checkpoint $cp, string $reason, array $details = []): array
    {
        return ['code' => $code, 'unit' => $unit->key, 'checkpoint' => $cp->key, 'metric' => null, 'reason' => $reason] + $details;
    }
}
