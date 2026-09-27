<?php

namespace App\Http\Controllers;

use App\Models\EngagementReferenceLabel;
use App\Services\ReferenceDatasetService;
use App\Services\EngagementEvaluationService;
use App\Services\EngagementEvaluationExportService;
use App\Services\ResearchCartService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EngagementResearchController extends Controller
{
    public function __construct(private ReferenceDatasetService $references, private EngagementEvaluationService $evaluation) {}

    public function index(Request $request)
    {
        $this->references->authorize($request->user());
        $dataset = $this->references->current();
        $records = null;
        if ($dataset) {
            $query = $this->references->records($dataset)->orderBy('member_id');
            foreach (['verification_status', 'review_status', 'reference_label', 'partition'] as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->string($field)->toString());
                }
            }
            $records = $query->paginate(10)->withQueryString();
        }
        return view('admin.engagement-research.index', ['dataset' => $dataset, 'summary' => $this->references->summary($dataset), 'records' => $records,
            'run' => $dataset?->activeModelRun, 'labels' => ResearchCartService::LABELS, 'datasetPage' => $request->routeIs('admin.engagement.dataset')]);
    }

    public function show(Request $request, EngagementReferenceLabel $reference)
    {
        $this->references->authorize($request->user());
        abort_unless($reference->dataset_version === $this->references->current()?->version, 404);
        return view('admin.engagement-research.show', ['reference' => $reference->load(['member.satellite', 'reviewer']),
            'audits' => $reference->audits()->latest('id')->get(), 'prediction' => $this->evaluation->predictionFor($reference), 'labels' => ResearchCartService::LABELS]);
    }

    public function verify(Request $request, EngagementReferenceLabel $reference)
    {
        $this->references->authorize($request->user());
        abort_unless($reference->dataset_version === $this->references->current()?->version, 404);
        $validated = $request->validate(['reference_label' => ['required', Rule::in([...ResearchCartService::LABELS, ReferenceDatasetService::CLARIFICATION])],
            'reason' => ['required', 'string', 'max:10000'], 'attested' => ['accepted'], 'review_revision' => ['required', 'integer', 'min:1']]);
        $label = $validated['reference_label'] === ReferenceDatasetService::CLARIFICATION ? null : $validated['reference_label'];
        $this->references->verify($reference, $request->user(), $label, $validated['reason'], (int) $validated['review_revision']);
        return redirect()->route('admin.engagement.show', $reference)->with('status', 'Human verification saved with audit history. The previous evaluation is retained as history; train a new evaluation for the updated dataset revision.');
    }

    public function train(Request $request)
    {
        $this->references->authorize($request->user());
        $dataset = $this->references->current();
        abort_unless($dataset, 404);
        $this->evaluation->train($dataset, $request->user());
        return redirect()->route('admin.engagement.dataset')->with('status', 'CART training and held-out evaluation completed.');
    }

    public function finalize(Request $request)
    {
        $this->references->authorize($request->user());
        $dataset = $this->references->current();
        abort_unless($dataset, 404);
        $this->references->finalize($dataset, $request->user());
        return redirect()->route('admin.engagement.dataset')->with('status', 'The reference dataset is Human Verified Final. Model runs retain their original verification status; run evaluation again to use the final status.');
    }

    public function export(Request $request, EngagementEvaluationExportService $export)
    {
        $this->references->authorize($request->user());
        $dataset = $this->references->current();
        abort_unless($dataset, 404);
        return response($export->bytes($dataset), 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="engagement-evaluation-'.$dataset->revision_version.'.xlsx"', 'Cache-Control' => 'private, no-store']);
    }
}
