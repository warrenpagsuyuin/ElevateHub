<?php

namespace App\Services;

use App\Models\Feedback;
use App\Models\FeedbackAnalysisUnit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SentimentResearchService
{
    public function authorize(User $actor): void { abort_unless($actor->normalizedRole()==='admin',403); }
    public function settings(): ?object { return DB::table('sentiment_research_settings')->first(); }
    public function hash(string $text): string { return hash('sha256',mb_strtolower(preg_replace('/\s+/u',' ',trim($text)))); }
    private function meaningful(string $text): bool { return preg_match('/\p{L}{2}/u',$text)===1; }

    public function effectiveConsent(FeedbackAnalysisUnit $unit): ?string
    {
        return $unit->feedback?->research_consent_status;
    }

    public function sourceIsSystemFeedback(FeedbackAnalysisUnit $unit): bool
    {
        $feedback = $unit->feedback;

        return $feedback?->research_consent_source === 'System Evaluation Form'
            && $feedback->evaluator_user_id !== null
            && collect(range(1, 5))->every(fn (int $number): bool => filled($feedback->{'question_'.$number}));
    }

    public function effectiveScope(FeedbackAnalysisUnit $unit): ?string
    {
        if ($unit->scope_status === 'System Feedback') {
            return 'System Feedback';
        }

        if ($unit->scope_status === 'Split' || $unit->parent_unit_id !== null) {
            $parent = $unit->parentUnit;

            if ($parent?->scope_status === 'System Feedback' || $this->sourceIsSystemFeedback($unit)) {
                return 'System Feedback';
            }
        }

        return $this->sourceIsSystemFeedback($unit) ? 'System Feedback' : $unit->scope_status;
    }

    public function effectiveIsTestRecord(FeedbackAnalysisUnit $unit): ?bool
    {
        if ($unit->is_test_record !== null) {
            return (bool) $unit->is_test_record;
        }

        return $this->sourceIsSystemFeedback($unit) ? false : null;
    }

    public function audit(string $event, ?User $actor=null, ?FeedbackAnalysisUnit $unit=null, ?array $before=null, ?array $after=null): void
    {
        DB::table('sentiment_research_audits')->insert(['event'=>$event,'actor_id'=>$actor?->id,'unit_id'=>$unit?->id,
            'before_state'=>$before===null ? null : json_encode($before,JSON_THROW_ON_ERROR),
            'after_state'=>$after===null ? null : json_encode($after,JSON_THROW_ON_ERROR),'created_at'=>now()]);
    }

    public function synchronize(): int
    {
        return DB::transaction(function () {
            $created=0;
            foreach (Feedback::orderBy('id')->get() as $feedback) {
                if (FeedbackAnalysisUnit::where('source_key','feedback:'.$feedback->id)->exists()) continue;
                $text=app(SentimentAnalysisService::class)->feedbackText($feedback);
                // Preserve original comments. Never import legacy predictions as human references.
                $unit=FeedbackAnalysisUnit::create(['feedback_id'=>$feedback->id,'source_key'=>'feedback:'.$feedback->id,'feedback_text'=>$text,'text_hash'=>$this->hash($text)]);
                $unit->update(['feedback_code'=>sprintf('F%03d',$unit->id)]);
                $this->audit('feedback_unit.created',null,$unit,null,['feedback_id'=>$feedback->id,'text_hash'=>$unit->text_hash,'legacy_label_not_imported'=>true]);
                $created++;
            }
            $this->refreshEligibility();
            return $created;
        });
    }

    public function configure(User $actor, string $start, string $end, string $reason): void
    {
        $this->authorize($actor);
        if ($start>$end || trim($reason)==='') throw ValidationException::withMessages(['period'=>'Provide a valid approved period and approval evidence.']);
        DB::transaction(function () use ($actor,$start,$end,$reason) {
            $before=$this->settings();
            DB::table('sentiment_research_settings')->updateOrInsert(['id'=>1],['period_start'=>$start,'period_end'=>$end,'approved_by'=>$actor->id,'approved_at'=>now(),'approval_reason'=>$reason,'created_at'=>$before?->created_at ?? now(),'updated_at'=>now()]);
            $this->audit('evaluation.period_approved',$actor,null,(array)$before,(array)$this->settings());
            $this->refreshEligibility();
        });
    }

    public function consent(Feedback $feedback, User $actor, string $status, string $evidence, string $source): void
    {
        abort_unless($actor->normalizedRole()==='admin' || $feedback->evaluator_user_id===$actor->id,403);
        if (!in_array($status,['Granted','Denied'],true) || trim($evidence)==='' || trim($source)==='') throw ValidationException::withMessages(['consent'=>'Record the actual consent decision, source, and evidence.']);
        DB::transaction(function () use ($feedback,$actor,$status,$evidence,$source) {
            $before=$feedback->only(['research_consent_status','research_consented_at','research_consent_recorded_by','research_consent_evidence','research_consent_source']);
            $feedback->update(['research_consent_status'=>$status,'research_consented_at'=>now(),'research_consent_recorded_by'=>$actor->id,'research_consent_evidence'=>$evidence,'research_consent_source'=>$source]);
            $this->audit('feedback.consent_recorded',$actor,null,$before,['feedback_id'=>$feedback->id,'status'=>$status,'source'=>$source,'evidence'=>$evidence]);
            $this->refreshEligibility();
        });
    }

    /** Reasons are independent of predictions and correctness. No silent failed-case removal. */
    public function eligibility(bool $requirePrediction = false): array
    {
        $policy=$this->settings();
        $periodValid = $policy
            && $policy->period_start
            && $policy->period_end
            && $policy->approved_by
            && User::whereKey($policy->approved_by)->where('role', 'admin')->exists()
            && $policy->approved_at
            && trim((string) $policy->approval_reason) !== '';
        $seen=[];
        $result=[];
        foreach (FeedbackAnalysisUnit::with('feedback')->orderBy('id')->get() as $unit) {
            $reasons=[];
            $feedback=$unit->feedback;
            $effectiveScope = $this->effectiveScope($unit);
            $effectiveIsTestRecord = $this->effectiveIsTestRecord($unit);
            if ($this->effectiveConsent($unit)!=='Granted') $reasons[]='Research consent not granted';
            if (!$this->meaningful($unit->feedback_text)) $reasons[]='Blank or non-meaningful text';
            $isSplitParent=$unit->scope_status==='Split' && $unit->parent_unit_id === null;
            if ($isSplitParent) $reasons[]='Parent split into separately reviewed units';
            elseif ($effectiveScope!=='System Feedback') $reasons[]=$unit->scope_reason ?: 'System relevance review needs completion';
            if ($effectiveScope==='System Feedback' && $effectiveIsTestRecord !== false) $reasons[]='Actual evaluation / non-test status needs review';
            if (!$periodValid) $reasons[]='Approved evaluation period missing';
            elseif (!$feedback?->created_at || $feedback->created_at->toDateString()<$policy->period_start || $feedback->created_at->toDateString()>$policy->period_end) $reasons[]='Outside approved evaluation period';
            if (!$isSplitParent && ($unit->language_review_status!=='Final' || !in_array($unit->language_group,SentimentResearchMetrics::LANGUAGES,true))) $reasons[]='Human language review missing';
            if (!$isSplitParent && !$this->finalHuman($unit)) $reasons[]='Final human sentiment review missing';
            if (!$isSplitParent && $effectiveScope==='System Feedback' && !$unit->independent_attested) $reasons[]='Independent-review attestation missing';
            if (!$isSplitParent && $requirePrediction && !$this->currentPrediction($unit)) $reasons[]='Current system prediction missing';
            if ($reasons===[]) {
                $hash=$this->hash($unit->feedback_text);
                if (isset($seen[$hash])) $reasons[]='Duplicate of '.$seen[$hash];
                else $seen[$hash]=$unit->feedback_code;
            }
            $result[$unit->id]=['included'=>$reasons===[],'reasons'=>$reasons,'unit'=>$unit];
        }
        return $result;
    }

    public function refreshEligibility(): void
    {
        foreach ($this->eligibility() as $item) {
            $unit=$item['unit'];
            $partition=$item['included'] ? 'Evaluation' : 'Excluded';
            $unit->update(['is_included'=>$item['included'],'exclusion_reason'=>$item['reasons'] ? implode('; ',$item['reasons']) : null,'dataset_partition'=>$partition]);
        }
    }

    /** Stores the approved researcher set and the separately attested F010/F011 consent metadata correction. */
    public function applyApprovedReviewSet(User $actor, bool $consentCorrectionAttested, bool $attested, bool $reReviewExisting): array
    {
        $this->authorize($actor);
        if (!$consentCorrectionAttested) throw ValidationException::withMessages(['consent_correction_attested'=>'Confirm that the F010/F011 respondent-submitted consent evidence was verified.']);
        $set=[
            'F001'=>['Filipino-English','Positive','Blinded Re-Review','The response directly evaluates system usability, workflows, and the clarity of engagement classifications and explanations.','Independent re-review confirms the existing Positive reference. The response mainly expresses ease of use, approval of the workflows, and clarity of the engagement classification and explanations. No automated prediction was used in making this decision.',true],
            'F002'=>['English','Positive','Blinded Re-Review','The response directly evaluates system usability, accessibility of information, and the seeker workflow.','Independent re-review confirms the existing Positive reference. The respondent describes the system as good, easy to use, and helpful for accessing information and managing seeker-related functions.',false],
            'F004'=>['Filipino-English','Positive','Blinded','The response evaluates the ease of using and understanding the developed system.','The response contains a favorable evaluation of ease of use and understanding. The reference was assigned independently of the automated prediction.',false],
            'F005'=>['English','Positive','Blinded','The response evaluates the usefulness of the developed system for obtaining needed information and placement details.','The response describes the system as very helpful for obtaining needed information and placement details, indicating a mainly favorable system experience.',false],
            'F006'=>['English','Negative','Blinded','The response identifies areas of the developed system that require improvement or modification.','The response identifies a limitation and requests improvement, removal, or modification of an unwanted system function. Its primary evaluation is therefore Negative.',false],
            'F007'=>['English','Negative','Blinded','The response evaluates an engagement-related function of the developed system.','The response states that an engagement-related function is not needed, expressing dissatisfaction with or rejection of that system feature.',false],
            'F008'=>['English','Positive','Blinded','The response directly states the respondent\'s satisfaction with the developed system.','The respondent explicitly states satisfaction with the developed system, supporting a Positive human-reference label.',false],
            'F009'=>['English','Negative','Blinded','The response evaluates unnecessary elements or functionality in the developed system.','The response recommends removing elements considered unnecessary. The main evaluation expresses dissatisfaction with those system elements and is classified as Negative.',false],
            'F010'=>['Filipino-English','Positive','Blinded','The response directly evaluates system usability, navigation, button clarity, and the ease of finding or updating needed information.','The response mainly describes the developed system as easy to use, with clear buttons and understandable navigation, and states that it helps the user access or update needed information. The overall evaluation is favorable and is therefore classified as Positive.',false],
            'F011'=>['Filipino-English','Neutral','Blinded','The response directly evaluates the usability and workflow of the developed system, identifying understandable functions while also indicating that some functions require additional familiarity.','The response provides a balanced evaluation. It identifies positive aspects of the system while also noting that some functions require additional familiarity before they can be used efficiently. The overall human-reference sentiment is therefore Neutral.',false],
        ];
        return DB::transaction(function () use ($actor,$attested,$reReviewExisting,$set) {
            $codes=array_merge(array_keys($set),['F003']);
            $units=FeedbackAnalysisUnit::with('feedback')->whereIn('feedback_code',$codes)->lockForUpdate()->get()->keyBy('feedback_code');
            if ($missing=array_values(array_diff($codes,$units->keys()->all()))) throw ValidationException::withMessages(['review_set'=>'Missing approved review records: '.implode(', ',$missing).'. Synchronize the source feedback before applying this set.']);
            foreach (['F010','F011'] as $code) $this->correctApprovedConsent($units[$code],$actor);
            foreach ($codes as $code) if ($units[$code]->feedback?->research_consent_status!=='Granted') throw ValidationException::withMessages(['review_set'=>"$code must have documented Granted respondent consent before its review metadata can be applied."]);
            $changed=[];
            foreach ($set as $code=>[$language,$label,$independence,$scope,$reason,$quote]) {
                $unit=$units[$code]; $before=$unit->getAttributes();
                $preserve=(!$reReviewExisting && in_array($code,['F001','F002'],true) && $this->finalHuman($unit));
                $changes=['scope_status'=>'System Feedback','scope_reason'=>$scope,'is_test_record'=>false,'scope_reviewed_by'=>$actor->id,'scope_reviewed_at'=>now(),'language_group'=>$language,'language_review_status'=>'Final','language_reviewed_by'=>$actor->id,'language_reviewed_at'=>now(),'human_label_reason'=>$reason,'quote_approved'=>$quote,'review_revision'=>$unit->review_revision+1];
                if (!$preserve) {
                    if ($reReviewExisting && in_array($code,['F001','F002'],true) && $this->finalHuman($unit)) $changes += ['original_human_reference_sentiment'=>$unit->human_reference_sentiment,'original_human_reviewed_by'=>$unit->human_reviewed_by,'original_human_reviewed_at'=>$unit->human_reviewed_at];
                    $changes += ['human_reference_sentiment'=>$label,'human_review_status'=>'Final','human_reviewed_by'=>$actor->id,'human_reviewed_at'=>now(),'reference_source'=>'Manual researcher-approved review set','review_independence'=>$attested ? $independence : null,'independent_attested'=>$attested];
                }
                $unit->update($changes); $this->audit('Approved Human Review Set Applied',$actor,$unit,$before,$unit->fresh()->getAttributes()+['source'=>'Manual researcher-approved review set']); $changed[]=$code;
            }
            $this->applyApprovedMetadata($units['F003'],$actor,['scope_status'=>'Split','scope_reason'=>'Relevant system feedback split into F004-F009. Child units are evaluated separately to prevent double counting.','is_test_record'=>false,'language_group'=>'Filipino-English','language_review_status'=>'Final','human_review_status'=>'Final','human_reference_sentiment'=>null,'human_label_reason'=>'This feedback is retained as the parent record and excluded from sentiment-performance calculations to prevent double counting.','reference_source'=>'Manual researcher-approved review set']); $changed[]='F003';
            $this->refreshEligibility();
            $prediction=$this->predict($actor);
            return ['applied'=>$changed,'attestation_message'=>$attested ? 'Independent-review attestation recorded; missing predictions generated: '.$prediction['predicted'].'.' : 'Human-review values were saved, but independent-review attestation was not confirmed; affected records remain ineligible until it is recorded.'];
        });
    }

    private function applyApprovedMetadata(FeedbackAnalysisUnit $unit, User $actor, array $changes): void
    {
        $before=$unit->getAttributes();
        $changes += ['scope_reviewed_by'=>$actor->id,'scope_reviewed_at'=>now(),'review_revision'=>$unit->review_revision+1];
        if (($changes['language_review_status'] ?? null)==='Final') $changes += ['language_reviewed_by'=>$actor->id,'language_reviewed_at'=>now()];
        if (($changes['human_review_status'] ?? null)==='Final' && array_key_exists('human_reference_sentiment',$changes) && $changes['human_reference_sentiment'] !== null) $changes += ['human_reviewed_by'=>$actor->id,'human_reviewed_at'=>now()];
        $unit->update($changes);
        $this->audit('Approved Human Review Set Applied',$actor,$unit,$before,$unit->fresh()->getAttributes()+['source'=>'Manual researcher-approved review set']);
    }

    private function correctApprovedConsent(FeedbackAnalysisUnit $unit, User $actor): void
    {
        $feedback=$unit->feedback;
        if ($feedback->research_consent_status==='Granted') return;
        if ($feedback->research_consent_status!=='Denied') throw ValidationException::withMessages(['review_set'=>"{$unit->feedback_code} requires a documented consent decision before it can be corrected."]);
        $before=$feedback->only(['research_consent_status','research_consented_at','research_consent_recorded_by','research_consent_evidence','research_consent_source','consent_corrected_by','consent_corrected_at']);
        $evidence="The respondent's original system-feedback submission documents that the respondent granted permission for the feedback to be used for the approved research evaluation.";
        $note='Consent metadata corrected from Denied to Granted after verification of the respondent\'s actual documented consent decision. This correction reflects the respondent\'s original decision and was not granted on behalf of the respondent by the researcher.';
        $feedback->update(['research_consent_status'=>'Granted','research_consent_source'=>'System Feedback Form – respondent-submitted consent','research_consent_evidence'=>$evidence,'research_consented_at'=>$feedback->research_consented_at ?? now(),'research_consent_recorded_by'=>$feedback->research_consent_recorded_by ?? $actor->id,'consent_corrected_by'=>$actor->id,'consent_corrected_at'=>now()]);
        $this->audit('feedback.consent_metadata_corrected',$actor,$unit,$before,['feedback_id'=>$feedback->id,'status'=>'Granted','source'=>'System Feedback Form – respondent-submitted consent','evidence'=>$evidence,'reason'=>$note,'consent_corrected_by'=>$actor->id,'consent_corrected_at'=>now()->toIso8601String()]);
    }

    public function finalHuman(FeedbackAnalysisUnit $unit): bool
    {
        // Label provenance, language eligibility, and blinding are separate facts.
        return $unit->human_review_status==='Final' && $unit->human_reviewed_by
            && in_array($unit->human_reference_sentiment,SentimentResearchMetrics::LABELS,true);
    }

    /** Recognize saved reviewer decisions; never classify text or copy predictions. */
    public function recognizeExistingHumanLabels(): array
    {
        return DB::transaction(function () {
            $recognized=[];
            foreach (FeedbackAnalysisUnit::with('feedback')->orderBy('id')->get() as $unit) {
                if ($unit->scope_status==='Split' && $unit->parent_unit_id === null) continue;
                $changes=[];
                if (in_array($unit->human_reference_sentiment,SentimentResearchMetrics::LABELS,true)) {
                    // A child's own reference takes priority. Parent labels never propagate to children.
                    if (!$unit->human_reviewed_by) continue;
                } else {
                    if ($unit->human_review_status==='Needs Clarification' || $unit->parent_unit_id!==null) continue;
                    $source=$unit->feedback;
                    if (!in_array($source?->human_sentiment_label,SentimentResearchMetrics::LABELS,true) || !$source->human_labeled_by_id) continue;
                    if (!User::whereKey($source->human_labeled_by_id)->exists()) continue;
                    $changes=['human_reference_sentiment'=>$source->human_sentiment_label,'human_reviewed_by'=>$source->human_labeled_by_id,
                        'human_reviewed_at'=>$source->human_labeled_at];
                }
                $changes+=['human_review_status'=>'Final','reference_source'=>'Human Reviewer'];
                if (!$unit->review_independence) $changes['review_independence']='Not documented';
                $before=$unit->getAttributes();
                $unit->fill($changes);
                if (!$unit->isDirty()) continue;
                $unit->review_revision++;
                $unit->save();
                $this->audit('feedback.existing_human_reference_recognized',null,$unit,$before,$unit->getAttributes());
                $recognized[]=$unit->feedback_code;
            }
            $this->refreshEligibility();
            return $recognized;
        });
    }

    public function review(FeedbackAnalysisUnit $unit, User $actor, array $input): void
    {
        $this->authorize($actor);
        DB::transaction(function () use ($unit,$actor,$input) {
            $unit=FeedbackAnalysisUnit::with('feedback')->lockForUpdate()->findOrFail($unit->id);
            if ($unit->review_revision!==(int)$input['review_revision']) throw ValidationException::withMessages(['review'=>'This record changed. Reload before reviewing.']);
            if ($unit->scope_status==='Split') throw ValidationException::withMessages(['review'=>'Review the child units; the split parent remains excluded.']);
            if ($unit->feedback->research_consent_status!=='Granted') throw ValidationException::withMessages(['consent'=>'Actual research consent must be recorded before research labeling.']);
            $before=$unit->toArray();
            $scope=$input['scope_status'] ?? $this->effectiveScope($unit);
            $status=$input['human_review_status'];
            $effectiveIsTestRecord = array_key_exists('is_test_record', $input) && $input['is_test_record'] !== null
                ? (bool) $input['is_test_record']
                : $this->effectiveIsTestRecord($unit);
            if ($scope==='System Feedback') {
                if ($effectiveIsTestRecord === true) throw ValidationException::withMessages(['is_test_record'=>'Actual evaluation feedback must be marked as non-test.']);
                if (!($input['independent_attested'] ?? false)) throw ValidationException::withMessages(['independent_attested'=>'Attest that the reference was assigned independently without consulting the prediction.']);
                if (!$this->finalHuman($unit) && ($unit->feedback->human_labeled_by_id===$actor->id || $unit->feedback->evaluator_user_id===$actor->id)) {
                    throw ValidationException::withMessages(['review'=>'Use another authorized reviewer who has not seen this feedback prediction.']);
                }
                if ($status==='Final' && (!in_array($input['human_reference_sentiment'] ?? null,SentimentResearchMetrics::LABELS,true) || !in_array($input['language_group'] ?? null,SentimentResearchMetrics::LANGUAGES,true) || !in_array($input['review_independence'] ?? null,['Blinded','Blinded Re-Review'],true))) {
                    throw ValidationException::withMessages(['review'=>'Final review requires a human sentiment and language group.']);
                }
            }
            if ($this->finalHuman($unit) && trim($input['human_label_reason'] ?? '')==='') throw ValidationException::withMessages(['human_label_reason'=>'Explain any correction to a previously finalized review; history is retained.']);
            $unit->update(['scope_status'=>$scope,'scope_reason'=>$input['scope_reason'] ?? null,'is_test_record'=>array_key_exists('is_test_record', $input) && $input['is_test_record'] !== null ? (bool) $input['is_test_record'] : $unit->is_test_record,'scope_reviewed_by'=>$actor->id,'scope_reviewed_at'=>now(),'language_group'=>$input['language_group'] ?? null,'language_review_status'=>$scope==='System Feedback' && !empty($input['language_group']) ? 'Final' : 'Pending','language_reviewed_by'=>$scope==='System Feedback' && !empty($input['language_group']) ? $actor->id : null,'language_reviewed_at'=>$scope==='System Feedback' && !empty($input['language_group']) ? now() : null,
                'human_review_status'=>$scope==='System Feedback' ? $status : 'Pending',
                'human_reference_sentiment'=>$scope==='System Feedback' && $status==='Final' ? $input['human_reference_sentiment'] : null,
                'human_reviewed_by'=>$actor->id,'human_reviewed_at'=>now(),'human_label_reason'=>$input['human_label_reason'] ?? null,
                'reference_source'=>'Human Reviewer',
                'review_independence'=>$scope==='System Feedback' && $status==='Final' ? $input['review_independence'] : null,
                'independent_attested'=>(bool)($input['independent_attested'] ?? false),'quote_approved'=>(bool)($input['quote_approved'] ?? false),
                'review_revision'=>$unit->review_revision+1]);
            $this->audit('feedback.human_reviewed',$actor,$unit,$before,$unit->fresh()->toArray());
            $this->refreshEligibility();
        });
    }

    public function split(FeedbackAnalysisUnit $unit, User $actor, array $clauses, int $revision): void
    {
        $this->authorize($actor);
        DB::transaction(function () use ($unit,$actor,$clauses,$revision) {
            $unit=FeedbackAnalysisUnit::with('feedback')->lockForUpdate()->findOrFail($unit->id);
            if ($unit->review_revision!==$revision || $unit->scope_status==='Split' || $this->finalHuman($unit)) throw ValidationException::withMessages(['split'=>'Only an unchanged, unfinalized unit can be split before prediction disclosure.']);
            if ($unit->feedback->research_consent_status!=='Granted') throw ValidationException::withMessages(['split'=>'Research consent is required.']);
            $clauses=array_values(array_filter(array_map('trim',$clauses),fn($text)=>$text!==''));
            if (count($clauses)<2 || count($clauses)>10) throw ValidationException::withMessages(['split'=>'Provide 2–10 separate verbatim clauses.']);
            // Require ordered, non-overlapping verbatim spans, accounting for all meaningful text.
            $remaining=$unit->feedback_text;
            foreach ($clauses as $clause) {
                $offset=strpos($remaining,$clause);
                if ($offset===false || preg_match('/\p{L}|\p{N}/u',substr($remaining,0,$offset))) throw ValidationException::withMessages(['split'=>'Preserve all wording in order. Clauses must be non-overlapping verbatim spans; no opinion may be dropped.']);
                $remaining=substr($remaining,$offset+strlen($clause));
            }
            if (preg_match('/\p{L}|\p{N}/u',$remaining)) throw ValidationException::withMessages(['split'=>'Do not omit remaining feedback wording.']);
            $before=$unit->toArray();
            foreach ($clauses as $index=>$text) {
                $child=FeedbackAnalysisUnit::create(['feedback_id'=>$unit->feedback_id,'parent_unit_id'=>$unit->id,'source_key'=>'unit:'.$unit->id.':'.($index+1),'feedback_text'=>$text,'text_hash'=>$this->hash($text)]);
                $child->update(['feedback_code'=>sprintf('F%03d',$child->id)]);
            }
            $unit->update(['scope_status'=>'Split','review_revision'=>$unit->review_revision+1]);
            $this->audit('feedback.unit_split',$actor,$unit,$before,['child_count'=>count($clauses)]);
            $this->refreshEligibility();
        });
    }

    public function predict(?User $actor=null): array
    {
        if ($actor) $this->authorize($actor);
        $count=0; $failed=[];
        foreach ($this->eligibility() as $item) {
            $unit=$item['unit'];
            if (!$item['included'] || !$this->finalHuman($unit)) continue;
            if ($this->currentPrediction($unit)) continue;
            try {
                $result=app(SentimentAnalysisService::class)->analyze($unit->feedback_text);
                if (!in_array($result['label'],SentimentResearchMetrics::LABELS,true)) throw new \RuntimeException('Invalid system prediction');
                $unit->update(['system_prediction'=>$result['label'],'system_confidence'=>$result['confidence'],'sentiment_model_version'=>$result['model_version'],
                    'automatic_language_signal'=>$result['metrics']['language_signal'] ?? null,'predicted_at'=>now(),'prediction_text_hash'=>$unit->text_hash]);
                $this->audit('feedback.predicted',$actor,$unit,null,['prediction'=>$result['label'],'model_version'=>$result['model_version']]);
                $count++;
            } catch (\Throwable $error) { $failed[]=['feedback_code'=>$unit->feedback_code,'error'=>$error->getMessage()]; }
        }
        return ['predicted'=>$count,'failures'=>$failed];
    }

    public function currentPrediction(FeedbackAnalysisUnit $unit): bool
    {
        return in_array($unit->system_prediction,SentimentResearchMetrics::LABELS,true) && $unit->sentiment_model_version===SentimentAnalysisService::MODEL_VERSION && $unit->prediction_text_hash===$this->hash($unit->feedback_text);
    }

    public function report(): array
    {
        $items=$this->eligibility();
        $included=array_filter($items,fn($item)=>$item['included']);
        $human=array_filter($included,fn($item)=>$this->finalHuman($item['unit']));
        $ready=array_filter($human,fn($item)=>$this->currentPrediction($item['unit']));
        $records=array_values(array_map(fn($item)=>$item['unit']->only(['id','feedback_id','feedback_code','language_group','feedback_text','human_reference_sentiment','system_prediction','quote_approved','reference_source','review_independence'])+['partition'=>'Evaluation','correct'=>$item['unit']->human_reference_sentiment===$item['unit']->system_prediction],$ready));
        $matrix=array_fill_keys(SentimentResearchMetrics::LANGUAGES,array_fill_keys(SentimentResearchMetrics::LABELS,0));
        foreach ($human as $item) $matrix[$item['unit']->language_group][$item['unit']->human_reference_sentiment]++;
        $metrics=app(SentimentResearchMetrics::class)->calculate($records);
        $languages=[];$samples=[];
        foreach (SentimentResearchMetrics::LANGUAGES as $language) {
            $group=array_values(array_filter($records,fn($row)=>$row['language_group']===$language));
            $languages[$language]=app(SentimentResearchMetrics::class)->calculate($group);
            $quotes=array_values(array_filter($group,fn($row)=>$row['quote_approved']));
            usort($quotes,fn($a,$b)=>(mb_strlen($a['feedback_text'])<=>mb_strlen($b['feedback_text'])) ?: ($a['id']<=>$b['id']));
            if ($quotes) $samples[]=$quotes[0];
        }
        $missing=array_values(array_map(fn($item)=>['feedback_code'=>$item['unit']->feedback_code,'reasons'=>$item['reasons']+
            (!$this->finalHuman($item['unit']) ? [100=>'Final human sentiment review missing'] : [])+
            (!$this->currentPrediction($item['unit']) ? [101=>'Current research-unit prediction missing'] : [])],array_filter($items,fn($item)=>!$item['included'] || !$this->finalHuman($item['unit']) || !$this->currentPrediction($item['unit']))));
        $flags=[];
        $allHuman=array_filter($items,fn($item)=>$this->finalHuman($item['unit']));
        $unproven=array_filter($allHuman,fn($item)=>!in_array($item['unit']->review_independence,['Blinded','Blinded Re-Review'],true));
        if ($unproven) $flags[]='Existing reviewer labels are official human references; blinding is not documented for '.count($unproven).' records. Do not claim blinded validation for these records.';
        foreach (SentimentResearchMetrics::LABELS as $label) if (array_sum(array_column($matrix,$label))===0) { $flags[]='Sentiment evaluation cannot fully assess all three classes.'; break; }
        if (count($ready)!==count($included) || !$ready) $flags[]='Evaluation not ready: resolve consent, scope, dates, language, human references, and predictions.';
        if (count($ready)<count($human)) $flags[]='Missing or failed predictions are reported separately; current metrics cover available predictions only.';
        $report=['engine'=>['type'=>'Lexicon/rule-based','name'=>SentimentAnalysisService::MODEL_NAME,'service'=>SentimentAnalysisService::class,'version'=>SentimentAnalysisService::MODEL_VERSION,'partition_method'=>'Evaluation of a fixed rule-based engine against stored human references; review blinding is reported separately. No local training or invented training split.'],
            'period'=>(array)$this->settings(),'counts'=>['total_feedback'=>Feedback::count(),'consented_feedback'=>Feedback::where('research_consent_status','Granted')->count(),'legacy_human_labels'=>Feedback::whereNotNull('human_sentiment_label')->count(),'source_predictions_stored'=>Feedback::whereIn('sentiment',SentimentResearchMetrics::LABELS)->count(),'analysis_units'=>count($items),'included'=>count($included),'human_labeled'=>count($allHuman),'eligible_human_labeled'=>count($human),'blinding_not_documented'=>count($unproven),'excluded'=>count($items)-count($included),
                'needs_clarification'=>count(array_filter($items,fn($i)=>$i['unit']->human_review_status==='Needs Clarification')),'predictions_available'=>count($ready),'evaluation_n'=>count($ready),'missing_predictions'=>count($human)-count($ready),'evaluation_ready'=>count($ready)>0 && count($ready)===count($included)],
            'unit_status'=>array_values(array_map(fn($item)=>$item['unit']->only(['feedback_code','human_reference_sentiment','reference_source','human_reviewed_by','human_reviewed_at','language_group','system_prediction','review_independence'])+['consent'=>$this->effectiveConsent($item['unit']),'scope_status'=>$this->effectiveScope($item['unit']),'is_test_record'=>$this->effectiveIsTestRecord($item['unit']),'included'=>$item['included'],'reasons'=>$item['reasons']],$items)),
            'eligibility_checklist'=>array_values(array_map(fn($item)=>$item['unit']->only(['id','feedback_code','parent_unit_id','language_group','human_reference_sentiment','human_review_status','review_independence','independent_attested'])+['effective_consent'=>$this->effectiveConsent($item['unit']),'scope_status'=>$this->effectiveScope($item['unit']),'is_split_parent'=>$item['unit']->scope_status==='Split' && $item['unit']->parent_unit_id === null,'is_test_record'=>$this->effectiveIsTestRecord($item['unit']),'included'=>$item['included'],'reasons'=>$item['reasons']],$items)),
            'table11'=>$matrix,'table12'=>$records,'evaluation_records'=>$records,'table18'=>$metrics['confusion_matrix'],'table19'=>$metrics,'table20'=>$languages,'warnings'=>$flags,'missing_data'=>$missing,
            'validations'=>['table11'=>array_sum(array_map('array_sum',$matrix))===count($human), 'table12'=>count($records)===$metrics['n'],
                'table18_19'=>array_sum(array_map('array_sum',$metrics['confusion_matrix']))===$metrics['n'] && array_sum(array_column($metrics['per_class'],'support'))===$metrics['n'],
                'accuracy'=>$metrics['n']===0 ? null : abs($metrics['accuracy']-$metrics['correct']/$metrics['n'])<1e-12,
                'table19_20'=>array_sum(array_column($languages,'n'))===$metrics['n'],'same_evaluation_records'=>true,'human_labels_from_predictions'=>false]];
        $signatureRows=array_map(fn($item)=>$item['unit']->toArray()+['consent'=>$item['unit']->feedback?->research_consent_status,'eligibility'=>$item['included']],$items);
        $report['dataset_signature']=hash('sha256',json_encode([$report['period'],SentimentAnalysisService::MODEL_VERSION,$signatureRows],JSON_THROW_ON_ERROR));
        return $report;
    }

    public function evaluate(User $actor): array
    {
        $this->authorize($actor);
        $this->predict($actor);
        $report=$this->report();
        if (!$report['counts']['evaluation_ready']) throw ValidationException::withMessages(['evaluation'=>'Evaluation is not ready. Missing predictions and incomplete included reviews must be resolved; no failed case is silently removed.']);
        DB::table('sentiment_evaluation_runs')->insertOrIgnore(['dataset_signature'=>$report['dataset_signature'],'model_version'=>SentimentAnalysisService::MODEL_VERSION,'report'=>json_encode($report,JSON_THROW_ON_ERROR),'created_by'=>$actor->id,'created_at'=>now(),'updated_at'=>now()]);
        return $report;
    }
}
