<?php

namespace Tests\Unit;

use App\Services\ClassificationMetrics;
use PHPUnit\Framework\TestCase;

class ClassificationMetricsTest extends TestCase
{
    public function test_matrix_drives_accuracy_macro_weighted_and_totals(): void
    {
        $result = (new ClassificationMetrics)->calculate(['Positive', 'Neutral', 'Negative'], [
            'Positive' => ['Positive' => 4, 'Neutral' => 1, 'Negative' => 1],
            'Neutral' => ['Positive' => 1, 'Neutral' => 0, 'Negative' => 0],
            'Negative' => ['Positive' => 1, 'Neutral' => 0, 'Negative' => 2],
        ]);
        $this->assertSame(10, $result['n']);
        $this->assertSame(6, $result['correct']);
        $this->assertSame([6, 1, 3], array_values(array_map(fn ($row) => array_sum($row), $result['confusion_matrix'])));
        $this->assertEqualsWithDelta(.6, $result['accuracy'], 1e-12);
        $this->assertEqualsWithDelta((4 / 6 + 0 + 2 / 3) / 3, $result['macro']['precision'], 1e-12);
        $this->assertEqualsWithDelta((4 / 6 + 0 + 2 / 3) / 3, $result['macro']['recall'], 1e-12);
        $this->assertEqualsWithDelta((2 / 3 + 0 + 2 / 3) / 3, $result['macro']['f1'], 1e-12);
        $this->assertTrue($result['small_sample']);
        $this->assertLessThan($result['accuracy'], $result['accuracy_ci'][0]);
        $this->assertGreaterThan($result['accuracy'], $result['accuracy_ci'][1]);
    }

    public function test_empty_and_single_class_are_finite(): void
    {
        $service = new ClassificationMetrics;
        $this->assertNull($service->calculate(['A', 'B'], [])['accuracy']);
        $result = $service->calculate(['A', 'B'], ['A' => ['A' => 1000000]]);
        $this->assertSame(1000000, $result['n']);
        $this->assertSame(0, $result['per_class']['B']['predicted']);
        $this->assertSame(.5, $result['macro']['f1']);
    }
}
