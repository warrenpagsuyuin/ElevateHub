<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recommendation extends Model
{
    public const STATUS_PENDING = 'Pending Review';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_MODIFIED = 'Modified';
    public const STATUS_REJECTED = 'Rejected';
    public const STATUS_EXPIRED = 'Expired';

    public const REVIEW_STATUSES = [
        self::STATUS_APPROVED,
        self::STATUS_MODIFIED,
        self::STATUS_REJECTED,
    ];

    public const STATUS_OPTIONS = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_MODIFIED,
        self::STATUS_REJECTED,
        self::STATUS_EXPIRED,
    ];

    protected $fillable = [
        'member_id',
        'engagement_result_id',
        'recommended_action_id',
        'assigned_by',
        'assigned_to',
        'status',
        'action_title_snapshot',
        'action_description_snapshot',
        'assignment_notes',
        'review_notes',
        'reviewed_by',
        'expires_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function engagementResult(): BelongsTo
    {
        return $this->belongsTo(EngagementResult::class);
    }

    public function recommendedAction(): BelongsTo
    {
        return $this->belongsTo(RecommendationAction::class, 'recommended_action_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeForSatellite(Builder $query, Satellite $satellite): Builder
    {
        return $query->whereHas('member', fn (Builder $query): Builder => $query->where('satellite_id', $satellite->id));
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $this->isAdmin($user)
            ? $query
            : $query->where('assigned_to', $user->id);
    }

    public function getEffectiveStatusAttribute(): string
    {
        return $this->is_expired ? self::STATUS_EXPIRED : $this->status;
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING && ! $this->is_expired;
    }

    public function getIsReviewableAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_MODIFIED], true) && ! $this->is_expired;
    }

    public function getIsExpiredAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_MODIFIED], true)
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function getActionTitleAttribute(): string
    {
        return $this->action_title_snapshot ?: ($this->recommendedAction?->title ?? 'Recommendation action');
    }

    public function getActionDescriptionAttribute(): string
    {
        return $this->action_description_snapshot ?: ($this->recommendedAction?->description ?? 'No action description recorded.');
    }

    private function isAdmin(User $user): bool
    {
        return str_replace([' ', '-'], '_', strtolower((string) ($user->role ?: 'member'))) === 'admin';
    }
}
