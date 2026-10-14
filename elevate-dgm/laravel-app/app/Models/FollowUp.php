<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FollowUp extends Model
{
    public const ORIGIN_LEADER_ASSIGNMENT = 'leader_assignment';

    public const STATUS_PENDING = 'Pending';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    public const METHOD_PHONE = 'Phone';
    public const METHOD_EMAIL = 'Email';
    public const METHOD_IN_PERSON = 'In-Person';
    public const METHOD_OTHER = 'Other';

    public const METHODS = [
        self::METHOD_PHONE,
        self::METHOD_EMAIL,
        self::METHOD_IN_PERSON,
        self::METHOD_OTHER,
    ];

    public const SENTIMENT_POSITIVE = 'Positive';
    public const SENTIMENT_NEUTRAL = 'Neutral';
    public const SENTIMENT_NEGATIVE = 'Negative';

    public const SENTIMENTS = [
        self::SENTIMENT_POSITIVE,
        self::SENTIMENT_NEUTRAL,
        self::SENTIMENT_NEGATIVE,
    ];

    protected $fillable = [
        'seeker_id',
        'member_id',
        'satellite_id',
        'leader_member_id',
        'origin',
        'created_by_id',
        'follow_up_date',
        'method',
        'notes',
        'status',
        'sentiment_label',
        'sentiment_score',
        'sentiment_confidence',
        'sentiment_model',
        'sentiment_model_version',
        'sentiment_metrics',
        'sentiment_analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_date' => 'date',
            'sentiment_score' => 'float',
            'sentiment_confidence' => 'float',
            'sentiment_metrics' => 'array',
            'sentiment_analyzed_at' => 'datetime',
        ];
    }

    public function seeker(): BelongsTo
    {
        return $this->belongsTo(Seeker::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'leader_member_id');
    }

    public function satellite(): BelongsTo
    {
        return $this->belongsTo(Satellite::class)->withoutGlobalScope('active');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function getStatusClassAttribute(): string
    {
        return Str::slug($this->status ?: self::STATUS_PENDING);
    }

    public function getSentimentClassAttribute(): string
    {
        return Str::slug($this->sentiment_label ?: 'unanalyzed');
    }

    public static function normalizeStatus(?string $status): string
    {
        $normalized = Str::of((string) $status)
            ->trim()
            ->lower()
            ->replace(['_', '-'], ' ')
            ->title()
            ->toString();

        return in_array($normalized, self::STATUSES, true)
            ? $normalized
            : self::STATUS_PENDING;
    }
}
