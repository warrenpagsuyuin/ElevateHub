<?php

namespace Tests\Feature;

use App\Models\RecommendationAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationActionCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_empty_recommendation_catalogue(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.recommendation-actions.index'))
            ->assertOk()
            ->assertSee('Recommendation Catalogue')
            ->assertSee('No recommendation actions yet');
    }

    public function test_admin_can_create_draft_recommendation_action(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.recommendation-actions.store'), [
                'title' => 'Schedule reviewed follow-up',
                'description' => 'Prepare a human-reviewed follow-up plan for the assigned satellite user.',
                'condition_metadata' => '{"classification":"Possible Need for Follow-Up"}',
            ])
            ->assertRedirect(route('admin.recommendation-actions.index'));

        $action = RecommendationAction::firstOrFail();

        $this->assertSame('Schedule reviewed follow-up', $action->title);
        $this->assertFalse($action->is_active);
        $this->assertNull($action->approved_by);
        $this->assertNull($action->approved_at);
        $this->assertSame('Possible Need for Follow-Up', $action->condition_metadata['classification']);
    }

    public function test_admin_must_confirm_approval_before_activating_action(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.recommendation-actions.index'))
            ->post(route('admin.recommendation-actions.store'), [
                'title' => 'Assign care review',
                'description' => 'Assign this recommendation for authorized human review.',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.recommendation-actions.index'))
            ->assertSessionHasErrors('approval_confirmed');

        $this->assertDatabaseMissing('recommendation_actions', [
            'title' => 'Assign care review',
        ]);
    }

    public function test_admin_can_create_active_action_with_approval_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.recommendation-actions.store'), [
                'title' => 'Offer reviewed follow-up conversation',
                'description' => 'Offer a follow-up conversation after authorized review of the engagement result.',
                'condition_metadata' => '{"classification":"Possible Need for Follow-Up","requires_human_review":true}',
                'is_active' => '1',
                'approval_confirmed' => '1',
            ])
            ->assertRedirect(route('admin.recommendation-actions.index'));

        $action = RecommendationAction::firstOrFail();

        $this->assertTrue($action->is_active);
        $this->assertSame($admin->id, $action->approved_by);
        $this->assertNotNull($action->approved_at);
        $this->assertTrue($action->condition_metadata['requires_human_review']);
    }

    public function test_invalid_condition_metadata_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.recommendation-actions.index'))
            ->post(route('admin.recommendation-actions.store'), [
                'title' => 'Invalid metadata',
                'description' => 'This row has malformed metadata.',
                'condition_metadata' => '{"classification":',
            ])
            ->assertRedirect(route('admin.recommendation-actions.index'))
            ->assertSessionHasErrors('condition_metadata');
    }

    public function test_admin_can_update_and_deactivate_recommendation_action(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $action = RecommendationAction::create([
            'title' => 'Original action',
            'description' => 'Original description.',
            'is_active' => true,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.recommendation-actions.update', $action), [
                'title' => 'Updated action',
                'description' => 'Updated description.',
                'condition_metadata' => '',
            ])
            ->assertRedirect(route('admin.recommendation-actions.index'));

        $action->refresh();

        $this->assertSame('Updated action', $action->title);
        $this->assertFalse($action->is_active);
        $this->assertNull($action->approved_by);
        $this->assertNull($action->approved_at);
    }

    public function test_satellite_user_cannot_access_recommendation_catalogue(): void
    {
        $satelliteUser = User::factory()->create(['role' => 'satellite']);

        $this->actingAs($satelliteUser)
            ->get(route('admin.recommendation-actions.index'))
            ->assertForbidden();
    }

    public function test_admin_can_export_recommendation_catalogue(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        RecommendationAction::create([
            'title' => 'Exported action',
            'description' => 'Visible in CSV export.',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.recommendation-actions.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
