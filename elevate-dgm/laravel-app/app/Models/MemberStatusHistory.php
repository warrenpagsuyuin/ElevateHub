<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberStatusHistory extends Model
{
    protected $table = 'member_status_history';
    public $timestamps = false;
    protected $guarded = [];
    protected function casts(): array { return ['effective_at'=>'datetime','recorded_at'=>'datetime','version'=>'integer']; }
    protected static function booted(): void
    {
        foreach (['updating','deleting'] as $event) static::$event(fn () => throw new \DomainException('Status history is append-only.'));
    }
}
