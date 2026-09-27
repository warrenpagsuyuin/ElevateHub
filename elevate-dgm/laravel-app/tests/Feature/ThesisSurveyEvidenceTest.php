<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\FeedbackAnalysisUnit;
use App\Models\User;
use App\Services\ThesisSurveyEvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class ThesisSurveyEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private function period(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $reviewer=User::factory()->create();
        DB::table('sentiment_research_settings')->insert([
            'period_start' => '2026-09-03', 'period_end' => '2026-09-10',
            'approved_by'=>$reviewer->id, 'approved_at'=>'2026-09-11', 'approval_reason'=>'Synthetic instrument review fixture',
        ]);
    }

    private function unit(string $text, array $unitValues = [], string $consent = 'Granted'): FeedbackAnalysisUnit
    {
        $feedback = Feedback::create(['message' => $text, 'research_consent_status' => $consent, 'created_at' => '2026-09-05',
            'research_consent_source'=>'Synthetic test form','research_consent_evidence'=>'Synthetic permission','research_consented_at'=>'2026-09-05']);

        return FeedbackAnalysisUnit::create($unitValues + [
            'feedback_id' => $feedback->id, 'source_key' => 'feedback:'.$feedback->id,
            'feedback_code' => sprintf('F%03d', $feedback->id), 'feedback_text' => $text,
            'text_hash' => hash('sha256', $text), 'scope_status' => 'System Feedback',
            'scope_reviewed_by' => 1, 'scope_reviewed_at' => '2026-09-06', 'is_test_record' => false,
        ]);
    }

    public function test_text_numeric_strings_payload_numbers_and_sentiment_cannot_become_likert_ratings(): void
    {
        $respondent = User::factory()->create();
        foreach (['Very useful', '5', '4.50'] as $answer) {
            Feedback::create([
                'evaluator_user_id' => $respondent->id, 'message' => $answer,
                'question_1' => $answer, 'question_5' => 'Excellent',
                'feedback_payload' => ['answers' => ['question_1' => 5], 'ratings' => [5, 4]],
                'research_consent_status' => 'Granted', 'sentiment' => 'Positive', 'sentiment_score' => 5,
            ]);
        }
        Feedback::create(['message' => 'Unknown respondent', 'research_consent_status' => 'Denied']);

        $report = app(ThesisSurveyEvidenceService::class)->report();
        $this->assertSame(4, $report['metadata']['submission_count']);
        $this->assertSame(1, $report['metadata']['distinct_respondent_count']);
        $this->assertSame(1, $report['metadata']['submissions_without_respondent']);
        $this->assertSame(3, $report['metadata']['consented_submission_count']);
        $this->assertSame(0, $report['metadata']['eligible_likert_n']);
        $this->assertSame('N/A – No eligible observations', $report['table22']['status']);
        $this->assertNull($report['table22']['overall_weighted_mean']);
        $this->assertSame(ThesisSurveyEvidenceService::CATEGORIES, array_column($report['table22']['categories'], 'category'));
        foreach ($report['table22']['categories'] as $row) {
            $this->assertSame(0, $row['eligible_n']);
            $this->assertSame([], $row['item_mapping']);
            $this->assertNull($row['weighted_mean']);
            $this->assertNull($row['distribution']);
            $this->assertSame('N/A – No eligible observations', $row['interpretation']);
        }
        $this->assertFalse($report['validations']['text_converted_to_ratings']);
    }

    public function test_denied_and_unknown_consent_are_excluded_and_all_portable_text_is_withheld(): void
    {
        $this->period();
        $allowed = $this->unit('Fixture consented text with name person@example.test', ['quote_approved' => true]);
        $this->unit('Fixture denied sensitive text', ['quote_approved' => true], 'Denied');
        $this->unit('Fixture unknown sensitive text', [], 'Unknown');

        $report = app(ThesisSurveyEvidenceService::class)->report();
        $this->assertSame(1, $report['qualitative']['candidate_count']);
        $this->assertSame(2, $report['qualitative']['excluded_unconsented_leaf_count']);
        $candidate = $report['qualitative']['candidates'][0];
        $this->assertSame($allowed->feedback_code, $candidate['feedback_code']);
        $this->assertTrue($candidate['quote_approved']);
        $this->assertNull($candidate['text']);
        $json = json_encode($report, JSON_THROW_ON_ERROR);
        foreach (['person@example.test', 'Fixture consented text', 'Fixture denied', 'Fixture unknown'] as $text) {
            $this->assertStringNotContainsString($text, $json);
        }
        $this->assertSame('HUMAN THEMATIC CODING REQUIRED', $report['qualitative']['status']);
        $this->assertSame([], $report['qualitative']['themes']);
        $this->assertSame(0, $report['qualitative']['coded_n']);
        $this->assertNull($allowed->fresh()->human_reference_sentiment);
    }

    public function test_parent_is_excluded_when_children_exist_even_if_saved_scope_is_inconsistent(): void
    {
        $this->period();
        $parent = $this->unit('Fixture system is helpful but reports are slow.');
        foreach (['Fixture system is helpful', 'reports are slow.'] as $index => $text) {
            FeedbackAnalysisUnit::create([
                'feedback_id' => $parent->feedback_id, 'parent_unit_id' => $parent->id,
                'source_key' => 'child:'.$index, 'feedback_code' => 'C'.$index, 'feedback_text' => $text,
                'text_hash' => hash('sha256', $text), 'scope_status' => 'System Feedback',
                'scope_reviewed_by' => 1, 'scope_reviewed_at' => '2026-09-06', 'is_test_record' => false,
            ]);
        }

        $report = app(ThesisSurveyEvidenceService::class)->report();
        $this->assertSame(1, $report['metadata']['submission_count']);
        $this->assertSame(1, $report['qualitative']['excluded_split_parent_count']);
        $this->assertSame(2, $report['qualitative']['candidate_count']);
        $this->assertSame(['C0', 'C1'], array_column($report['qualitative']['candidates'], 'feedback_code'));
        $this->assertSame([$parent->feedback_code, $parent->feedback_code], array_column($report['qualitative']['candidates'], 'parent_code'));
    }

    public function test_scope_test_period_and_duplicates_require_review_without_sentiment_requirements(): void
    {
        $this->period();
        $this->unit('Fixture same system opinion', ['scope_status' => 'Pending', 'is_test_record' => null]);
        $eligible = $this->unit('Fixture same system opinion');
        $this->unit(' Fixture same   SYSTEM opinion ');
        $this->unit('Fixture marked as test', ['is_test_record' => true]);
        $old = $this->unit('Fixture earlier opinion');
        $old->feedback->update(['created_at' => '2026-09-02']);
        $this->unit('1234');

        $report = app(ThesisSurveyEvidenceService::class)->report();
        $this->assertSame(6, $report['qualitative']['potential_leaf_count']);
        $this->assertSame(1, $report['qualitative']['candidate_count']);
        $this->assertSame($eligible->feedback_code, $report['qualitative']['candidates'][0]['feedback_code']);
        $this->assertSame(5, $report['qualitative']['pending_or_excluded_consented_leaf_count']);
        $this->assertNull($eligible->human_reference_sentiment);
        $this->assertNull($eligible->system_prediction);
        $this->assertSame(1, $report['qualitative']['exclusion_reason_counts']['Duplicate of '.$eligible->feedback_code]);
    }

    public function test_report_issues_no_database_mutations(): void
    {
        $this->period();
        $this->unit('Fixture source opinion');
        $queries = [];
        DB::listen(function ($event) use (&$queries): void { $queries[] = $event->sql; });
        app(ThesisSurveyEvidenceService::class)->report();
        $this->assertNotEmpty($queries);
        foreach ($queries as $query) {
            $this->assertMatchesRegularExpression('/^select\s/i', trim($query));
        }
    }

    public function test_explicit_integer_rating_calculator_uses_correct_frequencies_and_weighted_mean(): void
    {
        $service = app(ThesisSurveyEvidenceService::class);
        $result = $service->calculateWeightedMean([1, 2, 3, 4, 5, 5]);
        $this->assertSame(6, $result['n']);
        $this->assertSame([1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 2], $result['distribution']);
        $this->assertEqualsWithDelta(20 / 6, $result['raw_weighted_mean'], 1e-12);
        $this->assertSame(3.33, $result['weighted_mean']);
        $this->assertSame('Satisfactory', $result['interpretation']);
        $empty = $service->calculateWeightedMean([]);
        $this->assertSame(0, $empty['n']);
        $this->assertNull($empty['weighted_mean']);
        $this->assertSame('N/A – No eligible observations', $empty['interpretation']);
        foreach ([0, 6, '5', 'Very useful', 4.5, true, null] as $invalid) {
            try {
                $service->calculateWeightedMean([$invalid]);
                $this->fail('Non-integer or out-of-range rating must be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('integer from 1 to 5', $exception->getMessage());
            }
        }
    }

    public function test_overall_mean_uses_explicit_weights_and_cannot_hide_missing_categories(): void
    {
        $ratings=array_combine(ThesisSurveyEvidenceService::CATEGORIES,[[1],[2],[3],[4],[5]]);
        $weights=array_fill_keys(ThesisSurveyEvidenceService::CATEGORIES,1);
        $service=app(ThesisSurveyEvidenceService::class);
        $this->assertSame(3.0,$service->calculateOverallMean($ratings,$weights)['weighted_mean']);
        $weights['Overall Satisfaction']=2;
        $this->assertEqualsWithDelta(20/6,$service->calculateOverallMean($ratings,$weights)['raw_weighted_mean'],1e-12);
        $ratings['Reliability']=[];
        $this->assertNull($service->calculateOverallMean($ratings,$weights)['weighted_mean']);
        $this->expectException(InvalidArgumentException::class);
        $service->calculateOverallMean($ratings,[]);
    }

    public function test_qualitative_candidates_require_documented_consent_and_approved_period(): void
    {
        $this->period();
        $unit=$this->unit('Synthetic source needing valid consent evidence');
        $unit->feedback->update(['research_consent_evidence'=>null]);
        $this->assertSame(0,app(ThesisSurveyEvidenceService::class)->report()['qualitative']['candidate_count']);
        $unit->feedback->update(['research_consent_evidence'=>'Synthetic permission']);
        DB::table('sentiment_research_settings')->update(['approved_at'=>null]);
        $this->assertSame(0,app(ThesisSurveyEvidenceService::class)->report()['qualitative']['candidate_count']);
    }

    public function test_interpretation_boundaries_use_two_decimal_half_up_rounding(): void
    {
        $service = app(ThesisSurveyEvidenceService::class);
        $labels = ['Poor', 'Fair', 'Satisfactory', 'Very Satisfactory', 'Excellent'];
        foreach (range(1, 4) as $lower) {
            $below = [...array_fill(0, 51, $lower), ...array_fill(0, 49, $lower + 1)];
            $boundary = [...array_fill(0, 50, $lower), ...array_fill(0, 50, $lower + 1)];
            $this->assertSame($labels[$lower - 1], $service->calculateWeightedMean($below)['interpretation']);
            $this->assertSame($labels[$lower], $service->calculateWeightedMean($boundary)['interpretation']);
        }
        $roundsUp = [...array_fill(0, 101, 4), ...array_fill(0, 99, 5)];
        $this->assertSame(4.5, $service->calculateWeightedMean($roundsUp)['weighted_mean']);
        $this->assertSame('Excellent', $service->calculateWeightedMean($roundsUp)['interpretation']);
        $this->assertSame('Poor', $service->calculateWeightedMean([1])['interpretation']);
        $this->assertSame('Excellent', $service->calculateWeightedMean([5])['interpretation']);
    }
}
