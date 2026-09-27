<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\FeedbackAnalysisUnit;
use App\Models\User;
use App\Services\SentimentAnalysisService;
use App\Services\SentimentResearchMetrics;
use App\Services\ThesisSentimentEvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ThesisSentimentEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', app()->environment());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->reviewer = User::factory()->create(['role' => 'admin']);
        DB::table('sentiment_research_settings')->insert([
            'period_start' => '2026-09-01', 'period_end' => '2026-09-30',
            'approved_by' => $this->reviewer->id, 'approved_at' => '2026-09-01 08:00:00',
            'approval_reason' => 'SYNTHETIC TEST FIXTURE ONLY',
        ]);
    }

    private function unit(string $text = 'The system is useful.', string $actual = 'Positive', string $language = 'English'): FeedbackAnalysisUnit
    {
        $feedback = Feedback::create([
            'message' => $text, 'created_at' => '2026-09-05 12:00:00',
            'research_consent_status' => 'Granted', 'research_consent_source' => 'SYNTHETIC TEST CONSENT',
            'research_consent_evidence' => 'SYNTHETIC TEST EVIDENCE', 'research_consented_at' => '2026-09-05 12:01:00',
        ]);
        return FeedbackAnalysisUnit::create($this->reviewFields($text, $actual, $language) + [
            'feedback_id' => $feedback->id, 'source_key' => 'test:'.$feedback->id, 'feedback_code' => 'T'.$feedback->id,
        ]);
    }

    private function reviewFields(string $text, string $actual = 'Positive', string $language = 'English'): array
    {
        return [
            'feedback_text' => $text, 'text_hash' => $this->hash($text),
            'scope_status' => 'System Feedback', 'is_test_record' => false,
            'scope_reviewed_by' => $this->reviewer->id, 'scope_reviewed_at' => '2026-09-06 08:00:00',
            'language_group' => $language, 'language_review_status' => 'Final',
            'language_reviewed_by' => $this->reviewer->id, 'language_reviewed_at' => '2026-09-06 08:00:00',
            'human_reference_sentiment' => $actual, 'human_review_status' => 'Final',
            'human_reviewed_by' => $this->reviewer->id, 'human_reviewed_at' => '2026-09-06 08:00:00',
            'reference_source' => 'Human Reviewer', 'independent_attested' => true,
            'review_independence' => 'Blinded', 'quote_approved' => false,
        ];
    }

    private function hash(string $text): string { return hash('sha256', mb_strtolower(preg_replace('/\s+/u', ' ', trim($text)))); }
    private function report(): array { return app(ThesisSentimentEvidenceService::class)->report(); }
    private function reasons(array $report, int $id): array
    {
        return collect($report['audit']['units'])->firstWhere('id', $id)['reasons'];
    }

    public function test_completed_system_form_never_overrides_scope_exclusions_or_unknown_test_status(): void
    {
        $unit=$this->unit();
        $unit->feedback->update(['research_consent_source'=>'System Evaluation Form','evaluator_user_id'=>$this->reviewer->id,
            'question_1'=>'Useful','question_2'=>'Clear','question_3'=>'Helpful','question_4'=>'No issues','question_5'=>'Satisfied']);
        foreach (['Excluded','Needs Clarification','Pending'] as $scope) {
            $unit->update(['scope_status'=>$scope]);
            $this->assertContains('System-feedback scope review is not finalized',$this->reasons($this->report(),$unit->id));
        }
        $unit->update(['scope_status'=>'System Feedback','is_test_record'=>null,'scope_reviewed_by'=>null]);
        $reasons=$this->reasons($this->report(),$unit->id);
        $this->assertContains('Actual non-test feedback status is not documented',$reasons);
        $this->assertContains('Scope review attribution or timestamp is missing',$reasons);
        $this->assertSame(0,$this->report()['counts']['evaluation_n']);
    }

    public static function exclusionCases(): array
    {
        return [
            'denied consent' => ['feedback', ['research_consent_status' => 'Denied'], 'Research consent is not granted'],
            'unknown consent' => ['feedback', ['research_consent_status' => 'Unknown'], 'Research consent is not granted'],
            'missing consent source' => ['feedback', ['research_consent_source' => null], 'Research consent source, evidence, or timestamp is missing'],
            'missing consent evidence' => ['feedback', ['research_consent_evidence' => '  '], 'Research consent source, evidence, or timestamp is missing'],
            'missing consent date' => ['feedback', ['research_consented_at' => null], 'Research consent source, evidence, or timestamp is missing'],
            'out of period' => ['feedback', ['created_at' => '2026-08-31 23:59:59'], 'Feedback is outside the approved evaluation period'],
            'test record' => ['unit', ['is_test_record' => true], 'Actual non-test feedback status is not documented'],
            'unknown actual status' => ['unit', ['is_test_record' => null], 'Actual non-test feedback status is not documented'],
            'pending scope' => ['unit', ['scope_status' => 'Pending'], 'System-feedback scope review is not finalized'],
            'non-system comment' => ['unit', ['scope_status' => 'Excluded'], 'System-feedback scope review is not finalized'],
            'unattributed scope' => ['unit', ['scope_reviewed_by' => null], 'Scope review attribution or timestamp is missing'],
            'undated scope' => ['unit', ['scope_reviewed_at' => null], 'Scope review attribution or timestamp is missing'],
            'unknown language' => ['unit', ['language_group' => 'Unknown'], 'Final human language review is missing'],
            'unfinalized language' => ['unit', ['language_review_status' => 'Pending'], 'Final human language review is missing'],
            'undated language' => ['unit', ['language_reviewed_at' => null], 'Language review attribution or timestamp is missing'],
            'missing language reviewer' => ['unit', ['language_reviewed_by' => 999999], 'Language review attribution or timestamp is missing'],
            'missing human label' => ['unit', ['human_reference_sentiment' => null], 'Final human sentiment reference is missing'],
            'unfinalized human label' => ['unit', ['human_review_status' => 'Pending'], 'Final human sentiment reference is missing'],
            'undated human label' => ['unit', ['human_reviewed_at' => null], 'Human sentiment review attribution or timestamp is missing'],
            'missing human reviewer' => ['unit', ['human_reviewed_by' => 999999], 'Human sentiment review attribution or timestamp is missing'],
            'copied prediction reference' => ['unit', ['reference_source' => 'System Prediction'], 'Human reviewer reference provenance is not documented'],
            'missing attestation' => ['unit', ['independent_attested' => false], 'Independent blinded human review is not documented'],
            'unknown independence' => ['unit', ['review_independence' => 'Not documented'], 'Independent blinded human review is not documented'],
            'null independence' => ['unit', ['review_independence' => null], 'Independent blinded human review is not documented'],
        ];
    }

    #[DataProvider('exclusionCases')]
    public function test_missing_or_ineligible_evidence_cannot_enter_the_thesis_cohort(string $target, array $changes, string $reason): void
    {
        $unit = $this->unit();
        ($target === 'unit' ? $unit : $unit->feedback)->update($changes);
        $this->partialMock(SentimentAnalysisService::class)->shouldNotReceive('analyze');
        $report = $this->report();
        $this->assertSame(0, $report['counts']['candidate_n']);
        $this->assertSame(0, $report['counts']['evaluation_n']);
        $this->assertContains($reason, $this->reasons($report, $unit->id));
        $this->assertSame('N/A – No eligible observations', $report['table19']['status']);
        $this->assertNull($report['table19']['accuracy']);
    }

    public function test_actual_fixed_engine_predictions_and_all_tables_share_exact_ids_and_metrics(): void
    {
        $rows = [
            ['The system is useful.', 'Positive', 'English'],
            ['The page displays Tuesday.', 'Positive', 'English'],
            ['The calendar displays Wednesday.', 'Neutral', 'Filipino'],
            ['Entries appear Thursday.', 'Negative', 'Filipino-English'],
            ['The report is slow.', 'Negative', 'Filipino'],
        ];
        $ids = [];
        foreach ($rows as [$text, $actual, $language]) $ids[] = $this->unit($text, $actual, $language)->id;
        $report = $this->report();
        $this->assertSame(5, $report['counts']['evaluation_n']);
        $this->assertSame($ids, array_column($report['records'], 'id'));
        $this->assertSame($ids, $report['table18_ids']);
        $this->assertSame($ids, $report['table19']['record_ids']);
        $this->assertSame([$ids[0], $ids[1]], $report['table11_ids']['English']['Positive']);
        $this->assertSame([$ids[2], $ids[4]], $report['table20']['Filipino']['record_ids']);
        $this->assertSame(['Positive' => 1, 'Neutral' => 1, 'Negative' => 0], $report['table18']['Positive']);
        $this->assertSame(['Positive' => 0, 'Neutral' => 1, 'Negative' => 0], $report['table18']['Neutral']);
        $this->assertSame(['Positive' => 0, 'Neutral' => 1, 'Negative' => 1], $report['table18']['Negative']);
        $this->assertEqualsWithDelta(.6, $report['table19']['accuracy'], 1e-12);
        $this->assertEqualsWithDelta(7 / 9, $report['table19']['macro']['precision'], 1e-12);
        $this->assertEqualsWithDelta(2 / 3, $report['table19']['macro']['recall'], 1e-12);
        $this->assertEqualsWithDelta(11 / 18, $report['table19']['macro']['f1'], 1e-12);
        $this->assertEqualsWithDelta(13 / 15, $report['table19']['weighted']['precision'], 1e-12);
        $this->assertEqualsWithDelta(.6, $report['table19']['weighted']['recall'], 1e-12);
        $this->assertEqualsWithDelta(19 / 30, $report['table19']['weighted']['f1'], 1e-12);
        foreach (['table11', 'table12', 'table18_19', 'accuracy', 'table19_20', 'same_evaluation_records'] as $key) $this->assertTrue($report['validations'][$key], $key);
        $this->assertFalse($report['validations']['human_labels_from_predictions']);
        $this->assertSame(0, FeedbackAnalysisUnit::whereNotNull('system_prediction')->count());
    }

    public function test_report_does_not_mutate_sources_and_withholds_text_notes_and_identifiers_even_if_quote_approved(): void
    {
        $unit = $this->unit('PRIVATE PERSON SENTINEL: The system is useful.');
        $unit->update(['system_prediction' => 'Negative', 'quote_approved' => true,
            'human_label_reason' => 'PRIVATE REVIEW NOTE', 'scope_reason' => 'PRIVATE SCOPE NOTE']);
        $unit->feedback->update(['research_consent_evidence' => 'PRIVATE CONSENT NOTE', 'research_consent_source' => 'PRIVATE CONSENT SOURCE']);
        $before = $this->snapshot();
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        $report = $this->report();
        $this->assertSame($before, $this->snapshot());
        foreach ($queries as $query) $this->assertMatchesRegularExpression('/^select\b/i', $query);
        $json = json_encode($report, JSON_THROW_ON_ERROR);
        foreach (['PRIVATE PERSON SENTINEL', 'PRIVATE REVIEW NOTE', 'PRIVATE SCOPE NOTE', 'PRIVATE CONSENT NOTE', 'PRIVATE CONSENT SOURCE', 'evaluator_user_id', 'email'] as $secret) $this->assertStringNotContainsString($secret, $json);
        $this->assertCount(1, $report['table12']);
        $this->assertTrue($report['table12'][0]['text_withheld']);
        $this->assertSame('Negative', $report['audit']['units'][0]['stored_prediction']);
        $this->assertSame('Positive', $report['audit']['units'][0]['computed_prediction']);
        $this->assertSame('Positive', $report['records'][0]['human_reference_sentiment']);
        $this->assertSame('Negative', $unit->fresh()->system_prediction);
        $this->assertFalse($report['audit']['units'][0]['stored_prediction_current']);
        $unit->update(['quote_approved' => false]);
        $withoutQuote = $this->report();
        $this->assertSame($report['table11'], $withoutQuote['table11']);
        $this->assertSame($report['table19'], $withoutQuote['table19']);
        $this->assertSame(1, $withoutQuote['counts']['evaluation_n']);
        $this->assertSame([], $withoutQuote['table12']);
    }

    public function test_a_parent_is_excluded_even_if_its_scope_flag_was_changed_back_and_children_need_own_reviews(): void
    {
        $parent = $this->unit('The system is useful. The report is slow.');
        $child = FeedbackAnalysisUnit::create($this->reviewFields('The system is useful.') + [
            'feedback_id' => $parent->feedback_id, 'parent_unit_id' => $parent->id,
            'source_key' => 'test:child-a', 'feedback_code' => 'CHILD_A',
        ]);
        $unreviewed = FeedbackAnalysisUnit::create([
            'feedback_id' => $parent->feedback_id, 'parent_unit_id' => $parent->id,
            'source_key' => 'test:child-b', 'feedback_code' => 'CHILD_B',
            'feedback_text' => 'The report is slow.', 'text_hash' => $this->hash('The report is slow.'),
        ]);
        $report = $this->report();
        $this->assertSame([$child->id], array_column($report['records'], 'id'));
        $this->assertContains('Split parent excluded from the analysis cohort', $this->reasons($report, $parent->id));
        $this->assertContains('Final human sentiment reference is missing', $this->reasons($report, $unreviewed->id));
        $this->assertNull($unreviewed->fresh()->human_reference_sentiment);
        $parent->update(['scope_status' => 'Split']);
        $this->assertSame([$child->id], array_column($this->report()['records'], 'id'));
        $parent->feedback->update(['research_consent_status' => 'Denied']);
        $this->assertSame(0, $this->report()['counts']['evaluation_n']);
    }

    public function test_parent_exclusion_source_mismatch_nonverbatim_and_cycles_are_rejected(): void
    {
        $parent = $this->unit('The system is useful. The report is slow.');
        $parent->update(['scope_status' => 'Split']);
        $child = FeedbackAnalysisUnit::create($this->reviewFields('The system is useful.') + [
            'feedback_id' => $parent->feedback_id, 'parent_unit_id' => $parent->id,
            'source_key' => 'test:child-a', 'feedback_code' => 'CHILD_A',
        ]);
        $this->assertSame(1, $this->report()['counts']['evaluation_n']);
        $parent->update(['scope_status' => 'Excluded']);
        $this->assertContains('Parent feedback is excluded, a test record, or has unresolved scope', $this->reasons($this->report(), $child->id));
        $parent->update(['scope_status' => 'Split']);
        $other = $this->unit('Another source has a good system.');
        $child->update(['feedback_id' => $other->feedback_id]);
        $this->assertContains('Parent and child source feedback do not match', $this->reasons($this->report(), $child->id));
        $child->update(['feedback_id' => $parent->feedback_id, 'feedback_text' => 'Invented quote', 'text_hash' => $this->hash('Invented quote')]);
        $this->assertContains('Child text is not a verbatim span of its parent', $this->reasons($this->report(), $child->id));
        $child->update(['feedback_text' => 'The system is useful.', 'text_hash' => $this->hash('The system is useful.')]);
        $parent->update(['parent_unit_id' => $child->id]);
        $this->assertContains('Analysis-unit parent cycle detected', $this->reasons($this->report(), $child->id));
        $parent->update(['parent_unit_id' => null]);
        $child->update(['parent_unit_id' => 999999]);
        $this->assertContains('Parent analysis unit is missing', $this->reasons($this->report(), $child->id));
    }

    public function test_blank_duplicate_stale_source_and_undocumented_period_are_excluded(): void
    {
        $first = $this->unit();
        $duplicate = $this->unit('  THE   SYSTEM IS USEFUL.  ');
        $blank = $this->unit('123 ! ?');
        $stale = $this->unit('The report is slow.');
        $stale->feedback->update(['message' => 'The report is fast.']);
        $report = $this->report();
        $this->assertSame([$first->id], array_column($report['records'], 'id'));
        $this->assertContains('Duplicate of analysis unit '.$first->id, $this->reasons($report, $duplicate->id));
        $this->assertContains('Blank or non-meaningful text', $this->reasons($report, $blank->id));
        $this->assertContains('Root analysis text does not match the stored feedback source', $this->reasons($report, $stale->id));
        DB::table('sentiment_research_settings')->update(['approval_reason' => null]);
        $this->assertContains('Documented approved evaluation period is missing', $this->reasons($this->report(), $first->id));
        $this->assertSame(0, $this->report()['counts']['evaluation_n']);
    }

    public function test_prediction_failure_blocks_the_whole_cohort_instead_of_dropping_a_failed_case(): void
    {
        $first = $this->unit();
        $second = $this->unit('The report is slow.', 'Negative');
        $actual = app(SentimentAnalysisService::class)->analyze($first->feedback_text);
        $mock = $this->partialMock(SentimentAnalysisService::class);
        $mock->shouldReceive('analyze')->with($first->feedback_text)->once()->andReturn($actual);
        $mock->shouldReceive('analyze')->with($second->feedback_text)->once()->andThrow(new \RuntimeException('PRIVATE EXCEPTION SOURCE TEXT'));
        $report = $this->report();
        $this->assertSame(2, $report['counts']['candidate_n']);
        $this->assertSame(1, $report['counts']['predictions_available']);
        $this->assertSame(1, $report['counts']['missing_predictions']);
        $this->assertSame(0, $report['counts']['evaluation_n']);
        $this->assertFalse($report['counts']['evaluation_ready']);
        $this->assertSame([], $report['records']);
        $this->assertSame(0, array_sum(array_map('array_sum', $report['table11'])));
        $this->assertSame(0, array_sum(array_map('array_sum', $report['table18'])));
        $this->assertSame(0, array_sum(array_column($report['table20'], 'n')));
        $this->assertTrue($report['validations']['same_evaluation_records']);
        $this->assertStringNotContainsString('PRIVATE EXCEPTION', json_encode($report));
        $this->assertSame(0, FeedbackAnalysisUnit::whereNotNull('system_prediction')->count());
    }

    public function test_absent_classes_and_languages_stay_in_fixed_three_class_metrics(): void
    {
        $this->unit();
        $report = $this->report();
        $this->assertEqualsWithDelta(1 / 3, $report['table19']['macro']['f1'], 1e-12);
        $this->assertSame(0, $report['table19']['per_class']['Negative']['support']);
        $this->assertSame(0.0, $report['table19']['per_class']['Negative']['f1']);
        foreach (['Filipino', 'Filipino-English'] as $language) {
            $this->assertSame('N/A – No eligible observations', $report['table20'][$language]['status']);
            $this->assertNull($report['table20'][$language]['accuracy']);
            $this->assertSame([], $report['table20'][$language]['record_ids']);
        }
    }

    public function test_legacy_labels_are_not_imported_and_missing_units_are_reported_without_creation(): void
    {
        Feedback::create(['message' => 'PRIVATE UNUSED SOURCE', 'human_sentiment_label' => 'Positive',
            'human_labeled_by_id' => $this->reviewer->id, 'sentiment' => 'Positive']);
        $report = $this->report();
        $this->assertSame(0, $report['counts']['analysis_units']);
        $this->assertSame(0, $report['counts']['evaluation_n']);
        $this->assertCount(1, $report['missing_data']);
        $this->assertSame(0, FeedbackAnalysisUnit::count());
        $this->assertStringNotContainsString('PRIVATE UNUSED SOURCE', json_encode($report));
    }

    private function snapshot(): string
    {
        $rows = [];
        foreach (['feedback', 'feedback_analysis_units', 'sentiment_research_settings', 'sentiment_research_audits', 'sentiment_evaluation_runs'] as $table) {
            $rows[$table] = DB::table($table)->orderBy('id')->get()->all();
        }
        return json_encode($rows, JSON_THROW_ON_ERROR);
    }
}
