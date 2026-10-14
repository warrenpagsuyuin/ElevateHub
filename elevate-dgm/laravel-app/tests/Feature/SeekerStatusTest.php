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

class SeekerStatusTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $satellite = Satellite::create(['name' => 'Pasig', 'slug' => 'pasig']);
        $actor = User::factory()->create(['role' => 'satellite', 'district' => 'Pasig']);
        $leader = Member::create(['satellite_id' => $satellite->id, 'name' => 'DLeader Example', 'full_name' => 'DLeader Example', 'dgroup_role' => Member::ROLE_LEADER]);
        $seeker = Seeker::create([
            'name' => 'Seeker Example', 'first_name' => 'Seeker', 'last_name' => 'Example',
            'email' => 'seeker@example.test', 'contact_number' => '09123456789',
            'facebook_link' => 'https://facebook.com/example', 'age' => 21, 'gender' => 'Female',
            'school' => 'Example School', 'assigned_satellite_slug' => 'pasig', 'assigned_district' => 'Pasig',
            'dgroup_leader_name' => $leader->display_name, 'status' => Seeker::STATUS_PENDING,
            'year_joined' => 2000, 'ministry_involvement' => 'Do not copy', 'refresh_date' => '2026-01-01',
        ]);

        return [$satellite, $actor, $seeker, $leader];
    }

    public function test_successful_conversion_maps_fields_is_idempotent_and_updates_members(): void
    {
        [$satellite, $actor, $seeker, $leader] = $this->fixture();
        $url = route('satellites.seekers.status', [$satellite, $seeker]);
        $this->actingAs($actor)->patchJson($url, ['status' => 'successful'])->assertOk()
            ->assertJsonPath('message', 'Moved to Members')->assertJsonPath('counts.active', 0)->assertJsonPath('counts.members', 2);
        $member = Member::findOrFail($seeker->refresh()->converted_member_id);
        $this->assertSame($satellite->id, $member->satellite_id);
        foreach (['name' => 'name', 'email_address' => 'email', 'contact_number' => 'contact_number', 'facebook_link' => 'facebook_link', 'age' => 'age', 'gender' => 'gender', 'campus_school' => 'school'] as $target => $source) {
            $this->assertSame($seeker->$source, $member->$target);
        }
        $this->assertSame($leader->id, $member->leader_member_id);
        foreach (['year_joined', 'ministry_involvement', 'refresh_date', 'weekly_dgroups_led', 'generation'] as $field) {
            $this->assertNull($member->$field);
        }
        $this->assertSame(Member::STATUS_NEW, $member->status);
        $this->assertNotNull($seeker->converted_at);
        $this->assertNotNull($seeker->archived_at);
        $this->assertSame(2, $satellite->fresh()->members_count);
        $this->assertSame(1, $satellite->fresh()->new_members_count);
        $stamp = $seeker->converted_at->toDateTimeString();
        $this->patchJson($url, ['status' => 'Successful'])->assertOk();
        $this->assertDatabaseCount('members', 2);
        $this->assertDatabaseCount('seeker_archive_log', 1);
        $this->assertSame($stamp, $seeker->fresh()->converted_at->toDateTimeString());
        $this->get(route('satellites.members', $satellite))->assertOk()->assertSee('Seeker Example');
        $this->get(route('satellites.seekers', $satellite))->assertOk()->assertDontSee('Seeker Example');
        $this->postJson(route('satellites.seekers.restore', [$satellite, $seeker]))->assertUnprocessable();
    }

    public function test_duplicate_email_links_existing_member_without_overwriting_it(): void
    {
        [$satellite, $actor, $seeker, $leader] = $this->fixture();
        $member = Member::create(['satellite_id' => $satellite->id, 'name' => 'Existing Name', 'email_address' => 'SEEKER@EXAMPLE.TEST', 'status' => Member::STATUS_ACTIVE]);
        $before = $member->fresh()->getAttributes();
        $this->actingAs($actor)->patchJson(route('satellites.seekers.status', [$satellite, $seeker]), ['status' => 'Successful'])
            ->assertOk()->assertJsonPath('linked_existing', true)->assertJsonPath('member_id', $member->id);
        $this->assertEquals($before, $member->fresh()->getAttributes());
        $this->assertDatabaseCount('members', 2);
        $other = Seeker::create(['name' => 'Second seeker', 'email' => 'seeker@example.test', 'assigned_satellite_slug' => 'pasig', 'dgroup_leader_name' => $leader->display_name, 'status' => 'Pending']);
        $this->patchJson(route('satellites.seekers.status', [$satellite, $other]), ['status' => 'Successful'])->assertOk()->assertJsonPath('member_id', $member->id);
        $this->assertDatabaseCount('members', 2);
    }

    public function test_email_match_in_another_satellite_does_not_link(): void
    {
        [$satellite, $actor, $seeker] = $this->fixture();
        $other = Satellite::create(['name' => 'Anonas', 'slug' => 'anonas']);
        Member::create(['satellite_id' => $other->id, 'name' => 'Other Member', 'email' => $seeker->email]);
        $this->actingAs($actor)->patchJson(route('satellites.seekers.status', [$satellite, $seeker]), ['status' => 'Successful'])
            ->assertOk()->assertJsonPath('linked_existing', false);
        $this->assertSame($satellite->id, Member::findOrFail($seeker->fresh()->converted_member_id)->satellite_id);
    }

    public function test_final_status_requires_a_leader_and_pending_is_always_allowed(): void
    {
        [$satellite, $actor, $seeker] = $this->fixture();
        $seeker->update(['dgroup_leader_name' => null]);

        $this->actingAs($actor)
            ->patchJson(route('satellites.seekers.status', [$satellite, $seeker]), ['status' => 'Unsuccessful'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'Assign a DGroup Leader to Seeker Example before marking them as successful or unsuccessful.');
        $this->assertSame(Seeker::STATUS_PENDING, $seeker->fresh()->status);

        $this->patchJson(route('satellites.seekers.status', [$satellite, $seeker]), ['status' => 'Pending'])
            ->assertOk()->assertJsonPath('status', Seeker::STATUS_PENDING);
    }

    public function test_satellite_status_dropdown_disables_final_options_without_a_leader(): void
    {
        [$satellite, $actor, $seeker] = $this->fixture();
        $seeker->update(['dgroup_leader_name' => null]);

        $response = $this->actingAs($actor)->get(route('satellites.seekers', $satellite))->assertOk();
        $this->assertStringContainsString('Assign a DGroup Leader first', $response->getContent());
        $this->assertStringContainsString('aria-describedby="status-hint-'.$seeker->id.'"', $response->getContent());
        $this->assertMatchesRegularExpression(
            '/<span class="status-control status-pending"[^>]*>\s*<select[^>]+data-status-select[^>]*>.*?<\/select>\s*<\/span>\s*<\/form>\s*<p class="status-leader-help[^"]*" id="status-hint-'.$seeker->id.'"/s',
            $response->getContent()
        );
        $this->assertMatchesRegularExpression('/<option value="Successful"[^>]*disabled/', $response->getContent());
        $this->assertMatchesRegularExpression('/<option value="Unsuccessful"[^>]*disabled/', $response->getContent());
        $this->assertMatchesRegularExpression('/<option value="Pending"[^>]*selected[^>]*>/', $response->getContent());
    }

    public function test_inactive_or_removed_leader_does_not_satisfy_final_status_requirement(): void
    {
        [$satellite, $actor, $seeker, $leader] = $this->fixture();
        $leader->update(['status' => Member::STATUS_INACTIVE]);

        $this->actingAs($actor)
            ->patchJson(route('satellites.seekers.status', [$satellite, $seeker]), ['status' => 'Successful'])
            ->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->assertSame(Seeker::STATUS_PENDING, $seeker->fresh()->status);
        $this->assertDatabaseCount('members', 1);
    }

    public function test_leader_archived_after_the_page_loaded_does_not_satisfy_final_status_requirement(): void
    {
        [$satellite, $actor, $seeker, $leader] = $this->fixture();

        // The status service re-queries the leader in its transaction rather than
        // trusting the leader name rendered into a previously opened page.
        $leader->update(['archived_at' => now()]);

        $this->actingAs($actor)
            ->patchJson(route('satellites.seekers.status', [$satellite, $seeker]), ['status' => 'Successful'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Assign a DGroup Leader to Seeker Example before marking them as successful or unsuccessful.');

        $this->assertSame(Seeker::STATUS_PENDING, $seeker->fresh()->status);
        $this->assertDatabaseCount('members', 1);
    }

    public function test_leader_from_another_satellite_does_not_satisfy_final_status_requirement(): void
    {
        [$satellite, $actor, $seeker] = $this->fixture();
        $otherSatellite = Satellite::create(['name' => 'Anonas', 'slug' => 'anonas']);
        $foreignLeader = Member::create([
            'satellite_id' => $otherSatellite->id,
            'name' => 'Foreign Leader',
            'full_name' => 'Foreign Leader',
            'dgroup_role' => Member::ROLE_LEADER,
        ]);
        $seeker->update(['dgroup_leader_name' => $foreignLeader->display_name]);

        $this->actingAs($actor)
            ->patchJson(route('satellites.seekers.status', [$satellite, $seeker]), ['status' => 'Unsuccessful'])
            ->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->assertSame(Seeker::STATUS_PENDING, $seeker->fresh()->status);
    }

    public function test_admin_status_route_uses_the_same_leader_requirement(): void
    {
        [, , $seeker] = $this->fixture();
        $seeker->update(['dgroup_leader_name' => null]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patchJson(route('admin.seekers.status', $seeker), ['status' => 'Successful'])
            ->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->assertSame(Seeker::STATUS_PENDING, $seeker->fresh()->status);
    }

    public function test_admin_actions_disable_final_statuses_without_a_valid_leader(): void
    {
        [, , $seeker] = $this->fixture();
        $seeker->update(['dgroup_leader_name' => null]);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.seekers'))
            ->assertOk();

        $this->assertStringContainsString('Assign a DGroup Leader first', $response->getContent());
        $this->assertMatchesRegularExpression('/aria-label="Mark Successful: Seeker Example"[^>]*disabled/', $response->getContent());
        $this->assertMatchesRegularExpression('/aria-label="Mark Unsuccessful: Seeker Example"[^>]*disabled/', $response->getContent());
    }

    public function test_unsuccessful_archives_hides_from_active_counts_notifications_export_and_restores(): void
    {
        [$satellite, $actor, $seeker] = $this->fixture();
        $followUp = FollowUp::create(['seeker_id' => $seeker->id, 'satellite_id' => $satellite->id, 'method' => 'Phone', 'status' => 'Completed', 'notes' => 'Retained history']);
        $this->actingAs($actor)->patchJson(route('satellites.seekers.status', [$satellite, $seeker]), ['status' => 'unsuccessful'])
            ->assertOk()->assertJsonPath('message', 'Seeker archived')->assertJsonPath('counts.pending', 0)->assertJsonPath('counts.archived', 1);
        $this->get(route('satellites.seekers', $satellite))->assertOk()->assertDontSee('Seeker Example')
            ->assertViewHas('activeCount', 0)->assertViewHas('notificationCount', 0);
        $this->get(route('satellites.seekers', [$satellite, 'view' => 'archive']))->assertOk()->assertSee('Seeker Example')->assertSee('Restore to Pending');
        $this->assertDatabaseHas('follow_ups', ['id' => $followUp->id, 'notes' => 'Retained history']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.seekers', ['search' => 'Seeker Example']))->assertOk()->assertViewHas('seekers', fn ($rows) => $rows->total() === 0);
        $this->assertStringNotContainsString('seeker@example.test', $this->get(route('admin.seekers.export'))->streamedContent());
        $this->actingAs($actor)->postJson(route('satellites.seekers.restore', [$satellite, $seeker]))
            ->assertOk()->assertJsonPath('counts.active', 1)->assertJsonPath('status', 'Pending');
        $this->assertNull($seeker->fresh()->archived_at);
        $this->assertSame(1, $satellite->fresh()->seekers_count);
        $this->get(route('satellites.seekers', $satellite))->assertSee('Seeker Example');
    }

    public function test_scoping_invalid_status_noop_and_admin_access(): void
    {
        [$satellite, $actor, $seeker] = $this->fixture();
        $other = Satellite::create(['name' => 'Anonas', 'slug' => 'anonas']);
        $foreign = Seeker::create(['name' => 'Other', 'email' => 'other@example.test', 'assigned_satellite_slug' => 'anonas']);
        $otherLeader = Member::create(['satellite_id' => $other->id, 'name' => 'Anonas Leader', 'full_name' => 'Anonas Leader', 'dgroup_role' => Member::ROLE_LEADER]);
        $foreign->update(['dgroup_leader_name' => $otherLeader->display_name]);
        $this->actingAs($actor)->patchJson(route('satellites.seekers.status', [$satellite, $foreign]), ['status' => 'Successful'])->assertNotFound();
        $this->patchJson(route('satellites.seekers.status', [$other, $foreign]), ['status' => 'Successful'])->assertForbidden();
        $this->postJson(route('satellites.seekers.restore', [$satellite, $foreign]))->assertNotFound();
        $this->patchJson(route('satellites.seekers.status', [$satellite, $seeker]), ['status' => 'not successful'])->assertUnprocessable();
        $before = $seeker->fresh()->getAttributes();
        $this->patchJson(route('satellites.seekers.status', [$satellite, $seeker]), ['status' => 'Pending'])->assertOk()->assertJsonPath('message', 'Status unchanged');
        $this->assertEquals($before, $seeker->fresh()->getAttributes());
        $this->assertDatabaseCount('seeker_archive_log', 0);
        foreach (['member'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'district' => 'Pasig']))
                ->patchJson(route('satellites.seekers.status', [$satellite, $seeker]), ['status' => 'Successful'])->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patchJson(route('satellites.seekers.status', [$other, $foreign]), ['status' => 'Successful'])->assertOk();
        $this->patchJson(route('admin.seekers.status', $seeker), ['status' => 'Successful'])->assertOk();
        $this->assertNotNull($seeker->fresh()->converted_member_id);
    }

    public function test_assigned_volunteer_can_change_status_but_cannot_change_another_satellites_seeker(): void
    {
        [$satellite, , $seeker] = $this->fixture();
        $volunteer = User::factory()->create(['role' => 'volunteer', 'district' => 'Pasig']);
        $foreignSatellite = Satellite::create(['name' => 'Anonas', 'slug' => 'anonas']);
        $foreignSeeker = Seeker::create([
            'name' => 'Foreign Seeker', 'email' => 'foreign@example.test',
            'assigned_satellite_slug' => $foreignSatellite->slug,
            'status' => Seeker::STATUS_PENDING,
        ]);

        $this->assertTrue($volunteer->fresh()->canChangeSeekerStatus($satellite));
        $this->actingAs($volunteer)->get(route('satellites.seekers', $satellite))
            ->assertOk()->assertViewHas('canChangeSeekerStatus', true);

        $this->actingAs($volunteer)
            ->patchJson(route('satellites.seekers.status', [$satellite, $seeker]), ['status' => 'Unsuccessful'])
            ->assertOk()->assertJsonPath('status', 'Unsuccessful');
        $this->assertNotNull($seeker->fresh()->archived_at);

        $this->patchJson(route('satellites.seekers.status', [$satellite, $foreignSeeker]), ['status' => 'Unsuccessful'])
            ->assertNotFound();
        $this->assertSame(Seeker::STATUS_PENDING, $foreignSeeker->fresh()->status);
    }

    public function test_member_creation_failure_rolls_back_the_entire_transition(): void
    {
        [$satellite, $actor, $seeker] = $this->fixture();
        $before = $seeker->fresh()->getAttributes();
        $counts = $satellite->fresh()->getAttributes();
        DB::statement("CREATE TRIGGER reject_member BEFORE INSERT ON members BEGIN SELECT RAISE(ABORT, 'Simulated member failure'); END");
        $this->actingAs($actor)->patchJson(route('satellites.seekers.status', [$satellite, $seeker]), ['status' => 'Successful'])->assertStatus(500);
        $this->assertEquals($before, $seeker->fresh()->getAttributes());
        $this->assertEquals($counts, $satellite->fresh()->getAttributes());
        $this->assertDatabaseCount('seeker_archive_log', 0);
        $this->assertDatabaseCount('members', 1);
    }

    public function test_archive_failure_rolls_back_new_member_and_counts(): void
    {
        [$satellite, $actor, $seeker] = $this->fixture();
        $before = $seeker->fresh()->getAttributes();
        $counts = $satellite->fresh()->getAttributes();
        DB::statement("CREATE TRIGGER reject_status_archive BEFORE INSERT ON seeker_archive_log BEGIN SELECT RAISE(ABORT, 'Simulated archive failure'); END");
        $this->actingAs($actor)->patchJson(route('satellites.seekers.status', [$satellite, $seeker]), ['status' => 'Successful'])->assertStatus(500);
        $this->assertEquals($before, $seeker->fresh()->getAttributes());
        $this->assertEquals($counts, $satellite->fresh()->getAttributes());
        $this->assertDatabaseCount('members', 1);
    }
}
