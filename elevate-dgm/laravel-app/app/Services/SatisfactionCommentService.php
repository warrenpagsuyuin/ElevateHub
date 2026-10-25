<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Optional questionnaire comments: sanitizing, sentiment scoring, blind double review
 * and adjudication. Yes/No answers are never scored; only the free-text comment is.
 */
class SatisfactionCommentService
{
    public const MAX_LENGTH = 1000;
    public const LABELS = ['Positive', 'Neutral', 'Negative'];
    public const CANNOT_JUDGE = 'Cannot judge';
    public const REVIEW_LABELS = [...self::LABELS, self::CANNOT_JUDGE];
    public const REVIEWS_REQUIRED = 2;
    public const CACHE_VERSION_KEY = 'sentiment-evaluation:version';

    public function __construct(private SentimentAnalysisService $analyzer) {}

    /** Strip markup, normalize whitespace; blank input becomes null. Output is escaped by Blade. */
    public function sanitize(?string $comment): ?string
    {
        if ($comment === null) return null;
        $text = strip_tags($comment);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? '';
        $text = implode("\n", array_map('trim', explode("\n", $text)));
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? '';
        $text = trim($text);
        return $text === '' ? null : Str::limit($text, self::MAX_LENGTH, '');
    }

    /** Called inside the submission transaction. */
    public function insert(string $responseId, string $comment, bool $consent): string
    {
        $id = (string) Str::uuid();
        DB::table('satisfaction_comments')->insert([
            'id' => $id, 'response_id' => $responseId, 'comment' => $comment, 'research_consent' => $consent,
        ]);
        return $id;
    }

    /** Scores one comment. Never throws: failures are logged and leave the prediction null. */
    public function analyze(string $commentId): bool
    {
        try {
            $comment = DB::table('satisfaction_comments')->where('id', $commentId)->value('comment');
            if ($comment === null) return false;
            $result = $this->analyzer->analyze($comment);
            if (! in_array($result['label'] ?? null, self::LABELS, true)) {
                throw new \UnexpectedValueException('Sentiment analyzer returned an unknown label.');
            }
            DB::table('satisfaction_comments')->where('id', $commentId)->update([
                'predicted_sentiment' => $result['label'], 'sentiment_score' => $result['score'],
                'sentiment_confidence' => $result['confidence'], 'sentiment_model_version' => $result['model_version'],
                'analyzed_at' => now(),
            ]);
            $this->bumpVersion();
            return true;
        } catch (Throwable $exception) {
            // Comment text is intentionally not logged.
            Log::warning('Satisfaction comment sentiment analysis failed.', ['comment_id' => $commentId, 'error' => $exception->getMessage()]);
            return false;
        }
    }

    /** Comments with no prediction, or a prediction from another model version. */
    public function pendingAnalysisQuery(): Builder
    {
        return DB::table('satisfaction_comments')->where(fn ($q) => $q->whereNull('predicted_sentiment')
            ->orWhereNull('sentiment_model_version')
            ->orWhere('sentiment_model_version', '!=', SentimentAnalysisService::MODEL_VERSION));
    }

    /** @return array{analyzed:int, failed:int} */
    public function analyzePending(bool $all = false): array
    {
        $analyzed = $failed = 0;
        $query = $all ? DB::table('satisfaction_comments') : $this->pendingAnalysisQuery();
        $ids = $query->orderBy('id')->pluck('id');
        foreach ($ids as $id) $this->analyze($id) ? $analyzed++ : $failed++;
        return ['analyzed' => $analyzed, 'failed' => $failed];
    }

    public function version(): int
    {
        return (int) Cache::get(self::CACHE_VERSION_KEY, 0);
    }

    public function bumpVersion(): void
    {
        Cache::forever(self::CACHE_VERSION_KEY, $this->version() + 1);
    }

    /** Comments that can enter blind review (consented only). */
    public function reviewable(): Builder
    {
        return DB::table('satisfaction_comments as c')->where('c.research_consent', true);
    }

    public function review(string $commentId, User $reviewer, string $label): array
    {
        $this->authorize($reviewer);
        if (! in_array($label, self::REVIEW_LABELS, true)) throw ValidationException::withMessages(['label' => 'Choose Positive, Neutral, Negative or Cannot judge.']);
        return DB::transaction(function () use ($commentId, $reviewer, $label) {
            $comment = $this->reviewable()->where('c.id', $commentId)->lockForUpdate()->first(['c.id', 'c.final_label']);
            if (! $comment) throw ValidationException::withMessages(['label' => 'This comment is not available for review.']);
            $reviews = DB::table('satisfaction_comment_reviews')->where('comment_id', $commentId)->where('role', 'reviewer')->orderBy('id')->get(['reviewer_id', 'label']);
            if ($reviews->contains('reviewer_id', $reviewer->id)) throw ValidationException::withMessages(['label' => 'You already labeled this comment. Each reviewer labels a comment once.']);
            if ($reviews->count() >= self::REVIEWS_REQUIRED) throw ValidationException::withMessages(['label' => 'This comment already has two independent reviews.']);
            try {
                DB::table('satisfaction_comment_reviews')->insert(['comment_id' => $commentId, 'reviewer_id' => $reviewer->id, 'role' => 'reviewer', 'label' => $label, 'reviewed_at' => now()]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['label' => 'You already labeled this comment. Each reviewer labels a comment once.']);
            }
            $labels = $reviews->pluck('label')->push($label);
            $state = 'awaiting_second';
            if ($labels->count() === self::REVIEWS_REQUIRED) {
                if ($labels[0] === $labels[1]) {
                    DB::table('satisfaction_comments')->where('id', $commentId)->update(['final_label' => $label, 'final_label_source' => 'Agreement']);
                    $state = 'final';
                } else {
                    $state = 'needs_adjudication';
                }
            }
            $this->bumpVersion();
            return ['state' => $state];
        });
    }

