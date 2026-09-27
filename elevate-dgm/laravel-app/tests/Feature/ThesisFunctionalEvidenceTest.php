<?php

namespace Tests\Feature;

use App\Models\EngagementModelRun;
use App\Models\EngagementResearchAudit;
use App\Models\EngagementResult;
use App\Models\Member;
use App\Models\Recommendation;
use App\Models\RecommendationAction;
use App\Models\Satellite;
use App\Models\User;
use App\Services\EngagementClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Synthetic HTTP fixtures verify application behavior, not CART predictive accuracy or live review outcomes. */
class ThesisFunctionalEvidenceTest extends TestCase
{
    use RefreshDatabase { refreshDatabase as private refreshMemoryDatabase; }

    public function refreshDatabase(): void
    {
        // Fail before RefreshDatabase can migrate if a cached/live connection was supplied.
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertEmpty(config('database.connections.sqlite.url'));
        $this->refreshMemoryDatabase();
    }

    public function test_t13_xai_displays_exact_stored_engagement_category(): void
    {
        [$manager, $satellite, $member, $result] = $this->fixture();
        foreach (['Active', 'Moderately Active', 'Possible Need for Follow-Up'] as $category) {
            $result->update(['classification' => $category]);
            $this->actingAs($manager)->get(route('satellites.engagement.show', [$satellite, $member]))
                ->assertOk()->assertSeeText($category)
                ->assertViewHas('result', fn ($stored) => $stored->classification === $category);
        }
    }

    public function test_t13_xai_displays_recorded_model_inputs_without_protected_predictors(): void
    {
        [$manager, $satellite, $member, $result] = $this->fixture();
        $this->actingAs($manager)->get(route('satellites.engagement.show', [$satellite, $member]))
            ->assertOk()->assertSeeText('Factors Used by CART')->assertSeeText('Active Disciples')
            ->assertSee('<td>4</td>', false)->assertSeeText('Weekly Dgroups Led')->assertSee('<td>2</td>', false)
            ->assertDontSeeText('member_status')->assertDontSeeText('feedback_sentiment')
            ->assertViewHas('result', fn ($stored) => $stored->used_features === $result->used_features);
    }

    public function test_t13_xai_displays_stored_threshold_branch_and_final_leaf(): void
    {
        [$manager, $satellite, $member, $result] = $this->fixture();
        $this->actingAs($manager)->get(route('satellites.engagement.show', [$satellite, $member]))
            ->assertOk()->assertSeeText('Actual CART Traversal')->assertSeeText('View CART Decision Path')
            ->assertSeeText('Node: root')->assertSeeText('Next Node: right')
            ->assertSee('<strong>1.5</strong>', false)->assertSeeText('Branch: Right')
            ->assertSee('Final leaf predicts <strong>Active</strong>', false)
            ->assertViewHas('result', fn ($stored) => $stored->decision_path === $result->decision_path);
        $path = $result->decision_path;
        $tree = $result->modelRun->tree;
        $this->assertSame($tree['threshold'], $path[0]['threshold']);
        $this->assertSame($tree['right']['prediction'], $path[1]['outcome']);
    }

    public function test_t13_xai_displays_model_version_and_generation_date(): void
    {
        [$manager, $satellite, $member] = $this->fixture();
        $this->actingAs($manager)->get(route('satellites.engagement.show', [$satellite, $member]))
            ->assertOk()->assertSeeText('CART Engagement Classifier')->assertSeeText('synthetic-functional-v1')
            ->assertSeeText('Sep 17, 2026 9:15 AM')->assertSeeText('Generated');
    }

    public function test_t13_xai_displays_human_review_and_nonjudgment_notice(): void
    {
        [$manager, $satellite, $member] = $this->fixture();
        $this->actingAs($manager)->get(route('satellites.engagement.show', [$satellite, $member]))
            ->assertOk()->assertSeeText('Human review is required before any follow-up decision.')
            ->assertSeeText('It does not measure faith, spiritual maturity, character, emotional condition, or personal worth.')
            ->assertSeeText('The system does not automatically contact, discipline, or exclude members.');
    }

