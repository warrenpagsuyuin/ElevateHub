<?php

namespace App\Services;

use InvalidArgumentException;

/** Metrics for a fixed-label confusion matrix. Rows are human labels. */
class ClassificationMetrics
{
    public const SMALL_SAMPLE = 30;
    public const SMALL_CLASS = 5;
    /** Below this many evaluated items the dashboard labels metrics as unstable. */
    public const UNSTABLE_SAMPLE = 50;

    public function calculate(array $labels, array $matrix): array
    {
        $normalized = array_fill_keys($labels, array_fill_keys($labels, 0));
        foreach ($matrix as $actual => $predictions) {
            if (! array_key_exists($actual, $normalized)) throw new InvalidArgumentException('Unknown actual label.');
            foreach ($predictions as $predicted => $count) {
                if (! array_key_exists($predicted, $normalized[$actual]) || ! is_numeric($count) || $count < 0) throw new InvalidArgumentException('Invalid matrix cell.');
                $normalized[$actual][$predicted] = (int) $count;
            }
        }
        $n = array_sum(array_map('array_sum', $normalized));
        $correct = 0;
        $macro = $weighted = ['precision' => 0.0, 'recall' => 0.0, 'f1' => 0.0];
        $classes = [];
        foreach ($labels as $label) {
            $tp = $normalized[$label][$label];
            $correct += $tp;
            $support = array_sum($normalized[$label]);
            $predicted = array_sum(array_column($normalized, $label));
            $precision = $predicted ? $tp / $predicted : 0.0;
            $recall = $support ? $tp / $support : 0.0;
            $f1 = $precision + $recall ? 2 * $precision * $recall / ($precision + $recall) : 0.0;
            $classes[$label] = compact('precision', 'recall', 'f1', 'support', 'predicted');
            foreach (['precision' => $precision, 'recall' => $recall, 'f1' => $f1] as $metric => $value) {
                $macro[$metric] += $value / count($labels);
                $weighted[$metric] += $n ? $value * $support / $n : 0;
            }
        }
        $z = 1.959963984540054;
        $p = $n ? $correct / $n : 0;
        $center = $n ? ($p + $z * $z / (2 * $n)) / (1 + $z * $z / $n) : 0;
        $margin = $n ? $z * sqrt(($p * (1 - $p) + $z * $z / (4 * $n)) / $n) / (1 + $z * $z / $n) : 0;
        // Majority-class baseline: accuracy of always predicting the most frequent actual class.
        $majoritySupport = $n ? max(array_map(fn ($row) => $row['support'], $classes)) : 0;
        $majorityLabel = $n ? array_search($majoritySupport, array_map(fn ($row) => $row['support'], $classes), true) : null;
        return ['labels' => $labels, 'confusion_matrix' => $normalized, 'n' => $n, 'correct' => $correct,
            'majority_baseline' => $n ? $majoritySupport / $n : null, 'majority_label' => $majorityLabel,
            'unstable' => $n > 0 && ($n < self::UNSTABLE_SAMPLE || collect($classes)->contains(fn ($row) => $row['support'] < self::SMALL_CLASS)),
            'accuracy' => $n ? $p : null, 'accuracy_ci' => $n ? [max(0, $center - $margin), min(1, $center + $margin)] : null,
            'per_class' => $classes, 'macro' => $n ? $macro : null, 'weighted' => $n ? $weighted : null,
            'small_sample' => $n > 0 && ($n < self::SMALL_SAMPLE || collect($classes)->contains(fn ($row) => $row['support'] < self::SMALL_CLASS))];
    }
}
