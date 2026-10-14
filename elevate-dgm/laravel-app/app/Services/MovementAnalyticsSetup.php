<?php
namespace App\Services;

use App\Models\Checkpoint;
use App\Models\OrgUnit;
use App\Models\Satellite;
use Illuminate\Support\Facades\DB;

class MovementAnalyticsSetup
{
    public function run(bool $dryRun = false): array
    {
        $definitions = config('movement_analytics.units');
        $keys = [];
        foreach ($definitions as $row) {
            if (isset($keys[$row['key']]) || ($row['parent'] !== null && !isset($keys[$row['parent']]))) {
                throw new \DomainException('Hierarchy requires unique keys, with parents before children.');
            }
            $keys[$row['key']] = true;
        }
        $mappings = config('movement_analytics.satellite_mappings');
        foreach ($mappings as $key) if (!isset($keys[$key])) throw new \DomainException('Unknown mapped unit: '.$key);
        $unmapped = Satellite::whereNotIn('slug', array_keys($mappings))->orderBy('name')->get(['id','name','slug'])->toArray();
        if ($dryRun) return ['units'=>count($definitions),'checkpoints'=>count(config('movement_analytics.checkpoints')),'unmapped'=>$unmapped];
        return DB::transaction(function () use ($definitions, $mappings, $unmapped) {
            $units = [];
            foreach ($definitions as $sort => $row) {
                $attributes = array_diff_key($row, ['parent'=>true]);
                $attributes['parent_id'] = $row['parent'] === null ? null : $units[$row['parent']]->id;
                $attributes['sort'] = $sort;
                $existing = OrgUnit::where('key',$row['key'])->first();
                if ($existing && UnitSnapshotGuard::hasClosedHistory($existing->id) && $existing->only(array_keys($attributes)) != $attributes) {
                    throw new \DomainException('Cannot redefine a unit with closed reporting history: '.$row['key']);
                }
                $units[$row['key']] = OrgUnit::updateOrCreate(['key'=>$row['key']], $attributes);
            }
            foreach (config('movement_analytics.checkpoints') as $row) {
                $checkpoint = Checkpoint::firstOrCreate(['key'=>$row['key']], $row);
                foreach (['as_of_date','kind','label'] as $field) {
                    $value = $field === 'as_of_date' ? $checkpoint->as_of_date->toDateString() : $checkpoint->$field;
                    if ($value !== $row[$field]) throw new \DomainException('Existing checkpoint differs from configuration: '.$row['key']);
                }
                // Never reopen a checkpoint from configuration.
            }
            foreach (Satellite::whereIn('slug',array_keys($mappings))->get() as $satellite) {
                $unitId = $units[$mappings[$satellite->slug]]->id;
                $existing = DB::table('org_unit_satellite_mappings')->where('satellite_id',$satellite->id)->first();
                if ($existing && $existing->unit_id !== $unitId) throw new \DomainException('Satellite remapping requires an explicit historical migration: '.$satellite->slug);
                if (!$existing) DB::table('org_unit_satellite_mappings')->insert(['satellite_id'=>$satellite->id,'unit_id'=>$unitId,'created_at'=>now(),'updated_at'=>now()]);
            }
            foreach (config('movement_analytics.declared_conflicts') as $conflict) {
                $checkpoint = Checkpoint::where('key',$conflict['checkpoint'])->firstOrFail();
                DB::table('analytics_data_issues')->insertOrIgnore([
                    'fingerprint'=>hash('sha256',json_encode(['declared_conflict',$units[$conflict['unit']]->id,$checkpoint->id,null])), 'code'=>'declared_conflict',
                    'unit_id'=>$units[$conflict['unit']]->id,'checkpoint_id'=>$checkpoint->id,
                    'reason'=>$conflict['reason'],'details'=>json_encode($conflict,JSON_THROW_ON_ERROR),
                    'status'=>'open','created_at'=>now(),'updated_at'=>now(),
                ]);
            }
            return ['units'=>count($units),'checkpoints'=>Checkpoint::count(),'unmapped'=>$unmapped];
        });
    }
}
