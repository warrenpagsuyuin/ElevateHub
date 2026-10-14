<?php

namespace App\Services;

use App\Models\Checkpoint;
use App\Models\Member;
use App\Models\OrgUnit;
use App\Models\UnitSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Read-only entry point for movement widgets, exports and checkpoint review. */
class MovementAnalyticsService
{
    public function dashboard(string $checkpointKey, string $compare = 'baseline', ?string $group = null, string $mode = 'auto', bool $sample = false): array
    {
        if ($sample) {
            abort_unless(app()->environment('local') && config('movement_analytics.sample_enabled'), 403);

            return (new MovementSample)->build($this->report($checkpointKey, $compare, $group, 'reported'));
        }

        return app(MovementAnalyticsCache::class)->remember(
            [$checkpointKey, $compare, $group, $mode],
            fn () => (new MovementDashboardData)->enrich((new MovementOverview)->present($this->report($checkpointKey, $compare, $group, 'reported'), $mode))
        );
    }

    public function report(string $checkpointKey, string $compare = 'baseline', ?string $group = null, string $mode = 'auto'): array
    {
        if (! in_array($compare, ['baseline', 'previous'], true) || ! in_array($mode, ['auto', 'reported', 'live'], true)) {
            throw new \InvalidArgumentException('Invalid comparison or count mode.');
        }
        $units = OrgUnit::orderBy('sort')->orderBy('id')->get()->keyBy('id');
        if ($group !== null && ! $units->contains(fn ($u) => $u->type === 'group' && $u->group === $group)) {
            throw new \InvalidArgumentException('Unknown organization group.');
        }
        $checkpoints = Checkpoint::orderBy('as_of_date')->get();
        $current = $checkpoints->firstWhere('key', $checkpointKey);
        if (! $current) {
            throw new \InvalidArgumentException('Unknown checkpoint; run analytics:setup first.');
        }
        $baseline = $checkpoints->firstWhere('kind', 'baseline');
        $previous = $checkpoints->last(fn ($cp) => $cp->as_of_date->lt($current->as_of_date));
        $snapshots = UnitSnapshot::orderBy('id')->get()->groupBy('checkpoint_id');
        $revisions = DB::table('checkpoint_revisions')->orderBy('version')->get()->keyBy('checkpoint_id');
        $resolutions = DB::table('movement_conflict_resolutions')->get()->keyBy('conflict_code');
        $issues = [];
        $periods = [];
        $liveSource = null;
        $updated = $snapshots->flatten(1)->max('updated_at')?->toDateTimeString();
        foreach ($checkpoints as $cp) {
            // Closed periods always use their saved evidence, even when live is requested.
            $live = $cp->status !== 'closed' && ($mode === 'live' || ($mode === 'auto' && $cp->id === $current->id));
            if ($live) {
                $liveSource ??= new MovementLiveCounts;
            }
            $rows = $snapshots->get($cp->id, collect());
            $frozenIds = $rows->filter(fn ($row) => $row->source === 'system' && $row->closed_at !== null)->pluck('unit_id')->all();
            $revision = $cp->status === 'closed' ? $revisions->get($cp->id) : null;
            $revisedUnits = $revision ? json_decode($revision->units, true) : [];
            $updated = max($updated, $revision?->created_at);
            $direct = [];
            $reported = [];
            $periodIssues = [];
            $system = $live ? $liveSource->at($cp, $checkpoints->last(fn ($p) => $p->as_of_date->lt($cp->as_of_date))?->as_of_date, $units) : [];
            $updated = max($updated, $system['data_last_updated'] ?? null);
            foreach ($units as $unit) {
                $legacy = $rows->first(fn ($r) => $r->unit_id === $unit->id && $r->source === 'legacy_import');
                $saved = $cp->status === 'closed' ? ($rows->first(fn ($r) => $r->unit_id === $unit->id && $r->source === 'system') ?? $legacy) : $legacy;
                foreach (config('movement_analytics.metrics') as $metric) {
                    $reported[$unit->id][$metric] = $this->snapshotCell($legacy, $unit, $metric);
                    $direct[$unit->id][$metric] = $live
                        ? ($metric === 'campus' ? $reported[$unit->id][$metric] : $system['units'][$unit->id][$metric])
                        : $this->snapshotCell($saved, $unit, $metric);
                    if (isset($revisedUnits[$unit->id][$metric])) {
                        $direct[$unit->id][$metric] = $revisedUnits[$unit->id][$metric];
                    }
                }
                if ($cp->status === 'closed' && ! $saved && ! $frozenIds) {
                    $periodIssues[] = $this->issue('missing_closed_snapshot', $unit, $cp, null, 'No snapshot was supplied for this closed checkpoint.');
                } elseif ($cp->status === 'closed' && $saved && collect($direct[$unit->id])->contains(fn ($cell) => ! in_array($cell['state'], ['reported', 'not_applicable'], true))) {
                    $periodIssues[] = $this->issue('incomplete_closed_snapshot', $unit, $cp, null, 'This closed snapshot contains missing or conflicting metrics.');
                }
            }
            // A declared conflict is visible even before its CSV is supplied.
            foreach (config('movement_analytics.declared_conflicts', []) as $conflict) {
                if ($conflict['checkpoint'] !== $cp->key) {
                    continue;
                }
                $unit = $units->firstWhere('key', $conflict['unit']);
                if (! $unit) {
                    continue;
                }
                if ($resolution = $resolutions->get($conflict['code'])) {
                    foreach (json_decode($resolution->chosen_values, true) as $metric => $value) {
                        $reported[$unit->id][$metric] = $this->cell($value, 'reported', 'resolved_report');
                        if (! $live && $cp->status !== 'closed') {
                            $direct[$unit->id][$metric] = $reported[$unit->id][$metric];
                        }
                    }

                    continue;
                }
                foreach ($conflict['metrics'] as $metric) {
                    $reported[$unit->id][$metric] = $this->cell(null, 'conflict', 'legacy_import');
                    if (! $live && ! isset($revisedUnits[$unit->id]) && ! $rows->contains(fn ($r) => $r->unit_id === $unit->id && $r->source === 'system')) {
                        $direct[$unit->id][$metric] = $reported[$unit->id][$metric];
                    }
                }
                $periodIssues[] = $this->issue('declared_conflict', $unit, $cp, null, $conflict['reason'], ['alternatives' => $conflict['alternatives']]);
            }
            $rolled = [];
            $visit = function ($unit) use (&$visit, &$rolled, &$periodIssues, $units, $direct, $cp, $rows) {
                $children = $units->where('parent_id', $unit->id);
                if ($children->isEmpty()) {
                    return $rolled[$unit->id] = $direct[$unit->id];
                }
                foreach ($children as $child) {
                    $visit($child);
                }
                // A finalized parent is an immutable as-of aggregate. Later additions
                // to the organization must not change the closed period's numbers.
                if ($cp->status === 'closed' && $rows->contains(fn ($row) => $row->unit_id === $unit->id && $row->source === 'system' && $row->closed_at !== null)) {
                    return $rolled[$unit->id] = $direct[$unit->id];
                }
                foreach (config('movement_analytics.metrics') as $metric) {
                    $cells = $children->map(fn ($child) => $rolled[$child->id][$metric])->values()->all();
                    $sum = $this->sum($cells);
                    $rolled[$unit->id][$metric] = $sum;
                    $assertion = $direct[$unit->id][$metric];
                    if ($sum['state'] !== 'reported' && $sum['state'] !== 'not_applicable' && ($assertion['state'] === 'reported' || $cp->status === 'closed' || collect($cells)->contains(fn ($cell) => in_array($cell['state'], ['reported', 'conflict'], true)))) {
                        $periodIssues[] = $this->issue('incomplete_children', $unit, $cp, $metric, 'A complete roll-up requires every applicable child.', ['reported' => $assertion['value']]);
                    } elseif ($assertion['state'] === 'reported' && $assertion['value'] !== $sum['value']) {
                        $periodIssues[] = $this->issue('parent_mismatch', $unit, $cp, $metric, 'Recorded parent differs from its immediate child sum.', ['reported' => $assertion['value'], 'children_sum' => $sum['value']]);
                    }
                }

                return $rolled[$unit->id];
            };
            foreach ($units->whereNull('parent_id') as $root) {
                $visit($root);
            }
            $periods[$cp->key] = ['checkpoint' => ['key' => $cp->key, 'label' => $cp->label, 'as_of_date' => $cp->as_of_date->toDateString(), 'status' => $cp->status],
                'mode' => $live ? 'live' : 'snapshot', 'units' => $rolled, 'recorded' => $direct, 'reported' => $reported];
            if ($cp->id === $current->id) {
                $issues = array_merge($issues, $periodIssues, $system['issues'] ?? []);
            }
        }
        $selected = $periods[$current->key];
        $result = [];
        foreach ($units as $unit) {
            $metrics = [];
            $reportedComparisons = [];
            foreach (config('movement_analytics.metrics') as $metric) {
                $cell = $selected['units'][$unit->id][$metric];
                $base = $baseline ? $periods[$baseline->key]['units'][$unit->id][$metric] : $this->cell();
                $prev = $previous ? $periods[$previous->key]['units'][$unit->id][$metric] : $this->cell();
                $changes = ['baseline' => $this->change($cell, $base), 'previous' => $this->change($cell, $prev)];
                $chosen = $changes[$compare];
                $comparisonBase = $compare === 'baseline' ? $base : $prev;
                $rawPct = $chosen['variance'] !== null ? $chosen['variance'] / $comparisonBase['value'] * 100 : null;
                $metrics[$metric] = $cell + ['changes' => $changes, 'status' => $this->status($cell, $rawPct)];
                $reportedComparisons[$metric] = [
                    'baseline' => $this->change($selected['reported'][$unit->id][$metric], $baseline ? $periods[$baseline->key]['reported'][$unit->id][$metric] : $this->cell()),
                    'previous' => $this->change($selected['reported'][$unit->id][$metric], $previous ? $periods[$previous->key]['reported'][$unit->id][$metric] : $this->cell()),
                ];
                if ($rawPct !== null && abs($rawPct) > config('movement_analytics.thresholds.anomaly_change_pct')) {
                    $issues[] = $this->issue('large_change', $unit, $current, $metric, 'Absolute percentage change exceeds the configured threshold.', ['pct' => $chosen['pct']]);
                }
                if ($cell['state'] === 'conflict') {
                    $issues[] = $this->issue('conflict', $unit, $current, $metric, 'Conflicting evidence prevents a count.');
                }
            }
            $values = $selected['units'][$unit->id];
            $ratio = $this->ratio($values['dmembers'], $values['dgroups']);
            if ($ratio !== null && ($ratio < config('movement_analytics.thresholds.members_per_group_min') || $ratio > config('movement_analytics.thresholds.members_per_group_max'))) {
                $issues[] = $this->issue('members_per_group_out_of_range', $unit, $current, 'dmembers', 'Members per group is outside the configured range.', ['ratio' => $ratio]);
            }
            if ($values['dleaders']['state'] === 'reported' && $values['dgroups']['state'] === 'reported' && $values['dleaders']['value'] === $values['dgroups']['value']) {
                $issues[] = $this->issue('groups_equal_leaders', $unit, $current, null, 'DGroups exactly equals DLeaders; verify the independent counts.');
            }
            if ($previous && $this->unchanged($values, $periods[$previous->key]['units'][$unit->id])) {
                $issues[] = $this->issue('possibly_carried_forward', $unit, $current, null, 'All applicable metrics are unchanged from the previous checkpoint.');
            }
            $last = [];
            foreach (config('movement_analytics.metrics') as $metric) {
                $lastCell = $this->cell();
                $lastKey = null;
                foreach ($checkpoints as $cp) {
                    if ($cp->as_of_date->gt($current->as_of_date)) {
                        break;
                    }
                    $candidate = $periods[$cp->key]['reported'][$unit->id][$metric];
                    // A conflict is the latest evidence, not permission to silently use older figures.
                    if (in_array($candidate['state'], ['reported', 'conflict', 'not_applicable'], true)) {
                        $lastCell = $candidate;
                        $lastKey = $cp->key;
                    }
                }
                $last[$metric] = ['checkpoint' => $lastKey, 'reported' => $lastCell,
                    'difference' => $selected['mode'] === 'live' && $metric !== 'campus' && $values[$metric]['state'] === 'reported' && $lastCell['state'] === 'reported' ? $values[$metric]['value'] - $lastCell['value'] : null,
                    'change' => $selected['mode'] === 'live' && $metric !== 'campus' ? $this->change($values[$metric], $lastCell) : null];
            }
            $unitStatus = $metrics[config('movement_analytics.status_metric', 'dmembers')]['status'];
            if (collect($metrics)->contains(fn ($metric) => $metric['status'] === 'Anomaly') || ($ratio !== null && ($ratio < config('movement_analytics.thresholds.members_per_group_min') || $ratio > config('movement_analytics.thresholds.members_per_group_max')))) {
                $unitStatus = 'Anomaly';
            }
            $result[$unit->key] = ['id' => $unit->id, 'key' => $unit->key, 'name' => $unit->name, 'type' => $unit->type, 'group' => $unit->group,
                'parent_id' => $unit->parent_id, 'has_campus' => $unit->has_campus, 'metrics' => $metrics, 'recorded' => $selected['recorded'][$unit->id], 'reported' => $selected['reported'][$unit->id],
                'ratios' => ['members_per_group' => $ratio, 'groups_per_leader' => $this->ratio($values['dgroups'], $values['dleaders'])],
                'status' => $unitStatus, 'system_vs_last_reported' => $last,
                'reported_comparisons' => $reportedComparisons];
        }
        foreach (DB::table('satellites')->whereIn('satellites.id', \App\Models\Satellite::active()->select('id'))->leftJoin('org_unit_satellite_mappings as map', 'map.satellite_id', '=', 'satellites.id')->whereNull('map.id')->get(['satellites.id', 'satellites.name', 'satellites.hub']) as $satellite) {
            if ($group !== null && $satellite->hub !== $group) {
                continue;
            }
            $issues[] = ['code' => 'unmapped_satellite', 'unit' => null, 'group' => $satellite->hub, 'checkpoint' => $current->key, 'metric' => null,
                'reason' => 'Satellite has no configured organizational mapping.', 'satellite_id' => $satellite->id, 'satellite' => $satellite->name];
        }
        // Current record quality is clearly separate from historical/as-of counts.
        $genderRows = DB::table('members')->leftJoin('org_unit_satellite_mappings as map', 'map.satellite_id', '=', 'members.satellite_id')
            ->whereNotIn('members.satellite_id', \App\Models\Satellite::archived()->select('id'))->whereNull('members.archived_at')->where('members.status', '!=', Member::STATUS_INACTIVE)
            ->where(fn ($q) => $q->whereNull('gender')->orWhereRaw("TRIM(gender) = ''"))
            ->selectRaw('map.unit_id, COUNT(*) as missing_count')->groupBy('map.unit_id')->get();
        foreach ($genderRows as $row) {
            $unit = $units->get($row->unit_id);
            if ($group !== null && $unit?->group !== $group) {
                continue;
            }
            $issues[] = ['code' => 'missing_gender', 'unit' => $unit?->key, 'checkpoint' => $current->key, 'metric' => null,
                'reason' => 'Current member records have missing gender.', 'count' => (int) $row->missing_count, 'scope' => 'current_records'];
        }
        // Retain unresolved imported evidence, including conflicts outside the selected period.
        foreach (DB::table('analytics_data_issues')->where('status', 'open')->whereIn('code', ['conflict', 'declared_conflict'])->get() as $issue) {
            if ($issue->code === 'declared_conflict' && $resolutions->contains(fn ($r) => $r->unit_id === $issue->unit_id && $r->checkpoint_id === $issue->checkpoint_id)) {
                continue;
            }
            $unit = $units->get($issue->unit_id);
            if ($group !== null && $unit?->group !== $group) {
                continue;
            }
            $issues[] = ['code' => $issue->code, 'unit' => $unit?->key, 'checkpoint' => $checkpoints->firstWhere('id', $issue->checkpoint_id)?->key,
                'metric' => json_decode($issue->details, true)['metric'] ?? null, 'reason' => $issue->reason, 'details' => json_decode($issue->details, true)];
        }
        if ($group !== null) {
            $result = array_filter($result, fn ($row) => $row['group'] === $group);
            $keys = array_keys($result);
            $issues = array_values(array_filter($issues, fn ($issue) => $issue['unit'] === null || in_array($issue['unit'], $keys, true)));
            foreach ($periods as &$period) {
                foreach (['units', 'recorded', 'reported'] as $field) {
                    $period[$field] = array_intersect_key($period[$field], array_flip(array_column($result, 'id')));
                }
            }
            unset($period);
        }
        $unique = [];
        foreach ($issues as $issue) {
            $unique[json_encode([$issue['code'], $issue['unit'], $issue['checkpoint'], $issue['metric'] ?? null, $issue['satellite_id'] ?? null])] = $issue;
        }

        return ['checkpoint' => $selected['checkpoint'], 'revision' => (int) ($revisions->get($current->id)?->version ?? 0), 'mode' => $selected['mode'], 'compare' => $compare, 'group' => $group,
            'baseline' => $baseline?->key, 'previous' => $previous?->key, 'units' => $result, 'periods' => $periods, 'issues' => array_values($unique),
            'data_last_updated' => $updated ? CarbonImmutable::parse($updated)->toIso8601String() : null,
            'definitions' => config('movement_analytics.definitions'), 'thresholds' => config('movement_analytics.thresholds')];
    }

