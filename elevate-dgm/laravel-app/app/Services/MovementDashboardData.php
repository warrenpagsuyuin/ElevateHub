<?php

namespace App\Services;

use App\Models\EngagementResult;
use Illuminate\Support\Facades\DB;

/** Internal dashboard projections; every consumer enters through MovementAnalyticsService. */
class MovementDashboardData
{
    public function enrich(array $report): array
    {
        $units = collect($report['units'])->keyBy('id');
        $maps = DB::table('org_unit_satellite_mappings as map')->join('satellites as s', 's.id', '=', 'map.satellite_id')->get(['map.unit_id', 's.id', 's.slug', 's.name', 's.archived_at']);
        $maps->each(function ($map) { if ($map->archived_at) $map->name .= ' (archived)'; });
        $bySlug = $maps->keyBy('slug');
        $bySatellite = $maps->keyBy('id');
        $seekers = DB::table('seekers as s')->leftJoin('members as m', 'm.id', '=', 's.converted_member_id')
            ->where(fn ($q) => $q->whereNull('s.assigned_satellite_slug')->orWhereNotIn('s.assigned_satellite_slug', \App\Models\Satellite::archived()->select('slug')))
            ->get(['s.id', 's.archived_at', 's.assigned_satellite_slug', 's.converted_member_id', 'm.id as joined_member']);
        $pipeline = ['active' => 0, 'archived' => 0, 'total' => 0, 'placed' => 0, 'non_placed' => 0, 'unknown' => 0, 'rate' => null, 'by_unit' => [], 'trend' => []];
        foreach ($seekers as $seeker) {
            $unitId = $bySlug->get($seeker->assigned_satellite_slug)?->unit_id;
            if ($report['group'] !== null && ! $units->has($unitId)) {
                continue;
            }
            $key = $units->get($unitId)['key'] ?? 'unmapped';
            $pipeline['by_unit'][$key] ??= ['name' => $units->get($unitId)['name'] ?? 'Unmapped / unassigned', 'total' => 0, 'placed' => 0, 'non_placed' => 0, 'unknown' => 0];
            $state = $seeker->joined_member ? 'placed' : ($seeker->converted_member_id ? 'unknown' : 'non_placed');
            $pipeline['total']++;
            $pipeline[$seeker->archived_at ? 'archived' : 'active']++;
            $pipeline[$state]++;
            $pipeline['by_unit'][$key]['total']++;
            $pipeline['by_unit'][$key][$state]++;
        }
        if ($pipeline['total'] && ! $pipeline['unknown']) {
            $pipeline['rate'] = round($pipeline['placed'] / $pipeline['total'] * 100, 2);
        }
        // Only dated conversion events can establish a historical joining trend.
        $events = DB::table('analytics_seeker_placements')->whereNotIn('seeker_id', DB::table('seekers')->whereIn('assigned_satellite_slug', \App\Models\Satellite::archived()->select('slug'))->select('id'))->where('event', 'converted')->orderBy('effective_at')->orderBy('id')->get()->unique('seeker_id');
        $previous = null;
        foreach ($report['periods'] as $period) {
            $end = $period['checkpoint']['as_of_date'].' 23:59:59';
            $eligible = $events->filter(fn ($event) => $report['group'] === null || $units->has($event->unit_id));
            $count = $eligible->filter(fn ($event) => $event->effective_at <= $end && ($previous === null || $event->effective_at > $previous))->count();
            $pipeline['trend'][] = ['label' => $period['checkpoint']['label'], 'value' => ! $eligible->contains(fn ($event) => $event->effective_at <= $end) || $period['checkpoint']['as_of_date'] > now('Asia/Manila')->toDateString() ? null : $count];
            $previous = $end;
        }
        $engagement = ['total' => 0, 'classes' => [], 'by_unit' => []];
        $members = DB::table('members as m')->leftJoin('engagement_results as e', 'e.member_id', '=', 'm.id')
            ->whereNotIn('m.satellite_id', \App\Models\Satellite::archived()->select('id'))->whereNull('m.archived_at')->where('m.status', '!=', 'Inactive')
            ->get(['m.id', 'm.satellite_id', 'e.classification']);
        foreach ($members as $member) {
            $unitId = $bySatellite->get($member->satellite_id)?->unit_id;
            if ($report['group'] !== null && ! $units->has($unitId)) {
                continue;
            }
            $key = $units->get($unitId)['key'] ?? 'unmapped';
            $label = $member->classification ? (new EngagementResult(['classification' => $member->classification]))->classification_label : 'Not classified';
            $engagement['classes'][$label] = ($engagement['classes'][$label] ?? 0) + 1;
            $engagement['by_unit'][$key] ??= ['name' => $units->get($unitId)['name'] ?? 'Unmapped / unassigned', 'classes' => []];
            $engagement['by_unit'][$key]['classes'][$label] = ($engagement['by_unit'][$key]['classes'][$label] ?? 0) + 1;
            $engagement['total']++;
        }
        $sentiment = ['Positive' => 0, 'Neutral' => 0, 'Negative' => 0];
        $followups = DB::table('follow_ups as f')->leftJoin('members as m', 'm.id', '=', 'f.member_id')->leftJoin('seekers as s', 's.id', '=', 'f.seeker_id')
            ->where(fn ($q) => $q->whereNotNull('f.member_id')->orWhereNotNull('f.seeker_id'))
            ->whereNotNull('f.sentiment_label')->get(['f.satellite_id', 'm.satellite_id as member_satellite', 's.assigned_satellite_slug', 'f.sentiment_label']);
        $historicalUnitIds = \App\Models\OrgUnit::when($report['group'], fn ($q) => $q->where('group', $report['group']))->pluck('id');
        foreach ($followups as $followup) {
            $unitId = $bySatellite->get($followup->satellite_id)?->unit_id ?? $bySatellite->get($followup->member_satellite)?->unit_id ?? $bySlug->get($followup->assigned_satellite_slug)?->unit_id;
            if ($report['group'] !== null && ! $historicalUnitIds->contains($unitId)) {
                continue;
            }
            if (array_key_exists($followup->sentiment_label, $sentiment)) {
                $sentiment[$followup->sentiment_label]++;
            }
        }
        $remarks = DB::table('unit_snapshots')->whereIn('unit_id', $units->keys())->get(['unit_id', 'checkpoint_id', 'source', 'remarks', 'reported_at', 'closed_at']);
        $imports = DB::table('analytics_import_rows as r')->join('analytics_imports as i', 'i.id', '=', 'r.import_id')->join('checkpoints as c', 'c.id', '=', 'r.checkpoint_id')
            ->whereIn('r.unit_id', $units->keys())->orderByDesc('i.created_at')->get(['r.unit_id', 'r.source_tab', 'r.row_number', 'i.created_at', 'c.label']);
        $cpLabels = collect($report['periods'])->mapWithKeys(fn ($p) => [$p['checkpoint']['key'] => $p['checkpoint']['label']]);
        $ids = DB::table('checkpoints')->pluck('key', 'id');
        $closures = DB::table('checkpoint_closures')->orderByDesc('created_at')->get();
        $revisions = DB::table('checkpoint_revisions')->orderByDesc('version')->get();
        foreach ($report['units'] as &$unit) {
            $descendants = [$unit['id']];
            do {
                $before = count($descendants);
                foreach ($units as $child) {
                    if (in_array($child['parent_id'], $descendants, true) && ! in_array($child['id'], $descendants, true)) {
                        $descendants[] = $child['id'];
                    }
                }
            } while (count($descendants) > $before);
            $unit['satellites'] = $maps->whereIn('unit_id', $descendants)->pluck('name')->values()->all();
            $unit['remarks'] = $remarks->where('unit_id', $unit['id'])->filter(fn ($r) => filled($r->remarks))->map(fn ($r) => ['checkpoint' => $cpLabels[$ids[$r->checkpoint_id]] ?? '', 'text' => $r->remarks, 'source' => $r->source])->values()->all();
            $unit['change_log'] = $imports->where('unit_id', $unit['id'])->take(20)->map(fn ($r) => ['at' => $r->created_at, 'text' => $r->label.' · '.$r->source_tab.' · source row '.$r->row_number])->values()->all();
            foreach ($closures as $close) {
                $unit['change_log'][] = ['at' => $close->created_at, 'text' => ($ids[$close->checkpoint_id] ?? '').' closed by actor #'.$close->actor_id.'; '.$close->reason.'; evidence: '.$close->evidence_reference];
            }
            foreach ($revisions as $revision) {
                $changes = array_values(array_filter(json_decode($revision->changes, true), fn ($change) => $change['unit'] === $unit['key']));
                if ($changes) {
                    $unit['change_log'][] = ['at' => $revision->created_at, 'text' => ($ids[$revision->checkpoint_id] ?? '').' revision '.$revision->version.' by actor #'.$revision->actor_id.'; '.$revision->reason.'; evidence: '.$revision->evidence_reference, 'changes' => $changes];
                }
            }
            foreach ($report['conflicts'] ?? [] as $conflict) {
                if ($conflict['unit'] === $unit['key'] && $conflict['resolution']) {
                    $r = $conflict['resolution'];
                    $unit['change_log'][] = ['at' => $r->created_at, 'text' => 'Conflict resolved by actor #'.$r->actor_id.'; chosen '.$r->chosen_values.'; '.$r->reason.'; evidence '.$r->evidence_reference];
                }
            }
            usort($unit['change_log'], fn ($a, $b) => strcmp($b['at'], $a['at']));
        }
        unset($unit);
        $report['historical_sentiment_note'] = 'Historical sentiment includes archived satellites; linked records are retained.';
        $report['activity'] = ['scope' => 'Current records · '.now('Asia/Manila')->toDateString(), 'pipeline' => $pipeline, 'engagement' => $engagement, 'sentiment' => $sentiment];
        $report['forecasts'] = (new MovementForecast)->build($report);
        $report['pivot'] = $this->pivot($report);

        return $report;
    }

