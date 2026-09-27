<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EngagementResult extends Model
{
    protected $guarded = [];

    protected $fillable = [
        'member_id',
        'classification',
        'confidence',
        'metrics',
        'predicted_attendance',
        'model_name',
        'model_version',
        'decision_path',
        'used_features',
        'explanation',
        'data_sufficiency',
        'generated_by_id',
        'generated_at',
        'human_review_required',
        'model_metadata',
        'engagement_model_run_id',
    ];

    protected function casts(): array
    {
        return [
            'metrics' => 'json',
            'decision_path' => 'json',
            'used_features' => 'json',
            'model_metadata' => 'json',
            'confidence' => 'float',
            'predicted_attendance' => 'float',
            'generated_at' => 'datetime',
            'human_review_required' => 'bool',
            'engagement_model_run_id' => 'integer',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_id');
    }

    public function modelRun(): BelongsTo
    {
        return $this->belongsTo(EngagementModelRun::class, 'engagement_model_run_id');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    public function getClassificationLabelAttribute(): string
    {
        return match ($this->classification) {
            'High', 'Active' => 'Active',
            'Medium', 'Moderately Active' => 'Moderately Active',
            'Low', 'Possible Need for Follow-Up' => 'Possible Need for Follow-Up',
            'Inactive', 'Insufficient information for a reliable prediction.' => 'Insufficient Information',
            default => 'Unknown',
        };
    }
}
