<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EngagementModelRun extends Model
{
    protected $fillable = [
        'satellite_id',
        'model_name',
        'model_version',
        'training_signature',
        'training_config',
        'split_summary',
        'label_distribution',
        'validation_metrics',
        'holdout_metrics',
        'tree',
        'trained_at',
        'is_active',
        'dataset_version',
        'evaluation_status',
    ];

    protected function casts(): array
    {
        return [
            'training_config' => 'json',
            'split_summary' => 'json',
            'label_distribution' => 'json',
            'validation_metrics' => 'json',
            'holdout_metrics' => 'json',
            'tree' => 'json',
            'trained_at' => 'datetime',
            'is_active' => 'bool',
        ];
    }

    public function satellite(): BelongsTo
    {
        return $this->belongsTo(Satellite::class)->withoutGlobalScope('active');
    }

    public function engagementResults(): HasMany
    {
        return $this->hasMany(EngagementResult::class);
    }
}
