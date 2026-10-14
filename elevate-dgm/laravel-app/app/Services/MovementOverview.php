<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Presentation only. Never used to approve or materialize a checkpoint. */
class MovementOverview
{
    private array $units;

    private array $leaves = [];

    public function present(array $report, string $mode): array
    {
        $report = (new SatelliteArchiveVisibility)->project($report);
        $this->units = array_column($report['units'], null, 'id');
        foreach ($this->units as $id => $unit) {
            $this->leaves[$id] = $this->descendants($id);
        }
        $today = now('Asia/Manila')->toDateString();
        $members = DB::table('members as m')->join('org_unit_satellite_mappings as map', 'map.satellite_id', '=', 'm.satellite_id')
            ->whereNotIn('m.satellite_id', \App\Models\Satellite::archived()->select('id'))->whereNull('m.archived_at')->where('m.status', '!=', 'Inactive')
            ->select('map.unit_id', 'm.status', 'm.dgroup_role', 'm.updated_at')->get()->groupBy('unit_id');
        $groups = DB::table('analytics_dgroups')->whereNotIn('leader_member_id', DB::table('members')->whereIn('satellite_id', \App\Models\Satellite::archived()->select('id'))->select('id'))->where('effective_from', '<=', $today)->get()->groupBy('unit_id');
        $live = [];
        foreach ($this->units as $id => $unit) {
            foreach (config('movement_analytics.metrics') as $metric) {
                $cell = $this->decorate(['value' => null, 'state' => $metric === 'campus' && ! $unit['has_campus'] ? 'not_applicable' : 'not_reported', 'source' => null], $id, $today);
                $rows = $members->get($id, collect());
                $verified = $rows->filter(fn ($row) => in_array($row->status, config('movement_analytics.active_definition.allowed_statuses'), true));
                $active = $verified->where('status', config('movement_analytics.active_definition.active_value'));
                $roles = $active->filter(fn ($row) => in_array($row->dgroup_role, ['Dgroup Leader', 'Member'], true));
                $value = match ($metric) {
                    'dmembers' => $verified->isNotEmpty() ? $active->count() : null,
                    'dleaders' => $verified->isNotEmpty() && ($active->isEmpty() || $roles->isNotEmpty()) ? $roles->where('dgroup_role', 'Dgroup Leader')->count() : null,
                    'dgroups' => $groups->has($id) ? $groups[$id]->filter(fn ($g) => $g->effective_to === null || $g->effective_to > $today)->count() : null,
                    default => null,
                };
                if ($value !== null) {
                    $cell = $this->decorate(['value' => $value, 'state' => 'reported', 'source' => 'live'], $id, $today);
                    $cell['basis'] = $metric === 'dgroups' ? 'Current dated group registrations' : 'Current member status and role fields; historical leader decisions not verified';
                    if (in_array($metric, ['dmembers', 'dleaders'], true)) {
                        $cell['record_coverage'] = ['known' => $metric === 'dleaders' ? $verified->count() - $active->count() + $roles->count() : $verified->count(), 'total' => $rows->count()];
                        if ($verified->count() < $rows->count() || ($metric === 'dleaders' && $roles->count() < $active->count())) {
                            $cell['quality'] = 'partial';
                        }
                    }
                }
                $live[$id][$metric] = $cell;
            }
        }
        // Period series contain actual checkpoint evidence only, never today's fallback.
        foreach ($report['periods'] as &$period) {
            $direct = [];
            foreach ($this->units as $id => $unit) {
                foreach (config('movement_analytics.metrics') as $metric) {
                    $cell = $period['checkpoint']['status'] === 'closed' ? $period['recorded'][$id][$metric] : $period['reported'][$id][$metric];
                    if ($cell['state'] === 'not_reported') {
                        $cell = $period['reported'][$id][$metric];
                    }
                    $direct[$id][$metric] = $this->decorate($cell, $id, $period['checkpoint']['as_of_date']);
                }
            }
            $period['units'] = $this->rollup($direct);
        }
        unset($period);
        $currentDirect = [];
        foreach ($this->units as $id => $unit) {
            foreach (config('movement_analytics.metrics') as $metric) {
                // Aggregate saved values must be rebuilt from the selected leaves.
                $raw = $report['periods'][$report['checkpoint']['key']][$report['checkpoint']['status'] === 'closed' ? 'recorded' : 'reported'][$id][$metric];
                if ($raw['state'] === 'not_reported') {
                    $raw = $report['periods'][$report['checkpoint']['key']]['reported'][$id][$metric];
                }
                $saved = $this->decorate($raw, $id, $report['checkpoint']['as_of_date']);
                $currentDirect[$id][$metric] = $mode === 'live' ? $live[$id][$metric] : $saved;
                if ($mode === 'auto' && $saved['state'] === 'not_reported') {
                    $currentDirect[$id][$metric] = $live[$id][$metric];
                }
            }
        }
        $current = $this->rollup($currentDirect);
        foreach ($report['units'] as &$unit) {
            $id = $unit['id'];
            foreach (config('movement_analytics.metrics') as $metric) {
                $cell = $current[$id][$metric];
                foreach (['baseline', 'previous'] as $compare) {
                    $base = $report[$compare] ? $report['periods'][$report[$compare]]['units'] : [];
                    $cell['changes'][$compare] = $this->comparison($id, $metric, $current, $base);
                }
                $pct = $cell['changes'][$report['compare']]['raw_pct'];
                $cell['status'] = $cell['quality'] === 'conflict' ? 'Anomaly' : (in_array($cell['source'], ['live', 'mixed_live'], true) ? 'Live only' : ($pct === null ? 'Not reported' : (abs($pct) > config('movement_analytics.thresholds.anomaly_change_pct', 40) ? 'Anomaly' : (abs($pct) <= config('movement_analytics.thresholds.stable_change_pct', 0) ? 'Stable' : ($pct > 0 ? 'Growing' : 'Declining')))));
                $unit['metrics'][$metric] = $cell;
            }
            $unit['status'] = $unit['metrics'][config('movement_analytics.status_metric', 'dmembers')]['status'];
            $counts = $unit['metrics'];
            $ratio = $counts['dmembers']['value'] !== null && $counts['dgroups']['value'] ? $counts['dmembers']['value'] / $counts['dgroups']['value'] : null;
            $unit['ratios']['members_per_group'] = $ratio === null ? null : round($ratio, 4);
            $flags = [];
            if ($ratio !== null && ($ratio < config('movement_analytics.thresholds.members_per_group_min') || $ratio > config('movement_analytics.thresholds.members_per_group_max'))) {
                $flags[] = ['members_per_group_out_of_range', 'dmembers', 'Available members per group is outside the configured range; review source and coverage.'];
                $unit['status'] = 'Anomaly';
            }
            if ($counts['dleaders']['value'] !== null && $counts['dleaders']['value'] === $counts['dgroups']['value']) {
                $flags[] = ['groups_equal_leaders', null, 'DGroups equals DLeaders; verify these independent counts.'];
            }
            foreach ($counts as $metric => $cell) {
                if ($cell['changes'][$report['compare']]['raw_pct'] !== null && abs($cell['changes'][$report['compare']]['raw_pct']) > config('movement_analytics.thresholds.anomaly_change_pct')) {
                    $flags[] = ['large_change', $metric, 'Like-for-like change exceeds the configured anomaly threshold.'];
                    $unit['status'] = 'Anomaly';
                }
                if ($cell['quality'] === 'conflict') $unit['status'] = 'Anomaly';
                $last = $unit['system_vs_last_reported'][$metric]['reported']['value'];
                $unit['system_vs_last_reported'][$metric]['difference'] = $live[$id][$metric]['value'] !== null && $last !== null ? $live[$id][$metric]['value'] - $last : null;
            }
            foreach ($flags as [$code,$metric,$reason]) {
                $report['issues'][] = ['code' => $code, 'unit' => $unit['key'], 'checkpoint' => $report['checkpoint']['key'], 'metric' => $metric, 'reason' => $reason];
            }
            foreach ($report['issues'] as $issue) {
                if ($issue['unit'] !== $unit['key'] || $issue['checkpoint'] !== $report['checkpoint']['key']) {
                    continue;
                }
                if ($issue['code'] === 'possibly_carried_forward' || $issue['code'] === 'carried_forward') {
                    foreach ($unit['metrics'] as &$cell) {
                        if ($cell['quality'] === 'ok' && $cell['source'] !== 'live') {
                            $cell['quality'] = 'carried_forward';
                        }
                    }
                    unset($cell);
                }
            }
        }
        unset($unit);
        $report['mode'] = $mode;
        $report['live'] = $this->rollup($live);
        $leafIds = array_values(array_filter(array_keys($this->units), fn ($id) => $this->leaves[$id] === [$id]));
        $report['completeness'] = ['total' => count($leafIds), 'reported' => 0, 'live' => 0, 'missing' => 0];
        foreach ($leafIds as $id) {
            $cell = $current[$id]['dmembers'];
            $type = $cell['value'] === null ? 'missing' : ($cell['source'] === 'live' ? 'live' : 'reported');
            $report['completeness'][$type]++;
        }
        $report['completeness']['percent'] = count($leafIds) ? round(100 * $report['completeness']['reported'] / count($leafIds)) : 0;
        $report['live_as_of'] = $today;
        if ($mode !== 'reported') {
            $latest = $members->flatten(1)->max('updated_at');
            if ($latest && (! $report['data_last_updated'] || strtotime($latest) > strtotime($report['data_last_updated']))) {
                $report['data_last_updated'] = CarbonImmutable::parse($latest)->toIso8601String();
            }
        }
        foreach ($leafIds as $id) {
            $missing = array_keys(array_filter($current[$id], fn ($c) => $c['quality'] === 'missing'));
            if ($missing) {
                $report['issues'][] = ['code' => 'missing_overview_data', 'unit' => $this->units[$id]['key'], 'checkpoint' => $report['checkpoint']['key'], 'metric' => null, 'reason' => 'Missing '.implode(', ', $missing).'. Import verified reports or register the current source records.'];
            }
        }
        $report['conflicts'] = config('movement_analytics.declared_conflicts');
        $resolved = DB::table('movement_conflict_resolutions')->get()->keyBy('conflict_code');
        foreach ($report['conflicts'] as &$conflict) {
            $conflict['resolution'] = $resolved->get($conflict['code']);
            $conflict['closed'] = ($report['periods'][$conflict['checkpoint']]['checkpoint']['status'] ?? null) === 'closed';
        }
        unset($conflict);
        foreach ($report['issues'] as &$issue) {
            $issue['severity'] = str_contains($issue['code'], 'conflict') || $issue['code'] === 'parent_mismatch' ? 'error' : 'warning';
            $issue['type'] = str_contains($issue['code'], 'conflict') || $issue['code'] === 'parent_mismatch' ? 'conflicts' : (str_contains($issue['code'], 'missing') || str_contains($issue['code'], 'incomplete') ? 'missing data' : ($issue['code'] === 'unmapped_satellite' ? 'unmapped satellites' : 'anomalies'));
        }
        unset($issue);
        $unique = [];
        foreach ($report['issues'] as $issue) {
            $unique[json_encode([$issue['code'], $issue['unit'], $issue['checkpoint'], $issue['metric'] ?? null, $issue['satellite_id'] ?? null])] = $issue;
        }
        $report['issues'] = array_values($unique);

        return $report;
    }

