<?php

namespace App\Services;

/** In-memory, explicitly synthetic preview. Never persists or exports records. */
class MovementSample
{
    public function build(array $report): array
    {
        foreach ($report['periods'] as $index => &$period) {
            $position = array_search($index, array_keys($report['periods']), true);
            foreach ($report['units'] as $unit) {
                foreach (config('movement_analytics.metrics') as $metric) {
                    $leaf = ! collect($report['units'])->contains('parent_id', $unit['id']);
                    $na = $metric === 'campus' && ! $unit['has_campus'];
                    $value = $leaf && ! $na ? ($unit['id'] % 5 + 2 + $position) * ($metric === 'dmembers' ? 4 : 1) : null;
                    $cell = ['value' => $value, 'state' => $na ? 'not_applicable' : ($value === null ? 'not_reported' : 'reported'), 'source' => 'sample'];
                    foreach (['units', 'reported', 'recorded'] as $field) {
                        $period[$field][$unit['id']][$metric] = $cell;
                    }
                }
            }
        }
        unset($period);
        $report['issues'] = [];
        $report = (new MovementOverview)->present($report, 'reported');
        $report = (new MovementDashboardData)->enrich($report);
        $report['sample'] = true;
        $report['conflicts'] = [];
        $report['activity'] = null;
        $report['live'] = [];
        $report['data_last_updated'] = null;
        $report['revision'] = 0;
        foreach ($report['units'] as &$unit) {
            $unit['remarks'] = [];
            $unit['change_log'] = [];
            $unit['satellites'] = [];
            $unit['reported'] = $report['periods'][$report['checkpoint']['key']]['reported'][$unit['id']];
            $unit['recorded'] = $unit['reported'];
            $unit['reported_comparisons'] = [];
            $unit['ratios'] = [];
            $unit['system_vs_last_reported'] = array_fill_keys(config('movement_analytics.metrics'), ['checkpoint' => null, 'reported' => ['value' => null, 'state' => 'not_reported'], 'difference' => null]);
        }
        unset($unit);

        return $report;
    }
}
