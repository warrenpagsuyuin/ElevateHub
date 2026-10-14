<?php
namespace App\Console\Commands;

use App\Models\User;
use App\Services\MovementSnapshotImport;
use Illuminate\Console\Command;

class ImportMovementSnapshots extends Command
{
    protected $signature='analytics:import {--main= : MAIN Data CSV} {--ncp= : NCP Data CSV} {--icp= : ICP Data CSV} {--actor= : Admin user ID recorded as importer} {--dry-run : Validate and reconcile without writing}';
    protected $description='Import attributed movement snapshots, preserving blanks, N/A and conflicting reports';
    public function handle(MovementSnapshotImport $import): int
    {
        $files=[];
        foreach (['MAIN','NCP','ICP'] as $group) if ($path=$this->option(strtolower($group))) $files[$group]=$path;
        try { $result=$import->run($files,$this->option('actor') ? User::find($this->option('actor')) : null,(bool)$this->option('dry-run')); }
        catch (\DomainException $error) { $this->error($error->getMessage()); return self::FAILURE; }
        $this->info($result['unchanged'] ? 'Unchanged: this exact import already exists.' : ($this->option('dry-run') ? 'Dry run: ' : 'Imported: ').$result['rows'].' rows; '.$result['snapshots'].' snapshots.');
        foreach ($result['issues'] as $issue) $this->warn(json_encode($issue,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        if ($result['issues']) { $this->warn('Unresolved data-quality issues remain. No values were silently reconciled.'); return 2; }
        return self::SUCCESS;
    }
}
