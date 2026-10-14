<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrgUnit extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['has_campus'=>'boolean','sort'=>'integer']; }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id')->orderBy('sort'); }
    public function satellites() { return $this->belongsToMany(Satellite::class, 'org_unit_satellite_mappings', 'unit_id', 'satellite_id'); }
    public function snapshots() { return $this->hasMany(UnitSnapshot::class, 'unit_id'); }
}
