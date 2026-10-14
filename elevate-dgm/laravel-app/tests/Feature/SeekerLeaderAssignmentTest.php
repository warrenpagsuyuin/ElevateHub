<?php

namespace Tests\Feature;

use App\Models\FollowUp;
use App\Models\Member;
use App\Models\Satellite;
use App\Models\Seeker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeekerLeaderAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function satellite(string $name, string $slug, User $admin, ?User $assignedUser = null): Satellite
    {
        return Satellite::create([
            'name' => $name,
            'slug' => $slug,
            'assigned_user_id' => $assignedUser?->id,
            'created_by_id' => $admin->id,
        ]);
    }

    private function leader(Satellite $satellite, string $name): Member
    {
        return Member::create([
            'satellite_id' => $satellite->id,
            'name' => $name,
            'full_name' => $name,
            'first_name' => $name,
            'last_name' => 'Leader',
            'status' => Member::STATUS_ACTIVE,
            'dgroup_role' => Member::ROLE_LEADER,
            'campus_school' => $satellite->display_name.' School',
            'district' => $satellite->display_name,
        ]);
    }

    private function seeker(Satellite $satellite, string $name = 'Pasig Seeker'): Seeker
    {
        return Seeker::create([
            'name' => $name,
            'email' => str_replace(' ', '.', strtolower($name)).'@example.test',
            'assigned_district' => $satellite->display_name,
            'assigned_satellite_slug' => $satellite->slug,
            'status' => Seeker::STATUS_PENDING,
        ]);
    }

    public function test_assigned_volunteer_can_assign_a_leader_from_their_satellite(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $pasigUser = User::factory()->create(['role' => 'volunteer', 'district' => 'Pasig']);
        $pasig = $this->satellite('Pasig', 'pasig', $admin, $pasigUser);
        $anonas = $this->satellite('Anonas', 'anonas', $admin);
        $pasigLeader = $this->leader($pasig, 'Pasig Leader');
        $this->leader($anonas, 'Anonas Leader');
        $seeker = $this->seeker($pasig);

        $this->actingAs($pasigUser)
            ->patchJson(route('satellites.seekers.leader', [$pasig, $seeker]), ['leader_id' => $pasigLeader->id])
            ->assertOk()
            ->assertJsonPath('leader.name', 'Pasig Leader');

        $this->assertSame('Pasig Leader', $seeker->refresh()->dgroup_leader_name);
        $followUp = FollowUp::query()->where('origin', FollowUp::ORIGIN_LEADER_ASSIGNMENT)->sole();
        $this->assertSame($seeker->id, $followUp->seeker_id);
        $this->assertSame($pasig->id, $followUp->satellite_id);
        $this->assertSame($pasigLeader->id, $followUp->leader_member_id);
        $this->assertSame(now()->toDateString(), $followUp->follow_up_date->toDateString());
        $this->assertSame(FollowUp::STATUS_PENDING, $followUp->status);
        $this->assertNull($followUp->method);
        $this->assertNull($followUp->notes);
        $this->assertNull($followUp->sentiment_label);
        $this->assertNull($followUp->sentiment_confidence);

        $this->patchJson(route('satellites.seekers.leader', [$pasig, $seeker]), ['leader_id' => $pasigLeader->id])
            ->assertOk();
        $this->assertSame(1, FollowUp::where('origin', FollowUp::ORIGIN_LEADER_ASSIGNMENT)->count());

        $this->get(route('satellites.seekers', $pasig))
            ->assertOk()
            ->assertSee('Pasig Leader')
            ->assertDontSee('Anonas Leader')
            ->assertViewHas('leaderOptions', fn ($options) => array_keys($options) === [$pasigLeader->id]);
    }

    public function test_assigned_user_cannot_assign_a_leader_to_another_satellites_seeker(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $pasigUser = User::factory()->create(['role' => 'volunteer', 'district' => 'Pasig']);
        $pasig = $this->satellite('Pasig', 'pasig', $admin, $pasigUser);
        $anonas = $this->satellite('Anonas', 'anonas', $admin);
        $leader = $this->leader($pasig, 'Pasig Leader');
        $seeker = $this->seeker($anonas, 'Anonas Seeker');

        $this->actingAs($pasigUser)
            ->patchJson(route('satellites.seekers.leader', [$pasig, $seeker]), ['leader_id' => $leader->id])
            ->assertNotFound();

        $this->assertNull($seeker->refresh()->dgroup_leader_name);
    }

    public function test_assigned_user_cannot_assign_a_leader_from_another_satellite(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $pasigUser = User::factory()->create(['role' => 'volunteer', 'district' => 'Pasig']);
        $pasig = $this->satellite('Pasig', 'pasig', $admin, $pasigUser);
        $anonas = $this->satellite('Anonas', 'anonas', $admin);
        $anonasLeader = $this->leader($anonas, 'Anonas Leader');
        $seeker = $this->seeker($pasig);

        $this->actingAs($pasigUser)
            ->patchJson(route('satellites.seekers.leader', [$pasig, $seeker]), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('leader_id')
            ->assertJsonPath('errors.leader_id.0', 'Select a DGroup Leader.');

        $this->actingAs($pasigUser)
            ->patchJson(route('satellites.seekers.leader', [$pasig, $seeker]), ['leader_id' => $anonasLeader->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('leader_id');

        $this->assertNull($seeker->refresh()->dgroup_leader_name);
    }

    public function test_reassignment_updates_the_open_assignment_follow_up_and_keeps_its_original_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satellite = $this->satellite('Pasig', 'pasig', $admin);
        $firstLeader = $this->leader($satellite, 'First Leader');
        $secondLeader = $this->leader($satellite, 'Second Leader');
        $seeker = $this->seeker($satellite);

        $this->actingAs($admin)->patchJson(route('admin.seekers.assign-leader', $seeker), ['leader_id' => $firstLeader->id])->assertOk();
        $followUp = FollowUp::where('origin', FollowUp::ORIGIN_LEADER_ASSIGNMENT)->sole();
        $originalDate = $followUp->follow_up_date->toDateString();

        $this->patchJson(route('admin.seekers.assign-leader', $seeker), ['leader_id' => $secondLeader->id])->assertOk();

        $this->assertSame(1, FollowUp::where('origin', FollowUp::ORIGIN_LEADER_ASSIGNMENT)->count());
        $this->assertSame($secondLeader->id, $followUp->fresh()->leader_member_id);
        $this->assertSame($originalDate, $followUp->fresh()->follow_up_date->toDateString());
    }

    public function test_reassignment_after_assignment_follow_up_is_completed_creates_a_new_pending_entry(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satellite = $this->satellite('Pasig', 'pasig', $admin);
        $firstLeader = $this->leader($satellite, 'First Leader');
        $secondLeader = $this->leader($satellite, 'Second Leader');
        $seeker = $this->seeker($satellite);

        $this->actingAs($admin)->patchJson(route('admin.seekers.assign-leader', $seeker), ['leader_id' => $firstLeader->id])->assertOk();
        FollowUp::where('origin', FollowUp::ORIGIN_LEADER_ASSIGNMENT)->update(['status' => FollowUp::STATUS_COMPLETED]);

        $this->patchJson(route('admin.seekers.assign-leader', $seeker), ['leader_id' => $secondLeader->id])->assertOk();

        $followUps = FollowUp::where('origin', FollowUp::ORIGIN_LEADER_ASSIGNMENT)->orderBy('id')->get();
        $this->assertCount(2, $followUps);
        $this->assertSame(FollowUp::STATUS_COMPLETED, $followUps[0]->status);
        $this->assertSame(FollowUp::STATUS_PENDING, $followUps[1]->status);
        $this->assertSame($secondLeader->id, $followUps[1]->leader_member_id);
    }

    public function test_assignment_rolls_back_when_follow_up_cannot_be_created(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satellite = $this->satellite('Pasig', 'pasig', $admin);
        $leader = $this->leader($satellite, 'Pasig Leader');
        $seeker = $this->seeker($satellite);
        DB::statement("CREATE TRIGGER reject_assignment_follow_up BEFORE INSERT ON follow_ups BEGIN SELECT RAISE(ABORT, 'Simulated follow-up failure'); END");

        $this->actingAs($admin)
            ->patchJson(route('admin.seekers.assign-leader', $seeker), ['leader_id' => $leader->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('leader_id');

        $this->assertNull($seeker->fresh()->dgroup_leader_name);
        $this->assertDatabaseCount('follow_ups', 0);
    }

    public function test_admin_can_assign_leader_and_admin_seeker_page_only_offers_that_satellites_leaders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $pasig = $this->satellite('Pasig', 'pasig', $admin);
        $anonas = $this->satellite('Anonas', 'anonas', $admin);
        $pasigLeader = $this->leader($pasig, 'Pasig Leader');
        $this->leader($anonas, 'Anonas Leader');
        $seeker = $this->seeker($pasig);

        $this->actingAs($admin)
            ->get(route('admin.seekers'))
            ->assertOk()
            ->assertSee('Assign DGroup Leader')
            ->assertSee('Pasig Leader')
            ->assertDontSee('Anonas Leader');

        $this->patchJson(route('admin.seekers.assign-leader', $seeker), ['leader_id' => $pasigLeader->id])
            ->assertOk()
            ->assertJsonPath('leader.name', 'Pasig Leader');

        $this->assertSame('Pasig Leader', $seeker->refresh()->dgroup_leader_name);
    }
}
