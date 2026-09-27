<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecommendationAction extends Model
{
    protected $fillable = [
        'title',
        'description',
        'condition_metadata',
        'is_active',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'condition_metadata' => 'json',
            'is_active' => 'bool',
            'approved_at' => 'datetime',
        ];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class, 'recommended_action_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->is_active ? 'Approved / Active' : 'Draft / Inactive';
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNotNull('approved_by')->whereNotNull('approved_at');
    }

    public function supportsResult(EngagementResult $result): bool
    {
        if (! $this->is_active || $this->approved_by === null || $this->approved_at === null) {
            return false;
        }

        $conditions = is_array($this->condition_metadata) ? $this->condition_metadata : [];
        $classification = $conditions['classification'] ?? null;
        if ($classification !== null && ! in_array($result->classification, (array) $classification, true)) {
            return false;
        }

        $insufficient = in_array($result->classification, ['Inactive', \App\Services\EngagementClassificationService::CLASSIFICATION_INSUFFICIENT], true)
            || str_starts_with((string) $result->data_sufficiency, 'insufficient');

        // Incomplete evidence can only prompt a reviewed request to validate records.
        return ! $insufficient || ($conditions['purpose'] ?? null) === 'data_validation';
    }
}
