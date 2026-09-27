<?php
namespace App\Services;

use App\Models\EngagementModelRun;
use Illuminate\Support\Facades\DB;

class ThesisEvidenceService
{
    public function report(): array
    {
        $sentiment = app(ThesisSentimentEvidenceService::class)->report();
        foreach (['table11','table18_19','table19_20','same_evaluation_records','accuracy'] as $check) {
            if (!$sentiment['validations'][$check]) throw new \RuntimeException('Evidence reconciliation failed: '.$check);
        }
        $verification = app(ThesisEvidenceVerification::class);
        $run = $verification->report();
        $counts = [];
        foreach (['feedback','feedback_analysis_units','sentiment_evaluation_runs','sentiment_research_audits','engagement_results','engagement_model_runs','recommendation_actions','recommendations','engagement_research_audits','members','seekers','follow_ups','events','attendance'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }
        return ['generated_at'=>gmdate('c'), 'privacy'=>'No names, contacts, answers, or free-text audit notes are exported. Codes are pseudonymous; keep reports within authorized research access.',
            'sentiment'=>$sentiment, 'survey'=>app(ThesisSurveyEvidenceService::class)->report(),
            'table13'=>$verification->conditions('table13', $run), 'table14'=>$verification->conditions('table14', $run),
            'verification'=>$run, 'database_counts'=>$counts,
            'cart_preserved'=>EngagementModelRun::where('is_active',true)->get(['model_version','dataset_version','evaluation_status','trained_at','holdout_metrics'])->toArray(),
            'method'=>'Read-only SELECTs and in-memory fixed lexicon predictions. No synchronization, training, seeding, or research updates.',
        ];
    }
}
