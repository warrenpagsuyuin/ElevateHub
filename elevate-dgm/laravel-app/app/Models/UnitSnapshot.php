<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitSnapshot extends Model
{
    protected $guarded = [];
    protected function casts(): array
    {
        return ['metric_states'=>'array','quality_flags'=>'array','conflict'=>'boolean','closed_at'=>'datetime','reported_at'=>'datetime'];
    }
    public function unit() { return $this->belongsTo(OrgUnit::class, 'unit_id'); }
    public function checkpoint() { return $this->belongsTo(Checkpoint::class); }
    protected static function booted(): void
    {
        foreach (['saving','deleting'] as $event) static::$event(function (self $snapshot) {
            if (($snapshot->exists && $snapshot->getOriginal('closed_at')) || Checkpoint::whereKey($snapshot->checkpoint_id)->where('status','closed')->exists()) {
                throw new \DomainException('Closed snapshots are immutable.');
            }
        });
    }
}
