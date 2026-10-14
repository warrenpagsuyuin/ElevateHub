<?php

namespace App\Services;

use App\Models\Satellite;
use Illuminate\Support\Facades\DB;

/** Apply archive visibility to dashboard projections, never to saved report evidence. */
class SatelliteArchiveVisibility
{
    public function project(array $report): array
    {
        $archived = Satellite::archived()->pluck('id');
        if ($archived->isEmpty()) {
            return $report;
        }
        $maps = DB::table('org_unit_satellite_mappings')->get()->groupBy('unit_id');
        $excluded = $maps->filter(fn ($rows) => $rows->every(fn ($row) => $archived->contains($row->satellite_id)))->keys()->all();
        $affected = $maps->filter(fn ($rows) => $rows->contains(fn ($row) => $archived->contains($row->satellite_id)))->keys()->all();
        $units = collect($report['units'])->keyBy('id');
        foreach ($affected as $id) {
            $parent = $units->get($id)['parent_id'] ?? null;
            while ($parent !== null) {
                $affected[] = $parent;
                $parent = $units->get($parent)['parent_id'] ?? null;
            }
        }
        $remaining = $units->except($excluded);
        foreach ($report['periods'] as &$period) {
            foreach (['recorded', 'reported'] as $field) {
                foreach (array_unique($affected) as $id) {
                    if (! isset($period[$field][$id])) {
                        continue;
                    }
                    // Parent snapshots include archived children. Rebuild from remaining leaves.
                    $emptyParent = ! $maps->has($id) && ! $remaining->contains(fn ($u) => $u['parent_id'] === $id);
                    foreach ($period[$field][$id] as &$cell) {
                        $cell = ['value' => $emptyParent ? 0 : null, 'state' => $emptyParent ? 'reported' : 'not_reported', 'source' => 'archive_projection'];
                    }
                    unset($cell);
                }
            }
        }
        unset($period);
        $report['units'] = $remaining->values()->all();
        $report['archive_note'] = 'Active projections exclude archived satellites. Original checkpoint evidence is retained in historical reports.';

        return $report;
    }
}
