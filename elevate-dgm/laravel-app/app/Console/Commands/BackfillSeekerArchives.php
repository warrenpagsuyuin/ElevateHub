<?php

namespace App\Console\Commands;

use App\Models\Seeker;
use App\Models\User;
use App\Services\SeekerArchiveService;
use Illuminate\Console\Command;

class BackfillSeekerArchives extends Command
{
    protected $signature = 'seekers:archive-backfill {--apply : Apply after explicit approval} {--actor= : Admin user ID for the audit trail}';

    protected $description = 'Preview final-status seekers requiring archive; read-only unless --apply is supplied';

    public function handle(SeekerArchiveService $service): int
    {
        $rows = Seeker::whereIn('status', config('seekers.final_statuses'))->whereNull('archived_at')->orderBy('id')->get();
        $this->table(['ID', 'Name', 'Status'], $rows->map(fn ($row) => [$row->id, $row->name, $row->status])->all());
        $this->info($rows->count().' candidate(s).');
        if (! $this->option('apply')) {
            $this->info('Dry run: no changes made. Obtain approval before using --apply --actor=ID.');

            return self::SUCCESS;
        }
        $actor = User::find($this->option('actor'));
        if (! $actor || $actor->normalizedRole() !== 'admin') {
            $this->error('An existing admin --actor is required.');

            return self::FAILURE;
        }
        foreach ($rows as $row) {
            $service->archive($row, $actor, 'Approved final-status backfill.', true);
        }
        $this->info('Backfill complete. Existing archives were left unchanged.');

        return self::SUCCESS;
    }
}
