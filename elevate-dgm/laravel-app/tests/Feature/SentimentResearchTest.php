<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\FeedbackAnalysisUnit;
use App\Models\User;
use App\Services\SentimentAnalysisService;
use App\Services\SentimentResearchService;
use App\Services\SentimentResearchMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SentimentResearchTest extends TestCase
{
    use RefreshDatabase;

    private function setupResearch(): array
    {
        $this->assertSame(':memory:',config('database.connections.sqlite.database'));
        $admin=User::factory()->create(['role'=>'admin']);
        $service=app(SentimentResearchService::class);
        $service->configure($admin,'2026-09-01','2026-09-30','Test fixture: approved evaluation window');
        return [$admin,$service];
    }
    private function unit(SentimentResearchService $service, string $text='The system is useful.', string $consent='Granted'): FeedbackAnalysisUnit
    {
        $feedback=Feedback::create(['evaluator_user_id'=>User::factory()->create(['role'=>'member'])->id,'message'=>$text,'research_consent_status'=>$consent,'created_at'=>'2026-09-05 12:00:00']);
        $service->synchronize();
        return FeedbackAnalysisUnit::where('feedback_id',$feedback->id)->firstOrFail();
    }
    private function reviewData(string $label='Positive', string $language='English'): array
    {
        return ['is_test_record'=>0,'review_independence'=>'Blinded','review_revision'=>1,'scope_status'=>'System Feedback','human_review_status'=>'Final','human_reference_sentiment'=>$label,'language_group'=>$language,'independent_attested'=>1,'quote_approved'=>1];
    }

    public function test_workspace_shows_only_overview_and_review_tabs(): void
    {
        [$admin] = $this->setupResearch();
        $this->actingAs($admin)->get(route('admin.analytics.sentiment'))->assertOk()
            ->assertSee('Sentiment at a glance')->assertDontSee('Your next steps')
            ->assertSee('distribution-panel', false)->assertSee('Distribution reflects eligible human references, not automated predictions.')
            ->assertSee('quality-grid', false)->assertDontSee('review-progress', false)
            ->assertSee('Human Feedback Review')->assertDontSee('Settings')
            ->assertDontSee('Evaluation Results')->assertDontSee('Explore results')
            ->assertDontSee('Approval evidence')->assertDontSee('Confusion Matrix');
    }

    public function test_settings_and_unknown_views_redirect_to_overview_without_changing_data(): void
    {
        [$admin, $service] = $this->setupResearch();
        $unit = $this->unit($service);
        $service->review($unit, $admin, $this->reviewData());
        $service->predict($admin);
        $before = $service->report();
        $this->assertSame(1, $before['table19']['n']);
        foreach (['settings', 'unknown', '', ['settings']] as $view) {
            foreach (['admin.analytics.sentiment', 'admin.sentiment-research.review'] as $route) {
                $this->actingAs($admin)->get(route($route, ['view' => $view]))
                    ->assertRedirect(route('admin.analytics.sentiment'));
            }
        }
        $this->get(route('admin.analytics.sentiment', ['view' => 'overview']))->assertOk();
        $this->get(route('admin.sentiment-research.review'))->assertOk()
            ->assertSee('Human Feedback Review')->assertDontSee('Settings');
        $this->get(route('admin.sentiment-research.export'))->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertEquals($before, $service->report());
        $this->assertDatabaseHas('sentiment_research_settings', ['period_start' => '2026-09-01', 'period_end' => '2026-09-30']);
    }

    public function test_missing_approved_period_keeps_safe_defaults_and_export_available(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(SentimentResearchService::class);
        $unit = $this->unit($service);
        $this->assertContains('Approved evaluation period missing', $service->eligibility()[$unit->id]['reasons']);
        $this->assertNull($service->report()['table19']['accuracy']);
        $this->actingAs($admin)->get(route('admin.analytics.sentiment'))->assertOk()->assertSee('UNTESTED');
        $this->get(route('admin.sentiment-research.review'))->assertOk();
        $this->get(route('admin.sentiment-research.export'))->assertOk();
        $this->assertDatabaseCount('sentiment_research_settings', 0);
    }

    public function test_settings_only_save_endpoints_are_removed(): void
    {
        [$admin] = $this->setupResearch();
        foreach (['period', 'apply-approved-review-set'] as $path) {
            $this->actingAs($admin)->post('/admin/analytics/sentiment/research/'.$path)->assertNotFound();
        }
    }

    public function test_legacy_sentiment_dataset_url_redirects_to_overview(): void
    {
        [$admin] = $this->setupResearch();

        $this->actingAs($admin)
            ->get('/admin/analytics/sentiment/research/dataset')
            ->assertRedirect(route('admin.analytics.sentiment'));
    }

    public function test_feedback_cards_filter_search_and_status_without_revealing_unreviewed_predictions(): void
    {
        [$admin, $service] = $this->setupResearch();
        $pending = $this->unit($service, 'A uniquely searchable pending response.');
        $pending->update(['system_prediction'=>'Negative']);
        $reviewed = $this->unit($service, 'A uniquely searchable completed response.');
        $service->review($reviewed, $admin, $this->reviewData());
        $this->actingAs($admin)->get(route('admin.sentiment-research.review', ['search'=>'uniquely', 'status'=>'pending']))
            ->assertOk()->assertSee('A uniquely searchable pending response.')
            ->assertDontSee('A uniquely searchable completed response.')
            ->assertSee('Hidden until independent review');
        $this->get(route('admin.sentiment-research.review', ['search'=>$reviewed->feedback_code, 'status'=>'reviewed']))
            ->assertOk()->assertSee('A uniquely searchable completed response.')
            ->assertDontSee('A uniquely searchable pending response.');
        $this->get(route('admin.sentiment-research.review', ['search'=>'no-such-feedback']))
            ->assertOk()->assertSee('No matching feedback');
    }

    public function test_legacy_labels_consent_and_predictions_are_not_assumed_independent(): void
    {
        [$admin,$service]=$this->setupResearch();
        $feedback=Feedback::create(['message'=>'The system is useful.','sentiment'=>'Positive','human_sentiment_label'=>'Positive','human_labeled_by_id'=>$admin->id,'human_labeled_at'=>now()]);
        $service->synchronize();
        $unit=FeedbackAnalysisUnit::first();
        $this->assertNull($unit->human_reference_sentiment);
        $this->assertSame('Unknown',$feedback->fresh()->research_consent_status);
        $report=$service->report();
        $this->assertSame(0,$report['counts']['included']);
        $this->assertSame(0,$report['counts']['evaluation_n']);
        $this->assertNull($report['table19']['accuracy']);
        foreach ($report['table20'] as $language) $this->assertSame('N/A – No eligible observations',$language['status']);
        $this->assertSame(0,$service->synchronize());
        $this->actingAs($admin)->get(route('admin.sentiment-research.show',$unit))->assertOk()->assertSee('System prediction is hidden')->assertDontSee('System prediction: Positive');
        $this->actingAs($admin)->patch(route('admin.analytics.sentiment.reference-label',$feedback),['human_sentiment_label'=>'Negative'])->assertSessionHasErrors('review');
        $this->assertSame('Positive',$feedback->fresh()->human_sentiment_label);
    }

    public function test_human_review_requires_consent_independence_and_language_and_hides_prediction(): void
    {
        [$admin,$service]=$this->setupResearch();
        $unit=$this->unit($service,'The system is useful.','Unknown');
        $url=route('admin.sentiment-research.save',$unit);
        $this->actingAs($admin)->patch($url,$this->reviewData())->assertSessionHasErrors('consent');
        $service->consent($unit->feedback,$admin,'Granted','Test fixture: respondent documented consent','Test consent form');
        $data=$this->reviewData(); unset($data['independent_attested']);
        $this->actingAs($admin)->patch($url,$data)->assertSessionHasErrors('independent_attested');
        $data=$this->reviewData(); $data['language_group']=null;
        $this->actingAs($admin)->patch($url,$data)->assertSessionHasErrors('review');
        $this->actingAs($admin)->patch($url,$this->reviewData())->assertRedirect();
        $this->assertSame('Positive',$unit->fresh()->human_reference_sentiment);
        $this->assertNull($unit->fresh()->system_prediction);
        $this->actingAs($admin)->post(route('admin.sentiment-research.evaluate'))->assertRedirect(route('admin.analytics.sentiment'));
        $this->assertSame('Positive',$unit->fresh()->system_prediction);
        $this->assertSame(1,DB::table('sentiment_evaluation_runs')->count());
        $this->actingAs($admin)->patch($url,$this->reviewData())->assertSessionHasErrors('review');
        $this->assertDatabaseHas('sentiment_research_audits',['event'=>'feedback.human_reviewed']);
    }

    public function test_all_tables_use_same_records_and_fixed_three_class_metrics(): void
    {
        [$admin,$service]=$this->setupResearch();
        $fixture=[['Positive','Positive','English'],['Positive','Neutral','English'],['Neutral','Neutral','Filipino'],['Negative','Neutral','Filipino-English'],['Negative','Negative','Filipino']];
        foreach ($fixture as $i=>[$actual,$prediction,$language]) {
            $unit=$this->unit($service,'Test-only system evaluation opinion '.($i+1));
            $service->review($unit,$admin,$this->reviewData($actual,$language));
            $unit->update(['system_prediction'=>$prediction,'sentiment_model_version'=>SentimentAnalysisService::MODEL_VERSION,'prediction_text_hash'=>$unit->text_hash,'predicted_at'=>now()]);
        }
        $report=$service->report();
        $this->assertSame(5,$report['counts']['evaluation_n']);
        $this->assertEqualsWithDelta(.6,$report['table19']['accuracy'],1e-12);
        $this->assertEqualsWithDelta(7/9,$report['table19']['macro']['precision'],1e-12);
        $this->assertEqualsWithDelta(2/3,$report['table19']['macro']['recall'],1e-12);
        $this->assertEqualsWithDelta(11/18,$report['table19']['macro']['f1'],1e-12);
        $this->assertEqualsWithDelta(13/15,$report['table19']['weighted']['precision'],1e-12);
        $this->assertEqualsWithDelta(.6,$report['table19']['weighted']['recall'],1e-12);
        $this->assertEqualsWithDelta(19/30,$report['table19']['weighted']['f1'],1e-12);
        $this->assertSame(5,array_sum(array_column($report['table20'],'n')));
        foreach (['table11','table12','table18_19','accuracy','table19_20','same_evaluation_records'] as $key) $this->assertTrue($report['validations'][$key]);
        $this->assertCount(5,$report['table12']);
        $response=$this->actingAs($admin)->get(route('admin.sentiment-research.export'))->assertOk();
        $this->assertStringStartsWith("PK\x03\x04",$response->getContent());
        foreach (['Human Label Distribution','Feedback Samples','Evaluation Records','Confusion Matrix','Overall Metrics','Language Metrics'] as $name) $this->assertStringContainsString($name,$response->getContent());
        $this->assertStringNotContainsString('<f>',$response->getContent());
        $this->assertStringNotContainsString('evaluator_user_id',$response->getContent());
    }

    public function test_period_duplicates_nonconsent_and_scope_are_enforced_without_deleting(): void
    {
        [$admin,$service]=$this->setupResearch();
        $a=$this->unit($service);
        $b=$this->unit($service);
        foreach ([$a,$b] as $unit) $service->review($unit,$admin,$this->reviewData());
        $denied=$this->unit($service,'Other system opinion','Denied');
        $old=$this->unit($service,'An older system opinion');
        $old->feedback->update(['created_at'=>'2026-08-01']);
        $service->review($old,$admin,$this->reviewData());
        $report=$service->report();
        $this->assertSame(1,$report['counts']['included']);
        $this->assertSame(3,$report['counts']['excluded']);
        $this->assertStringContainsString('Duplicate',$b->fresh()->exclusion_reason);
        $this->assertSame(4,Feedback::count());
        $this->assertSame(4,FeedbackAnalysisUnit::count());
        $service->consent($a->feedback,$admin,'Denied','Test fixture: withdrawal','Test withdrawal form');
        $this->assertFalse($a->fresh()->is_included);
        $this->assertSame('Positive',$a->fresh()->human_reference_sentiment);
    }

    public function test_split_preserves_all_original_opinions_and_parent_history(): void
    {
        [$admin,$service]=$this->setupResearch();
        $unit=$this->unit($service,'Madali gamitin pero mabagal ang reports.');
        try { $service->split($unit,$admin,['Madali gamitin','reports.'],1); $this->fail('Omitted negative opinion should fail'); } catch (ValidationException) {}
        $service->split($unit,$admin,['Madali gamitin','pero mabagal ang reports.'],1);
        $this->assertSame('Split',$unit->fresh()->scope_status);
        $this->assertSame('Madali gamitin pero mabagal ang reports.',$unit->fresh()->feedback_text);
        $this->assertSame(2,FeedbackAnalysisUnit::where('parent_unit_id',$unit->id)->count());
        $this->assertSame(0,FeedbackAnalysisUnit::whereNotNull('human_reference_sentiment')->count());
        $this->assertSame(1,Feedback::count());
    }

    public function test_failed_prediction_blocks_evaluation_instead_of_silently_removing_the_case(): void
    {
        [$admin,$service]=$this->setupResearch();
        $unit=$this->unit($service);
        $service->review($unit,$admin,$this->reviewData());
        $this->mock(SentimentAnalysisService::class)->shouldReceive('analyze')->andThrow(new \RuntimeException('Test failure'));
        $this->actingAs($admin)->post(route('admin.sentiment-research.evaluate'))->assertSessionHasErrors('evaluation');
        $this->assertSame(1,$service->report()['counts']['missing_predictions']);
        $this->assertSame(0,DB::table('sentiment_evaluation_runs')->count());
    }

    public function test_non_admin_endpoints_and_other_respondent_consent_are_blocked(): void
    {
        [$admin,$service]=$this->setupResearch();
        $unit=$this->unit($service);
        $member=User::factory()->create(['role'=>'member']);
        foreach (['review','export'] as $route) $this->actingAs($member)->get(route('admin.sentiment-research.'.$route))->assertForbidden();
        $this->actingAs($member)->get('/admin/analytics/sentiment/research/dataset')->assertForbidden();
        $this->actingAs($member)->get(route('admin.sentiment-research.show',$unit))->assertForbidden();
        $this->actingAs($member)->patch(route('admin.sentiment-research.save',$unit),$this->reviewData())->assertForbidden();
        $this->actingAs($member)->post(route('admin.sentiment-research.evaluate'))->assertForbidden();
        $this->actingAs($member)->patch(route('feedback.research-consent',$unit->feedback),['consent_status'=>'Granted','consent_source'=>'Test form','evidence'=>'Unauthorized','attested'=>1])->assertForbidden();
    }

    public function test_absent_class_stays_in_macro_and_absent_language_is_untested(): void
    {
        $metrics=app(SentimentResearchMetrics::class)->calculate([['human_reference_sentiment'=>'Positive','system_prediction'=>'Positive']]);
        $this->assertSame(0.0,$metrics['per_class']['Negative']['precision']);
        $this->assertSame(0,$metrics['per_class']['Negative']['support']);
        $this->assertContains('No predictions were produced for this class.',$metrics['per_class']['Negative']['flags']);
        $this->assertEqualsWithDelta(1/3,$metrics['macro']['f1'],1e-12);
        $this->assertNull(app(SentimentResearchMetrics::class)->calculate([])['accuracy']);
    }

    public function test_prior_exposed_labeler_is_not_allowed_to_supply_initial_independent_review(): void
    {
        [$admin,$service]=$this->setupResearch();
        $unit=$this->unit($service);
        $unit->feedback->update(['human_sentiment_label'=>'Positive','human_labeled_by_id'=>$admin->id,'human_labeled_at'=>now(),'sentiment'=>'Positive']);
        $this->actingAs($admin)->patch(route('admin.sentiment-research.save',$unit),$this->reviewData())->assertSessionHasErrors('review');
        $this->assertNull($unit->fresh()->human_reference_sentiment);
    }

    public function test_recognition_preserves_original_review_and_does_not_copy_to_children(): void
    {
        [$admin,$service]=$this->setupResearch();
        $unit=$this->unit($service);
        $unit->feedback->update(['human_sentiment_label'=>'Positive','human_labeled_by_id'=>$admin->id,'human_labeled_at'=>'2026-09-06 08:01:31','sentiment'=>'Negative']);
        $child=FeedbackAnalysisUnit::create(['feedback_id'=>$unit->feedback_id,'parent_unit_id'=>$unit->id,'source_key'=>'test-child','feedback_code'=>'F099','feedback_text'=>'Stored child text','text_hash'=>'fixture']);
        $this->assertSame([$unit->feedback_code],$service->recognizeExistingHumanLabels());
        $unit->refresh();
        $this->assertSame('Positive',$unit->human_reference_sentiment);
        $this->assertSame('Final',$unit->human_review_status);
        $this->assertSame('Human Reviewer',$unit->reference_source);
        $this->assertSame($admin->id,$unit->human_reviewed_by);
        $this->assertSame('2026-09-06 08:01:31',$unit->human_reviewed_at->format('Y-m-d H:i:s'));
        $this->assertSame('Not documented',$unit->review_independence);
        $this->assertFalse($unit->independent_attested);
        $this->assertNull($child->fresh()->human_reference_sentiment);
        $this->assertSame([],$service->recognizeExistingHumanLabels());
        $this->assertSame(1,$service->report()['counts']['human_labeled']);
        $this->assertSame(0,$service->report()['counts']['eligible_human_labeled']);
        $unit->update(['scope_status'=>'System Feedback','is_test_record'=>false,'language_group'=>'English','language_review_status'=>'Final']);
        $this->assertSame(0,$service->predict()['predicted']);
        $unit->update(['independent_attested'=>true,'review_independence'=>'Blinded']);
        $this->assertSame(1,$service->predict()['predicted']);
        $this->assertSame(1,$service->report()['counts']['evaluation_n']);
        $this->assertSame('Positive',$unit->fresh()->human_reference_sentiment);
        $this->assertSame('Negative',$unit->feedback->fresh()->sentiment);
    }

    public function test_system_feedback_provenance_inherits_consent_scope_and_non_test_status_for_children(): void
    {
        [$admin, $service] = $this->setupResearch();
        $feedback = Feedback::create([
            'evaluator_user_id' => User::factory()->create(['role' => 'member'])->id,
            'message' => 'The system is easy to use. The report is clear.',
            'question_1' => 'Easy',
            'question_2' => 'Helpful',
            'question_3' => 'Clear',
            'question_4' => 'None',
            'question_5' => 'Satisfied',
            'research_consent_status' => 'Granted',
            'research_consent_source' => 'System Evaluation Form',
            'research_consent_evidence' => 'Recorded respondent consent',
            'research_consented_at' => now(),
        ]);
        $parent = FeedbackAnalysisUnit::create([
            'feedback_id' => $feedback->id,
            'source_key' => 'test-parent:'.$feedback->id,
            'feedback_code' => 'PARENT',
            'feedback_text' => 'The system is easy to use. The report is clear.',
            'text_hash' => $service->hash('The system is easy to use. The report is clear.'),
            'scope_status' => 'Split',
        ]);
        $child = FeedbackAnalysisUnit::create([
            'feedback_id' => $feedback->id,
            'parent_unit_id' => $parent->id,
            'source_key' => 'test-child:'.$feedback->id,
            'feedback_code' => 'CHILD',
            'feedback_text' => 'The system is easy to use.',
            'text_hash' => $service->hash('The system is easy to use.'),
        ]);

        $this->assertSame('Granted', $service->effectiveConsent($child));
        $this->assertSame('System Feedback', $service->effectiveScope($child));
        $this->assertFalse($service->effectiveIsTestRecord($child));
        $items = $service->eligibility();
        $reasons = $items[$child->id]['reasons'];
        $this->assertNotContains('Research consent not granted', $reasons);
        $this->assertNotContains('System relevance review needs completion', $reasons);
        $this->assertNotContains('Actual evaluation / non-test status needs review', $reasons);
        $this->assertFalse($items[$parent->id]['included']);
        $this->assertContains('Parent split into separately reviewed units', $items[$parent->id]['reasons']);
    }

    public function test_denied_source_consent_is_never_inherited_into_eligible_children(): void
    {
        [$admin, $service] = $this->setupResearch();
        $feedback = Feedback::create([
            'evaluator_user_id' => User::factory()->create(['role' => 'member'])->id,
            'message' => 'The system is useful.',
            'question_1' => 'Easy', 'question_2' => 'Helpful', 'question_3' => 'Clear', 'question_4' => 'None', 'question_5' => 'Satisfied',
            'research_consent_status' => 'Denied',
            'research_consent_source' => 'System Evaluation Form',
            'research_consent_evidence' => 'Respondent denied research use',
            'research_consented_at' => now(),
        ]);
        $child = FeedbackAnalysisUnit::create([
            'feedback_id' => $feedback->id,
            'source_key' => 'denied-child:'.$feedback->id,
            'feedback_code' => 'DENIED-CHILD',
            'feedback_text' => 'The system is useful.',
            'text_hash' => $service->hash('The system is useful.'),
        ]);

        $this->assertSame('Denied', $service->effectiveConsent($child));
        $this->assertContains('Research consent not granted', $service->eligibility()[$child->id]['reasons']);
        $this->assertFalse($service->eligibility()[$child->id]['included']);
    }

}