    public function pivot(array $report): array
    {
        $columns = ['Unit', 'Group', 'Metric'];
        foreach ($report['periods'] as $period) {
            foreach (['value', 'state', 'source', 'as_of', 'quality', 'coverage'] as $field) {
                $columns[] = $period['checkpoint']['label'].' '.$field;
            }
        }
        array_push($columns, 'Displayed value', 'Displayed source', 'Displayed as of', 'Displayed quality', 'Displayed coverage');
        array_push($columns, 'Change vs baseline', 'Percent vs baseline', 'Change vs previous', 'Percent vs previous');
        array_push($columns, 'Baseline comparison coverage', 'Previous comparison coverage');
        $rows = [];
        foreach ($report['units'] as $unit) {
            foreach (config('movement_analytics.metrics') as $metric) {
                $row = [$unit['name'], $unit['group'] ?? 'ALL', $metric];
                foreach ($report['periods'] as $period) {
                    $cell = $period['units'][$unit['id']][$metric];
                    array_push($row, $cell['value'], $cell['state'], $cell['source'], $cell['as_of'], $cell['quality'], $cell['coverage']['known'].' of '.$cell['coverage']['total']);
                }
                $display = $unit['metrics'][$metric];
                array_push($row, $display['value'], $display['source'], $display['as_of'], $display['quality'], $display['coverage']['known'].' of '.$display['coverage']['total']);
                $changes = $unit['metrics'][$metric]['changes'];
                array_push($row, $changes['baseline']['variance'], $changes['baseline']['pct'], $changes['previous']['variance'], $changes['previous']['pct']);
                foreach (['baseline', 'previous'] as $comparison) {
                    $row[] = $changes[$comparison]['compared'].' of '.$changes[$comparison]['total'];
                }
                $rows[] = $row;
            }
        }

        return ['columns' => $columns, 'rows' => $rows];
    }
}
