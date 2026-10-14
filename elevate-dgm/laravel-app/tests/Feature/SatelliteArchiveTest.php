<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\FollowUp;
use App\Models\Member;
use App\Models\OrgUnit;
use App\Models\Satellite;
use App\Models\Seeker;
use App\Models\UnitSnapshot;
use App\Models\User;
use App\Services\DashboardDirectoryService;
use App\Services\MovementAnalyticsCache;
use App\Services\MovementAnalyticsService;
use App\Services\MovementAnalyticsSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SatelliteArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_archive_preserves_history_hides_active_counts_and_exports_and_restore_returns_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satellite = Satellite::create(['name' => 'Plp', 'slug' => 'plp', 'hub' => 'MAIN']);
        $other = Satellite::create(['name' => 'Other', 'slug' => 'other', 'hub' => 'MAIN']);
        $member = Member::create(['name' => 'Person', 'satellite_id' => $satellite->id, 'status' => 'Active', 'dgroup_role' => Member::ROLE_LEADER]);
        $seeker = Seeker::create(['name' => 'Seeker', 'email' => 'seeker@example.test', 'assigned_satellite_slug' => 'plp']);
        $user = User::factory()->create(['role' => 'satellite', 'district' => 'Plp']);
        $follow = FollowUp::create(['member_id' => $member->id, 'satellite_id' => $satellite->id, 'follow_up_date' => now(), 'method' => 'Phone', 'status' => 'Pending', 'notes' => 'History', 'sentiment_label' => 'Positive']);
        $this->actingAs($admin)->get(route('admin.satellites'))->assertOk()->assertViewHas('openReports', 1)->assertSee('This satellite still has 1 members, 1 seekers, and 1 assigned users');
        $cache = app(MovementAnalyticsCache::class);
        $this->assertSame(['before'], $cache->remember(['archive-test'], fn () => ['before']));
        $this->deleteJson(route('admin.satellites.destroy', $satellite))->assertOk()->assertJsonPath('message', 'Satellite archived')->assertJsonPath('restore_url', route('admin.satellites.restore', 'plp'));
        $this->assertSame(['after'], $cache->remember(['archive-test'], fn () => ['after']));
        $this->assertDatabaseHas('satellites', ['id' => $satellite->id, 'archived_by' => $admin->id]);
        foreach ([$member, $seeker, $user, $follow] as $record) {
            $this->assertModelExists($record);
        }
        $this->assertEquals($satellite->id, $follow->fresh()->satellite->id);
        $this->assertSame(0, app(DashboardDirectoryService::class)->query('dgroup-leaders')->count());
        $this->get(route('admin.satellites'))->assertOk()->assertViewHas('totalSatellites', 1)->assertViewHas('activeMembers', 0)->assertViewHas('activeLeaders', 0)->assertViewHas('openReports', 0)->assertDontSee('Actions for Plp');
        $this->get(route('admin.analytics'))->assertOk()->assertViewHas('activeSatelliteCounts', fn ($counts) => $counts['activeMembers'] === 0 && $counts['totalSatellites'] === 1);
        $this->get(route('admin.members'))->assertOk()->assertViewHas('totalMembers', 0)->assertViewHas('totalLeaders', 0);
        $this->assertStringNotContainsString('Plp', $this->get(route('admin.satellites.export', ['view' => 'archived']))->streamedContent());
        $this->get(route('admin.users'))->assertOk()->assertViewHas('districts', fn ($districts) => ! $districts->contains('Plp'));
        $this->get(route('admin.seekers'))->assertOk()->assertViewHas('districtOptions', fn ($districts) => ! in_array('Plp', $districts))->assertViewHas('leaderOptionsBySatellite', fn ($options) => ! isset($options['plp']));
        $this->get(route('admin.satellites', ['view' => 'archived']))->assertOk()->assertSee('Archived on')->assertSee('Restore')->assertDontSee('Archive satellite</button>', false);
        $this->get(route('admin.satellites.archived-details', 'plp'))->assertOk()->assertSee('read-only');
        $this->get(route('satellites.dashboard', $satellite))->assertNotFound();
        $this->actingAs($user)->get(route('satellites.members', $satellite))->assertNotFound();
        $this->assertTrue($user->hasArchivedSatellite());
        $this->actingAs($admin)->postJson(route('admin.satellites.restore', 'plp'))->assertOk();
        $this->assertNull($satellite->fresh()->archived_at);
        $this->get(route('admin.satellites'))->assertViewHas('activeMembers', 1)->assertViewHas('activeLeaders', 1)->assertViewHas('openReports', 1);
        $this->actingAs($user)->get(route('satellites.members', $satellite))->assertOk();
    }

    public function test_policy_and_routes_reject_non_admin_archive_and_restore(): void
    {
        $satellite = Satellite::create(['name' => 'Plp', 'slug' => 'plp']);
        $user = User::factory()->create(['role' => 'satellite']);
        $this->actingAs($user)->deleteJson(route('admin.satellites.destroy', $satellite))->assertForbidden();
        $this->assertFalse(Gate::forUser($user)->allows('archive', $satellite));
        $satellite->archive();
        $this->postJson(route('admin.satellites.restore', 'plp'))->assertForbidden();
        $this->assertFalse(Gate::forUser($user)->allows('restore', $satellite));
    }

    public function test_archived_name_and_slug_stay_reserved_and_model_delete_archives(): void
    {
        $satellite = Satellite::create(['name' => 'Plp', 'slug' => 'plp']);
        $satellite->delete();
        $this->assertNotNull($satellite->fresh()->archived_at);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.satellites.store'), ['name' => 'Plp', 'hub' => 'MAIN'])->assertSessionHasErrors('name');
        $this->post(route('admin.satellites.store'), ['name' => 'PLP', 'hub' => 'MAIN']);
        $this->assertDatabaseHas('satellites', ['id' => $satellite->id, 'slug' => 'plp']);
    }

    public function test_archived_satellite_blocks_new_and_pending_otp_signins(): void
    {
        Mail::fake();
        $satellite = Satellite::create(['name' => 'Plp', 'slug' => 'plp']);
        $user = User::factory()->create(['role' => 'satellite', 'district' => 'Plp']);
        $satellite->archive();

        // Email submission succeeds generically (prevents account enumeration)
        $this->post(route('login.email'), ['email' => $user->email])->assertSessionHasNoErrors();
        $code = null;
        Mail::assertSent(\App\Mail\OtpMail::class, function (\App\Mail\OtpMail $mail) use (&$code) {
            $code = $mail->otp;
            return true;
        });

        // Verification blocks sign-in and reports archived satellite error
        $this->post(route('login.otp'), ['otp' => $code])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'This satellite is archived. Contact an administrator.']);
        $this->assertGuest();
    }

    public function test_active_analytics_excludes_archive_but_saved_report_and_sentiment_survive(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 13));
        $satellite = Satellite::create(['name' => 'Pasig', 'slug' => 'pasig', 'hub' => 'MAIN']);
        app(MovementAnalyticsSetup::class)->run();
        $unit = OrgUnit::where('key', 'main.pasig')->firstOrFail();
        $checkpoint = Checkpoint::where('key', '2026-06')->firstOrFail();
        $states = array_fill_keys(config('movement_analytics.metrics'), 'reported');
        UnitSnapshot::create(['unit_id' => $unit->id, 'checkpoint_id' => $checkpoint->id, 'source' => 'legacy_import', 'dmembers' => 9, 'dleaders' => 2, 'dgroups' => 2, 'metric_states' => $states, 'quality_flags' => []]);
        $member = Member::create(['name' => 'History', 'satellite_id' => $satellite->id, 'status' => 'Active']);
        FollowUp::create(['member_id' => $member->id, 'satellite_id' => $satellite->id, 'follow_up_date' => now(), 'method' => 'Phone', 'status' => 'Completed', 'sentiment_label' => 'Positive']);
        $service = app(MovementAnalyticsService::class);
        $before = $service->report('2026-06', mode: 'reported');
        $satellite->archive();
        $after = $service->report('2026-06', mode: 'reported');
        $this->assertEquals($before['periods'], $after['periods']);
        $dashboard = $service->dashboard('2026-06', mode: 'live');
        $this->assertNotContains($unit->id, array_column($dashboard['units'], 'id'));
        $this->assertSame(0, $dashboard['activity']['engagement']['total']);
        $this->assertSame(1, $dashboard['activity']['sentiment']['Positive']);
        $this->assertStringContainsString('archived', $dashboard['historical_sentiment_note']);
        $satellite->restore();
        $this->assertContains($unit->id, array_column($service->dashboard('2026-06', mode: 'live')['units'], 'id'));
    }

    public function test_bulk_eloquent_removal_also_archives_without_deleting(): void
    {
        $satellite = Satellite::create(['name' => 'Retained', 'slug' => 'retained']);
        $this->assertSame(1, Satellite::whereKey($satellite->id)->delete());
        $this->assertNotNull($satellite->fresh()->archived_at);
        Satellite::archived()->whereKey($satellite->id)->forceDelete();
        $this->assertDatabaseHas('satellites', ['id' => $satellite->id]);
    }
}
