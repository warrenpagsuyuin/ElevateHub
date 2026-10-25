<?php

namespace App\Services;

/**
 * Cohen's kappa for two ratings per item.
 * kappa = (p_o - p_e) / (1 - p_e); returns null when undefined (no items, or p_e = 1).
 */
class CohenKappa
{
    /** @param array<int, array{0:string,1:string}> $pairs */
    public function calculate(array $pairs, array $labels): array
    {
        $n = count($pairs);
        if ($n === 0) return ['n' => 0, 'observed' => null, 'expected' => null, 'kappa' => null];
        $agree = 0;
        $first = array_fill_keys($labels, 0);
        $second = array_fill_keys($labels, 0);
        foreach ($pairs as [$a, $b]) {
            if ($a === $b) $agree++;
            if (array_key_exists($a, $first)) $first[$a]++;
            if (array_key_exists($b, $second)) $second[$b]++;
        }
        $observed = $agree / $n;
        $expected = 0.0;
        foreach ($labels as $label) $expected += ($first[$label] / $n) * ($second[$label] / $n);
        $kappa = abs(1 - $expected) < 1e-12 ? null : ($observed - $expected) / (1 - $expected);
        return ['n' => $n, 'observed' => $observed, 'expected' => $expected, 'kappa' => $kappa];
    }
}