    public function test_t13_xai_denies_unauthorized_and_unauthenticated_access(): void
    {
        [, $satellite, $member] = $this->fixture();
        $url = route('satellites.engagement.show', [$satellite, $member]);
        $this->get($url)->assertRedirect(route('login'));
        $outsider = User::factory()->create(['role' => 'satellite', 'district' => 'Elsewhere']);
        $this->actingAs($outsider)->get($url)->assertForbidden();
    }

    public function test_t14_generates_pending_recommendation_from_approved_matching_catalogue(): void
    {
        [$manager, $satellite, $member, $result] = $this->fixture();
        $action = $this->approvedAction($manager);
        $this->actingAs($manager)->get(route('satellites.engagement.show', [$satellite, $member]))
            ->assertOk()->assertSeeText('Assign recommendation for human review')->assertSeeText($action->title);
        $this->assign($manager, $satellite, $member, $action)->assertRedirect();
        $recommendation = Recommendation::sole();
        $this->assertSame(Recommendation::STATUS_PENDING, $recommendation->status);
        $this->assertSame($result->id, $recommendation->engagement_result_id);
        $this->assertSame($action->id, $recommendation->recommended_action_id);
        $this->assertSame($action->description, $recommendation->action_description_snapshot);
        $this->assertNull($recommendation->reviewed_by);
        $this->actingAs($manager)->get(route('satellites.recommendations.show', [$satellite, $recommendation]))
            ->assertOk()->assertSeeText('Pending Review')->assertSeeText('Approve')->assertSeeText('Modify')->assertSeeText('Reject');
        $this->get(route('satellites.recommendations.index', $satellite))->assertOk()->assertSeeText($action->title);
    }

    public function test_t14_rejects_inactive_unapproved_and_mismatched_catalogue_actions(): void
    {
        [$manager, $satellite, $member] = $this->fixture();
        foreach ([['is_active' => false], ['approved_by' => null], ['approved_at' => null], ['condition_metadata' => ['classification' => 'Possible Need for Follow-Up']]] as $override) {
            $action = $this->approvedAction($manager, $override);
            $this->assign($manager, $satellite, $member, $action)->assertSessionHasErrors('recommended_action_id');
            $this->assertDatabaseCount('recommendations', 0);
            $this->actingAs($manager)->get(route('satellites.engagement.show', [$satellite, $member]))
                ->assertOk()->assertDontSeeText($action->title);
        }
    }

    public function test_t14_insufficient_information_allows_only_reviewed_data_validation(): void
    {
        [$manager, $satellite, $member, $result] = $this->fixture();
        $result->update(['classification' => EngagementClassificationService::CLASSIFICATION_INSUFFICIENT, 'data_sufficiency' => 'insufficient_member_indicators']);
        $ordinary = $this->approvedAction($manager, ['condition_metadata' => null]);
        $this->assign($manager, $satellite, $member, $ordinary)->assertSessionHasErrors('recommended_action_id');
        $this->assertDatabaseCount('recommendations', 0);
        $validation = $this->approvedAction($manager, ['condition_metadata' => ['purpose' => 'data_validation']]);
        $this->actingAs($manager)->get(route('satellites.engagement.show', [$satellite, $member]))
            ->assertOk()->assertSeeText('Insufficient Information')->assertSeeText($validation->title)->assertDontSeeText($ordinary->title);
        $this->assign($manager, $satellite, $member, $validation)->assertSessionHasNoErrors();
        $this->assertSame($validation->id, Recommendation::sole()->recommended_action_id);
        $this->assertSame(Recommendation::STATUS_PENDING, Recommendation::sole()->status);
    }

