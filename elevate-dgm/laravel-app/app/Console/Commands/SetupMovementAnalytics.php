<?php
namespace App\Console\Commands;

use App\Services\MovementAnalyticsSetup;
use Illuminate\Console\Command;

class SetupMovementAnalytics extends Command
{
    protected $signature = 'analytics:setup {--dry-run : Validate configuration and list mappings without writing}';
    protected $description = 'Seed movement structure and known conflicts, never reported counts or member statuses';
    public function handle(MovementAnalyticsSetup $setup): int
    {
        try { $result = $setup->run((bool) $this->option('dry-run')); }
        catch (\DomainException $error) { $this->error($error->getMessage()); return self::FAILURE; }
        $this->info(($this->option('dry-run') ? 'Dry run: ' : 'Configured: ').$result['units'].' units, '.$result['checkpoints'].' checkpoints. No reported counts seeded.');
        $this->table(['Unmapped satellite ID','Name','Slug'], $result['unmapped']);
        return self::SUCCESS;
    }
}