    public function cell(?int $value = null, string $state = 'not_reported', ?string $source = null): array
    {
        return ['value' => $state === 'reported' ? $value : null, 'state' => $state, 'source' => $source];
    }

    public function change(array $current, array $base): array
    {
        if ($current['state'] !== 'reported' || $base['state'] !== 'reported' || $current['value'] === null || $base['value'] === null || $base['value'] === 0) {
            return ['variance' => null, 'pct' => null];
        }
        $delta = $current['value'] - $base['value'];

        return ['variance' => $delta, 'pct' => round($delta / $base['value'] * 100, 2)];
    }

    public function sum(array $cells): array
    {
        if (! $cells) {
            return $this->cell();
        }
        $applicable = array_values(array_filter($cells, fn ($cell) => $cell['state'] !== 'not_applicable'));
        if (! $applicable) {
            return $this->cell(null, 'not_applicable', 'rollup');
        }
        if (in_array('conflict', array_column($applicable, 'state'), true)) {
            return $this->cell(null, 'conflict', 'rollup');
        }
        foreach ($applicable as $cell) {
            if ($cell['state'] !== 'reported' || $cell['value'] === null) {
                return $this->cell(null, 'not_reported', 'rollup');
            }
        }

        return $this->cell(array_sum(array_column($applicable, 'value')), 'reported', 'rollup');
    }

