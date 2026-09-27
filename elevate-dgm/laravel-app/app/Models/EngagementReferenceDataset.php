<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EngagementReferenceDataset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['split_summary' => 'array', 'observation_start' => 'date', 'observation_end' => 'date', 'finalized_at' => 'datetime', 'revision' => 'integer', 'seed' => 'integer'];
    }

    public function references(): HasMany
    {
        return $this->hasMany(EngagementReferenceLabel::class, 'dataset_version', 'version');
    }

    public function activeModelRun(): BelongsTo
    {
        return $this->belongsTo(EngagementModelRun::class, 'active_model_run_id');
    }

    public function getRevisionVersionAttribute(): string
    {
        return $this->version.'-r'.$this->revision;
    }
}
