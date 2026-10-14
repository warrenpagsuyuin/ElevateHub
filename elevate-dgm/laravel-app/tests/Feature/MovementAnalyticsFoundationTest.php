<?php
namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Member;
use App\Models\MemberStatusHistory;
use App\Models\OrgUnit;
use App\Models\Satellite;
use App\Models\UnitSnapshot;
use App\Models\User;
use App\Services\MovementAnalyticsSetup;
use App\Services\MovementSnapshotImport;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MovementAnalyticsFoundationTest extends TestCase
{
    use RefreshDatabase;
    private array $files=[];
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin=User::factory()->create(['role'=>'admin']);
        app(MovementAnalyticsSetup::class)->run();
    }
    protected function tearDown(): void
    {
        foreach ($this->files as $path) if (is_file($path)) unlink($path);
        parent::tearDown();
    }
    private function csv(string $text): string
    {
        $file=tempnam(sys_get_temp_dir(),'movement-fixture-');
        file_put_contents($file,$text); $this->files[]=$file; return $file;
    }
    private function import(string $text, string $group='MAIN', bool $dry=false): array
    {
        return app(MovementSnapshotImport::class)->run([$group=>$this->csv($text)],$this->admin,$dry);
    }
    private function snapshot(string $key,string $period='2025-12'): UnitSnapshot
    {
        return UnitSnapshot::where('unit_id',OrgUnit::where('key',$key)->value('id'))->where('checkpoint_id',Checkpoint::where('key',$period)->value('id'))->firstOrFail();
    }

    public function test_setup_is_idempotent_seeds_only_structure_and_reports_unmapped_satellites(): void
    {
        $pasig=Satellite::create(['name'=>'Pasig','slug'=>'pasig','hub'=>'MAIN']);
        Satellite::create(['name'=>'Unmapped fixture','slug'=>'unmapped-fixture','hub'=>'MAIN']);
        $before=OrgUnit::count();
        $setup=app(MovementAnalyticsSetup::class);
        $dry=$setup->run(true);
        $this->assertSame(0,DB::table('org_unit_satellite_mappings')->count());
        $first=$setup->run(); $setup->run();
        $this->assertSame($before,OrgUnit::count());
        $this->assertSame(5,Checkpoint::count());
        $this->assertSame(0,UnitSnapshot::count());
        $this->assertSame(0,MemberStatusHistory::count());
        $this->assertSame(1,DB::table('analytics_data_issues')->where('code','declared_conflict')->count());
        $this->assertSame('main.pasig',OrgUnit::find(DB::table('org_unit_satellite_mappings')->where('satellite_id',$pasig->id)->value('unit_id'))->key);
        $this->assertSame('unmapped-fixture',$first['unmapped'][0]['slug']);
        $this->assertSame($first['unmapped'],$dry['unmapped']);
        $this->assertFalse(OrgUnit::where('key','icp')->first()->has_campus);
        $this->assertSame(0,Checkpoint::where('status','closed')->count());
    }

    public function test_import_preserves_null_zero_and_not_applicable_and_dry_run_executes_only_selects(): void
    {
        $file=$this->csv("unit,checkpoint,dleaders,dgroups,dmembers,campus,new_leaders\nPasig,Dec 2025,,0,12,,\n");
        $queries=[];
        DB::listen(function ($query) use (&$queries) { $queries[]=$query->sql; });
        $service=app(MovementSnapshotImport::class);
        $result=$service->run(['MAIN'=>$file],null,true);
        foreach ($queries as $sql) $this->assertMatchesRegularExpression('/^\s*select\b/i',$sql);
        $this->assertSame(1,$result['snapshots']);
        $this->assertSame(0,UnitSnapshot::count());
        $service->run(['MAIN'=>$file],$this->admin,false);
        $snapshot=$this->snapshot('main.pasig');
        $this->assertNull($snapshot->dleaders);
        $this->assertSame('not_reported',$snapshot->metric_states['dleaders']);
        $this->assertSame(0,$snapshot->dgroups);
        $this->assertNull($snapshot->reported_at);
        $this->import("unit,checkpoint,dleaders,dgroups,dmembers,campus\nAsia,2025-12,1,1,3,-\n",'ICP');
        $asia=$this->snapshot('icp.asia');
        $this->assertNull($asia->campus);
        $this->assertSame('not_applicable',$asia->metric_states['campus']);
    }

    public function test_repeat_import_does_not_add_rows_and_changed_values_become_conflicts(): void
    {
        $file=$this->csv("unit,checkpoint,dleaders,dgroups,dmembers,campus\nPasig,2025-12,1,2,9,1\n");
        $service=app(MovementSnapshotImport::class);
        $service->run(['MAIN'=>$file],$this->admin,false);
        $this->assertTrue($service->run(['MAIN'=>$file],$this->admin,false)['unchanged']);
        $renamed=$this->csv(file_get_contents($file));
        $this->assertTrue($service->run(['MAIN'=>$renamed],$this->admin,false)['unchanged']);
        $this->assertSame(1,DB::table('analytics_import_rows')->count());
        $changed=$this->import("unit,checkpoint,dleaders,dgroups,dmembers,campus\nPasig,2025-12,2,2,9,1\n");
        $this->assertContains('conflict',array_column($changed['issues'],'code'));
        $this->assertNull($this->snapshot('main.pasig')->dleaders);
        $this->assertSame(2,$this->snapshot('main.pasig')->dgroups);
        $this->assertSame(2,DB::table('analytics_import_rows')->count());
    }

    public function test_visayas_alternatives_are_preserved_and_neither_becomes_the_official_count(): void
    {
        $result=$this->import("unit,checkpoint,dleaders,dgroups,dmembers,campus\nVisayas,2025-12,68,68,358,\nVisayas,2025-12,106,106,472,\n",'NCP');
        $snapshot=$this->snapshot('ncp.visayas');
        $this->assertTrue($snapshot->conflict);
        foreach (['dleaders','dgroups','dmembers'] as $metric) {
            $this->assertNull($snapshot->$metric);
            $this->assertSame('conflict',$snapshot->metric_states[$metric]);
        }
        $values=DB::table('analytics_import_rows')->pluck('payload')->map(fn ($payload) => json_decode($payload,true)['dmembers'])->all();
        $this->assertSame([358,472],$values);
        $this->assertContains('declared_conflict',array_column($result['issues'],'code'));
        $this->assertSame(1,DB::table('analytics_data_issues')->where('code','declared_conflict')->count());
    }

    public function test_flattened_wide_csv_includes_empty_future_checkpoint_without_zero_filling(): void
    {
        $this->import("unit,Dec 2025 DLeaders,Dec 2025 DGroups,Dec 2025 DMembers,Dec 2026 DLeaders,Dec 2026 DGroups,Dec 2026 DMembers\nPasig,3,3,12,,,\n");
        $future=$this->snapshot('main.pasig','2026-12');
        $this->assertNull($future->dmembers);
        $this->assertSame('not_reported',$future->metric_states['dmembers']);
    }

    public function test_two_row_checkpoint_headers_from_a_sheet_export_are_supported(): void
    {
        $this->import("MAIN Data,,,,,,\nUnit,Dec 2025,,,Jun 2026,,\n,DLeaders,DGroups,DMembers,DLeaders,DGroups,DMembers\nPasig,2,3,9,3,4,12\n");
        $this->assertSame(9,$this->snapshot('main.pasig')->dmembers);
        $this->assertSame(12,$this->snapshot('main.pasig','2026-06')->dmembers);
        $this->assertSame(4,DB::table('analytics_import_rows')->value('row_number'));
    }

    public function test_supplied_aggregate_fixtures_reproduce_totals_and_reconcile_at_root(): void
    {
        $header="unit,checkpoint,dleaders,dgroups,dmembers,campus\n";
        $files=[
            'MAIN'=>$this->csv($header."Elevate,2025-12,2751,2848,12771,300\nMAIN,2025-12,484,513,1925,90\nElevate,2026-06,3124,3257,13857,319\nMAIN,2026-06,515,546,1911,95\n"),
            'NCP'=>$this->csv($header."NCP,2025-12,2051,2119,9842,210\nNCP,2026-06,2296,2398,10648,224\n"),
            'ICP'=>$this->csv($header."ICP,2025-12,216,216,1004,-\nICP,2026-06,313,313,1298,-\n"),
        ];
        $result=app(MovementSnapshotImport::class)->run($files,$this->admin,false);
        $this->assertSame(12771,$this->snapshot('elevate')->dmembers);
        $this->assertSame(13857,$this->snapshot('elevate','2026-06')->dmembers);
        $this->assertSame(319,$this->snapshot('elevate','2026-06')->campus);
        $this->assertEmpty(array_filter($result['issues'],fn ($issue) => $issue['unit']==='elevate'));
        // The supplied group totals are not invented leaf-unit evidence.
        $this->assertContains('incomplete_children',array_column($result['issues'],'code'));
        $this->assertSame(8,UnitSnapshot::count());
    }

    public function test_complete_children_mismatch_is_printed_and_not_silently_fixed(): void
    {
        $result=$this->import("unit,checkpoint,dleaders\nMindanao,2026-06,20\nMindanao North,2026-06,1\nMindanao North East,2026-06,2\nMindanao North West,2026-06,3\nMindanao South,2026-06,4\n",'NCP');
        $issue=collect($result['issues'])->firstWhere('code','parent_mismatch');
        $this->assertSame(20,$issue['reported']); $this->assertSame(10,$issue['children_sum']);
        $this->assertSame(20,$this->snapshot('ncp.mindanao','2026-06')->dleaders);
    }

    public function test_invalid_data_aborts_the_whole_batch_instead_of_partially_importing(): void
    {
        $file=$this->csv("unit,checkpoint,dleaders,campus\nAsia,2025-12,1,-\nEurope,2025-12,2,0\n");
        $this->artisan('analytics:import',['--icp'=>$file,'--actor'=>$this->admin->id])->assertFailed();
        $this->assertSame(0,UnitSnapshot::count());
        $this->assertSame(0,DB::table('analytics_imports')->count());
    }

    public function test_closed_snapshot_cannot_be_changed_by_import_or_direct_sql(): void
    {
        $this->import("unit,checkpoint,dleaders\nPasig,2025-12,3\n");
        $snapshot=$this->snapshot('main.pasig');
        $snapshot->update(['closed_by'=>$this->admin->id,'closed_at'=>now()]);
        $file=$this->csv("unit,checkpoint,dleaders\nPasig,2025-12,4\n");
        $this->artisan('analytics:import',['--main'=>$file,'--actor'=>$this->admin->id])->assertFailed();
        $this->assertSame(3,$snapshot->fresh()->dleaders);
        $this->expectException(QueryException::class);
        DB::table('unit_snapshots')->where('id',$snapshot->id)->update(['dleaders'=>999]);
    }

    public function test_closed_checkpoint_cannot_be_reopened_or_receive_new_imports(): void
    {
        $checkpoint=Checkpoint::where('key','2025-12')->first();
        $checkpoint->update(['status'=>'closed']);
        app(MovementAnalyticsSetup::class)->run();
        $this->assertSame('closed',$checkpoint->fresh()->status);
        $file=$this->csv("unit,checkpoint,dleaders\nPasig,2025-12,4\n");
        $this->artisan('analytics:import',['--main'=>$file,'--actor'=>$this->admin->id])->assertFailed();
        $this->expectException(QueryException::class);
        DB::table('checkpoints')->where('id',$checkpoint->id)->update(['status'=>'open']);
    }

    public function test_only_assigned_leader_can_append_status_and_original_member_row_is_untouched(): void
    {
        $satellite=Satellite::create(['name'=>'Pasig','slug'=>'pasig','hub'=>'MAIN']);
        $member=Member::create(['name'=>'Synthetic member','satellite_id'=>$satellite->id,'status'=>'New']);
        $before=$member->fresh()->getRawOriginal();
        $data=['status'=>'Active','effective_at'=>now()->subMinute()->toDateTimeString(),'expected_version'=>0,'reason'=>'Leader confirmed participation'];
        foreach (['admin','staff','member'] as $role) {
            $actor=User::factory()->create(['role'=>$role,'district'=>'Pasig']);
            $this->actingAs($actor)->postJson(route('analytics.members.status.store',$member),$data)->assertForbidden();
        }
        $outside=User::factory()->create(['role'=>'leader','district'=>'Elsewhere']);
        $this->actingAs($outside)->postJson(route('analytics.members.status.store',$member),$data)->assertForbidden();
        $leader=User::factory()->create(['role'=>'leader','district'=>'Pasig']);
        $this->actingAs($leader)->postJson(route('analytics.members.status.store',$member),$data)->assertCreated()->assertJsonPath('version',1);
        $event=MemberStatusHistory::firstOrFail();
        $this->assertNull($event->old_status); $this->assertSame($leader->id,$event->actor_id);
        $this->assertSame($before,$member->fresh()->getRawOriginal());
        $this->postJson(route('analytics.members.status.store',$member),$data)->assertUnprocessable();
        $this->assertSame(1,MemberStatusHistory::count());
        $this->expectException(QueryException::class);
        DB::table('member_status_history')->where('id',$event->id)->delete();
    }

    public function test_all_confirmed_definitions_are_configuration_not_inferred_from_activity(): void
    {
        $this->assertTrue(config('movement_analytics.definitions.dmembers_include_leaders'));
        $this->assertSame('leader_reported_totals',config('movement_analytics.definitions.campus_basis'));
        $this->assertSame('joined_as_member',config('movement_analytics.definitions.placement_basis'));
        $this->assertSame(3,config('movement_analytics.definitions.new_leader_minimum_members'));
        $this->assertFalse(config('movement_analytics.definitions.new_leaders_include_reactivations'));
    }
}