    public function ratio(array $numerator, array $denominator): ?float
    {
        if ($numerator['state'] !== 'reported' || $denominator['state'] !== 'reported' || $numerator['value'] === null || ! $denominator['value']) {
            return null;
        }

        return round($numerator['value'] / $denominator['value'], 4);
    }

    private function snapshotCell(?UnitSnapshot $snapshot, OrgUnit $unit, string $metric): array
    {
        if ($metric === 'campus' && ! $unit->has_campus) {
            return $this->cell(null, 'not_applicable', $snapshot?->source);
        }

        return $this->cell($snapshot?->$metric, $snapshot?->metric_states[$metric] ?? 'not_reported', $snapshot?->source);
    }

    private function status(array $cell, ?float $pct): string
    {
        if ($cell['state'] === 'conflict') {
            return 'Anomaly';
        }
        if ($cell['state'] !== 'reported') {
            return 'Not reported';
        }
        if ($pct === null) {
            return 'Not reported';
        }
        if (abs($pct) > config('movement_analytics.thresholds.anomaly_change_pct')) {
            return 'Anomaly';
        }
        if (abs($pct) <= config('movement_analytics.thresholds.stable_change_pct')) {
            return 'Stable';
        }

        return $pct > 0 ? 'Growing' : 'Declining';
    }

    private function unchanged(array $current, array $previous): bool
    {
        $reported = false;
        foreach (config('movement_analytics.metrics') as $metric) {
            if ($current[$metric]['state'] === 'not_applicable' && $previous[$metric]['state'] === 'not_applicable') {
                continue;
            }
            if ($current[$metric]['state'] !== 'reported' || $previous[$metric]['state'] !== 'reported' || $current[$metric]['value'] !== $previous[$metric]['value']) {
                return false;
            }
            $reported = true;
        }

        return $reported;
    }

    private function issue(string $code, OrgUnit $unit, Checkpoint $cp, ?string $metric, string $reason, array $details = []): array
    {
        return ['code' => $code, 'unit' => $unit->key, 'checkpoint' => $cp->key, 'metric' => $metric, 'reason' => $reason] + $details;
    }
}
