<?php

namespace App\Console\Commands;

use App\Services\LeaderReconciliationService;
use Illuminate\Console\Command;

class ReconcileMemberLeaders extends Command
{
    protected $signature = 'members:reconcile-leaders {manifest : Private approved JSON manifest} {--apply} {--approved-hash=}';

    protected $description = 'Review approved member/leader pairs; apply only with explicit manifest hash';

    public function handle(LeaderReconciliationService $service): int
    {
        $text = file_get_contents($this->argument('manifest'));
        $manifest = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        $pairs = $manifest['pairs'];
        $hash = hash('sha256', $text);
        $this->table(['Member ID', 'Member', 'Leader ID', 'Leader', 'Satellite ID'], array_map(fn ($p) => [$p['member_id'], $p['member_name'], $p['leader_id'], $p['leader_name'], $p['satellite_id']], $pairs));
        $this->info(count($pairs).' approved pair(s); SHA256 '.$hash);
        if (! $this->option('apply')) {
            $this->info('Preview only. No writes.');

            return self::SUCCESS;
        }
        if (! hash_equals($hash, (string) $this->option('approved-hash'))) {
            $this->error('Approval hash does not match.');

            return self::FAILURE;
        }
        $this->info($service->apply($pairs).' link(s) applied.');

        return self::SUCCESS;
    }
}
