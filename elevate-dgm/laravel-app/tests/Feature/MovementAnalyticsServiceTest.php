<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Member;
use App\Models\OrgUnit;
use App\Models\Satellite;
use App\Models\UnitSnapshot;
use App\Models\User;
use App\Services\MovementAnalyticsService;
use App\Services\MovementAnalyticsSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MovementAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 2)->startOfDay());
        app(MovementAnalyticsSetup::class)->run();
    }

    private function service(): MovementAnalyticsService
    {
        return app(MovementAnalyticsService::class);
    }

    private function unit(string $key): OrgUnit
    {
        return OrgUnit::where('key', $key)->firstOrFail();
    }

    private function snapshot(string $unit, string $period, array $values, string $source = 'legacy_import'): UnitSnapshot
    {
        $org = $this->unit($unit);
        $data = ['unit_id' => $org->id, 'checkpoint_id' => Checkpoint::where('key', $period)->value('id'), 'source' => $source, 'quality_flags' => [], 'metric_states' => []];
        foreach (config('movement_analytics.metrics') as $metric) {
            $data[$metric] = $values[$metric] ?? null;
            $data['metric_states'][$metric] = $metric === 'campus' && ! $org->has_campus ? 'not_applicable' : (isset($values[$metric]) ? 'reported' : 'not_reported');
        }

        return UnitSnapshot::create($data);
    }

    public function test_required_arithmetic_null_zero_and_not_applicable(): void
    {
        $s = $this->service();
        foreach ([[2751, 3124, 373, 13.56], [2848, 3257, 409, 14.36], [12771, 13857, 1086, 8.50], [300, 319, 19, 6.33],
            [484, 515, 31, 6.40], [1925, 1911, -14, -0.73], [513, 546, 33, 6.43], [90, 95, 5, 5.56],
            [2051, 2296, 245, 11.95], [2119, 2398, 279, 13.17], [9842, 10648, 806, 8.19], [210, 224, 14, 6.67],
            [216, 313, 97, 44.91], [1004, 1298, 294, 29.28]] as [$base,$current,$delta,$pct]) {
            $this->assertSame(['variance' => $delta, 'pct' => $pct], $s->change($s->cell($current, 'reported'), $s->cell($base, 'reported')));
        }
        foreach ([$s->cell(), $s->cell(null, 'not_applicable'), $s->cell(null, 'conflict'), $s->cell(0, 'reported')] as $base) {
            $this->assertSame(['variance' => null, 'pct' => null], $s->change($s->cell(5, 'reported'), $base));
        }
        $this->assertNull($s->change($s->cell(), $s->cell(5, 'reported'))['pct']);
        $this->assertSame(-100.0, $s->change($s->cell(0, 'reported'), $s->cell(5, 'reported'))['pct']);
        $this->assertNull($s->ratio($s->cell(1, 'reported'), $s->cell(0, 'reported')));
    }

    public function test_supplied_parent_figures_are_reproduced_without_inventing_leaf_data(): void
    {
        $fixtures = [
            'elevate' => [[2751, 2848, 12771, 300], [3124, 3257, 13857, 319]],
            'main' => [[484, 513, 1925, 90], [515, 546, 1911, 95]],
            'ncp' => [[2051, 2119, 9842, 210], [2296, 2398, 10648, 224]],
            'icp' => [[216, 216, 1004, null], [313, 313, 1298, null]],
        ];
        foreach ($fixtures as $key => $periods) {
            foreach (['2025-12', '2026-06'] as $i => $period) {
                $this->snapshot($key, $period, array_combine(['dleaders', 'dgroups', 'dmembers', 'campus'], $periods[$i]));
            }
        }
        $r = $this->service()->report('2026-06', mode: 'reported');
        $this->assertSame(13857, $r['units']['elevate']['reported']['dmembers']['value']);
        $this->assertSame(['variance' => 1086, 'pct' => 8.5], $r['units']['elevate']['reported_comparisons']['dmembers']['baseline']);
        $this->assertSame(['variance' => -14, 'pct' => -0.73], $r['units']['main']['reported_comparisons']['dmembers']['baseline']);
        $this->assertNull($r['units']['elevate']['metrics']['dmembers']['value']);
        $this->assertContains('incomplete_children', array_column($r['issues'], 'code'));
        foreach (['2025-12' => [2751, 2848, 12771, 300], '2026-06' => [3124, 3257, 13857, 319]] as $period => $expected) {
            foreach (['dleaders', 'dgroups', 'dmembers', 'campus'] as $i => $metric) {
                $cells = [];
                foreach (['main', 'ncp', 'icp'] as $key) {
                    $cells[] = $r['periods'][$period]['reported'][$this->unit($key)->id][$metric];
                }
                $this->assertSame($expected[$i], $this->service()->sum($cells)['value']);
            }
        }
        $this->assertSame('not_applicable', $r['units']['icp']['metrics']['campus']['state']);
        $this->assertNull($r['units']['icp']['metrics']['campus']['value']);
    }

    public function test_rollups_use_leaves_once_and_reconcile_each_parent_without_overwriting_it(): void
    {
        $parents = OrgUnit::whereNotNull('parent_id')->pluck('parent_id')->unique()->all();
        foreach (OrgUnit::whereNotIn('id', $parents)->get() as $unit) {
            $this->snapshot($unit->key, '2026-06', ['dleaders' => 1, 'dgroups' => 2, 'dmembers' => 6, 'campus' => $unit->has_campus ? 1 : null, 'new_leaders' => 0]);
        }
        $this->snapshot('main', '2026-06', ['dmembers' => 999]);
        $r = $this->service()->report('2026-06', mode: 'reported');
        $this->assertSame(48, $r['units']['main']['metrics']['dmembers']['value']);
        $this->assertSame(999, $r['units']['main']['recorded']['dmembers']['value']);
        $leafCount = OrgUnit::whereNotIn('id', $parents)->count();
        $this->assertSame($leafCount * 6, $r['units']['elevate']['metrics']['dmembers']['value']);
        $this->assertSame(OrgUnit::whereNotIn('id', $parents)->where('has_campus', true)->count(), $r['units']['elevate']['metrics']['campus']['value']);
        $this->assertSame(3.0, $r['units']['main']['ratios']['members_per_group']);
        $this->assertSame(2.0, $r['units']['main']['ratios']['groups_per_leader']);
        $mismatch = collect($r['issues'])->firstWhere('code', 'parent_mismatch');
        $this->assertSame('main', $mismatch['unit']);
        $this->assertSame(48, $mismatch['children_sum']);
    }

    public function test_conflicts_propagate_and_partial_sums_never_become_complete_totals(): void
    {
        $this->snapshot('main.pasig', '2025-12', ['dmembers' => 8]);
        $r = $this->service()->report('2025-12', mode: 'reported');
        $this->assertNull($r['units']['main']['metrics']['dmembers']['value']);
        $this->assertSame('conflict', $r['units']['ncp.visayas']['metrics']['dmembers']['state']);
        $this->assertSame('conflict', $r['units']['elevate']['metrics']['dmembers']['state']);
        $this->assertContains('declared_conflict', array_column($r['issues'], 'code'));
    }

    public function test_previous_checkpoint_is_not_previous_available_report_and_future_is_not_zero(): void
    {
        $this->snapshot('main.pasig', '2025-12', ['dmembers' => 10]);
        $this->snapshot('main.pasig', '2026-06', ['dmembers' => 11]);
        $r = $this->service()->report('2026-06', 'previous', mode: 'reported');
        $this->assertSame('2026-03', $r['previous']);
        $this->assertNull($r['units']['main.pasig']['metrics']['dmembers']['changes']['previous']['pct']);
        $this->assertSame(10.0, $r['units']['main.pasig']['metrics']['dmembers']['changes']['baseline']['pct']);
        $r = $this->service()->report('2026-12');
        $this->assertSame('Not reported', $r['units']['main.pasig']['status']);
        $this->assertNull($r['units']['main.pasig']['metrics']['dmembers']['value']);
        $this->assertNull($r['units']['main.pasig']['metrics']['dmembers']['changes']['baseline']['pct']);
    }

    public function test_status_thresholds_and_all_quality_rules_use_the_selected_comparison(): void
    {
        $baseline = ['dleaders' => 2, 'dgroups' => 2, 'dmembers' => 6, 'campus' => 1, 'new_leaders' => 0];
        foreach (['2025-12', '2026-03', '2026-06'] as $period) {
            $this->snapshot('main.pasig', $period, $baseline);
        }
        $this->snapshot('main.anonas', '2025-12', ['dmembers' => 10, 'dgroups' => 1]);
        $this->snapshot('main.anonas', '2026-06', ['dmembers' => 15, 'dgroups' => 1]);
        $this->snapshot('main.ubelt', '2025-12', ['dmembers' => 10]);
        $this->snapshot('main.ubelt', '2026-06', ['dmembers' => 9]);
        $this->snapshot('main.eastwood', '2025-12', ['dmembers' => 10]);
        $this->snapshot('main.eastwood', '2026-06', ['dmembers' => 11]);
        $r = $this->service()->report('2026-06', mode: 'reported');
        $this->assertSame('Stable', $r['units']['main.pasig']['status']);
        $this->assertSame('Anomaly', $r['units']['main.anonas']['status']);
        $this->assertSame('Declining', $r['units']['main.ubelt']['status']);
        $this->assertSame('Growing', $r['units']['main.eastwood']['status']);
        foreach (['possibly_carried_forward', 'groups_equal_leaders', 'large_change', 'members_per_group_out_of_range'] as $code) {
            $this->assertContains($code, array_column($r['issues'], 'code'));
        }
        config(['movement_analytics.thresholds.stable_change_pct' => 10]);
        $this->assertSame('Stable', $this->service()->report('2026-06', mode: 'reported')['units']['main.ubelt']['status']);
    }

    private function assignment(int $member, string $unit, string $from = '2026-01-01', ?string $to = null): void
    {
        DB::table('analytics_member_assignments')->insert(['member_id' => $member, 'unit_id' => $this->unit($unit)->id, 'effective_from' => $from, 'effective_to' => $to, 'actor_id' => 1]);
    }

    private function recordStatus(int $member, string $status = 'Active', string $date = '2026-01-01 00:00:00', int $version = 1): void
    {
        DB::table('member_status_history')->insert(['member_id' => $member, 'version' => $version, 'new_status' => $status, 'effective_at' => $date, 'recorded_at' => $date, 'actor_id' => 1, 'reason' => 'Synthetic fixture']);
    }

    private function dgroup(string $key, int $leader, string $unit = 'main.pasig', string $from = '2026-01-01', ?string $to = null): int
    {
        return DB::table('analytics_dgroups')->insertGetId(['key' => $key, 'name' => $key, 'unit_id' => $this->unit($unit)->id, 'leader_member_id' => $leader, 'effective_from' => $from, 'effective_to' => $to]);
    }

    public function test_live_counts_use_as_of_status_and_dated_assignments_including_leaders(): void
    {
        foreach ([1, 2, 3, 4] as $id) {
            $this->assignment($id, 'main.pasig');
            $this->recordStatus($id);
        }
        $this->recordStatus(2, 'Inactive', '2026-07-01 00:00:00', 2);
        $this->recordStatus(3, 'Inactive', '2026-06-30 23:59:59', 2);
        $this->assignment(5, 'main.pasig', '2026-07-01'); // outside the cutoff
        $this->dgroup('first', 1);
        $this->dgroup('second', 1); // unique leader, two groups
        $this->snapshot('main.pasig', '2025-12', ['dmembers' => 2, 'campus' => 3]);
        $this->snapshot('main.pasig', '2026-06', ['campus' => 4]);
        $r = $this->service()->report('2026-06');
        $pasig = $r['units']['main.pasig'];
        $this->assertSame(3, $pasig['metrics']['dmembers']['value']);
        $this->assertSame(1, $pasig['metrics']['dleaders']['value']);
        $this->assertSame(2, $pasig['metrics']['dgroups']['value']);
        $this->assertSame(4, $pasig['metrics']['campus']['value']);
        $this->assertSame('legacy_import', $pasig['metrics']['campus']['source']);
        $this->assertSame('2025-12', $pasig['system_vs_last_reported']['dmembers']['checkpoint']);
        $this->assertSame(['variance' => 1, 'pct' => 50.0], $pasig['system_vs_last_reported']['dmembers']['change']);
    }

    public function test_live_empty_registry_missing_status_and_overlapping_assignments_are_unknown(): void
    {
        $empty = $this->service()->report('2026-06');
        $this->assertNull($empty['units']['main.pasig']['metrics']['dmembers']['value']);
        $this->assignment(1, 'main.pasig');
        $r = $this->service()->report('2026-06');
        $this->assertNull($r['units']['main.pasig']['metrics']['dmembers']['value']);
        $this->assertContains('missing_status_history', array_column($r['issues'], 'code'));
        $this->recordStatus(1);
        $this->assignment(1, 'main.anonas');
        $r = $this->service()->report('2026-06');
        $this->assertSame('conflict', $r['units']['main.pasig']['metrics']['dmembers']['state']);
        $this->assertSame('conflict', $r['units']['main.anonas']['metrics']['dmembers']['state']);
    }

    public function test_live_membership_transfer_uses_exclusive_end_date_and_inactive_is_real_zero(): void
    {
        $this->assignment(1, 'main.pasig', '2026-01-01', '2026-06-30');
        $this->assignment(1, 'main.anonas', '2026-06-30');
        $this->recordStatus(1, 'Inactive');
        $r = $this->service()->report('2026-06');
        $this->assertSame(0, $r['units']['main.pasig']['metrics']['dmembers']['value']);
        $this->assertSame(0, $r['units']['main.anonas']['metrics']['dmembers']['value']);
        $this->assertNotContains('overlapping_assignments', array_column($r['issues'], 'code'));
    }

    public function test_new_leader_requires_three_concurrent_distinct_members_and_excludes_reactivation(): void
    {
        $first = $this->dgroup('first', 1);
        $second = $this->dgroup('second', 1);
        $this->recordStatus(1);
        foreach ([[2, '2026-02-01'], [3, '2026-04-01'], [4, '2026-05-01']] as [$member,$from]) {
            DB::table('analytics_dgroup_memberships')->insert(['dgroup_id' => $first, 'member_id' => $member, 'effective_from' => $from]);
        }
        DB::table('analytics_dgroup_memberships')->insert(['dgroup_id' => $second, 'member_id' => 2, 'effective_from' => '2026-02-01']);
        DB::table('analytics_leadership_events')->insert(['member_id' => 1, 'unit_id' => $this->unit('main.pasig')->id, 'event' => 'appointed', 'effective_at' => '2026-01-01 00:00:00', 'actor_id' => 1, 'recorded_at' => now(), 'reason' => 'Synthetic fixture']);
        $r = $this->service()->report('2026-06');
        $this->assertSame(1, $r['units']['main.pasig']['metrics']['new_leaders']['value']);
        $this->assertSame(0, $this->service()->report('2026-03')['units']['main.pasig']['metrics']['new_leaders']['value']);
        DB::table('analytics_leadership_events')->insert(['member_id' => 1, 'unit_id' => $this->unit('main.pasig')->id, 'event' => 'reactivated', 'effective_at' => '2026-05-15 00:00:00', 'actor_id' => 1, 'recorded_at' => now(), 'reason' => 'Synthetic fixture']);
        // A later reactivation does not erase the genuine first qualification or count it twice.
        $this->assertSame(1, $this->service()->report('2026-06')['units']['main.pasig']['metrics']['new_leaders']['value']);
        $this->assertSame(0, $this->service()->report('2026-09')['units']['main.pasig']['metrics']['new_leaders']['value']);
    }

    public function test_closed_checkpoint_uses_saved_system_snapshot_and_reports_missing_units(): void
    {
        $this->snapshot('main.pasig', '2026-06', ['dmembers' => 4]);
        $this->snapshot('main.pasig', '2026-06', ['dmembers' => 6], 'system');
        Checkpoint::where('key', '2026-06')->first()->update(['status' => 'closed']);
        $this->assignment(1, 'main.pasig');
        $this->recordStatus(1);
        $r = $this->service()->report('2026-06', mode: 'live');
        $this->assertSame('snapshot', $r['mode']);
        $this->assertSame(6, $r['units']['main.pasig']['metrics']['dmembers']['value']);
        $this->assertSame(4, $r['units']['main.pasig']['reported']['dmembers']['value']);
        $this->assertContains('missing_closed_snapshot', array_column($r['issues'], 'code'));
        $this->assertContains('incomplete_closed_snapshot', array_column($r['issues'], 'code'));
    }

    public function test_quality_checks_are_private_group_scoped_and_do_not_expose_member_records(): void
    {
        Satellite::create(['name' => 'Unmapped', 'slug' => 'unmapped', 'hub' => 'ICP']);
        $satellite = Satellite::create(['name' => 'Pasig', 'slug' => 'pasig', 'hub' => 'MAIN']);
        app(MovementAnalyticsSetup::class)->run();
        Member::create(['name' => 'PRIVATE NAME', 'satellite_id' => $satellite->id, 'gender' => null]);
        $r = $this->service()->report('2026-06', group: 'MAIN');
        $this->assertContains('missing_gender', array_column($r['issues'], 'code'));
        $this->assertNotContains('unmapped_satellite', array_column($r['issues'], 'code'));
        $this->assertStringNotContainsString('PRIVATE NAME', json_encode($r));
        $this->assertArrayNotHasKey('ncp', $r['units']);
        $this->assertNotContains($this->unit('ncp')->id, array_keys($r['periods']['2025-12']['units']));
        $this->assertContains('unmapped_satellite', array_column($this->service()->report('2026-06')['issues'], 'code'));
    }

    public function test_service_uses_only_selects_and_batched_queries(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->service()->report('2026-06', mode: 'live');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($queries as $query) {
            $this->assertMatchesRegularExpression('/^\s*select\b/i', $query['query']);
        }
        $this->assertLessThanOrEqual(13, count($queries)); // Includes append-only conflict resolutions.
    }

    public function test_endpoint_requires_admin_and_validates_filters(): void
    {
        $url = route('admin.analytics.movement-data');
        $this->getJson($url.'?checkpoint=2026-06')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'leader']))->getJson($url.'?checkpoint=2026-06')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson($url.'?checkpoint=2026-06&group=MAIN&mode=reported')->assertOk()->assertJsonPath('group', 'MAIN');
        $this->getJson($url.'?checkpoint=bad&mode=bad&compare=bad&group=bad')->assertUnprocessable()->assertJsonValidationErrors(['checkpoint', 'mode', 'compare', 'group']);
    }

    public function test_thresholds_use_unrounded_changes_and_empty_metrics_do_not_trigger_carried_forward(): void
    {
        $this->snapshot('main.pasig', '2025-12', ['dmembers' => 100000]);
        $this->snapshot('main.pasig', '2026-03', ['dmembers' => 100001]);
        $this->snapshot('main.pasig', '2026-06', ['dmembers' => 100001]);
        $this->snapshot('main.anonas', '2025-12', ['dmembers' => 100000]);
        $this->snapshot('main.anonas', '2026-06', ['dmembers' => 140001]);
        $r = $this->service()->report('2026-06', mode: 'reported');
        $this->assertSame('Growing', $r['units']['main.pasig']['status']);
        $this->assertSame('Anomaly', $r['units']['main.anonas']['status']);
        $this->assertNotContains('possibly_carried_forward', array_column($r['issues'], 'code'));
    }

    public function test_new_leader_counts_do_not_add_nonconcurrent_or_duplicate_members(): void
    {
        $group = $this->dgroup('first',1);
        $this->recordStatus(1);
        DB::table('analytics_leadership_events')->insert(['member_id' => 1, 'unit_id' => $this->unit('main.pasig')->id, 'event' => 'appointed', 'effective_at' => '2026-01-01 00:00:00', 'actor_id' => 1, 'recorded_at' => now(), 'reason' => 'Synthetic fixture']);
        foreach ([[2, '2026-01-01', '2026-04-01'], [3, '2026-02-01', null], [4, '2026-04-01', null]] as [$member,$from,$to]) {
            DB::table('analytics_dgroup_memberships')->insert(['dgroup_id' => $group, 'member_id' => $member, 'effective_from' => $from, 'effective_to' => $to]);
        }
        $this->assertSame(0,$this->service()->report('2026-06')['units']['main.pasig']['metrics']['new_leaders']['value']);
    }
}
