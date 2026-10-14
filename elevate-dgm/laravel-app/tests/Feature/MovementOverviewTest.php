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
use Tests\TestCase;

class MovementOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5));
        Satellite::create(['name' => 'Pasig', 'slug' => 'pasig', 'hub' => 'MAIN']);
        app(MovementAnalyticsSetup::class)->run();
    }

    private function report(string $mode = 'auto'): array
    {
        return app(MovementAnalyticsService::class)->dashboard('2026-09', mode: $mode);
    }

    private function snapshot(string $unit, string $checkpoint, int $value): void
    {
        UnitSnapshot::create(['unit_id' => OrgUnit::where('key', $unit)->value('id'), 'checkpoint_id' => Checkpoint::where('key', $checkpoint)->value('id'), 'source' => 'legacy_import', 'dmembers' => $value, 'metric_states' => ['dmembers' => 'reported'], 'quality_flags' => []]);
    }

    public function test_auto_displays_current_members_without_creating_historical_evidence(): void
    {
        foreach (['Active', 'Inactive', 'New'] as $status) {
            Member::create(['name' => 'Private fixture', 'satellite_id' => Satellite::first()->id, 'status' => $status, 'dgroup_role' => 'Dgroup Leader']);
        }
        $r = $this->report();
        $cell = $r['units']['main']['metrics']['dmembers'];
        $this->assertSame(1, $cell['value']);
        $this->assertSame('live', $cell['source']);
        $this->assertSame(1, $cell['coverage']['known']);
        $this->assertSame(8, $cell['coverage']['total']);
        $this->assertSame('partial', $cell['quality']);
        $this->assertSame('2026-10-05', $cell['as_of']);
        $this->assertNull($cell['changes']['baseline']['variance']);
        $this->assertNull($r['periods']['2026-09']['units'][$r['units']['main.pasig']['id']]['dmembers']['value']);
        $this->assertNull($r['periods']['2026-12']['units'][$r['units']['main.pasig']['id']]['dmembers']['value']);
        $this->assertNull($this->report('reported')['units']['main']['metrics']['dmembers']['value']);
        $this->assertSame('not_applicable', $r['units']['icp']['metrics']['campus']['state']);
        $this->assertDatabaseCount('unit_snapshots', 0);
    }

    public function test_partial_totals_and_like_for_like_exclude_unmatched_units(): void
    {
        $this->snapshot('main.pasig', '2025-12', 10);
        $this->snapshot('main.pasig', '2026-09', 12);
        $this->snapshot('main.anonas', '2026-09', 100);
        $r = $this->report('reported');
        $cell = $r['units']['main']['metrics']['dmembers'];
        $this->assertSame(112, $cell['value']);
        $this->assertSame(2, $cell['coverage']['known']);
        $this->assertSame(2, $cell['changes']['baseline']['variance']);
        $this->assertSame(20.0, $cell['changes']['baseline']['pct']);
        $this->assertSame(1, $cell['changes']['baseline']['compared']);
        $this->assertSame('partial', $cell['quality']);
    }

    public function test_parent_only_sheet_totals_remain_visible_without_fabricating_leaf_coverage(): void
    {
        $fixtures = [
            '2025-12'=>['main'=>[484,513,1925,90], 'ncp'=>[2051,2119,9842,210], 'icp'=>[216,216,1004,null]],
            '2026-06'=>['main'=>[515,546,1911,95], 'ncp'=>[2296,2398,10648,224], 'icp'=>[313,313,1298,null]],
        ];
        foreach ($fixtures as $period=>$units) foreach ($units as $key=>$values) {
            $metrics=array_combine(['dleaders','dgroups','dmembers','campus'],$values);
            UnitSnapshot::create($metrics+['unit_id'=>OrgUnit::where('key',$key)->value('id'),'checkpoint_id'=>Checkpoint::where('key',$period)->value('id'),'source'=>'legacy_import','metric_states'=>array_map(fn($v)=>$v===null?'not_applicable':'reported',$metrics),'quality_flags'=>[]]);
        }
        foreach (['2025-12'=>[2751,2848,12771,300],'2026-06'=>[3124,3257,13857,319]] as $period=>$totals) {
            $r=app(MovementAnalyticsService::class)->dashboard($period,mode:'reported');
            foreach (array_combine(['dleaders','dgroups','dmembers','campus'],$totals) as $metric=>$expected) {
                $this->assertSame($expected,$r['units']['elevate']['metrics'][$metric]['value']);
                $this->assertSame(0,$r['units']['elevate']['metrics'][$metric]['coverage']['known']);
            }
        }
    }

    public function test_zero_baseline_and_missing_cannot_become_a_decline(): void
    {
        $this->snapshot('main.pasig', '2025-12', 0);
        $this->snapshot('main.pasig', '2026-09', 5);
        $r = $this->report();
        $this->assertNull($r['units']['main.pasig']['metrics']['dmembers']['changes']['baseline']['variance']);
        $this->assertNull($r['units']['main.anonas']['metrics']['dmembers']['value']);
    }

    public function test_missing_status_or_role_is_not_a_verified_zero(): void
    {
        $member = Member::create(['name'=>'Unverified fixture', 'satellite_id'=>Satellite::first()->id, 'status'=>'', 'dgroup_role'=>'']);
        $this->assertNull($this->report()['units']['main.pasig']['metrics']['dmembers']['value']);
        $member->update(['status'=>'Active']);
        $this->assertSame(1,$this->report()['units']['main.pasig']['metrics']['dmembers']['value']);
        $this->assertNull($this->report()['units']['main.pasig']['metrics']['dleaders']['value']);
    }

    public function test_closed_system_count_precedes_import_and_current_records_without_writes(): void
    {
        $this->snapshot('main.pasig','2026-09',9);
        UnitSnapshot::create(['unit_id'=>OrgUnit::where('key','main.pasig')->value('id'), 'checkpoint_id'=>Checkpoint::where('key','2026-09')->value('id'), 'source'=>'system', 'dmembers'=>7, 'metric_states'=>['dmembers'=>'reported'], 'quality_flags'=>[], 'closed_at'=>now()]);
        Checkpoint::where('key','2026-09')->update(['status'=>'closed']);
        Member::create(['name'=>'Current fixture', 'satellite_id'=>Satellite::first()->id, 'status'=>'Active', 'dgroup_role'=>'Member']);
        $before=UnitSnapshot::orderBy('id')->get()->toJson();
        $this->assertSame(7,$this->report()['units']['main.pasig']['metrics']['dmembers']['value']);
        $this->assertSame($before,UnitSnapshot::orderBy('id')->get()->toJson());
    }

    public function test_import_precedes_live_and_live_only_does_not_use_import(): void
    {
        Member::create(['name' => 'Live', 'satellite_id' => Satellite::first()->id, 'status' => 'Active', 'dgroup_role' => 'Member']);
        $this->snapshot('main.pasig', '2026-09', 9);
        $this->assertSame(9, $this->report()['units']['main.pasig']['metrics']['dmembers']['value']);
        $this->assertSame(1, $this->report('live')['units']['main.pasig']['metrics']['dmembers']['value']);
    }

    public function test_resolution_requires_admin_and_preserves_original_snapshots(): void
    {
        $body = ['code' => 'visayas-baseline-disagreement', 'alternative' => 1, 'reason' => 'Verified both source tabs', 'evidence_reference' => 'Source document reference 2025'];
        $this->actingAs(User::factory()->create(['role' => 'leader']))->postJson(route('admin.analytics.resolve'), $body)->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->postJson(route('admin.analytics.resolve'), $body)->assertCreated();
        $this->assertDatabaseHas('movement_conflict_resolutions', ['actor_id' => $admin->id]);
        $this->assertDatabaseCount('unit_snapshots', 0);
        $r = app(MovementAnalyticsService::class)->dashboard('2025-12', mode: 'reported');
        $this->assertSame(472, $r['units']['ncp.visayas']['metrics']['dmembers']['value']);
        $this->postJson(route('admin.analytics.resolve'), $body)->assertUnprocessable();
    }

    public function test_sample_is_local_opt_in_and_export_is_forbidden(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $query = ['checkpoint' => '2026-09', 'sample' => 1];
        $this->getJson(route('admin.analytics.movement-data', $query))->assertForbidden();
        config(['movement_analytics.sample_enabled' => true]);
        $this->app['env'] = 'local';
        try {
            $r = $this->getJson(route('admin.analytics.movement-data', $query))->assertOk()->json();
            $this->assertTrue($r['sample']);
            $this->assertNull($r['activity']);
            $this->assertEmpty($r['conflicts']);
            $this->assertSame('sample', $r['units']['main.pasig']['metrics']['dmembers']['source']);
            $this->getJson(route('admin.analytics.movement-data', $query + ['format' => 'csv']))->assertForbidden();
            $this->assertDatabaseCount('unit_snapshots', 0);
            $this->app['env'] = 'production';
            $this->getJson(route('admin.analytics.movement-data', $query))->assertForbidden();
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_resolution_requires_csrf_and_closed_checkpoints_reject_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->app['env'] = 'local';
        try {
            $this->postJson(route('admin.analytics.resolve'), [])->assertStatus(419);
        } finally {
            $this->app['env'] = 'testing';
        }
        Checkpoint::where('key', '2025-12')->update(['status' => 'closed']);
        $this->postJson(route('admin.analytics.resolve'), ['code' => 'visayas-baseline-disagreement', 'alternative' => 0, 'reason' => 'Verified source records', 'evidence_reference' => 'Verified source reference'])->assertUnprocessable();
        $this->assertDatabaseCount('movement_conflict_resolutions', 0);
    }
}
