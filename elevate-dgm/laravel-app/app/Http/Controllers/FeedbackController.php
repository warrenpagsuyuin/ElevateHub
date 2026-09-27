<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Services\SentimentAnalysisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    private const TABLE_PAGE_SIZE = 10;
    private const PAGE_SIZES = [10, 25, 50, 100];

    public function __construct(private SentimentAnalysisService $sentimentService)
    {
    }

    public function index(Request $request): View
    {
        $perPage = $this->perPage($request);
        $feedbackItems = Feedback::query()
            ->where('evaluator_user_id', Auth::id())
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('feedback.index', [
            'feedbackItems' => $feedbackItems,
            'pageSizes' => self::PAGE_SIZES,
            'perPage' => $perPage,
        ]);
    }

    public function create(): View
    {
        return view('feedback.create', [
            'questions' => $this->questions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'question_1' => ['required', 'string', 'max:1200'],
            'question_2' => ['required', 'string', 'max:1200'],
            'question_3' => ['required', 'string', 'max:1200'],
            'question_4' => ['required', 'string', 'max:1200'],
            'question_5' => ['required', 'string', 'max:1200'],
            'message' => ['nullable', 'string', 'max:1500'],
            'research_consent' => ['nullable', 'boolean'],
        ]);

        $questions = $this->questions();
        $feedback = Feedback::create([
            'evaluator_user_id' => Auth::id(),
            'research_consent_status' => $request->boolean('research_consent') ? 'Granted' : 'Denied',
            'research_consented_at' => now(),
            'research_consent_recorded_by' => Auth::id(),
            'research_consent_evidence' => $request->boolean('research_consent') ? 'Respondent explicitly selected optional research/evaluation consent on the feedback form.' : 'Respondent did not grant optional research/evaluation consent.',
            'research_consent_source' => 'System Evaluation Form',
            'question_1' => $validated['question_1'],
            'question_2' => $validated['question_2'],
            'question_3' => $validated['question_3'],
            'question_4' => $validated['question_4'],
            'question_5' => $validated['question_5'],
            'message' => $this->combinedMessage($validated, $questions),
            'feedback_payload' => [
                'questions' => $questions,
                'answers' => [
                    'question_1' => $validated['question_1'],
                    'question_2' => $validated['question_2'],
                    'question_3' => $validated['question_3'],
                    'question_4' => $validated['question_4'],
                    'question_5' => $validated['question_5'],
                ],
                'additional_comments' => $validated['message'] ?? null,
                'submitted_role' => Auth::user()?->role,
            ],
        ]);

        $this->sentimentService->analyzeFeedback($feedback);
        app(\App\Services\SentimentResearchService::class)->synchronize();

        return redirect()
            ->route('feedback.index')
            ->with('status', 'Thank you. Your system feedback has been submitted.');
    }

    /**
     * @return array<string, string>
     */
    private function questions(): array
    {
        return [
            'question_1' => 'How easy was it to use the developed system?',
            'question_2' => 'How helpful were the member, seeker, and follow-up workflows?',
            'question_3' => 'How clear were the engagement classification and explanation results?',
            'question_4' => 'What problems, confusion, or missing features did you notice?',
            'question_5' => 'Overall, how satisfied are you with the developed system?',
        ];
    }

    /**
     * @param array<string, string|null> $validated
     * @param array<string, string> $questions
     */
    private function combinedMessage(array $validated, array $questions): string
    {
        $parts = [];

        foreach ($questions as $key => $question) {
            $parts[] = $question."\n".$validated[$key];
        }

        if (filled($validated['message'] ?? null)) {
            $parts[] = "Additional comments\n".$validated['message'];
        }

        return implode("\n\n", $parts);
    }

    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', self::TABLE_PAGE_SIZE);

        return in_array($perPage, self::PAGE_SIZES, true)
            ? $perPage
            : self::TABLE_PAGE_SIZE;
    }
}