    public function test_t14_missing_result_and_empty_catalogue_do_not_invent_recommendations(): void
    {
        [$manager, $satellite, $member, $result] = $this->fixture();
        $this->actingAs($manager)->get(route('satellites.engagement.show', [$satellite, $member]))
            ->assertOk()->assertSeeText('No eligible approved catalogue actions are available.');
        $action = $this->approvedAction($manager);
        $result->delete();
        $this->assign($manager, $satellite, $member, $action)
            ->assertSessionHas('error', 'Generate an engagement classification before assigning a recommendation.');
        $this->assertDatabaseCount('recommendations', 0);
    }

    public function test_t14_authorized_reviewer_can_approve_and_history_records_actor_action_time(): void
    {
        [$manager, $satellite, $member] = $this->fixture();
        $action = $this->approvedAction($manager);
        $reviewer = User::factory()->create(['name' => 'Synthetic Assigned Reviewer', 'role' => 'member', 'district' => $satellite->name]);
        $this->assign($manager, $satellite, $member, $action, $reviewer)->assertSessionHasNoErrors();
        $recommendation = Recommendation::sole();
        $this->travelTo(now()->setDate(2026, 9, 17)->setTime(10, 0));
        $this->review($reviewer, $satellite, $recommendation, ['status' => 'Approved', 'review_notes' => 'Reviewed supporting records.'])
            ->assertSessionHasNoErrors();
        $recommendation->refresh();
        $this->assertSame('Approved', $recommendation->status);
        $this->assertSame($reviewer->id, $recommendation->reviewed_by);
        $this->assertSame('2026-09-17 10:00:00', $recommendation->reviewed_at->toDateTimeString());
        $audit = EngagementResearchAudit::where('event', 'recommendation.approved')->sole();
        $this->assertSame($reviewer->id, $audit->actor_id);
        $this->assertSame('Pending Review', $audit->before_state['status']);
        $this->assertSame('Approved', $audit->after_state['status']);
        $this->assertSame($recommendation->id, $audit->after_state['id']);
        $this->assertSame('2026-09-17 10:00:00', $audit->created_at->toDateTimeString());
        $this->get(route('satellites.recommendations.show', [$satellite, $recommendation]))
            ->assertOk()->assertSeeText('Review history')->assertSeeText('Synthetic Assigned Reviewer')
            ->assertSeeText('Assigned')->assertSeeText('Approved')->assertSeeText('Sep 17, 2026 10:00:00 AM');
        $this->travelBack();
    }

