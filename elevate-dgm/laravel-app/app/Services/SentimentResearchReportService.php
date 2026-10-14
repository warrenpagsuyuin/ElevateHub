<?php

namespace App\Services;

class SentimentResearchReportService
{
    private function table(array $rows): string
    {
        $escape=fn($value)=>str_replace(["\r\n","\n",'|'],['<br>','<br>','\\|'],(string)$value);
        $text='| '.implode(' | ',array_map($escape,array_shift($rows)))." |\n";
        $width=count($rows[0] ?? []);
        $text.='|'.implode('|',array_fill(0,$width,'---'))."|\n";
        foreach ($rows as $row) $text.='| '.implode(' | ',array_map($escape,$row))." |\n";
        return $text."\n";
    }

    public function markdown(array $report): string
    {
        $pct=fn($value)=>$value===null ? 'UNTESTED' : number_format($value*100,2).'%';
        $section=fn($name)=>"\n========================================\n$name\n========================================\n\n";
        $text=$section('SENTIMENT IMPLEMENTATION');
        $text.="Historical research includes feedback from archived satellites. Archiving does not change eligibility, labels, predictions or results.\n\n";
        $text.="Actual sentiment engine: Current sentiment engine is lexicon/rule-based.\n\nModel/service: {$report['engine']['name']} / `{$report['engine']['service']}`\n\nModel version: `{$report['engine']['version']}`\n\nRule-based / ML / Transformer / Other: **Lexicon/rule-based**. Weighted English/Filipino phrase cues, positive-cue negation handling, a net-score threshold and heuristic confidence; no transformer, fitted local classifier or external inference API executes. The same lexical service also supports follow-up notes, but this dataset reads only system feedback.\n\n{$report['engine']['partition_method']}\n\n";
        $text.=$section('DATASET');
        foreach (['total_feedback'=>'Total stored feedback','consented_feedback'=>'Consented','included'=>'Included analysis units','human_labeled'=>'Stored official human references','eligible_human_labeled'=>'Eligible human-labeled units','excluded'=>'Excluded units','needs_clarification'=>'Needs Clarification','evaluation_n'=>'Evaluation/Test N'] as $key=>$label) $text.="$label: {$report['counts'][$key]}\n\n";
        $text.="Legacy human labels preserved: {$report['counts']['legacy_human_labels']}\n\nSource feedback predictions preserved: {$report['counts']['source_predictions_stored']}\n\n";
        $text.='Approved period: '.($report['period']['period_start'] ?? 'NOT PROVIDED').' to '.($report['period']['period_end'] ?? 'NOT PROVIDED').".\n\nEvaluation Ready: ".($report['counts']['evaluation_ready'] ? 'YES' : 'NO').".\n\nExisting attributed human labels are recognized as Final / Human Reviewer. Original labels, reviewers and available timestamps are preserved. Blinding is reported separately, not assumed. Consent is never inferred from submission. Pending scope decisions cover developer/test, unrelated and invalid comments; duplicates are detected on normalized text.\n\n";
        $rows=[['Language Group','Positive','Neutral','Negative','Total']];
        foreach ($report['table11'] as $language=>$counts) $rows[]=[$language,...array_values($counts),array_sum($counts)];
        $rows[]=['TOTAL',...array_map(fn($label)=>array_sum(array_column($report['table11'],$label)),SentimentResearchMetrics::LABELS),$report['counts']['eligible_human_labeled']];
        $text.=$section('TABLE 11')."Distribution of Human-Labeled System Feedback\n\n".$this->table($rows).'Validation: '.($report['validations']['table11'] ? 'PASS' : 'FAIL')." (arithmetic consistency, not evidence of readiness).\n";
        $text.=$section('TABLE 12')."Sample System Feedback and Sentiment Classification\n\n";
        $rows=[['Feedback Code','Language','Original Feedback','Human Reference','System Prediction']];
        foreach ($report['table12'] as $row) $rows[]=[$row['feedback_code'],$row['language_group'],$row['feedback_text'],$row['human_reference_sentiment'],$row['system_prediction']];
        if (!$report['table12']) $text.="No eligible consented, human-reviewed and quote-approved examples are available. No comments were fabricated.\n";
        else {
            $text.=$this->table($rows);
            foreach ($report['table12'] as $row) $text.=$row['feedback_code'].' Correct? '.($row['correct'] ? 'YES' : 'NO')."\n\n";
        }
        $text.="\nSelection: shortest quote-approved actual item per language, tie-break by code; correctness does not influence selection. Original text is preserved.\n";
        $rows=[['Actual / Predicted',...SentimentResearchMetrics::LABELS]];
        foreach ($report['table18'] as $label=>$counts) $rows[]=[$label,...array_values($counts)];
        $m=$report['table19'];
        $text.=$section('TABLE 18')."Confusion Matrix of System-Feedback Sentiment Classification\n\n".$this->table($rows)."Test N = {$m['n']}\n\nCorrect = {$m['correct']}\n\nIncorrect = {$m['incorrect']}\n\nAccuracy = ".$pct($m['accuracy'])."\n";
        $rows=[['Sentiment / Average','Precision','Recall','F1-Score','Support']];
        foreach ($m['per_class'] as $label=>$row) $rows[]=[$label,$pct($row['precision']),$pct($row['recall']),$pct($row['f1']),$row['support']];
        foreach (['macro'=>'Macro Average','weighted'=>'Weighted Average'] as $key=>$label) $rows[]=[$label,...array_map($pct,array_values($m[$key])),$m['n']];
        $rows[]=['Overall Accuracy','-','-',$pct($m['accuracy']),$m['n']];
        $text.=$section('TABLE 19')."Overall Sentiment Classification Performance\n\n".$this->table($rows);
        foreach ($m['per_class'] as $label=>$row) if ($row['flags']) $text.="$label: ".implode(' ',$row['flags'])."\n\n";
        $text.='Table 18 ↔ Table 19: '.($report['validations']['table18_19'] ? 'PASS' : 'FAIL').". Accuracy cross-check: ".($report['validations']['accuracy']===null ? 'UNTESTED (n=0)' : ($report['validations']['accuracy'] ? 'PASS' : 'FAIL')).".\n";
        $rows=[['Language','Test n','Accuracy','Macro Precision','Macro Recall','Macro F1']];
        foreach ($report['table20'] as $language=>$row) $rows[]=[$language,$row['n'],$pct($row['accuracy']),...array_map($pct,array_values($row['macro']))];
        $text.=$section('TABLE 20')."Sentiment Performance According to Language\n\n".$this->table($rows);
        foreach ($report['table20'] as $language=>$row) {
            $text.="$language: ";
            foreach ($row['per_class'] as $label=>$class) $text.="$label support = {$class['support']}; ";
            $text.="Total = {$row['n']}. ".($row['caution'] ?? '')."\n\n";
        }
        $text.='Table 19 ↔ Table 20: '.($report['validations']['table19_20'] ? 'PASS' : 'FAIL').". All three tables use exactly the same evaluation IDs.\n\nZero-denominator precision/recall/F1 is zero when n>0, and all three classes remain in macro averages. When n=0, metrics are UNTESTED rather than 0% accuracy. Small-group caution uses n<30 as a descriptive flag, not an asserted minimum valid sample size. No synthetic balance or artificial training split is created.\n";
        $text.=$section('PER-UNIT STATUS');
        $rows=[['Code','Human label','Reviewer ID / original date (UTC)','Language','Consent','Included','Research prediction']];
        foreach ($report['unit_status'] as $row) $rows[]=[$row['feedback_code'],$row['human_reference_sentiment'] ?? 'Not stored',($row['human_reviewed_by'] ?? 'Not stored').' / '.($row['human_reviewed_at'] ?? 'Not stored'),$row['language_group'] ?? 'Not stored',$row['consent'],$row['included'] ? 'Yes' : 'No',$row['system_prediction'] ?? 'Not generated'];
        $text.=$this->table($rows);
        $text.=$section('MISSING DATA');
        foreach ($report['missing_data'] as $row) $text.='- '.$row['feedback_code'].': '.implode('; ',$row['reasons']).".\n";
        $text.="\nTable: 11\n\nMissing data: Eligible human sentiment and language references.\n\nReason: Eligibility requires documented consent, approved dates and reviewed system relevance. Unknown blinding is a limitation, not an exclusion reason.\n\nExact action required: Supply existing child-unit review records and human language/scope reviews. The user confirmed the September 3?10 period; missing human language and scope reviews still require actual records. Do not replace saved labels or infer missing human judgments.\n\nTable: 12\n\nMissing data: Real eligible examples for each language.\n\nExact action required: Complete eligibility/review, run the fixed engine, and approve original text for de-identified quotation. Collect additional actual consented feedback where languages are absent; do not invent or rewrite comments.\n\nTables: 18, 19, 20\n\nMissing data: An eligible evaluation cohort with human references and current predictions.\n\nExact action required: Complete the review queue, then use Predict / Evaluate reviewed feedback. Resolve every included record's missing prediction/review before saving a completed evaluation. Missing-prediction counts are visible; failed cases are not silently discarded.\n\n";
        foreach ($report['warnings'] as $warning) $text.=$warning."\n\n";
        $text.=$section('FILES MODIFIED');
        foreach (['database/migrations/2026_09_10_000001_add_system_feedback_research.php','database/migrations/create_feedback_table.php','app/Models/Feedback.php','app/Models/FeedbackAnalysisUnit.php','app/Services/SentimentResearchMetrics.php','app/Services/SentimentResearchService.php','app/Services/SentimentResearchExportService.php','app/Services/SentimentResearchReportService.php','app/Console/Commands/PrepareSentimentEvaluation.php','app/Http/Controllers/SentimentResearchController.php','app/Http/Controllers/AdminAnalyticsController.php','app/Http/Controllers/FeedbackController.php','routes/web.php','resources/views/admin/sentiment-research/index.blade.php','resources/views/admin/sentiment-research/show.blade.php','resources/views/feedback/create.blade.php','resources/views/feedback/index.blade.php','tests/Feature/SentimentResearchTest.php','tests/Feature/SystemFeedbackSentimentTest.php','docs/system-feedback-research.md'] as $file) $text.="- `$file`\n";
        $text.="\nPaths relative to `elevate-dgm/laravel-app`. Database: `database/database.sqlite` received additive storage and analysis-unit import only. Source feedback, its predictions and legacy human labels remain unchanged. Backup: `storage/app/private/backups/before-human-reference-recognition-20260910.sqlite`. The undated original feedback creation migration includes new consent columns for fresh installations; no database reset was used on live data.\n";
        return $text;
    }
}
