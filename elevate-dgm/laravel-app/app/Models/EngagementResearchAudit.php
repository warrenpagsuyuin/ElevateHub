<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EngagementResearchAudit extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['before_state' => 'array', 'after_state' => 'array', 'created_at' => 'datetime'];
    }
}
