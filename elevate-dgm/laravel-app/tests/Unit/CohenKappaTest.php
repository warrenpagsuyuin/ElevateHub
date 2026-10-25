<?php

namespace Tests\Unit;

use App\Services\CohenKappa;
use PHPUnit\Framework\TestCase;

class CohenKappaTest extends TestCase
{
    public function test_perfect_agreement(): void
    {
        $pairs = [
            ['Positive', 'Positive'],
            ['Neutral', 'Neutral'],
            ['Negative', 'Negative'],
            ['Positive', 'Positive'],
        ];

        $result = (new CohenKappa)->calculate($pairs, ['Positive', 'Neutral', 'Negative']);
        $this->assertSame(4, $result['n']);
        $this->assertEqualsWithDelta(1.0, $result['observed'], 1e-6);
        $this->assertEqualsWithDelta(1.0, $result['kappa'], 1e-6);
    }

    public function test_known_kappa_example(): void
    {
        // 10 items
        // R1: 6 Positive, 4 Negative
        // R2: 5 Positive, 5 Negative
        // Agreement on 4 Positive, 3 Negative = 7 total agree
        $pairs = [
            ['Positive', 'Positive'],
            ['Positive', 'Positive'],
            ['Positive', 'Positive'],
            ['Positive', 'Positive'],
            ['Positive', 'Negative'],
            ['Positive', 'Negative'],
            ['Negative', 'Negative'],
            ['Negative', 'Negative'],
            ['Negative', 'Negative'],
            ['Negative', 'Positive'],
        ];

        $result = (new CohenKappa)->calculate($pairs, ['Positive', 'Negative']);
        $this->assertSame(10, $result['n']);
        // Observed = 7 / 10 = 0.70
        $this->assertEqualsWithDelta(0.70, $result['observed'], 1e-6);
        // P(e) = (0.6 * 0.5) + (0.4 * 0.5) = 0.30 + 0.20 = 0.50
        $this->assertEqualsWithDelta(0.50, $result['expected'], 1e-6);
        // Kappa = (0.70 - 0.50) / (1 - 0.50) = 0.20 / 0.50 = 0.40
        $this->assertEqualsWithDelta(0.40, $result['kappa'], 1e-6);
    }

    public function test_empty_pairs_returns_null_kappa(): void
    {
        $result = (new CohenKappa)->calculate([], ['Positive', 'Neutral', 'Negative']);
        $this->assertSame(0, $result['n']);
        $this->assertNull($result['kappa']);
    }
}
