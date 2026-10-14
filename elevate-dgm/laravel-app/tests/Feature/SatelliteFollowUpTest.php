<?php

namespace Tests\Feature;

use App\Models\FollowUp;
use App\Models\Member;
use App\Models\Satellite;
use App\Models\Seeker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SatelliteFollowUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_satellite_user_can_view_follow_ups_index(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satelliteUser = User::factory()->create(['role' => 'satellite']);
        $satellite = Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'assigned_user_id' => $satelliteUser->id,
            'created_by_id' => $admin->id,
        ]);

        $this->actingAs($satelliteUser)
            ->get(route('satellites.follow-ups.index', $satellite))
            ->assertOk()
            ->assertSee('Follow-Up History')
            ->assertSee('New Follow-Up')
            ->assertDontSee('Actions')
            ->assertDontSee('View only')
            ->assertSee('colspan="6"', false);
    }

    public function test_assignment_follow_up_with_null_fields_renders_as_unanalyzed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satelliteUser = User::factory()->create(['role' => 'satellite']);
        $satellite = Satellite::create([
            'name' => 'Pasig', 'slug' => 'pasig',
            'assigned_user_id' => $satelliteUser->id, 'created_by_id' => $admin->id,
        ]);
        $seeker = Seeker::create([
            'name' => 'Pending Seeker', 'email' => 'pending@example.test',
            'assigned_satellite_slug' => 'pasig', 'status' => 'Pending',
        ]);
        FollowUp::create([
            'seeker_id' => $seeker->id,
            'satellite_id' => $satellite->id,
            'leader_member_id' => Member::create([
                'satellite_id' => $satellite->id, 'name' => 'Pasig Leader',
                'full_name' => 'Pasig Leader', 'dgroup_role' => Member::ROLE_LEADER,
            ])->id,
            'origin' => FollowUp::ORIGIN_LEADER_ASSIGNMENT,
            'follow_up_date' => now()->toDateString(),
            'method' => null,
            'notes' => null,
            'status' => FollowUp::STATUS_PENDING,
        ]);

        $this->actingAs($satelliteUser)
            ->get(route('satellites.follow-ups.index', $satellite))
            ->assertOk()
            ->assertSee('Pending Seeker')
            ->assertSee('Unanalyzed')
            ->assertDontSee('% confidence')
            ->assertDontSee('Why this sentiment?')
            ->assertSee('—');
    }

    public function test_satellite_user_can_create_follow_up_for_seeker(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satelliteUser = User::factory()->create(['role' => 'satellite']);
        $satellite = Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'assigned_user_id' => $satelliteUser->id,
            'created_by_id' => $admin->id,
        ]);
        $seeker = Seeker::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'assigned_satellite_slug' => 'pasig',
            'status' => 'Pending',
        ]);

        $this->actingAs($satelliteUser)
            ->post(route('satellites.follow-ups.store', $satellite), [
                'seeker_id' => $seeker->id,
                'follow_up_date' => now()->addDays(3)->format('Y-m-d'),
                'method' => 'Phone',
                'notes' => 'Initial contact',
                'status' => 'Pending',
            ])
            ->assertRedirect(route('satellites.follow-ups.index', $satellite));

        $this->assertDatabaseHas('follow_ups', [
            'seeker_id' => $seeker->id,
            'satellite_id' => $satellite->id,
            'method' => 'Phone',
            'notes' => 'Initial contact',
            'status' => 'Pending',
        ]);
    }

    public function test_satellite_user_can_update_follow_up_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satelliteUser = User::factory()->create(['role' => 'satellite']);
        $satellite = Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'assigned_user_id' => $satelliteUser->id,
            'created_by_id' => $admin->id,
        ]);
        $followUp = FollowUp::create([
            'satellite_id' => $satellite->id,
            'seeker_id' => Seeker::create([
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'assigned_satellite_slug' => 'pasig',
                'status' => 'Pending',
            ])->id,
            'created_by_id' => $satelliteUser->id,
            'follow_up_date' => now()->addDays(1),
            'method' => 'Email',
            'notes' => 'Sent email',
            'status' => 'Pending',
        ]);

        $this->actingAs($satelliteUser)
            ->patch(route('satellites.follow-ups.update', [$satellite, $followUp]), [
                'follow_up_date' => now()->addDays(2)->format('Y-m-d'),
                'method' => 'Phone',
                'notes' => 'Called on phone',
                'status' => 'Completed',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('follow_ups', [
            'id' => $followUp->id,
            'status' => 'Completed',
            'method' => 'Phone',
            'notes' => 'Called on phone',
        ]);
    }

    public function test_satellite_user_can_delete_follow_up(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satelliteUser = User::factory()->create(['role' => 'satellite']);
        $satellite = Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'assigned_user_id' => $satelliteUser->id,
            'created_by_id' => $admin->id,
        ]);
        $followUp = FollowUp::create([
            'satellite_id' => $satellite->id,
            'seeker_id' => Seeker::create([
                'name' => 'Robert Smith',
                'email' => 'robert@example.com',
                'assigned_satellite_slug' => 'pasig',
                'status' => 'Pending',
            ])->id,
            'created_by_id' => $satelliteUser->id,
            'method' => 'In-Person',
            'notes' => 'Visited office',
            'status' => 'Completed',
        ]);

        $this->actingAs($satelliteUser)
            ->delete(route('satellites.follow-ups.destroy', [$satellite, $followUp]))
            ->assertRedirect();

        $this->assertDatabaseMissing('follow_ups', [
            'id' => $followUp->id,
        ]);
    }

    public function test_unauthorized_user_cannot_access_follow_ups(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satellite = Satellite::create([
            'name' => 'Pasig',
            'slug' => 'pasig',
            'assigned_user_id' => User::factory()->create(['role' => 'satellite'])->id,
            'created_by_id' => $admin->id,
        ]);
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)
            ->get(route('satellites.follow-ups.index', $satellite))
            ->assertStatus(403);
    }
}