    public function test_t14_modified_wording_requires_notes_and_can_be_approved_with_history_preserved(): void
    {
        [$manager, $satellite, $member] = $this->fixture();
        $action = $this->approvedAction($manager);
        $this->assign($manager, $satellite, $member, $action);
        $recommendation = Recommendation::sole();
        $this->review($manager, $satellite, $recommendation, ['status' => 'Modified'])
            ->assertSessionHasErrors(['review_notes', 'modified_action_description']);
        $this->review($manager, $satellite, $recommendation, ['status' => 'Modified', 'review_notes' => 'Clarify the records before offering follow-up.', 'modified_action_description' => 'Request a human review of the recorded attendance dates.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Modified', $recommendation->fresh()->status);
        $this->assertSame('Request a human review of the recorded attendance dates.', $recommendation->fresh()->action_description);
        $this->get(route('satellites.recommendations.show', [$satellite, $recommendation]))
            ->assertOk()->assertSeeText('Modified wording awaits a final approval or rejection.')->assertSeeText('Approve');
        $this->review($manager, $satellite, $recommendation, ['status' => 'Approved'])->assertSessionHasNoErrors();
        $this->assertSame('Approved', $recommendation->fresh()->status);
        $this->assertSame('Clarify the records before offering follow-up.', $recommendation->fresh()->review_notes);
        $this->assertSame($action->description, $action->fresh()->description);
        $this->assertSame(['recommendation.assigned', 'recommendation.modified', 'recommendation.approved'], EngagementResearchAudit::orderBy('id')->pluck('event')->all());
        $audit = EngagementResearchAudit::where('event', 'recommendation.modified')->sole();
        $this->assertSame($action->description, $audit->before_state['action_description_snapshot']);
        $this->assertSame($recommendation->fresh()->action_description, $audit->after_state['action_description_snapshot']);
    }

    public function test_t14_authorized_reviewer_can_reject_with_required_reason(): void
    {
        [$manager, $satellite, $member] = $this->fixture();
        $this->assign($manager, $satellite, $member, $this->approvedAction($manager));
        $recommendation = Recommendation::sole();
        $this->review($manager, $satellite, $recommendation, ['status' => 'Rejected'])->assertSessionHasErrors('review_notes');
        $this->review($manager, $satellite, $recommendation, ['status' => 'Rejected', 'review_notes' => 'Current records do not support this action.'])->assertSessionHasNoErrors();
        $this->assertSame('Rejected', $recommendation->fresh()->status);
        $this->assertDatabaseHas('engagement_research_audits', ['event' => 'recommendation.rejected', 'actor_id' => $manager->id]);
        $this->get(route('satellites.recommendations.show', [$satellite, $recommendation]))
            ->assertOk()->assertSeeText('Current records do not support this action.')->assertDontSeeText('Record human decision');
    }

    public function test_t14_permissions_deny_cross_district_and_unassigned_recommendation_access(): void
    {
        [$manager, $satellite, $member] = $this->fixture();
        $action = $this->approvedAction($manager);
        $this->assign($manager, $satellite, $member, $action);
        $recommendation = Recommendation::sole();
        foreach ([['role' => 'satellite', 'district' => 'Elsewhere'], ['role' => 'member', 'district' => $satellite->name]] as $attributes) {
            $outsider = User::factory()->create($attributes);
            $this->assign($outsider, $satellite, $member, $action)->assertForbidden();
            $this->actingAs($outsider)->get(route('satellites.recommendations.show', [$satellite, $recommendation]))->assertForbidden();
            $this->review($outsider, $satellite, $recommendation, ['status' => 'Approved'])->assertForbidden();
        }
        $this->assertSame('Pending Review', $recommendation->fresh()->status);
        $this->assertDatabaseCount('engagement_research_audits', 1);
    }

    public function test_t14_recommendations_do_not_contact_discipline_or_exclude_members(): void
    {
        [$manager, $satellite, $member, $result] = $this->fixture();
        Mail::fake(); Notification::fake(); Queue::fake(); Http::fake();
        $originalMember = $member->fresh()->getAttributes();
        $originalResult = $result->fresh()->getAttributes();
        $this->assign($manager, $satellite, $member, $this->approvedAction($manager));
        $recommendation = Recommendation::sole();
        $this->review($manager, $satellite, $recommendation, ['status' => 'Approved'])->assertSessionHasNoErrors();
        $this->assertSame($originalMember, $member->fresh()->getAttributes());
        $this->assertSame($originalResult, $result->fresh()->getAttributes());
        $this->assertDatabaseCount('follow_ups', 0);
        Mail::assertNothingSent(); Mail::assertNothingQueued(); Notification::assertNothingSent(); Queue::assertNothingPushed(); Http::assertNothingSent();
        $this->get(route('satellites.recommendations.show', [$satellite, $recommendation]))
            ->assertOk()->assertSeeText('the system does not automatically contact, discipline, or exclude members.');
    }

    public function test_t14_duplicate_pending_and_finalized_or_expired_reviews_are_blocked(): void
    {
        [$manager, $satellite, $member] = $this->fixture();
        $action = $this->approvedAction($manager);
        $this->assign($manager, $satellite, $member, $action);
        $this->assign($manager, $satellite, $member, $action)->assertSessionHas('error');
        $this->assertDatabaseCount('recommendations', 1);
        $recommendation = Recommendation::sole();
        $recommendation->update(['expires_at' => now()->subMinute()]);
        $this->review($manager, $satellite, $recommendation, ['status' => 'Approved'])->assertSessionHasErrors('status');
        $recommendation->update(['expires_at' => null, 'status' => 'Approved']);
        $this->review($manager, $satellite, $recommendation, ['status' => 'Rejected', 'review_notes' => 'Attempted overwrite'])->assertSessionHasErrors('status');
        $this->assertSame('Approved', $recommendation->fresh()->status);
    }

    private function fixture(): array
    {
        $manager = User::factory()->create(['name' => 'Synthetic District Manager', 'role' => 'satellite', 'district' => 'Fixture District']);
        $satellite = Satellite::create(['name' => 'Fixture District', 'slug' => 'fixture-district', 'created_by_id' => $manager->id]);
        $member = Member::create(['name' => 'Synthetic Functional Member', 'satellite_id' => $satellite->id, 'status' => 'Active', 'active_disciples' => 4, 'weekly_dgroups_led' => 2]);
        $tree = ['node_id' => 'root', 'feature' => 'active_disciples', 'threshold' => 1.5,
            'left' => ['node_id' => 'left', 'prediction' => 'Possible Need for Follow-Up'],
            'right' => ['node_id' => 'right', 'prediction' => 'Active']];
        $run = EngagementModelRun::create(['satellite_id' => $satellite->id, 'model_name' => EngagementClassificationService::MODEL_CART,
            'model_version' => 'synthetic-functional-v1', 'training_signature' => hash('sha256', 'synthetic-functional-fixture'),
            'training_config' => [], 'tree' => $tree, 'is_active' => true, 'trained_at' => '2026-09-17 09:00:00']);
        $result = EngagementResult::create(['member_id' => $member->id, 'classification' => 'Active', 'confidence' => .8,
            'model_name' => EngagementClassificationService::MODEL_CART, 'model_version' => 'synthetic-functional-v1',
            'engagement_model_run_id' => $run->id, 'generated_at' => '2026-09-17 09:15:00', 'generated_by_id' => $manager->id,
            'data_sufficiency' => 'sufficient', 'human_review_required' => true,
            'used_features' => [['feature' => 'active_disciples', 'label' => 'Active Disciples', 'value' => 4], ['feature' => 'weekly_dgroups_led', 'label' => 'Weekly Dgroups Led', 'value' => 2]],
            'decision_path' => [['node_id' => 'root', 'feature' => 'active_disciples', 'label' => 'Active Disciples', 'value' => 4, 'operator' => '>', 'threshold' => 1.5, 'next_node_id' => 'right', 'branch_taken' => 'right'],
                ['node_id' => 'right', 'feature' => 'leaf', 'label' => 'Final CART leaf', 'outcome' => 'Active']]]);
        return [$manager, $satellite, $member, $result];
    }

    private function approvedAction(User $approver, array $overrides = []): RecommendationAction
    {
        return RecommendationAction::create(array_merge(['title' => 'Synthetic reviewed action '.(RecommendationAction::count() + 1),
            'description' => 'Prepare records for an authorized human follow-up review.', 'condition_metadata' => ['classification' => 'Active'],
            'is_active' => true, 'approved_by' => $approver->id, 'approved_at' => now()], $overrides));
    }

    private function assign(User $actor, Satellite $satellite, Member $member, RecommendationAction $action, ?User $assignee = null)
    {
        return $this->actingAs($actor)->from(route('satellites.engagement.show', [$satellite, $member]))
            ->post(route('satellites.engagement.recommendations.store', [$satellite, $member]), ['recommended_action_id' => $action->id, 'assigned_to' => ($assignee ?? $actor)->id]);
    }

    private function review(User $actor, Satellite $satellite, Recommendation $recommendation, array $payload)
    {
        return $this->actingAs($actor)->from(route('satellites.recommendations.show', [$satellite, $recommendation]))
            ->patch(route('satellites.recommendations.review', [$satellite, $recommendation]), $payload);
    }
}
