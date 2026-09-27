<?php

namespace Tests\Feature;

use App\Models\EngagementModelRun;
use App\Models\EngagementReferenceLabel;
use App\Models\EngagementResearchAudit;
use App\Models\EngagementResult;
use App\Models\Member;
use App\Models\Satellite;
use App\Models\User;
use App\Services\ReferenceDatasetService;
use App\Services\EngagementEvaluationService;
use App\Services\ResearchCartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EngagementResearchTest extends TestCase
{
    use RefreshDatabase;

    private function cohort(): array
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $admin = User::factory()->create(['role' => 'admin', 'district' => 'Pasig']);
        $pasig = Satellite::create(['name' => 'Pasig', 'slug' => 'pasig', 'created_by_id' => $admin->id]);
        $ortigas = Satellite::create(['name' => 'MAIN ORTIGAS', 'slug' => 'main-ortigas', 'created_by_id' => $admin->id]);
        for ($i = 1; $i <= 206; $i++) {
            Member::create(['name' => 'Test member '.$i, 'satellite_id' => $i <= 105 ? $pasig->id : $ortigas->id, 'status' => 'Active',
                'dgroup_role' => 'Member', 'active_disciples' => $i % 7, 'weekly_dgroups_led' => null]);
        }
        $service = app(ReferenceDatasetService::class);
        return [$admin, $service, $service->initialize($admin)];
    }

    public function test_initialization_has_exact_counts_partitions_sources_and_is_idempotent(): void
    {
        [$admin,$service,$dataset] = $this->cohort();
        $summary = $service->summary($dataset);
        $this->assertSame(206, Member::count());
        $this->assertSame([112,63,25,6], array_column($summary['distribution'], 'frequency'));
        $this->assertSame([54.37,30.58,12.14,2.91], array_column($summary['distribution'], 'percentage'));
        $this->assertEqualsWithDelta(100, array_sum(array_column($summary['distribution'], 'percentage')), .00001);
        $this->assertSame(200, $summary['eligible']);
        $this->assertSame(206, $summary['provisional']);
        $this->assertSame(0, $summary['verified']);
        $this->assertSame(0, EngagementResult::count());
        $this->assertSame(160, $summary['split']['train_count']);
        $this->assertSame(40, $summary['split']['test_count']);
        $this->assertSame([90,50,20], array_column($summary['split']['classes'], 'training'));
        $this->assertSame([], array_intersect($summary['split']['train_member_ids'], $summary['split']['test_member_ids']));
        $assignments = $service->provisionalAssignments(Member::get(['id', 'satellite_id'])->map(fn ($m) => ['member_id'=>$m->id,'satellite_id'=>$m->satellite_id])->all());
        foreach ($assignments as $id=>$label) {
            $reference = EngagementReferenceLabel::where('member_id',$id)->firstOrFail();
            $this->assertSame($label, $reference->reference_label);
            $this->assertSame('researcher_entered', $reference->reference_source);
            $this->assertSame('Provisional', $reference->verification_status);
            $this->assertNull($reference->reviewed_by);
            $this->assertNull($reference->reviewed_at);
            $this->assertSame(ReferenceDatasetService::REASON, $reference->reason);
            $this->assertSame(ResearchCartService::FEATURE_NAMES, array_keys($reference->feature_snapshot));
            if ($label === null) {
                $this->assertSame('Needs Clarification', $reference->review_status);
                $this->assertSame('Excluded', $reference->partition);
            }
        }
        $this->assertSame([57,32,13,3,105], array_values(array_diff_key(collect($summary['satellites'])->firstWhere('satellite','Pasig'), ['satellite'=>true])));
        $this->assertSame([55,31,12,3,101], array_values(array_diff_key(collect($summary['satellites'])->firstWhere('satellite','MAIN ORTIGAS'), ['satellite'=>true])));
        $this->assertGreaterThan(1, EngagementReferenceLabel::where('member_id','<=',20)->distinct()->count('label'));
        $this->assertSame($dataset->id, $service->initialize($admin)->id);
        $this->assertSame(206, EngagementReferenceLabel::count());
        $this->assertDatabaseHas('engagement_research_audits',['event'=>'reference_dataset.created']);
        $this->assertDatabaseHas('engagement_research_audits',['event'=>'dataset.split']);
        $this->actingAs($admin)->get(route('admin.engagement.index'))->assertOk()->assertSee('54.37%')->assertSee('Researcher Entered')->assertSee('Provisional');
        $this->actingAs($admin)->get(route('admin.engagement.dataset'))->assertOk()->assertSee('No current evaluation');
        $this->actingAs($admin)->get(route('admin.engagement.show', EngagementReferenceLabel::first()))->assertOk()->assertSee('Authorized human verification');
    }

    public function test_train_persists_real_evaluation_without_changing_references_and_export_contains_sheets(): void
    {
        [$admin,$service,$dataset] = $this->cohort();
        $before = EngagementReferenceLabel::orderBy('id')->get(['member_id','reference_label','reference_source','verification_status'])->toArray();
        $run = app(EngagementEvaluationService::class)->train($dataset, $admin);
        $this->assertSame($before, EngagementReferenceLabel::orderBy('id')->get(['member_id','reference_label','reference_source','verification_status'])->toArray());
        $this->assertSame(206, EngagementResult::count());
        $this->assertSame(40, $run->holdout_metrics['sample_count']);
        $this->assertSame(3, count($run->holdout_metrics['confusion_matrix']));
        $this->assertSame(5, $run->validation_metrics['fold_count']);
        $this->assertFalse($run->training_config['heldout_used_for_selection']);
        $this->assertSame([], array_intersect($run->training_config['training_member_ids'],$run->training_config['heldout_member_ids']));
        $this->assertSame('Provisional', $run->evaluation_status);
        $this->assertSame($run->id, app(EngagementEvaluationService::class)->train($dataset->fresh(),$admin)->id);
        $this->assertSame(1, EngagementModelRun::count());
        $this->assertDatabaseHas('engagement_research_audits',['event'=>'cart.trained']);
        $this->assertDatabaseHas('engagement_research_audits',['event'=>'cart.evaluated']);
        $this->actingAs($admin)->get(route('admin.engagement.dataset'))->assertOk()->assertSee('Confusion matrix')->assertSee('5-fold stratified cross-validation');
        $this->actingAs($admin)->get(route('admin.engagement.show',EngagementReferenceLabel::first()))->assertOk()->assertSee('Why This Result?')->assertSee('Final CART Leaf');
        $response = $this->actingAs($admin)->get(route('admin.engagement.export'))->assertOk()->assertHeader('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith("PK\x03\x04", $response->getContent());
        foreach (['Reference Label Summary','Dataset Split','Performance','Reference Records','Satellite Distribution','CV Search'] as $sheet) {
            $this->assertStringContainsString($sheet, $response->getContent());
        }
        $this->assertStringNotContainsString('<f>', $response->getContent());
    }

    public function test_verification_keeps_history_invalidates_old_model_and_requires_attestation_and_current_revision(): void
    {
        [$admin,$service,$dataset] = $this->cohort();
        $run = EngagementModelRun::create(['model_name'=>'Test prior run','model_version'=>'test','training_signature'=>str_repeat('a',64),'is_active'=>true]);
        $dataset->update(['active_model_run_id'=>$run->id]);
        $reference = EngagementReferenceLabel::where('reference_label','Active')->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.engagement.verify',$reference),['reference_label'=>'Needs Clarification','reason'=>'Evidence is incomplete','review_revision'=>1])->assertSessionHasErrors('attested');
        $this->actingAs($admin)->patch(route('admin.engagement.verify',$reference),['reference_label'=>'Needs Clarification','reason'=>'Reviewed the attendance records; conflicting dates need confirmation.','attested'=>1,'review_revision'=>1])->assertRedirect();
        $reference->refresh();
        $this->assertNull($reference->reference_label);
        $this->assertSame('Excluded',$reference->partition);
        $this->assertSame('human_verified',$reference->reference_source);
        $this->assertSame('Verified',$reference->verification_status);
        $this->assertSame($admin->id,$reference->reviewed_by);
        $this->assertNotNull($reference->reviewed_at);
        $this->assertFalse($run->fresh()->is_active);
        $this->assertNull($dataset->fresh()->active_model_run_id);
        $this->assertSame(2,$dataset->fresh()->revision);
        $audit = EngagementResearchAudit::where('event','reference_label.updated')->firstOrFail();
        $this->assertSame('Active',$audit->before_state['reference_label']);
        $this->assertNull($audit->after_state['reference_label']);
        $this->assertSame('researcher_entered',$audit->before_state['reference_source']);
        $this->assertSame('human_verified',$audit->after_state['reference_source']);
        $this->actingAs($admin)->patch(route('admin.engagement.verify',$reference),['reference_label'=>'Active','reason'=>'Stale form','attested'=>1,'review_revision'=>1])->assertSessionHasErrors('reference');
        $this->actingAs($admin)->post(route('admin.engagement.finalize'))->assertSessionHasErrors('dataset');
        $this->assertSame(205,$service->summary($dataset->fresh())['provisional']);
        $this->assertSame(1,$service->summary($dataset->fresh())['verified']);
        $service->initialize($admin);
        $this->assertNull($reference->fresh()->reference_label);
        $this->assertSame(1, EngagementModelRun::count());
    }

    public function test_all_reference_endpoints_block_members_and_satellite_users(): void
    {
        [$admin,$service,$dataset] = $this->cohort();
        $reference = EngagementReferenceLabel::firstOrFail();
        foreach (['member','satellite','district_user'] as $role) {
            $user = User::factory()->create(['role'=>$role,'district'=>'Pasig']);
            foreach (['index','review','dataset','export'] as $action) {
                $this->actingAs($user)->get(route('admin.engagement.'.$action))->assertForbidden();
            }
            $this->actingAs($user)->get(route('admin.engagement.show',$reference))->assertForbidden();
            $this->actingAs($user)->patch(route('admin.engagement.verify',$reference),[])->assertForbidden();
            $this->actingAs($user)->post(route('admin.engagement.train'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.engagement.finalize'))->assertForbidden();
        }
        $this->assertSame(206,$service->summary($dataset)['provisional']);
    }

    public function test_wrong_cohort_size_is_rejected_without_partial_labels(): void
    {
        Member::create(['name'=>'Only member']);
        try {
            app(ReferenceDatasetService::class)->initialize();
            $this->fail('Expected cohort validation error.');
        } catch (ValidationException) {
            $this->assertSame(0, EngagementReferenceLabel::count());
            $this->assertDatabaseCount('engagement_reference_datasets',0);
        }
    }

    public function test_final_status_requires_complete_verified_provenance_and_reopens_after_correction(): void
    {
        [$admin,$service,$dataset] = $this->cohort();
        // Synthetic in-memory test fixtures only, never performed on the live dataset.
        $dataset->references()->update(['verification_status'=>'Verified','reference_source'=>'human_verified','reviewed_by'=>$admin->id,'reviewed_at'=>now(),'reason'=>'Test fixture: review provenance']);
        $this->actingAs($admin)->post(route('admin.engagement.finalize'))->assertRedirect();
        $this->assertSame('Human Verified Final',$dataset->fresh()->evaluation_status);
        $this->assertSame($admin->id,$dataset->fresh()->finalized_by);
        $this->assertDatabaseHas('engagement_research_audits',['event'=>'reference_dataset.finalized']);
        $service->verify(EngagementReferenceLabel::first(),$admin,'Active','Test fixture: correction');
        $this->assertSame('Provisional',$dataset->fresh()->evaluation_status);
        $this->assertNull($dataset->fresh()->finalized_at);
    }

    public function test_recommendation_reviews_are_audited_without_changing_member_status(): void
    {
        [$admin,$service,$dataset] = $this->cohort();
        $member = Member::firstOrFail();
        $result = EngagementResult::create(['member_id'=>$member->id,'classification'=>'Active']);
        $action = \App\Models\RecommendationAction::create(['title'=>'Test review action','description'=>'Test evidence review','is_active'=>true]);
        foreach (['Approved','Modified','Rejected'] as $status) {
            $recommendation = \App\Models\Recommendation::create(['member_id'=>$member->id,'engagement_result_id'=>$result->id,'recommended_action_id'=>$action->id,
                'assigned_by'=>$admin->id,'assigned_to'=>$admin->id,'status'=>'Pending Review','action_title_snapshot'=>'Test review action','action_description_snapshot'=>'Test evidence review']);
            $input=['status'=>$status,'review_notes'=>'Test fixture: actual review notes'];
            if ($status === 'Modified') $input['modified_action_description']='Test fixture: revised evidence review';
            $this->actingAs($admin)->patch(route('satellites.recommendations.review',[$member->satellite,$recommendation]),$input)->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('engagement_research_audits',['event'=>'recommendation.'.strtolower($status),'actor_id'=>$admin->id]);
        }
        $this->assertSame('Active',$member->fresh()->status);
    }

    public function test_assignment_shuffle_is_stable_under_input_order_and_preserves_satellite_margins(): void
    {
        [, $service] = $this->cohort();
        $members = Member::get(['id','satellite_id'])->map(fn ($m) => ['member_id'=>$m->id,'satellite_id'=>$m->satellite_id])->all();
        $a = $service->provisionalAssignments($members);
        $this->assertSame($a, $service->provisionalAssignments(array_reverse($members)));
        $b = $service->provisionalAssignments($members, 43);
        $this->assertNotSame($a, $b);
        foreach ([array_slice($members,0,105), array_slice($members,105)] as $group) {
            $counts = fn ($map) => array_count_values(array_map(fn ($m) => $map[$m['member_id']] ?? 'Clarification', $group));
            $this->assertEquals($counts($a), $counts($b));
        }
    }

    public function test_upgrade_retains_old_references_and_predictions_and_reuses_frozen_evidence(): void
    {
        [$admin,$service,$old] = $this->cohort();
        $old->references()->update(['dataset_version'=>'researcher-provisional-206-v1']);
        $old->update(['version'=>'researcher-provisional-206-v1']);
        $run = EngagementModelRun::create(['model_name'=>'Prior run','model_version'=>'v1','training_signature'=>str_repeat('b',64),'is_active'=>true]);
        $old->update(['active_model_run_id'=>$run->id]);
        $before = $old->references()->orderBy('member_id')->get()->toArray();
        Member::query()->update(['active_disciples'=>999]);
        $new = $service->initialize($admin);
        $this->assertSame('researcher-provisional-206-v2-r1',$new->revision_version);
        $this->assertSame($before,$old->references()->orderBy('member_id')->get()->toArray());
        $this->assertSame(array_column($before,'feature_snapshot'),$new->references()->orderBy('member_id')->pluck('feature_snapshot')->all());
        $this->assertSame(412, EngagementReferenceLabel::count());
        $this->assertFalse($run->fresh()->is_active);
        $this->assertSame($new->id,$service->initialize()->id);
        $this->assertDatabaseHas('engagement_research_audits',['event'=>'reference_dataset.superseded']);
    }
}
