<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\FeedbackAnalysisUnit;
use App\Models\User;
use App\Services\SentimentResearchService;
use App\Services\ThesisEvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ThesisEvidenceCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $output;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->output = storage_path('research/test-'.bin2hex(random_bytes(6)));
        config(['thesis_evidence.verification_path'=>storage_path('framework/testing/missing-evidence.json')]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->output.'/*') ?: [] as $file) unlink($file);
        if (is_dir($this->output)) rmdir($this->output);
        parent::tearDown();
    }

    public function test_exports_preserve_metrics_and_content_without_database_writes_or_private_data(): void
    {
        $admin = User::factory()->create(['role'=>'admin','name'=>'PRIVATE PERSON','email'=>'private-person@example.test']);
        DB::table('sentiment_research_settings')->insert([
            'period_start'=>'2026-09-01','period_end'=>'2026-09-30','approved_by'=>$admin->id,
            'approved_at'=>'2026-09-01 08:00:00','approval_reason'=>'PRIVATE APPROVAL NOTE',
        ]);
        $text = 'The system is useful. PRIVATE RESPONSE';
        $feedback = Feedback::create([
            'message'=>$text,'evaluator_user_id'=>$admin->id,'created_at'=>'2026-09-05 12:00:00',
            'research_consent_status'=>'Granted','research_consent_source'=>'PRIVATE CONSENT SOURCE',
            'research_consent_evidence'=>'PRIVATE CONSENT EVIDENCE','research_consented_at'=>'2026-09-05 12:01:00',
        ]);
        $unit = FeedbackAnalysisUnit::create([
            'feedback_id'=>$feedback->id,'source_key'=>'test:'.$feedback->id,'feedback_code'=>'PRIVATE_CODE',
            'feedback_text'=>$text,'text_hash'=>hash('sha256', mb_strtolower($text)),
            'scope_status'=>'System Feedback','is_test_record'=>false,'scope_reviewed_by'=>$admin->id,'scope_reviewed_at'=>'2026-09-06 08:00:00',
            'language_group'=>'English','language_review_status'=>'Final','language_reviewed_by'=>$admin->id,'language_reviewed_at'=>'2026-09-06 08:00:00',
            'human_reference_sentiment'=>'Positive','human_review_status'=>'Final','human_reviewed_by'=>$admin->id,'human_reviewed_at'=>'2026-09-06 08:00:00',
            'human_label_reason'=>'PRIVATE REVIEW NOTE','reference_source'=>'Human Reviewer','independent_attested'=>true,
            'review_independence'=>'Blinded','quote_approved'=>true,
        ]);
        $before = app(ThesisEvidenceService::class)->report();
        $operational = app(SentimentResearchService::class)->report();
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        foreach (['json','md'] as $format) {
            $this->artisan('research:thesis-evidence', ['--format'=>$format,'--output'=>$this->output])->assertSuccessful();
        }
        $this->assertNotEmpty($queries);
        foreach ($queries as $sql) $this->assertMatchesRegularExpression('/^\s*select\b/i', $sql);
        $json = file_get_contents(glob($this->output.'/*.json')[0]);
        $markdown = file_get_contents(glob($this->output.'/*.md')[0]);
        $report = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        foreach (['PRIVATE', 'private-person@example.test', 'review_url', '/research/units/'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
            $this->assertStringNotContainsString($secret, $markdown);
        }
        $this->assertSame(1, $report['sentiment']['counts']['evaluation_n']);
        foreach (['counts','table11','table18','validations'] as $key) {
            $this->assertSame($before['sentiment'][$key], $report['sentiment'][$key]);
        }
        $this->assertEquals($before['sentiment']['table19']['accuracy'], $report['sentiment']['table19']['accuracy']);
        $record = $report['sentiment']['records'][0];
        $this->assertMatchesRegularExpression('/^UNIT-[a-f0-9]{16}$/', $record['id']);
        $this->assertSame($record['id'], $record['feedback_code']);
        $this->assertSame([$record['id']], $report['sentiment']['table19']['record_ids']);
        $this->assertSame([$record['id']], $report['sentiment']['table11_ids']['English']['Positive']);
        $this->assertMatchesRegularExpression('/^SOURCE-[a-f0-9]{16}$/', $record['feedback_id']);
        foreach ([11,12,13,14,18,19,20,22,23] as $table) $this->assertStringContainsString('Table '.$table, $markdown);
        foreach (['A. Research Data Audit','K. Automated Test Results','L. Remaining Missing Research Data','M. Thesis-Ready Output'] as $section) {
            $this->assertStringContainsString($section, $markdown);
        }
        $this->assertSame(json_encode($operational), json_encode(app(SentimentResearchService::class)->report()));
        $this->assertNull($unit->fresh()->system_prediction);
    }

    public function test_production_requires_explicit_force(): void
    {
        app()->detectEnvironment(fn () => 'production');
        $this->artisan('research:thesis-evidence', ['--output'=>$this->output])->assertFailed();
        $this->assertDirectoryDoesNotExist($this->output);
        $this->artisan('research:thesis-evidence', ['--output'=>$this->output,'--force'=>true,'--format'=>'json'])->assertSuccessful();
    }

    public function test_invalid_formats_and_public_or_traversing_output_paths_are_rejected(): void
    {
        $this->artisan('research:thesis-evidence', ['--format'=>'html','--output'=>$this->output])->assertFailed();
        foreach (['public','storage/app/public','storage/app/private','storage/research/../../public'] as $path) {
            $this->artisan('research:thesis-evidence', ['--output'=>$path])->assertFailed();
        }
        $this->assertDirectoryDoesNotExist($this->output);
    }

    public function test_write_is_blocked_before_execution_and_error_does_not_disclose_source_text(): void
    {
        $feedback = Feedback::create(['message'=>'PRIVATE RESPONSE']);
        $this->mock(ThesisEvidenceService::class)->shouldReceive('report')->once()->andReturnUsing(function () use ($feedback) {
            DB::table('feedback')->where('id', $feedback->id)->update(['message'=>'MUTATED']);
            return [];
        });
        $this->artisan('research:thesis-evidence', ['--output'=>$this->output])
            ->expectsOutputToContain('source details were withheld')->assertFailed();
        $this->assertSame('PRIVATE RESPONSE', $feedback->fresh()->message);
        $this->assertDirectoryDoesNotExist($this->output);
    }
}
