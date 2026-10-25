<?php

namespace App\Http\Controllers;

use App\Services\ModelEvaluationDashboard;
use App\Services\SatisfactionCommentEvaluation;
use App\Services\SatisfactionCommentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Blind review, adjudication, export and maintenance for questionnaire comments. Admin only. */
class SatisfactionCommentController extends Controller
{
    public const FILTERS = ['mine' => 'Needs my review', 'awaiting' => 'Awaiting second review', 'adjudication' => 'Needs adjudication', 'final' => 'Final', 'all' => 'All'];
    public const PAGE_SIZE = 15;

    public function __construct(private SatisfactionCommentService $comments) {}

    public function review(Request $request): View
    {
        $user = $request->user();
        $this->comments->authorize($user);
        $filter = array_key_exists($request->query('status'), self::FILTERS) ? $request->query('status') : 'mine';
        $queue = $this->comments->queue($user, $filter)->orderBy('c.id')->paginate(self::PAGE_SIZE)->withQueryString();

        // Current item: an explicit ?comment=, else the first item that needs this admin's label.
        $requested = is_string($request->query('comment')) ? $request->query('comment') : null;
        $current = $requested
            ? $this->comments->queue($user, 'all')->where('c.id', $requested)->first()
            : $this->comments->queue($user, 'mine')->when(is_string($request->query('after')), fn ($q) => $q->where('c.id', '>', $request->query('after')))->orderBy('c.id')->first();
        $currentReviews = $current
            ? DB::table('satisfaction_comment_reviews')->where('comment_id', $current->id)->orderBy('id')->get(['reviewer_id', 'role', 'label'])
            : collect();
        $mode = null;
        if ($current) {
            $mine = $currentReviews->contains('reviewer_id', $user->id);
            if ($current->final_label === null && ! $mine && (int) $current->reviewer_count < SatisfactionCommentService::REVIEWS_REQUIRED) $mode = 'review';
            elseif ($current->final_label === null && ! $mine && (int) $current->distinct_labels > 1) $mode = 'adjudicate';
        }

        return view('admin.sentiment-research.comment-review', [
            'queue' => $queue, 'filter' => $filter, 'filters' => self::FILTERS,
            'current' => $current, 'currentReviews' => $currentReviews, 'mode' => $mode,
            'labels' => SatisfactionCommentService::REVIEW_LABELS,
            'agreement' => $this->comments->agreement(),
            'section' => 'comment-review',
        ]);
    }

    public function store(Request $request, string $comment): RedirectResponse
    {
        $data = $request->validate(['label' => ['required', Rule::in(SatisfactionCommentService::REVIEW_LABELS)]], ['label.required' => 'Choose a label before saving.', 'label.in' => 'Choose Positive, Neutral, Negative or Cannot judge.']);
        $result = $this->comments->review($comment, $request->user(), $data['label']);
        $message = match ($result['state']) {
            'final' => 'Label saved. Both reviewers agreed, so the reference label is final.',
            'needs_adjudication' => 'Label saved. The reviewers disagree; a third admin must adjudicate.',
            default => 'Label saved. Waiting for a second independent reviewer.',
        };
        return redirect()->route('admin.satisfaction-comments.review', ['status' => $request->input('status', 'mine'), 'page' => $request->input('page')])->with('status', $message);
    }

    public function adjudicate(Request $request, string $comment): RedirectResponse
    {
        $data = $request->validate(['label' => ['required', Rule::in(SatisfactionCommentService::REVIEW_LABELS)]], ['label.required' => 'Choose the final label before saving.', 'label.in' => 'Choose Positive, Neutral, Negative or Cannot judge.']);
        $this->comments->adjudicate($comment, $request->user(), $data['label']);
        return redirect()->route('admin.satisfaction-comments.review', ['status' => $request->input('status', 'adjudication'), 'page' => $request->input('page')])->with('status', 'Final label recorded by adjudication.');
    }

    public function export(Request $request, SatisfactionCommentEvaluation $evaluation): StreamedResponse
    {
        $this->comments->authorize($request->user());
        $dates = $this->dates($request);
        $name = 'questionnaire-comment-sentiment-evaluation-'.now()->format('Ymd-His').'.csv';
        return response()->streamDownload(function () use ($evaluation, $dates) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($evaluation->csvLines($dates['from'], $dates['to']) as $line) fputcsv($out, $line);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function recalculate(Request $request): RedirectResponse
    {
        $this->comments->authorize($request->user());
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Confirm before recalculating metrics.']);
        $this->comments->bumpVersion();
        ModelEvaluationDashboard::bumpVersion();
        return redirect()->route('admin.analytics.sentiment', $this->context($request))->with('status', 'Metrics recalculated from stored predictions and reference labels.');
    }

    public function reanalyze(Request $request, string $comment): RedirectResponse
    {
        $this->comments->authorize($request->user());
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Confirm before re-analyzing this comment.']);
        abort_unless(DB::table('satisfaction_comments')->where('id', $comment)->exists(), 404);
        $ok = $this->comments->analyze($comment);
        return redirect()->route('admin.analytics.sentiment', $this->context($request))
            ->with($ok ? 'status' : 'error', $ok ? 'Comment re-analyzed with model '.\App\Services\SentimentAnalysisService::MODEL_VERSION.'.' : 'Re-analysis failed. The error was logged; the prediction is unchanged.');
    }

    public function backfill(Request $request): RedirectResponse
    {
        $this->comments->authorize($request->user());
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Confirm before back-filling predictions.']);
        $result = $this->comments->analyzePending();
        return redirect()->route('admin.analytics.sentiment', $this->context($request))
            ->with($result['failed'] ? 'error' : 'status', "Back-fill finished: {$result['analyzed']} analyzed, {$result['failed']} failed.");
    }

    private function dates(Request $request): array
    {
        $dates = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        return ['from' => $dates['from'] ?? null, 'to' => $dates['to'] ?? null];
    }

    /** Return to the evaluation tab with the same scope and dataset. */
    private function context(Request $request): array
    {
        return array_filter(['view' => 'evaluation', 'from' => $request->input('from'), 'to' => $request->input('to'), 'dataset' => $request->input('dataset')], fn ($v) => is_string($v) && $v !== '');
    }
}
