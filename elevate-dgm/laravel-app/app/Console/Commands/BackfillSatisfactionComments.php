<?php

namespace App\Console\Commands;

use App\Services\SatisfactionCommentService;
use Illuminate\Console\Command;

class BackfillSatisfactionComments extends Command
{
    protected $signature = 'satisfaction:backfill-comments {--all : Re-analyze all comments even if already analyzed}';

    protected $description = 'Back-fill sentiment predictions for satisfaction comments that lack one or were analyzed with an older model version';

    public function handle(SatisfactionCommentService $service): int
    {
        $all = (bool) $this->option('all');
        $this->info($all ? 'Re-analyzing all satisfaction comments...' : 'Analyzing pending satisfaction comments...');

        $result = $service->analyzePending($all);

        $this->info("Completed: {$result['analyzed']} analyzed, {$result['failed']} failed.");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
