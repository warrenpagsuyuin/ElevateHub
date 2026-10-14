<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Checkpoint extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['as_of_date'=>'date']; }
    protected static function booted(): void
    {
        foreach (['updating','deleting'] as $event) static::$event(function (self $checkpoint) {
            if ($checkpoint->getOriginal('status') === 'closed') throw new \DomainException('Closed checkpoints are immutable.');
        });
    }
}
