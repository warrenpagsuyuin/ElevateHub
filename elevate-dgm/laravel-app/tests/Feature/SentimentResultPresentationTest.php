<?php

namespace Tests\Feature;

use Tests\TestCase;

class SentimentResultPresentationTest extends TestCase
{
    public function test_all_labels_render_native_collapsed_disclosures_with_original_explanation_and_confidence(): void
    {
        foreach (['Positive', 'Neutral', 'Negative'] as $sentiment) {
            $html = view('components.sentiment-result', [
                'showPrediction' => true, 'sentiment' => $sentiment, 'confidence' => .5,
                'decisionPath' => ['Stored explanation <unchanged>'],
            ])->render();
            $this->assertStringContainsString('<details class="sentiment-result"', $html);
            $this->assertStringNotContainsString('<details open', $html);
            $this->assertStringContainsString($sentiment, $html);
            $this->assertStringContainsString('50% confidence', $html);
            $this->assertStringContainsString('Stored explanation &lt;unchanged&gt;', $html);
            $this->assertStringNotContainsString('Why this sentiment?', $html);
        }
    }

    public function test_result_is_absent_when_prediction_visibility_is_false_or_not_supplied(): void
    {
        $data = ['sentiment' => 'Negative', 'confidence' => .73, 'decisionPath' => ['PRIVATE PREDICTION EXPLANATION']];
        $this->assertSame('', trim(view('components.sentiment-result', $data)->render()));
        $this->assertSame('', trim(view('components.sentiment-result', $data + ['showPrediction' => false])->render()));
    }

    public function test_unanalyzed_result_without_explanation_has_no_empty_disclosure_or_invented_confidence(): void
    {
        $html = view('components.sentiment-result', ['showPrediction' => true, 'sentiment' => null, 'confidence' => null, 'decisionPath' => []])->render();
        $this->assertStringContainsString('Unanalyzed', $html);
        $this->assertStringNotContainsString('<details', $html);
        $this->assertStringNotContainsString('% confidence', $html);
    }
}