    private function descendants(int $id): array
    {
        $children = array_filter($this->units, fn ($u) => $u['parent_id'] === $id);

        return $children ? array_merge(...array_map(fn ($u) => $this->descendants($u['id']), $children)) : [$id];
    }

    private function decorate(array $cell, int $id, string $date): array
    {
        $known = $cell['value'] !== null;
        $applicable = $cell['state'] !== 'not_applicable';

        return $cell + ['as_of' => $known ? $date : null, 'quality' => $cell['state'] === 'conflict' ? 'conflict' : ($known || ! $applicable ? 'ok' : 'missing'),
            'coverage' => ['known' => $known ? 1 : 0, 'total' => $applicable ? count($this->leaves[$id]) : 0, 'missing' => $known || ! $applicable ? [] : array_map(fn ($leaf) => $this->units[$leaf]['name'], $this->leaves[$id])]];
    }

    private function rollup(array $direct): array
    {
        $out = [];
        $visit = function ($id) use (&$visit, &$out, $direct) {
            if (isset($out[$id])) {
                return;
            }
            $children = array_filter($this->units, fn ($u) => $u['parent_id'] === $id);
            foreach ($children as $child) {
                $visit($child['id']);
            }
            foreach (config('movement_analytics.metrics') as $metric) {
                $own = $direct[$id][$metric];
                if (! $children) {
                    $out[$id][$metric] = $own;

                    continue;
                }
                $cells = array_map(fn ($u) => $out[$u['id']][$metric], $children);
                $applicable = array_filter($cells, fn ($c) => $c['state'] !== 'not_applicable');
                $known = array_filter($applicable, fn ($c) => $c['value'] !== null);
                $coverage = ['known' => array_sum(array_column(array_column($applicable, 'coverage'), 'known')), 'total' => array_sum(array_column(array_column($applicable, 'coverage'), 'total')), 'missing' => array_merge([], ...array_column(array_column($applicable, 'coverage'), 'missing'))];
                // Keep explicit parent assertions visible without pretending leaf coverage is known.
                if ($own['value'] !== null && $own['source'] !== 'live' && (! $known || in_array($own['source'], ['system', 'correction'], true))) {
                    $own['coverage'] = $coverage;
                    $own['quality'] = in_array('conflict', array_column($cells, 'quality'), true) ? 'conflict' : ($coverage['known'] < $coverage['total'] ? 'partial' : $own['quality']);
                    $own['basis'] = 'Reported parent total; coverage describes supporting leaf records';
                    $out[$id][$metric] = $own;

                    continue;
                }
                $sources = array_unique(array_column($known, 'source'));
                $out[$id][$metric] = ['value' => $known ? array_sum(array_column($known, 'value')) : null, 'state' => ! $applicable ? 'not_applicable' : ($known ? 'reported' : $own['state']),
                    'source' => ! $known ? null : (count($sources) === 1 ? reset($sources) : (in_array('live', $sources, true) || in_array('mixed_live', $sources, true) ? 'mixed_live' : 'mixed')),
                    'as_of' => $known ? max(array_column($known, 'as_of')) : null,
                    'quality' => in_array('conflict', array_column($cells, 'quality'), true) ? 'conflict' : (! $applicable ? 'ok' : (! $known ? 'missing' : ($coverage['known'] < $coverage['total'] || in_array('partial', array_column($cells, 'quality'), true) ? 'partial' : 'ok'))), 'coverage' => $coverage];
                if ($own['value'] !== null && $known && $own['value'] !== $out[$id][$metric]['value']) {
                    $out[$id][$metric]['asserted_value'] = $own['value'];
                    if ($coverage['known'] === $coverage['total']) {
                        $out[$id][$metric]['quality'] = 'conflict';
                    }
                }
            }
        };
        foreach (array_keys($this->units) as $id) {
            $visit($id);
        }

        return $out;
    }

    private function comparison(int $id, string $metric, array $current, array $base): array
    {
        $scope = array_filter($this->leaves[$id], fn ($leaf) => $current[$leaf][$metric]['state'] !== 'not_applicable');
        $pairs = array_filter($scope, fn ($leaf) => isset($base[$leaf]) && $current[$leaf][$metric]['value'] !== null && $base[$leaf][$metric]['value'] !== null && ! in_array($current[$leaf][$metric]['source'], ['live', 'mixed_live'], true) && $current[$leaf][$metric]['quality'] !== 'conflict' && $base[$leaf][$metric]['quality'] !== 'conflict');
        $a = array_sum(array_map(fn ($leaf) => $current[$leaf][$metric]['value'], $pairs));
        $b = array_sum(array_map(fn ($leaf) => $base[$leaf][$metric]['value'], $pairs));

        return ['variance' => $pairs && $b != 0 ? $a - $b : null, 'pct' => $pairs && $b != 0 ? round(($a - $b) / $b * 100, 2) : null, 'raw_pct' => $pairs && $b != 0 ? ($a - $b) / $b * 100 : null, 'compared' => count($pairs), 'total' => count($scope)];
    }
}
