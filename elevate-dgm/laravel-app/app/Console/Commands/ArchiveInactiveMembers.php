<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Satellite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveInactiveMembers extends Command
{
    protected $signature = 'members:archive-inactive';

    protected $description = 'Archive existing members whose status is Inactive and refresh active satellite totals';

    public function handle(): int
    {
        $count = DB::table('members')
            ->whereNull('archived_at')
            ->whereRaw('LOWER(TRIM(status)) = ?', ['inactive'])
            ->update(['archived_at' => now(), 'updated_at' => now()]);

        Member::refreshSatelliteCountsFor(Satellite::query()->pluck('id')->all());

        $this->info("Archived {$count} inactive member".($count === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}