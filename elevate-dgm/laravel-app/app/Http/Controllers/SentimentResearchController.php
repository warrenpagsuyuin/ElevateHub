<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\FeedbackAnalysisUnit;
use App\Services\SentimentResearchService;
use App\Services\SentimentResearchMetrics;
use App\Services\SentimentResearchExportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SentimentResearchController extends Controller
{
    public function __construct(private SentimentResearchService $service) {}
    public function index(Request $request)
    {
        $this->service->authorize($request->user());
        return view('admin.sentiment-research.index',['report'=>$this->service->report(),'units'=>FeedbackAnalysisUnit::with(['feedback','reviewer'])->orderBy('id')->paginate(10),'service'=>$this->service]);
    }
    public function show(Request $request, FeedbackAnalysisUnit $unit)
    {
        $this->service->authorize($request->user());
        return view('admin.sentiment-research.show',['unit'=>$unit->load(['feedback','reviewer']),'showPrediction'=>$this->service->finalHuman($unit),
            'sourceIsSystemFeedback'=>$this->service->sourceIsSystemFeedback($unit),
            'labels'=>SentimentResearchMetrics::LABELS,'languages'=>SentimentResearchMetrics::LANGUAGES]);
    }
    public function synchronize(Request $request)
    {
        $this->service->authorize($request->user());
        $count=$this->service->synchronize();
        return back()->with('status',"Imported $count existing feedback records for eligibility review. No human labels were generated.");
    }
    public function period(Request $request)
    {
        $data=$request->validate(['period_start'=>['required','date_format:Y-m-d'],'period_end'=>['required','date_format:Y-m-d','after_or_equal:period_start'],'approval_reason'=>['required','string','max:2000'],'attested'=>['accepted']]);
        $this->service->configure($request->user(),$data['period_start'],$data['period_end'],$data['approval_reason']);
        return back()->with('status','Approved evaluation period recorded.');
    }
    public function consent(Request $request, Feedback $feedback)
    {
        $data=$request->validate(['consent_status'=>['required',Rule::in(['Granted','Denied'])],'evidence'=>['required','string','max:2000'],'consent_source'=>['required','string','max:255'],'attested'=>['accepted']]);
        $this->service->consent($feedback,$request->user(),$data['consent_status'],$data['evidence'],$data['consent_source']);
        return back()->with('status','Actual consent decision recorded.');
    }
    public function review(Request $request, FeedbackAnalysisUnit $unit)
    {
        $data=$request->validate(['review_revision'=>['required','integer'],'scope_status'=>['nullable',Rule::in(['System Feedback','Excluded','Needs Clarification'])],
            'scope_reason'=>['required_if:scope_status,Excluded','nullable','string','max:2000'],
            'is_test_record'=>['nullable','boolean'],
            'language_group'=>['nullable',Rule::in(SentimentResearchMetrics::LANGUAGES)],'human_reference_sentiment'=>['nullable',Rule::in(SentimentResearchMetrics::LABELS)],
            'human_review_status'=>['required',Rule::in(['Final','Needs Clarification'])],'review_independence'=>['nullable',Rule::in(['Blinded','Blinded Re-Review'])],'human_label_reason'=>['nullable','string','max:4000'],
            'independent_attested'=>['sometimes','accepted'],'quote_approved'=>['sometimes','accepted'],'next'=>['nullable','boolean']]);
        $this->service->review($unit,$request->user(),$data);
        if ($request->boolean('next')) {
            $next=FeedbackAnalysisUnit::where('id','>',$unit->id)->where('human_review_status','!=','Final')->orderBy('id')->first();
            if ($next) return redirect()->route('admin.sentiment-research.show',$next)->with('status','Review saved.');
        }
        return redirect()->route('admin.sentiment-research.show',$unit)->with('status','Independent review saved with history.');
    }
    public function split(Request $request, FeedbackAnalysisUnit $unit)
    {
        $data=$request->validate(['review_revision'=>['required','integer'],'clauses'=>['required','string','max:12000'],'attested'=>['accepted']]);
        $this->service->split($unit,$request->user(),explode("\n---\n",str_replace("\r\n","\n",$data['clauses'])),$data['review_revision']);
        return redirect()->route('admin.sentiment-research.review')->with('status','Verbatim child units created. Each requires its own review.');
    }
    public function evaluate(Request $request)
    {
        $this->service->evaluate($request->user());
        return redirect()->route('admin.sentiment-research.dataset')->with('status','Fixed-engine evaluation saved. Tables 18–20 use the same records.');
    }
    public function applyApprovedReviewSet(Request $request)
    {
        $data=$request->validate(['confirm_apply'=>['accepted'],'consent_correction_attested'=>['accepted'],'independent_attested'=>['nullable','boolean'],'re_review_f001_f002'=>['nullable','boolean']]);
        $result=$this->service->applyApprovedReviewSet($request->user(),(bool)$data['consent_correction_attested'],(bool)($data['independent_attested'] ?? false),(bool)($data['re_review_f001_f002'] ?? false));
        return back()->with('status','Approved Human Review Set Applied to '.implode(', ',$result['applied']).'. '.$result['attestation_message']);
    }
    public function export(Request $request, SentimentResearchExportService $export)
    {
        $this->service->authorize($request->user());
        return response($export->bytes($this->service->report()),200,['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Content-Disposition'=>'attachment; filename="system-feedback-sentiment-evaluation.xlsx"','Cache-Control'=>'private, no-store']);
    }
}
