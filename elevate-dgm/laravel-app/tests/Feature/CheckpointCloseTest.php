<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\OrgUnit;
use App\Models\Satellite;
use App\Models\UnitSnapshot;
use App\Models\User;
use App\Services\CheckpointCloseService;
use App\Services\MovementAnalyticsService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckpointCloseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Checkpoint $checkpoint;

    private OrgUnit $leaf;

    private CheckpointCloseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Asia/Manila')->setDate(2026, 10, 2));
        config(['movement_analytics.declared_conflicts' => []]);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $root = OrgUnit::create(['key' => 'elevate', 'name' => 'Elevate', 'type' => 'organization', 'has_campus' => true, 'sort' => 0]);
        $this->leaf = OrgUnit::create(['key' => 'main.pasig', 'name' => 'Pasig', 'group' => 'MAIN', 'type' => 'unit', 'parent_id' => $root->id, 'has_campus' => true, 'sort' => 1]);
        $satellite = Satellite::create(['name' => 'Pasig', 'slug' => 'pasig', 'hub' => 'MAIN']);
        DB::table('org_unit_satellite_mappings')->insert(['unit_id' => $this->leaf->id, 'satellite_id' => $satellite->id]);
        $this->checkpoint = Checkpoint::create(['key' => '2026-09', 'label' => 'Sep 2026', 'kind' => 'quarter', 'status' => 'open', 'as_of_date' => '2026-09-30']);
        $this->service = app(CheckpointCloseService::class);
    }

    private function counts(): UnitSnapshot
    {
        return UnitSnapshot::create(['unit_id' => $this->leaf->id, 'checkpoint_id' => $this->checkpoint->id, 'source' => 'legacy_import',
            'dleaders' => 1, 'dgroups' => 2, 'dmembers' => 6, 'campus' => 1, 'new_leaders' => null, 'quality_flags' => [],
            'metric_states' => ['dleaders' => 'reported', 'dgroups' => 'reported', 'dmembers' => 'reported', 'campus' => 'reported', 'new_leaders' => 'not_reported']]);
    }

    private function evidence(): array
    {
        return ['reason' => 'Verified checkpoint evidence', 'evidence_reference' => 'Signed report dated 2026-09-30'];
    }

    private function attest(?User $actor = null): array
    {
        $actor ??= User::factory()->create(['role' => 'leader', 'district' => 'Pasig']);
        $review = $this->service->review($actor, $this->checkpoint, 'legacy_import');

        return $this->service->attest($actor, $this->checkpoint, $this->evidence() + ['basis' => 'legacy_import', 'unit' => $this->leaf->key, 'fingerprint' => $review['units'][$this->leaf->key]['fingerprint']]);
    }

    private function closeInput(): array
    {
        $review = $this->service->review($this->admin, $this->checkpoint, 'legacy_import');

        return $this->evidence() + ['basis' => 'legacy_import', 'token' => $review['token'], 'confirmation' => '2026-09', 'acknowledge_issues' => true];
    }

    public function test_missing_counts_cannot_be_confirmed_or_closed_and_review_is_select_only(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $review = $this->service->review($this->admin, $this->checkpoint);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($queries as $query) {
            $this->assertMatchesRegularExpression('/^select/i', $query['query']);
        }
        $this->assertFalse($review['ready']);
        $this->assertContains('missing_counts', array_column($review['blockers'], 'code'));
        $this->actingAs($this->admin)->postJson(route('analytics.checkpoints.close', $this->checkpoint->key), $this->closeInput())->assertUnprocessable();
        $this->assertDatabaseCount('checkpoint_closures', 0);
        $this->assertSame('open', $this->checkpoint->fresh()->status);
    }

    public function test_leader_can_confirm_only_assigned_units_and_cannot_finalize(): void
    {
        $this->counts();
        $leader = User::factory()->create(['role' => 'leader', 'district' => 'Pasig']);
        $outside = User::factory()->create(['role' => 'leader', 'district' => 'Elsewhere']);
        $this->assertEmpty($this->service->review($outside, $this->checkpoint, 'legacy_import')['units']);
        $review = $this->attest($leader);
        $this->assertTrue($review['units'][$this->leaf->key]['confirmed']);
        $this->assertFalse($review['may_finalize']);
        $this->actingAs($leader)->postJson(route('analytics.checkpoints.close', $this->checkpoint->key), $this->closeInput())->assertForbidden();
        $this->actingAs($outside)->postJson(route('analytics.checkpoints.attest', $this->checkpoint->key), $this->evidence() + ['basis' => 'legacy_import', 'unit' => $this->leaf->key, 'fingerprint' => str_repeat('a', 64)])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'staff']))->getJson(route('analytics.checkpoints.review', $this->checkpoint->key))->assertForbidden();
        $this->actingAs($this->admin)->postJson(route('analytics.checkpoints.attest', $this->checkpoint->key), $this->evidence() + ['basis' => 'legacy_import', 'unit' => $this->leaf->key, 'fingerprint' => $review['units'][$this->leaf->key]['fingerprint']])->assertForbidden();
    }

    public function test_close_requires_explicit_confirmation_and_records_immutable_materialized_snapshots(): void
    {
        $original = $this->counts();
        $before = $original->fresh()->getRawOriginal();
        $this->attest();
        $input = $this->closeInput();
        $this->actingAs($this->admin)->postJson(route('analytics.checkpoints.close', $this->checkpoint->key), array_replace($input, ['confirmation' => 'wrong']))->assertUnprocessable();
        $this->postJson(route('analytics.checkpoints.close', $this->checkpoint->key), array_replace($input, ['acknowledge_issues' => false]))->assertUnprocessable();
        $this->postJson(route('analytics.checkpoints.close', $this->checkpoint->key), $input)->assertCreated()->assertJsonPath('status', 'closed');
        $this->assertSame($before, $original->fresh()->getRawOriginal());
        $this->assertSame(2, UnitSnapshot::where('source', 'system')->whereNotNull('closed_at')->count());
        $this->assertDatabaseHas('checkpoint_closures', ['actor_id' => $this->admin->id, 'basis' => 'legacy_import']);
        $this->postJson(route('analytics.checkpoints.close', $this->checkpoint->key), $input)->assertUnprocessable();
        $this->assertDatabaseCount('checkpoint_closures', 1);
        $this->expectException(QueryException::class);
        DB::table('unit_snapshots')->where('source', 'system')->update(['dmembers' => 999]);
    }

    public function test_changed_evidence_invalidates_attestation_and_review_token(): void
    {
        $source = $this->counts();
        $this->attest();
        $input = $this->closeInput();
        $source->update(['dmembers' => 7]);
        $review = $this->service->review($this->admin, $this->checkpoint, 'legacy_import');
        $this->assertFalse($review['units'][$this->leaf->key]['confirmed']);
        $this->actingAs($this->admin)->postJson(route('analytics.checkpoints.close', $this->checkpoint->key), $input)->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertDatabaseCount('checkpoint_closures', 0);
        $this->assertSame(0, UnitSnapshot::where('source', 'system')->count());
    }

    public function test_same_count_source_changes_still_invalidate_the_final_review(): void
    {
        $source = $this->counts();
        $this->attest();
        $input = $this->closeInput();
        $source->update(['remarks' => 'Changed evidence after review']);
        $this->actingAs($this->admin)->postJson(route('analytics.checkpoints.close', $this->checkpoint->key), $input)->assertUnprocessable()->assertJsonValidationErrors('token');
    }

    public function test_future_or_current_day_checkpoint_cannot_close(): void
    {
        $this->checkpoint->update(['as_of_date' => '2026-10-02']);
        $review = $this->service->review($this->admin, $this->checkpoint);
        $this->assertContains('not_due', array_column($review['blockers'], 'code'));
    }

    public function test_correction_appends_version_reconciles_parents_and_preserves_all_originals(): void
    {
        $this->counts();
        $this->attest();
        $this->service->close($this->admin, $this->checkpoint, $this->closeInput());
        $originals = DB::table('unit_snapshots')->orderBy('id')->get()->toJson();
        $input = $this->evidence() + ['unit' => $this->leaf->key, 'expected_version' => 0, 'values' => ['dmembers' => 8]];
        $this->actingAs($this->admin)->postJson(route('analytics.checkpoints.correct', $this->checkpoint->key), $input)->assertCreated()->assertJsonPath('revision', 1);
        $report = app(MovementAnalyticsService::class)->dashboard('2026-09');
        $this->assertSame(8, $report['units']['main.pasig']['metrics']['dmembers']['value']);
        $this->assertSame(8, $report['units']['elevate']['metrics']['dmembers']['value']);
        $this->assertSame(6, $report['units']['main.pasig']['reported']['dmembers']['value']);
        $this->assertSame(1, $report['revision']);
        $this->assertSame($originals, DB::table('unit_snapshots')->orderBy('id')->get()->toJson());
        $this->postJson(route('analytics.checkpoints.correct', $this->checkpoint->key), $input)->assertUnprocessable()->assertJsonValidationErrors('expected_version');
        $this->assertDatabaseCount('checkpoint_revisions', 1);
        $this->expectException(QueryException::class);
        DB::table('checkpoint_revisions')->delete();
    }

    public function test_invalid_corrections_and_empty_reasons_do_not_create_a_revision(): void
    {
        $this->counts();
        $this->attest();
        $this->service->close($this->admin, $this->checkpoint, $this->closeInput());
        $url = route('analytics.checkpoints.correct', $this->checkpoint->key);
        $base = $this->evidence() + ['unit' => $this->leaf->key, 'expected_version' => 0, 'values' => ['dmembers' => 8]];
        $this->actingAs($this->admin)->postJson($url, array_replace($base, ['reason' => '           ']))->assertUnprocessable();
        $this->postJson($url, array_replace($base, ['unit' => 'elevate']))->assertUnprocessable();
        $this->postJson($url, array_replace($base, ['values' => ['dmembers' => null]]))->assertUnprocessable();
        $this->postJson($url, array_replace($base, ['values' => ['dmembers' => -1]]))->assertUnprocessable();
        $this->postJson($url, array_replace($base, ['values' => ['dmembers' => 6]]))->assertUnprocessable();
        $this->actingAs(User::factory()->create(['role' => 'leader', 'district' => 'Pasig']))->postJson($url, $base)->assertForbidden();
        $this->assertDatabaseCount('checkpoint_revisions', 0);
    }

    public function test_failure_during_snapshot_write_rolls_back_the_entire_close(): void
    {
        $this->counts();
        $this->attest();
        $input = $this->closeInput();
        DB::unprepared("CREATE TRIGGER reject_fixture_close BEFORE INSERT ON unit_snapshots WHEN NEW.source='system' AND NEW.unit_id={$this->leaf->id} BEGIN SELECT RAISE(ABORT,'Synthetic failure'); END");
        try {
            $this->service->close($this->admin, $this->checkpoint, $input);
            $this->fail('Expected transactional failure');
        } catch (QueryException $error) {
            $this->assertStringContainsString('Synthetic failure', $error->getMessage());
        }
        $this->assertSame('open', $this->checkpoint->fresh()->status);
        $this->assertDatabaseCount('checkpoint_closures', 0);
        $this->assertSame(0, UnitSnapshot::where('source', 'system')->count());
    }

    public function test_close_mutation_requires_csrf_outside_test_bypass(): void
    {
        $this->app['env'] = 'local';
        try {
            $this->actingAs($this->admin)->postJson(route('analytics.checkpoints.close', $this->checkpoint->key), [])->assertStatus(419);
            $this->postJson(route('analytics.checkpoints.correct', $this->checkpoint->key), [])->assertStatus(419);
            $leader = User::factory()->create(['role' => 'leader', 'district' => 'Pasig']);
            $this->actingAs($leader)->postJson(route('analytics.checkpoints.attest', $this->checkpoint->key), [])->assertStatus(419);
            $member = \App\Models\Member::create(['name' => 'CSRF fixture', 'satellite_id' => Satellite::first()->id]);
            $this->postJson(route('analytics.members.status.store', $member), [])->assertStatus(419);
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_new_units_cannot_change_frozen_parent_totals_or_enter_a_past_correction(): void
    {
        $this->counts();
        $this->attest();
        $this->service->close($this->admin, $this->checkpoint, $this->closeInput());
        OrgUnit::create(['key' => 'main.new', 'name' => 'Future unit', 'type' => 'unit', 'parent_id' => $this->leaf->parent_id, 'group' => 'MAIN', 'has_campus' => true, 'sort' => 2]);
        $this->assertSame(6, app(MovementAnalyticsService::class)->report('2026-09')['units']['elevate']['metrics']['dmembers']['value']);
        $this->actingAs($this->admin)->postJson(route('analytics.checkpoints.correct', $this->checkpoint->key), $this->evidence() + ['unit' => 'main.new', 'expected_version' => 0, 'values' => ['dmembers' => 8]])->assertUnprocessable();
        $this->postJson(route('analytics.checkpoints.correct',$this->checkpoint->key),$this->evidence() + ['unit' => $this->leaf->key, 'expected_version' => 0, 'values' => ['dmembers' => 8]])->assertCreated();
        $this->assertSame(8,app(MovementAnalyticsService::class)->report('2026-09')['units']['elevate']['metrics']['dmembers']['value']);
    }
}
