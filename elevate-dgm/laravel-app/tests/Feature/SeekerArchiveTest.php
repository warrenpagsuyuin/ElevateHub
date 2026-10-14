<?php

namespace Tests\Feature;

use App\Models\FollowUp;
use App\Models\Member;
use App\Models\Satellite;
use App\Models\Seeker;
use App\Models\SeekerArchiveLog;
use App\Models\User;
use App\Services\MovementAnalyticsService;
use App\Services\MovementAnalyticsSetup;
use App\Services\SeekerArchiveService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SeekerArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function seeker(array $values = []): Seeker
    {
        return Seeker::create(array_merge(['name' => 'Archive Example', 'email' => 'archive@example.test', 'status' => 'Pending', 'school' => 'Example School'], $values));
    }

    public function test_final_status_archives_atomically_and_default_view_hides_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satellite = Satellite::create(['name' => 'Pasig', 'slug' => 'pasig']);
        $leader = Member::create(['satellite_id' => $satellite->id, 'name' => 'Leader Example', 'full_name' => 'Leader Example', 'dgroup_role' => Member::ROLE_LEADER]);
        $seeker = $this->seeker(['assigned_district' => 'Pasig', 'assigned_satellite_slug' => 'pasig', 'dgroup_leader_name' => $leader->display_name]);
        $this->actingAs($admin)->patch(route('admin.seekers.status', $seeker), ['status' => 'Successful'])
            ->assertRedirect()->assertSessionMissing('archive_undo');
        $this->assertNotNull($seeker->refresh()->archived_at);
        $this->assertSame('Successful', $seeker->status);
        $this->assertSame('Pasig', $seeker->assigned_district);
        $this->assertDatabaseHas('seeker_archive_log', ['seeker_id' => $seeker->id, 'previous_status' => 'Pending', 'action' => 'archived', 'actor_id' => $admin->id]);
        $this->get(route('admin.seekers'))->assertOk()->assertViewHas('seekers', fn ($rows) => $rows->total() === 0)->assertSee('Active (0)')->assertSee('Archived (1)');
        $this->get(route('admin.seekers', ['view' => 'archive']))->assertOk()->assertSee('Archive Example')->assertSee('Final status');
        $this->patch(route('admin.seekers.status', $seeker), ['status' => 'Successful'])->assertRedirect();
        $this->assertDatabaseCount('seeker_archive_log', 1);
    }

    public function test_non_final_status_stays_active_and_restores_archived_record(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $satellite = Satellite::create(['name' => 'Pasig', 'slug' => 'pasig']);
        $leader = Member::create(['satellite_id' => $satellite->id, 'name' => 'Leader Example', 'full_name' => 'Leader Example', 'dgroup_role' => Member::ROLE_LEADER]);
        $seeker = $this->seeker(['assigned_satellite_slug' => 'pasig', 'dgroup_leader_name' => $leader->display_name]);
        $this->patch(route('admin.seekers.status', $seeker), ['status' => 'Pending'])->assertRedirect();
        $this->assertNull($seeker->refresh()->archived_at);
        $this->patch(route('admin.seekers.status', $seeker), ['status' => 'Unsuccessful'])->assertRedirect();
        $this->patch(route('admin.seekers.status', $seeker), ['status' => 'Pending'])->assertSessionHasErrors('status');
        $this->post(route('seekers.restore', $seeker), ['status' => 'Pending'])->assertRedirect();
        $this->assertNull($seeker->refresh()->archived_at);
        $this->assertDatabaseHas('seeker_archive_log', ['seeker_id' => $seeker->id, 'action' => 'restored', 'previous_status' => 'Unsuccessful']);
    }

    public function test_manual_archive_requires_reason_and_restore_requires_final_status_acknowledgement(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $seeker = $this->seeker(['status' => 'Successful']);
        $this->post(route('seekers.archive', $seeker))->assertSessionHasErrors('note');
        $this->post(route('seekers.archive', $seeker), ['note' => 'Follow-up complete.'])->assertRedirect();
        $this->assertSame('manual', $seeker->refresh()->archive_reason);
        $this->post(route('seekers.restore', $seeker))->assertSessionHasErrors('status');
        $this->assertNotNull($seeker->refresh()->archived_at);
        $this->post(route('seekers.restore', $seeker), ['keep_final' => 1, 'note' => 'Reopened intentionally.'])->assertSessionHasErrors('status');
        $this->post(route('seekers.restore', $seeker), ['status' => 'Pending', 'note' => 'Reopened as pending.'])->assertRedirect();
        $this->assertNull($seeker->refresh()->archived_at);
        $this->assertSame('Pending', $seeker->status);
        $this->assertDatabaseCount('seeker_archive_log', 2);
        $this->get(route('admin.seekers'))->assertSee('Archive Example');
    }

    public function test_undo_is_session_bound_one_use_and_rejects_stale_archive(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $seeker = $this->seeker();
        $this->post(route('seekers.archive', $seeker), ['note' => 'Completed.'])->assertRedirect();
        $token = session('archive_undo');
        $this->post(route('seekers.archive.undo'), ['token' => 'forged'])->assertStatus(409);
        $this->post(route('seekers.archive.undo'), ['token' => $token])->assertRedirect();
        $this->assertNull($seeker->refresh()->archived_at);
        $this->post(route('seekers.archive.undo'), ['token' => $token])->assertStatus(409);
        $this->post(route('seekers.archive', $seeker), ['note' => 'Again.'])->assertRedirect();
        $stale = session('archive_undo');
        $this->post(route('seekers.restore', $seeker))->assertRedirect();
        $this->post(route('seekers.archive', $seeker), ['note' => 'New action.'])->assertRedirect();
        $this->post(route('seekers.archive.undo'), ['token' => $stale])->assertStatus(409);
        $this->assertNotNull($seeker->refresh()->archived_at);
    }

    public function test_bulk_restore_and_archive_are_atomic_and_idempotent(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $a = $this->seeker();
        $b = $this->seeker(['name' => 'Final Example', 'status' => 'Successful']);
        $ids = [$a->id, $b->id];
        $this->post(route('seekers.archive.bulk'), ['action' => 'archive', 'ids' => $ids, 'note' => 'Batch completed.'])->assertRedirect();
        $this->post(route('seekers.archive.bulk'), ['action' => 'archive', 'ids' => $ids, 'note' => 'Batch completed.'])->assertRedirect();
        $this->assertDatabaseCount('seeker_archive_log', 2);
        $this->post(route('seekers.archive.bulk'), ['action' => 'restore', 'ids' => $ids])->assertSessionHasErrors('status');
        $this->assertSame(2, Seeker::whereNotNull('archived_at')->count());
        $this->post(route('seekers.archive.bulk'), ['action' => 'restore', 'ids' => $ids, 'status' => 'Pending'])->assertRedirect();
        $this->assertSame(0, Seeker::whereNotNull('archived_at')->count());
        $this->assertDatabaseCount('seeker_archive_log', 4);
    }

    public function test_roles_and_leader_scope_are_enforced_for_archive_restore_and_status(): void
    {
        Satellite::create(['name' => 'Pasig', 'slug' => 'pasig']);
        Satellite::create(['name' => 'Anonas', 'slug' => 'anonas']);
        $seeker = $this->seeker(['assigned_satellite_slug' => 'pasig']);
        foreach (['member', 'staff', 'district', 'satellite'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'district' => 'Pasig']))
                ->post(route('seekers.archive', $seeker), ['note' => 'Test'])->assertForbidden();
            $this->post(route('seekers.restore', $seeker), ['status' => 'Pending'])->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'leader', 'district' => 'Anonas']))
            ->post(route('seekers.archive', $seeker), ['note' => 'Test'])->assertForbidden();
        $leader = User::factory()->create(['role' => 'leader', 'district' => 'Pasig']);
        $this->actingAs($leader)->post(route('seekers.archive', $seeker), ['note' => 'Test'])->assertRedirect();
        $this->get(route('satellites.seekers', [Satellite::where('slug', 'pasig')->first(), 'view' => 'archive']))->assertOk()->assertSee('Archive Example');
        $this->post(route('seekers.restore', $seeker), ['status' => 'Pending'])->assertRedirect();
    }

    public function test_archive_filters_pagination_and_historical_export(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->seeker(['name' => 'Active Example']);
        for ($i = 0; $i < 12; $i++) {
            $this->seeker(['name' => 'History '.str_pad($i, 2, '0', STR_PAD_LEFT), 'archived_at' => '2026-09-01 10:00:00', 'status' => 'Successful', 'assigned_district' => 'Pasig']);
        }
        $params = ['view' => 'archive', 'search' => 'History', 'school' => 'Example', 'district' => 'Pasig', 'status' => 'Successful', 'archived_from' => '2026-09-01', 'archived_to' => '2026-09-02'];
        $this->get(route('admin.seekers', $params))->assertOk()->assertSee('History 00')->assertDontSee('History 11')->assertDontSee('Active Example');
        $this->get(route('admin.seekers', $params + ['page' => 2]))->assertOk()->assertSee('History 11');
        $csv = $this->get(route('admin.seekers.export', $params))->assertOk()->streamedContent();
        $this->assertStringContainsString('History 11', $csv);
        $this->assertStringContainsString('Archived on', $csv);
        $this->assertStringNotContainsString('Active Example', $csv);
        $all = $this->get(route('admin.seekers.export', ['view' => 'all']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Active Example', $all);
        $this->assertStringContainsString('History 11', $all);
        $this->get(route('admin.seekers', ['view' => 'archive', 'archived_to' => '2026-01-01']))->assertOk()->assertDontSee('History 00');
    }

    public function test_archive_preserves_pipeline_rate_followup_sentiment_and_placement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $satellite = Satellite::create(['name' => 'Pasig', 'slug' => 'pasig', 'hub' => 'MAIN']);
        app(MovementAnalyticsSetup::class)->run();
        $member = Member::create(['name' => 'Member', 'satellite_id' => $satellite->id]);
        $seeker = $this->seeker(['assigned_satellite_slug' => 'pasig', 'converted_member_id' => $member->id]);
        $this->seeker();
        FollowUp::create(['seeker_id' => $seeker->id, 'satellite_id' => $satellite->id, 'method' => 'Phone', 'status' => 'Completed', 'sentiment_label' => 'Positive']);
        $report = fn () => app(MovementAnalyticsService::class)->dashboard('2026-06', mode: 'reported')['activity'];
        $before = $report();
        app(SeekerArchiveService::class)->archive($seeker, $admin, 'Completed');
        $after = $report();
        foreach (['total', 'placed', 'non_placed', 'unknown', 'rate'] as $key) {
            $this->assertSame($before['pipeline'][$key], $after['pipeline'][$key]);
        }
        $this->assertSame(1, $after['pipeline']['archived']);
        $this->assertSame($before['sentiment'], $after['sentiment']);
        $this->assertSame($before['engagement'], $after['engagement']);
        $this->assertSame($member->id, $seeker->refresh()->converted_member_id);
        $this->assertDatabaseCount('follow_ups', 1);
    }

    public function test_archived_seekers_cannot_be_assigned_or_added_to_followup_queue(): void
    {
        $satellite = Satellite::create(['name' => 'Pasig', 'slug' => 'pasig']);
        $seeker = $this->seeker(['assigned_satellite_slug' => 'pasig', 'archived_at' => now()]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.seekers.assign-district', $seeker), ['assigned_district' => 'Anonas'])->assertStatus(422);
        $this->actingAs(User::factory()->create(['role' => 'leader', 'district' => 'Pasig']));
        $this->get(route('satellites.seekers', $satellite))->assertOk()->assertDontSee('Archive Example');
        $this->post(route('satellites.follow-ups.store', $satellite), ['seeker_id' => $seeker->id, 'method' => 'Phone', 'status' => 'Completed'])->assertStatus(422);
        $this->assertDatabaseCount('follow_ups', 0);
    }

    public function test_sync_preserves_archived_fields_and_explicit_stay_active_restoration(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $satellite = Satellite::create(['name' => 'Pasig', 'slug' => 'pasig']);
        $leader = Member::create(['satellite_id' => $satellite->id, 'name' => 'Leader Example', 'full_name' => 'Leader Example', 'dgroup_role' => Member::ROLE_LEADER]);
        config(['services.google_sheets.seekers_csv_url' => 'https://example.test/seekers.csv']);
        Http::fake(['https://example.test/seekers.csv' => Http::response("Name,Email,School,Assigned District,DGroup Leader,Status\nSync Example,sync@example.test,Example,Pasig,Leader Example,Successful\n", 200)]);
        $this->post(route('admin.seekers.sync-google-sheet'))->assertRedirect()->assertSessionMissing('error');
        $seeker = Seeker::where('email', 'sync@example.test')->firstOrFail();
        $this->assertNotNull($seeker->archived_at);
        $original = $seeker->getAttributes();
        Http::fake(['https://example.test/seekers.csv' => Http::response("Name,Email,School,Assigned District,DGroup Leader,Status\nChanged,sync@example.test,Other,Anonas,Leader Example,Pending\n", 200)]);
        $this->post(route('admin.seekers.sync-google-sheet'))->assertSessionMissing('error');
        $this->assertSame($original, $seeker->fresh()->getAttributes());
        $this->post(route('seekers.restore', $seeker), ['keep_final' => 1])->assertRedirect();
        Http::fake(['https://example.test/seekers.csv' => Http::response("Name,Email,School,Assigned District,DGroup Leader,Status\nSync Example,sync@example.test,Example,Pasig,Leader Example,Successful\n", 200)]);
        $this->post(route('admin.seekers.sync-google-sheet'))->assertSessionMissing('error');
        $this->assertNull($seeker->fresh()->archived_at);
    }

    public function test_assigned_staff_can_convert_through_scoped_status_route(): void
    {
        $satellite = Satellite::create(['name' => 'Pasig', 'slug' => 'pasig']);
        $leader = Member::create(['satellite_id' => $satellite->id, 'name' => 'Leader Example', 'full_name' => 'Leader Example', 'dgroup_role' => Member::ROLE_LEADER]);
        $seeker = $this->seeker(['assigned_satellite_slug' => 'pasig', 'dgroup_leader_name' => $leader->display_name]);
        $this->actingAs(User::factory()->create(['role' => 'staff', 'district' => 'Pasig']))
            ->patch(route('satellites.seekers.status', [$satellite, $seeker]), ['status' => 'Successful'])->assertRedirect();
        $this->assertSame('Successful', $seeker->fresh()->status);
        $this->assertNotNull($seeker->fresh()->archived_at);
        $this->assertNotNull($seeker->fresh()->converted_member_id);
        $this->assertDatabaseCount('seeker_archive_log', 1);
        $this->get(route('satellites.seekers', [$satellite, 'view' => 'archive']))->assertOk()->assertSee('Archive Example');
    }

    public function test_archiving_does_not_delete_or_edit_audit_history(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $seeker = $this->seeker();
        $service = app(SeekerArchiveService::class);
        $service->archive($seeker, $actor, 'Initial reason');
        $log = DB::table('seeker_archive_log')->first();
        $service->restore($seeker, $actor, 'Pending', false, 'Reopened');
        $service->archive($seeker, $actor, 'Second reason');
        $this->assertEquals($log, DB::table('seeker_archive_log')->where('id', $log->id)->first());
        $this->assertDatabaseCount('seeker_archive_log', 3);
        $this->expectException(\LogicException::class);
        SeekerArchiveLog::first()->delete();
    }

    public function test_conversion_archives_in_the_same_transaction_and_cannot_be_repeated(): void
    {
        $satellite = Satellite::create(['name' => 'Pasig', 'slug' => 'pasig']);
        $leader = User::factory()->create(['role' => 'leader', 'district' => 'Pasig']);
        $dgroupLeader = Member::create(['satellite_id' => $satellite->id, 'name' => 'Assigned Leader', 'full_name' => 'Assigned Leader', 'dgroup_role' => Member::ROLE_LEADER]);
        $seeker = $this->seeker(['assigned_satellite_slug' => 'pasig', 'dgroup_leader_name' => $dgroupLeader->display_name]);
        $data = ['first_name' => 'Converted', 'last_name' => 'Example', 'email_address' => 'converted@example.test', 'district' => 'Pasig', 'campus_school' => 'Example School'];
        $this->actingAs($leader)->post(route('satellites.seekers.convert', [$satellite, $seeker]), $data)->assertRedirect();
        $this->assertSame('Successful', $seeker->refresh()->status);
        $this->assertNotNull($seeker->archived_at);
        $this->assertNotNull($seeker->converted_member_id);
        $this->assertDatabaseCount('members', 2);
        $this->assertDatabaseCount('seeker_archive_log', 1);
        $this->post(route('satellites.seekers.convert', [$satellite, $seeker]), $data)->assertRedirect();
        $this->assertDatabaseCount('members', 2);
    }

    public function test_failed_audit_insert_rolls_back_status_and_archive_metadata(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $satellite = Satellite::create(['name' => 'Pasig', 'slug' => 'pasig']);
        $leader = Member::create(['satellite_id' => $satellite->id, 'name' => 'Leader Example', 'full_name' => 'Leader Example', 'dgroup_role' => Member::ROLE_LEADER]);
        $seeker = $this->seeker(['assigned_satellite_slug' => 'pasig', 'dgroup_leader_name' => $leader->display_name]);
        DB::statement("CREATE TRIGGER reject_archive_test BEFORE INSERT ON seeker_archive_log BEGIN SELECT RAISE(ABORT, 'Simulated audit write failure'); END");
        try {
            app(SeekerArchiveService::class)->changeStatus($seeker, 'Successful', $actor);
            $this->fail('Audit failure must abort the status transition.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Simulated audit write failure', $exception->getMessage());
        }
        $this->assertSame('Pending', $seeker->refresh()->status);
        $this->assertNull($seeker->archived_at);
        $this->assertNull($seeker->archived_by);
        $this->assertDatabaseCount('seeker_archive_log', 0);
    }

    public function test_backfill_dry_run_is_read_only_and_apply_is_idempotent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seeker = $this->seeker(['status' => 'Successful']);
        $this->artisan('seekers:archive-backfill')->expectsOutput('1 candidate(s).')->assertSuccessful();
        $this->assertNull($seeker->refresh()->archived_at);
        $this->assertDatabaseCount('seeker_archive_log', 0);
        $this->artisan('seekers:archive-backfill', ['--apply' => true, '--actor' => $admin->id])->assertSuccessful();
        $this->artisan('seekers:archive-backfill', ['--apply' => true, '--actor' => $admin->id])->assertSuccessful();
        $this->assertNotNull($seeker->refresh()->archived_at);
        $this->assertDatabaseCount('seeker_archive_log', 1);
    }
}
