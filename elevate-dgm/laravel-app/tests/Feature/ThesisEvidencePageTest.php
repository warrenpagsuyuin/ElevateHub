<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\ThesisEvidenceService;
use App\Services\ThesisEvidenceVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ThesisEvidencePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_and_all_exports_are_admin_only(): void
    {
        foreach (['/admin/research/thesis-evidence','/admin/research/thesis-evidence/export','/admin/research/thesis-evidence/export?format=json'] as $url) $this->get($url)->assertRedirect('/');
        foreach (['member','district','satellite_user'] as $role) {
            $this->actingAs(User::factory()->create(['role'=>$role]));
            foreach (['/admin/research/thesis-evidence','/admin/research/thesis-evidence/export','/admin/research/thesis-evidence/export?format=json'] as $url) $this->get($url)->assertForbidden();
        }
    }

    public function test_admin_can_render_all_tables_and_export_without_writing_research_rows(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin','name'=>'PRIVATE ADMIN NAME']));
        config(['thesis_evidence.verification_path'=>storage_path('framework/testing/nonexistent-verification.json')]);
        $queries=[];
        DB::listen(function($query) use (&$queries){$queries[]=$query->sql;});
        $this->get('/admin/research/thesis-evidence')->assertOk()->assertSee('Export Thesis Evidence Report')->assertSee('HUMAN THEMATIC CODING REQUIRED');
        $html=$this->get('/admin/research/thesis-evidence/export')->assertOk()->assertDontSee('PRIVATE ADMIN NAME');
        foreach ([11,12,13,14,18,19,20,22,23] as $table) $html->assertSee('Table '.$table);
        $this->getJson('/admin/research/thesis-evidence/export?format=json')->assertOk()
            ->assertJsonPath('sentiment.counts.evaluation_n',0)->assertJsonPath('sentiment.table19.accuracy',null)
            ->assertJsonPath('verification.current',false)->assertDontSee('PRIVATE ADMIN NAME');
        $writes=array_filter($queries,fn($sql)=>preg_match('/^\s*(insert|update|delete|replace|create|alter|drop)\b/i',$sql));
        $this->assertSame([],array_values($writes));
    }

    public function test_single_section_export_and_invalid_inputs(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $this->get('/admin/research/thesis-evidence/export?table=22')->assertOk()->assertSee('Table 22')->assertDontSee('Table 13');
        $this->getJson('/admin/research/thesis-evidence/export?table=15')->assertUnprocessable();
        $this->getJson('/admin/research/thesis-evidence/export?format=sql')->assertUnprocessable();
    }

    public function test_missing_stale_and_failed_verification_never_claim_pass(): void
    {
        $verifier=app(ThesisEvidenceVerification::class);
        $path=tempnam(sys_get_temp_dir(),'thesis-test-');
        config(['thesis_evidence.verification_path'=>$path]);
        try {
            file_put_contents($path,'{}');
            $this->assertFalse($verifier->report()['current']);
            file_put_contents($path,json_encode(['source_sha256'=>'stale','status'=>'PASS','tests'=>[]]));
            $this->assertFalse($verifier->report()['current']);
            $rows=$verifier->conditions('table13',$verifier->report());
            $this->assertCount(6,$rows);
            $this->assertSame(['UNTESTED'],array_values(array_unique(array_column($rows,'status'))));
            $name=$rows[0]['tests'][0];
            $failed=$verifier->conditions('table13',['current'=>true,'tests'=>[$name=>['status'=>'FAIL']],'reason'=>'fixture']);
            $this->assertSame('FAIL',$failed[0]['status']);
        } finally {unlink($path);}
    }
}