    public function adjudicate(string $commentId, User $adjudicator, string $label): void
    {
        $this->authorize($adjudicator);
        if (! in_array($label, self::REVIEW_LABELS, true)) throw ValidationException::withMessages(['label' => 'Choose Positive, Neutral, Negative or Cannot judge.']);
        DB::transaction(function () use ($commentId, $adjudicator, $label) {
            $comment = $this->reviewable()->where('c.id', $commentId)->lockForUpdate()->first(['c.id', 'c.final_label']);
            if (! $comment) throw ValidationException::withMessages(['label' => 'This comment is not available for review.']);
            $reviews = DB::table('satisfaction_comment_reviews')->where('comment_id', $commentId)->get(['reviewer_id', 'label', 'role']);
            $reviewers = $reviews->where('role', 'reviewer');
            if ($reviewers->count() < self::REVIEWS_REQUIRED || $reviewers->pluck('label')->unique()->count() === 1 || $comment->final_label !== null) {
                throw ValidationException::withMessages(['label' => 'Only comments where the two reviewers disagree can be adjudicated.']);
            }
            if ($reviews->contains('reviewer_id', $adjudicator->id)) throw ValidationException::withMessages(['label' => 'Adjudication must be done by a third admin who did not review this comment.']);
            DB::table('satisfaction_comment_reviews')->insert(['comment_id' => $commentId, 'reviewer_id' => $adjudicator->id, 'role' => 'adjudicator', 'label' => $label, 'reviewed_at' => now()]);
            DB::table('satisfaction_comments')->where('id', $commentId)->update(['final_label' => $label, 'final_label_source' => 'Adjudication']);
            $this->bumpVersion();
        });
    }

    /**
     * Queue rows with derived review state for the given admin. Never selects predictions.
     * Filters: mine (needs my label), awaiting (one review), adjudication, final, all.
     */
    public function queue(User $viewer, string $filter): Builder
    {
        $counts = DB::table('satisfaction_comment_reviews')->selectRaw("comment_id, SUM(CASE WHEN role = 'reviewer' THEN 1 ELSE 0 END) as reviewer_count, COUNT(DISTINCT CASE WHEN role = 'reviewer' THEN label END) as distinct_labels, MAX(CASE WHEN reviewer_id = ? THEN 1 ELSE 0 END) as mine", [$viewer->id])
            ->groupBy('comment_id');
        $query = $this->reviewable()->leftJoinSub($counts, 'rc', 'rc.comment_id', '=', 'c.id')
            ->select(['c.id', 'c.comment', 'c.final_label', 'c.final_label_source'])
            ->selectRaw('COALESCE(rc.reviewer_count, 0) as reviewer_count, COALESCE(rc.distinct_labels, 0) as distinct_labels, COALESCE(rc.mine, 0) as mine');
        return match ($filter) {
            'awaiting' => $query->whereRaw('COALESCE(rc.reviewer_count, 0) = 1'),
            'adjudication' => $query->whereNull('c.final_label')->whereRaw('COALESCE(rc.reviewer_count, 0) >= 2 AND rc.distinct_labels > 1'),
            'final' => $query->whereNotNull('c.final_label'),
            'all' => $query,
            default => $query->whereNull('c.final_label')->whereRaw('COALESCE(rc.reviewer_count, 0) < 2 AND COALESCE(rc.mine, 0) = 0'),
        };
    }

    public static function stateOf(object $row): string
    {
        if ($row->final_label !== null) return 'Final ('.$row->final_label_source.')';
        if ((int) $row->reviewer_count >= self::REVIEWS_REQUIRED) return 'Needs adjudication';
        return (int) $row->reviewer_count === 1 ? 'Awaiting second review' : 'Needs first review';
    }

    /** Inter-rater agreement for comments with two reviewer labels (first vs. second review). */
    public function agreement(): array
    {
        $pairs = DB::table('satisfaction_comment_reviews')->where('role', 'reviewer')
            ->whereIn('comment_id', DB::table('satisfaction_comment_reviews')->select('comment_id')->where('role', 'reviewer')->groupBy('comment_id')->havingRaw('COUNT(*) = ?', [self::REVIEWS_REQUIRED]))
            ->orderBy('comment_id')->orderBy('id')->get(['comment_id', 'label'])
            ->groupBy('comment_id')->map(fn ($rows) => [$rows[0]->label, $rows[1]->label])->values()->all();
        return app(CohenKappa::class)->calculate($pairs, self::REVIEW_LABELS);
    }

    public function authorize(User $user): void
    {
        abort_unless($user->normalizedRole() === 'admin', 403);
    }
}
