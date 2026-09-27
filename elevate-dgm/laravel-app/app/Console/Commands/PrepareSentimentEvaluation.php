<?php

namespace App\Console\Commands;

use App\Services\SentimentResearchService;
use App\Services\SentimentResearchExportService;
use App\Services\SentimentResearchReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PrepareSentimentEvaluation extends Command
{
    protected $signature='sentiment:prepare-evaluation {--recognize-existing : Recognize stored reviewer labels without changing their values or claiming blinding} {--predict : Predict eligible human-reviewed units with the fixed engine} {--export : Save research-safe tables and workbook under private storage}';
    protected $description='Import actual system feedback for eligibility review and report available sentiment evaluation data; never infer consent or human labels';
    public function handle(SentimentResearchService $service): int
    {
        $created=$service->synchronize();
        if ($this->option('recognize-existing')) $this->info('Existing human references recognized: '.implode(', ',$service->recognizeExistingHumanLabels()));
        if ($this->option('predict')) $this->line(json_encode($service->predict(),JSON_THROW_ON_ERROR));
        $report=$service->report();
        $markdown=app(SentimentResearchReportService::class)->markdown($report);
        if ($this->option('export')) {
            $path=storage_path('app/private/sentiment-research');
            File::ensureDirectoryExists($path);
            File::put($path.'/Sentiment-Evaluation.xlsx',app(SentimentResearchExportService::class)->bytes($report));
            File::put($path.'/Chapter-3-Sentiment-Tables.md',$markdown);
            // Internal record snapshot stays in private storage; public workbook excludes identities.
            File::put($path.'/report.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        }
        $this->info("Imported $created new actual feedback records. No consent, human labels or language groups were generated.");
        $this->line($markdown);
        return self::SUCCESS;
    }
}
