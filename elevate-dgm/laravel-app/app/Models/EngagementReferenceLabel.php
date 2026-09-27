<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngagementReferenceLabel extends Model
{
    protected $guarded = [];

    protected $fillable = [
        'member_id',
        'labeled_by_id',
        'label',
        'source',
        'notes',
        'satellite_id', 'observation_start', 'observation_end', 'reference_label',
        'review_status', 'reference_source', 'verification_status', 'reason',
        'reviewed_by', 'reviewed_at', 'dataset_version', 'partition', 'feature_snapshot', 'review_revision',
    ];

    protected function casts(): array
    {
        return ['feature_snapshot' => 'array', 'reviewed_at' => 'datetime', 'observation_start' => 'date', 'observation_end' => 'date', 'review_revision' => 'integer'];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function audits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EngagementResearchAudit::class, 'reference_label_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function labeler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'labeled_by_id');
    }
}
