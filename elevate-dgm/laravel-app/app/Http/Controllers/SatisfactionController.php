<?php

namespace App\Http\Controllers;

use App\Services\SatisfactionCommentService;
use App\Services\SatisfactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use App\Models\Satellite;

class SatisfactionController extends Controller
{
    public function satellite(Request $request, Satellite $satellite, SatisfactionService $service): View|JsonResponse
    {
        abort_unless($request->user()?->canAccessSatellite($satellite), 403);
        $period = $service->period($request);
        $data = $period + [
            'satisfaction' => $service->results($request, $period, $request->user()),
            'periodFormAction' => route('satellites.satisfaction', $satellite), 'satisfactionRoute' => 'satellites.satisfaction', 'satellite' => $satellite,
        ];
        if ($request->expectsJson()) return response()->json([
            'html' => view('admin.sentiment-research._satisfaction-results', $data)->render(),
            'controls' => view('components.satellite-period-controls', $data)->render(),
            'date' => $period['dashboardDate'], 'updatedAt' => $period['dashboardUpdatedAt'],
            'year' => $period['dashboardCurrentYear'], 'month' => $period['dashboardCurrentMonth'],
            'selectedMonth' => $period['selectedDashboardMonth'], 'selectedYear' => $period['selectedDashboardYear'],
        ], 200, ['Cache-Control' => 'private, no-store']);
        return view('satellites.satisfaction', $data);
    }

    public function store(Request $request, SatisfactionService $service): RedirectResponse
    {
        $satelliteSlug = $request->input('satellite');
        if ($satelliteSlug !== null && ! is_string($satelliteSlug)) abort(404);
        $context = $service->respondentContext($request->user(), $satelliteSlug);
        $categories = $service->activeCategories($context['inDgroup']);
        $questions = $categories->flatMap(fn ($category) => $category->questions)->keyBy('id');
        if ($questions->isEmpty()) {
            throw ValidationException::withMessages(['satisfaction' => 'No questions are available yet.']);
        }
        $validated = $request->validate([
            'service_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now(config('app.timezone'))->toDateString()],
            'answers' => ['required', 'array'],
            'answers.*' => ['required', Rule::in(['yes', 'no'])],
            'comment' => ['nullable', 'string', 'max:'.SatisfactionCommentService::MAX_LENGTH],
            'comment_consent' => ['nullable', 'boolean'],
        ], [
            'comment.max' => 'Comments can be at most :max characters.',
        ]);
        $answers = $validated['answers'];
        $errors = [];
        foreach ($questions as $question) {
            if (! in_array($answers[$question->id] ?? null, ['yes', 'no'], true)) {
                $errors['answers.'.$question->id] = 'Choose Yes or No.';
            }
        }
        if ($errors) throw ValidationException::withMessages($errors);
        // Ignore posted answers to hidden, inactive, or nonexistent questions.
        $answers = array_intersect_key($answers, $questions->all());
        $comments = app(SatisfactionCommentService::class);
        $comment = $comments->sanitize($validated['comment'] ?? null);
        $commentId = $service->submit($request->user(), $validated['service_date'], $answers, $context, $comment, $comment !== null && (bool) ($validated['comment_consent'] ?? false));
        // Scored after commit; a failure is logged and never blocks the submission.
        if ($commentId) $comments->analyze($commentId);
        return redirect()->route('feedback.create', ['satellite' => $context['satellite']->slug])
            ->with('satisfaction_status', 'Thank you. Your answers were recorded.');
    }

    public function manage(Request $request): View
    {
        $categories = DB::table('satisfaction_categories')->orderBy('position')->orderBy('id')->get();
        foreach ($categories as $category) {
            $category->questions = DB::table('satisfaction_questions')->where('category_id', $category->id)->orderBy('position')->orderBy('id')->get();
        }
        return view('admin.satisfaction-manage', ['categories' => $categories]);
    }

    public function saveCategory(Request $request, ?int $category = null): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'position' => ['required', 'integer', 'min:1'], 'active' => ['required', 'boolean']]);
        if ($category) DB::table('satisfaction_categories')->where('id', $category)->update($data);
        else DB::table('satisfaction_categories')->insert($data);
        return back()->with('status', 'Category saved.');
    }

    public function saveQuestion(Request $request, ?int $question = null): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:satisfaction_categories,id'],
            'text' => ['required', 'string', 'max:500'], 'position' => ['required', 'integer', 'min:1'],
            'active' => ['required', 'boolean'], 'dgroup_only' => ['required', 'boolean'],
            'cosmetic_only' => ['sometimes', 'boolean'],
        ]);
        $cosmeticOnly = (bool) ($data['cosmetic_only'] ?? false);
        unset($data['cosmetic_only']);
        if (! $question) DB::table('satisfaction_questions')->insert($data);
        else {
            $original = DB::table('satisfaction_questions')->where('id', $question)->first();
            abort_unless($original, 404);
            $answered = DB::table('satisfaction_answers')->where('question_id', $question)->exists();
            if ($answered && ($original->category_id != $data['category_id'] || (bool) $original->dgroup_only !== (bool) $data['dgroup_only'])) {
                throw ValidationException::withMessages(['question' => 'Answered questions cannot change category or audience. Deactivate and add a new question.']);
            }
            if ($answered && $original->text !== $data['text'] && ! $cosmeticOnly) {
                throw ValidationException::withMessages(['text' => 'Answered question text is locked. Deactivate and add a new question for a new meaning.']);
            }
            DB::transaction(function () use ($question, $original, $data) {
                if ($original->text !== $data['text']) DB::table('satisfaction_question_edits')->insert([
                    'question_id' => $question, 'old_text' => $original->text, 'new_text' => $data['text'], 'edited_at' => now(),
                ]);
                DB::table('satisfaction_questions')->where('id', $question)->update($data);
            });
        }
        return back()->with('status', 'Question saved.');
    }

}
