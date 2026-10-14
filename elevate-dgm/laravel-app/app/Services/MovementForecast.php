<?php

namespace App\Services;

/** Explanatory projections only; never stored as measured counts or trained accuracy. */
class MovementForecast
{
    public function build(array $report): array
    {
        $periods = array_values($report['periods']);
        $selected = array_search($report['checkpoint']['key'], array_column(array_column($periods, 'checkpoint'), 'key'), true);
        $next = $periods[$selected + 1]['checkpoint'] ?? null;
        $forecasts = [];
        foreach ($report['units'] as $unit) {
            $key = $unit['key'];
            foreach (config('movement_analytics.metrics') as $metric) {
                $points = [];
                foreach ($periods as $index => $period) {
                    if ($index > $selected || $period['checkpoint']['as_of_date'] > now('Asia/Manila')->toDateString()) {
                        continue;
                    }
                    $cell = $period['units'][$unit['id']][$metric];
                    if ($cell['state'] === 'reported' && $cell['value'] !== null && ($cell['quality'] ?? 'ok') !== 'conflict') {
                        $points[] = ['x' => $index, 'y' => $cell['value']];
                    }
                }
                $backtest = null;
                $byKey = $report['periods'];
                $values = [];
                foreach (['2025-12', '2026-03', '2026-06'] as $periodKey) {
                    $cell = $byKey[$periodKey]['units'][$unit['id']][$metric] ?? null;
                    $values[] = $cell && $cell['state'] === 'reported' ? $cell['value'] : null;
                }
                if ($report['checkpoint']['as_of_date'] >= '2026-06-30' && ! in_array(null, $values, true)) {
                    [$dec,$mar,$jun] = $values;
                    $prediction = max(0, $mar + ($mar - $dec));
                    $backtest = ['predicted' => $prediction, 'actual' => $jun, 'absolute_error' => abs($prediction - $jun), 'no_change_error' => abs($mar - $jun)];
                }
                if (count($points) < 3 || ! $next || end($points)['x'] !== $selected) {
                    $forecasts[$key][$metric] = ['available' => false, 'points' => count($points), 'backtest' => $backtest,
                        'reason' => ! $next ? 'No next checkpoint is configured.' : 'At least three reported checkpoints, including the selected one, are required.'];

                    continue;
                }
                $meanX = array_sum(array_column($points, 'x')) / count($points);
                $meanY = array_sum(array_column($points, 'y')) / count($points);
                $numerator = 0;
                $denominator = 0;
                foreach ($points as $point) {
                    $numerator += ($point['x'] - $meanX) * ($point['y'] - $meanY);
                    $denominator += ($point['x'] - $meanX) ** 2;
                }
                $slope = $numerator / $denominator;
                $intercept = $meanY - $slope * $meanX;
                $estimate = max(0, $intercept + $slope * ($selected + 1));
                $residual = 0;
                $steps = [];
                foreach ($points as $i => $point) {
                    $residual = max($residual, abs($point['y'] - ($intercept + $slope * $point['x'])));
                    if ($i) {
                        $steps[] = abs(($point['y'] - $points[$i - 1]['y']) / ($point['x'] - $points[$i - 1]['x']));
                    }
                }
                $spread = max(1, $residual, array_sum($steps) / count($steps));
                $forecasts[$key][$metric] = ['available' => true, 'points' => count($points), 'next' => $next['label'],
                    'low' => (int) floor(max(0, $estimate - $spread)), 'high' => (int) ceil($estimate + $spread), 'backtest' => $backtest];
            }
        }

        return ['units' => $forecasts, 'method' => 'Linear trend over checkpoint positions with at least three measured points. The range uses the larger of the largest fitted residual and average absolute checkpoint change (minimum one count). It is a scenario range, not a calibrated confidence interval.',
            'backtest_method' => 'Predict June 2026 using December 2025 and March 2026 only: March plus the previous change, floored at zero. Compare absolute count error with carrying March forward unchanged. No thresholds or models are fitted to June.'];
    }
}
