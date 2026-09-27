<?php

namespace Tests\Feature;

use App\Models\EngagementReferenceLabel;
use App\Models\EngagementModelRun;
use App\Models\Member;
use App\Models\Satellite;
use App\Models\User;
use App\Services\EngagementClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EngagementClassificationTest extends TestCase
{
    use RefreshDatabase;

    protected EngagementClassificationService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = app(EngagementClassificationService::class);
    }

    public function test_member_with_recorded_leadership_indicators_is_active_in_review_baseline(): void
    {
        $satellite = $this->satellite();
        $member = Member::create([
            'name' => 'John Leader',
            'satellite_id' => $satellite->id,
            'status' => 'Active',
            'dgroup_role' => 'Dgroup Leader',
            'active_disciples' => 3,
            'weekly_dgroups_led' => 2,
        ]);

        $result = $this->service->classifyMember($member);

        $this->assertEquals(EngagementClassificationService::CLASSIFICATION_ACTIVE, $result['classification']);
        $this->assertEquals(EngagementClassificationService::MODEL_BASELINE, $result['model_name']);
        $this->assertGreaterThan(0.7, $result['confidence']);
    }

    public function test_member_status_is_not_used_as_engagement_predictor(): void
    {
        $satellite = $this->satellite();
        $member = Member::create([
            'name' => 'Inactive Member',
            'satellite_id' => $satellite->id,
            'status' => 'Inactive',
        ]);

        $result = $this->service->classifyMember($member);

        $this->assertEquals(EngagementClassificationService::CLASSIFICATION_INSUFFICIENT, $result['classification']);
        $this->assertEquals('insufficient_member_indicators', $result['data_sufficiency']);
        $this->assertNotContains('member_status', array_keys($result['used_features']));
        $this->assertContains('member_status', $result['model_metadata']['training_config']['excluded_predictors']);
    }

    public function test_low_activity_member_is_flagged_for_follow_up_when_indicators_are_stale(): void
    {
        $satellite = $this->satellite();
        $member = Member::create([
            'name' => 'Low Activity',
            'satellite_id' => $satellite->id,
            'status' => 'Active',
            'dgroup_role' => 'Member',
            'active_disciples' => 0,
            'weekly_dgroups_led' => 0,
            'refresh_date' => now()->subMonths(9),
        ]);

        $result = $this->service->classifyMember($member);

        $this->assertEquals(EngagementClassificationService::CLASSIFICATION_NEEDS_FOLLOW_UP, $result['classification']);
        $this->assertGreaterThan(0, $result['confidence']);
    }

    public function test_store_classification_persists_xai_metadata(): void
    {
        $generator = User::factory()->create(['role' => 'admin']);
        $satellite = $this->satellite();
        $member = Member::create([
            'name' => 'Test Member',
            'satellite_id' => $satellite->id,
            'status' => 'Active',
            'dgroup_role' => 'Dgroup Leader',
        ]);

        $classificationResult = $this->service->classifyMember($member);
        $stored = $this->service->storeClassification($classificationResult, $generator->id);

        $this->assertDatabaseHas('engagement_results', [
            'member_id' => $member->id,
            'classification' => EngagementClassificationService::CLASSIFICATION_ACTIVE,
            'model_name' => EngagementClassificationService::MODEL_BASELINE,
            'model_version' => EngagementClassificationService::BASELINE_MODEL_VERSION,
            'generated_by_id' => $generator->id,
            'human_review_required' => true,
        ]);
        $this->assertNotEmpty($stored->decision_path);
        $this->assertNotEmpty($stored->used_features);
        $this->assertNotEmpty($stored->explanation);
        $this->assertNotNull($stored->generated_at);
    }

    public function test_actual_cart_mode_uses_separate_reference_labels(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satellite = $this->satellite($admin);
        $admin->update(['district' => $satellite->name]);

        $this->referenceMember($satellite, $admin, EngagementClassificationService::CLASSIFICATION_ACTIVE, [
            'name' => 'Active One',
            'dgroup_role' => 'Dgroup Leader',
            'active_disciples' => 4,
            'weekly_dgroups_led' => 2,
        ]);
        $this->referenceMember($satellite, $admin, EngagementClassificationService::CLASSIFICATION_ACTIVE, [
            'name' => 'Active Two',
            'dgroup_role' => 'Dgroup Leader',
            'active_disciples' => 3,
            'weekly_dgroups_led' => 1,
        ]);
        $this->referenceMember($satellite, $admin, EngagementClassificationService::CLASSIFICATION_ACTIVE, [
            'name' => 'Active Three',
            'dgroup_role' => 'Dgroup Leader',
            'active_disciples' => 5,
            'weekly_dgroups_led' => 2,
        ]);
        $this->referenceMember($satellite, $admin, EngagementClassificationService::CLASSIFICATION_ACTIVE, [
            'name' => 'Active Four',
            'dgroup_role' => 'Dgroup Leader',
            'active_disciples' => 6,
            'weekly_dgroups_led' => 3,
        ]);
        $this->referenceMember($satellite, $admin, EngagementClassificationService::CLASSIFICATION_NEEDS_FOLLOW_UP, [
            'name' => 'Review One',
            'dgroup_role' => 'Member',
            'active_disciples' => 0,
            'weekly_dgroups_led' => 0,
            'refresh_date' => now()->subMonths(9),
        ]);
        $this->referenceMember($satellite, $admin, EngagementClassificationService::CLASSIFICATION_NEEDS_FOLLOW_UP, [
            'name' => 'Review Two',
            'dgroup_role' => 'Member',
            'active_disciples' => 0,
            'weekly_dgroups_led' => 0,
            'refresh_date' => now()->subMonths(10),
        ]);
        $this->referenceMember($satellite, $admin, EngagementClassificationService::CLASSIFICATION_NEEDS_FOLLOW_UP, [
            'name' => 'Review Three',
            'dgroup_role' => 'Member',
            'active_disciples' => 0,
            'weekly_dgroups_led' => 0,
            'refresh_date' => now()->subMonths(11),
        ]);
        $this->referenceMember($satellite, $admin, EngagementClassificationService::CLASSIFICATION_NEEDS_FOLLOW_UP, [
            'name' => 'Review Four',
            'dgroup_role' => 'Member',
            'active_disciples' => 0,
            'weekly_dgroups_led' => 0,
            'refresh_date' => now()->subMonths(12),
        ]);

        $target = Member::create([
            'name' => 'Target Leader',
            'satellite_id' => $satellite->id,
            'status' => 'Inactive',
            'dgroup_role' => 'Dgroup Leader',
            'active_disciples' => 5,
            'weekly_dgroups_led' => 1,
        ]);

        $result = $this->service->classifyMember($target);

        $this->assertEquals(EngagementClassificationService::MODEL_CART, $result['model_name']);
        $this->assertEquals(EngagementClassificationService::CART_MODEL_VERSION, $result['model_version']);
        $this->assertEquals(EngagementClassificationService::CLASSIFICATION_ACTIVE, $result['classification']);
        $this->assertNotEmpty($result['decision_path']);
        $this->assertArrayHasKey('feature', $result['decision_path'][0]);
        $this->assertArrayHasKey('operator', $result['decision_path'][0]);
        $this->assertArrayHasKey('threshold', $result['decision_path'][0]);
        $this->assertArrayHasKey('tree', $result['model_metadata']);
        $this->assertArrayHasKey('model_run_id', $result['model_metadata']);
        $this->assertDecisionPathMatchesTree($result['model_metadata']['tree'], $result['decision_path'], $result['classification']);

        $modelRun = EngagementModelRun::findOrFail($result['engagement_model_run_id']);
        $this->assertSame(4, $modelRun->split_summary['train']['sample_count']);
        $this->assertSame(2, $modelRun->split_summary['validation']['sample_count']);
        $this->assertSame(2, $modelRun->split_summary['holdout']['sample_count']);
        $this->assertTrue($modelRun->validation_metrics['available']);
        $this->assertTrue($modelRun->holdout_metrics['available']);
        $this->assertArrayHasKey('accuracy', $modelRun->validation_metrics);
        $this->assertArrayHasKey('per_label', $modelRun->holdout_metrics);

        $stored = $this->service->storeClassification($result, $admin->id);
        $this->assertSame($modelRun->id, $stored->engagement_model_run_id);

        $this->actingAs($admin)
            ->get(route('satellites.engagement.show', [$satellite, $target]))
            ->assertOk()
            ->assertSee('Why This Result?')
            ->assertSee('Explainable AI (XAI)')
            ->assertSee('Plain-Language Explanation')
            ->assertSee('Factors Used by CART')
            ->assertSee('View CART Decision Path')
            ->assertSee('Actual CART Traversal')
            ->assertSee('Node: root')
            ->assertSee('Next Node:')
            ->assertSee('Validation and Holdout Results')
            ->assertSee('Classification is intended for authorized administrative review only')
            ->assertDontSee('weekly_dgroups_led');
    }

    public function test_baseline_xai_path_does_not_claim_cart_traversal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satellite = $this->satellite($admin);
        $admin->update(['district' => $satellite->name]);
        $member = Member::create([
            'name' => 'Baseline Only',
            'satellite_id' => $satellite->id,
            'status' => 'Active',
            'dgroup_role' => 'Dgroup Leader',
            'active_disciples' => 2,
            'weekly_dgroups_led' => 1,
        ]);

        $stored = $this->service->storeClassification($this->service->classifyMember($member), $admin->id);

        $this->assertSame(EngagementClassificationService::MODEL_BASELINE, $stored->model_name);
        $this->assertNull($stored->engagement_model_run_id);

        $this->actingAs($admin)
            ->get(route('satellites.engagement.show', [$satellite, $member]))
            ->assertOk()
            ->assertSee('Why This Result?')
            ->assertSee('Baseline Review Path')
            ->assertSee('View Review Decision Path')
            ->assertDontSee('Actual CART Traversal');
    }

    public function test_xai_is_not_registered_as_a_standalone_navigation_or_route(): void
    {
        $satelliteUser = User::factory()->create(['role' => 'satellite']);
        $satellite = $this->satellite(null, $satelliteUser);

        $xaiRoutes = collect(Route::getRoutes())
            ->map(fn ($route): string => strtolower($route->uri()))
            ->filter(fn (string $uri): bool => str_contains($uri, 'xai'))
            ->values();

        $this->assertCount(0, $xaiRoutes);

        $this->actingAs($satelliteUser)
            ->get(route('satellites.engagement.index', $satellite))
            ->assertOk()
            ->assertDontSee('>XAI</a>', false);
    }

    public function test_satellite_user_cannot_view_other_satellite_xai_detail(): void
    {
        $firstUser = User::factory()->create(['role' => 'satellite']);
        $secondUser = User::factory()->create(['role' => 'satellite']);
        $firstSatellite = $this->satellite(null, $firstUser);
        $secondSatellite = $this->satellite(null, $secondUser);
        $member = Member::create([
            'name' => 'Other Satellite Member',
            'satellite_id' => $secondSatellite->id,
            'status' => 'Active',
            'dgroup_role' => 'Dgroup Leader',
            'active_disciples' => 2,
            'weekly_dgroups_led' => 1,
        ]);

        $this->service->storeClassification($this->service->classifyMember($member), $secondUser->id);

        $this->actingAs($firstUser)
            ->get(route('satellites.engagement.show', [$secondSatellite, $member]))
            ->assertStatus(403);

        $this->assertNotSame($firstSatellite->id, $secondSatellite->id);
    }

    public function test_satellite_user_can_view_engagement_analytics(): void
    {
        $satelliteUser = User::factory()->create(['role' => 'satellite']);
        $satellite = $this->satellite(null, $satelliteUser);

        $this->actingAs($satelliteUser)
            ->get(route('satellites.engagement.index', $satellite))
            ->assertOk()
            ->assertSee('Engagement Analytics')
            ->assertSee(EngagementClassificationService::CLASSIFICATION_ACTIVE);
    }

    public function test_satellite_user_can_classify_members(): void
    {
        $satelliteUser = User::factory()->create(['role' => 'satellite']);
        $satellite = $this->satellite(null, $satelliteUser);
        Member::create([
            'name' => 'John Doe',
            'satellite_id' => $satellite->id,
            'status' => 'Active',
            'dgroup_role' => 'Dgroup Leader',
        ]);

        $this->actingAs($satelliteUser)
            ->post(route('satellites.engagement.classify', $satellite))
            ->assertRedirect(route('satellites.engagement.index', $satellite));

        $this->assertDatabaseHas('engagement_results', [
            'classification' => EngagementClassificationService::CLASSIFICATION_ACTIVE,
            'generated_by_id' => $satelliteUser->id,
        ]);
    }

    public function test_unauthorized_user_cannot_access_engagement(): void
    {
        $satellite = $this->satellite();
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)
            ->get(route('satellites.engagement.index', $satellite))
            ->assertStatus(403);
    }

    private function satellite(?User $admin = null, ?User $assignedUser = null): Satellite
    {
        $admin ??= User::factory()->create(['role' => 'admin']);
        $assignedUser ??= User::factory()->create(['role' => 'satellite']);

        $suffix = strtolower(fake()->bothify('??##'));

        return Satellite::create([
            'name' => 'Pasig '.strtoupper($suffix),
            'slug' => 'pasig-'.$suffix,
            'assigned_user_id' => $assignedUser->id,
            'created_by_id' => $admin->id,
        ]);
    }

    private function referenceMember(Satellite $satellite, User $labeler, string $label, array $attributes): Member
    {
        $member = Member::create($attributes + [
            'satellite_id' => $satellite->id,
            'status' => 'Active',
        ]);

        EngagementReferenceLabel::create([
            'member_id' => $member->id,
            'labeled_by_id' => $labeler->id,
            'label' => $label,
            'source' => 'human_reference',
        ]);

        return $member;
    }

    private function assertDecisionPathMatchesTree(array $tree, array $decisionPath, string $classification): void
    {
        $node = $tree;
        $splitSteps = array_values(array_filter(
            $decisionPath,
            fn (array $step): bool => ($step['feature'] ?? null) !== 'leaf'
        ));

        foreach ($splitSteps as $step) {
            $this->assertSame('split', $node['type']);
            $this->assertSame($node['node_id'], $step['node_id']);
            $this->assertSame($node['feature'], $step['feature']);
            $this->assertEquals($node['threshold'], $step['threshold']);

            $expectedBranch = $step['branch_taken'] === 'left' ? 'left' : 'right';
            $expectedNext = $node[$expectedBranch];
            $this->assertSame($expectedNext['node_id'], $step['next_node_id']);

            $node = $expectedNext;
        }

        $leafStep = $decisionPath[array_key_last($decisionPath)];
        $this->assertSame('leaf', $node['type']);
        $this->assertSame('leaf', $leafStep['feature']);
        $this->assertSame($node['node_id'], $leafStep['node_id']);
        $this->assertSame($classification, $leafStep['predicted_category']);
        $this->assertSame($classification, $node['prediction']);
    }
}
