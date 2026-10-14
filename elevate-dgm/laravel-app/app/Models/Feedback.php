<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Feedback extends Model
{
    public const SENTIMENT_POSITIVE = 'Positive';
    public const SENTIMENT_NEUTRAL = 'Neutral';
    public const SENTIMENT_NEGATIVE = 'Negative';

    public const SENTIMENTS = [
        self::SENTIMENT_POSITIVE,
        self::SENTIMENT_NEUTRAL,
        self::SENTIMENT_NEGATIVE,
    ];

    protected $table = 'feedback';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'feedback_payload' => 'array',
            'sentiment_metrics' => 'array',
            'sentiment_score' => 'float',
            'sentiment_confidence' => 'float',
            'sentiment_analyzed_at' => 'datetime',
            'human_labeled_at' => 'datetime',
            'research_consented_at' => 'datetime',
            'consent_corrected_at' => 'datetime',
        ];
    }

    public function getArchivedSatelliteAttribute(): ?Satellite
    {
        $user = $this->evaluator;
        return $user ? Satellite::archived()->where('name', $user->district)->first() : null;
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_user_id');
    }

    public function humanLabeler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'human_labeled_by_id');
    }

    public function getSentimentClassAttribute(): string
    {
        return Str::slug($this->sentiment ?: 'unanalyzed');
    }
}
