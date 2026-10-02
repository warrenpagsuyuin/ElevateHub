<?php
namespace Tests\Feature;

use App\Services\ThesisEvidenceVerification;
use Tests\TestCase;

class ThesisEvidenceVerificationTest extends TestCase
{
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
