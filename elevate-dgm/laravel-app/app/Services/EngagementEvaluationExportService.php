<?php

namespace App\Services;

use App\Models\EngagementReferenceDataset;

/** Application XLSX export: inline text cells and numeric cells, no macros or external links. */
class EngagementEvaluationExportService
{
    public function bytes(EngagementReferenceDataset $dataset): string
    {
        $summary = app(ReferenceDatasetService::class)->summary($dataset);
        $run = $dataset->activeModelRun;
        $reference = [['Engagement Label', 'Frequency', 'Percentage']];
        foreach ($summary['distribution'] as $row) {
            $reference[] = [$row['label'], $row['frequency'], $summary['reference_count'] ? $row['frequency'] / $summary['reference_count'] : 0];
        }
        $reference[] = ['TOTAL', $summary['reference_count'], $summary['reference_count'] ? 1 : 0];
        $reference[] = ['Reference Source', $summary['source']];
        $reference[] = ['Evaluation Status', $summary['evaluation_status']];
        $reference[] = ['Provisional / Human Verified', $summary['provisional'], $summary['verified']];
        $reference[] = ['Dataset version', $summary['dataset_version']];
        $reference[] = ['Assignment method', $dataset->assignment_method];
        $reference[] = ['Chapter 3 text', $summary['chapter_text']];
        $split = [['Class', 'Total', 'Training', 'Testing']];
        foreach ($summary['split']['classes'] as $label => $row) {
            $split[] = [$label, $row['total'], $row['training'], $row['testing']];
        }
        $split[] = ['TOTAL', $summary['eligible'], $summary['split']['train_count'], $summary['split']['test_count']];
        $split[] = ['Method', $summary['split']['method']];
        $split[] = ['Seed', $summary['split']['seed']];
        $split[] = ['Needs Clarification (excluded)', $summary['clarification']];
        $performance = [['Metric', 'Result']];
        if ($run) {
            foreach (['accuracy', 'macro_precision', 'macro_recall', 'macro_f1', 'weighted_precision', 'weighted_recall', 'weighted_f1'] as $key) {
                $performance[] = [ucwords(str_replace('_', ' ', $key)), $run->holdout_metrics[$key]];
            }
            $performance[] = ['Majority Baseline Accuracy', $run->training_config['baseline']['accuracy']];
            $performance[] = ['Majority Class (from training)', $run->training_config['baseline']['majority_class']];
            $performance[] = ['Dataset Version', $run->dataset_version];
            $performance[] = ['Model Version', $run->model_version];
            $performance[] = ['Evaluation Status', $run->evaluation_status];
            $performance[] = ['CV Folds', $run->validation_metrics['fold_count']];
            $performance[] = ['CV Macro F1', $run->validation_metrics['macro_f1']];
            $performance[] = ['Selected Parameters', json_encode($run->training_config['selected_parameters'])];
            $performance[] = ['Training Accuracy', $run->training_config['training_metrics']['accuracy']];
            $performance[] = ['Training Macro F1', $run->training_config['training_metrics']['macro_f1']];
            $performance[] = ['Failed Test Classifications', $run->holdout_metrics['failed_count']];
            $performance[] = [];
            $performance[] = ['Actual / Predicted', ...ResearchCartService::LABELS];
            foreach ($run->holdout_metrics['confusion_matrix'] as $actual => $predictions) {
                $performance[] = [$actual, ...array_values($predictions)];
            }
            $performance[] = [];
            $performance[] = ['Class', 'Precision', 'Recall', 'F1', 'Support'];
            foreach ($run->holdout_metrics['per_class'] as $label => $metric) {
                $performance[] = [$label, $metric['precision'], $metric['recall'], $metric['f1'], $metric['support']];
            }
            $performance[] = ['Model Review Status', $run->training_config['review_status'] ?? 'MODEL REVIEW REQUIRED'];
            $performance[] = ['Interpretation', 'Evaluation of provisional development assignments, not accuracy against independent human judgments.'];
        } else {
            $performance[] = ['Status', 'No current model. Train after the latest reference review updates.'];
        }
        $records = [['Member Code', 'Satellite', 'Reference Label', 'Reference Source', 'Verification Status', 'Review Status', 'Partition', 'Reason', 'Reviewer ID', 'Reviewed At', 'Dataset Version', ...ResearchCartService::FEATURE_NAMES]];
        foreach (app(ReferenceDatasetService::class)->records($dataset)->orderBy('member_id')->get() as $row) {
            $records[] = [sprintf('M%03d', $row->member_id), $row->member?->satellite?->name ?? 'Unassigned', $row->reference_label,
                $row->reference_source, $row->verification_status, $row->review_status, $row->partition, $row->reason, $row->reviewed_by,
                $row->reviewed_at?->toIso8601String(), $dataset->revision_version, ...array_map(fn ($key) => $row->feature_snapshot[$key] ?? null, ResearchCartService::FEATURE_NAMES)];
        }
        $satellites = [['Satellite', 'Active', 'Moderately Active', 'Possible Need for Follow-Up', 'Needs Clarification', 'Total']];
        foreach ($summary['satellites'] as $row) {
            $satellites[] = array_values($row);
        }
        $cv = [['Max depth', 'Min samples split', 'Min samples leaf', 'Class weight', 'CCP alpha', 'Mean CV Macro F1', 'Std CV Macro F1', 'Mean CV Accuracy', 'Fold Scores']];
        foreach ($run?->training_config['cv']['candidates'] ?? [] as $candidate) {
            $p = $candidate['parameters'];
            $cv[] = [$p['max_depth'] ?? 'None', $p['min_samples_split'], $p['min_samples_leaf'], $p['class_weight'] ?? 'None', $p['ccp_alpha'], $candidate['mean_macro_f1'], $candidate['std_macro_f1'], $candidate['mean_accuracy'], json_encode($candidate['fold_macro_f1'])];
        }
        $audit = [['Feature', 'Type', 'Missing', 'Unique values', 'Used by CART?', 'Reason', 'Quality', 'Value counts']];
        foreach (app(EngagementFeatureAuditService::class)->inspect(app(ReferenceDatasetService::class)->samples($dataset))['predictors'] as $row) {
            $audit[] = [$row['feature'], $row['data_type'], $row['missing_count'], json_encode($row['unique_values']), 'Yes: supplied', $row['reason'], implode('; ', $row['quality']), json_encode($row['value_counts'])];
        }
        $pruning = [['CCP alpha', 'Training leaf impurity', 'Leaf count']];
        foreach ($run?->training_config['final_training_pruning_path'] ?? [] as $point) $pruning[] = array_values($point);
        return $this->workbook(['Reference Label Summary' => $reference, 'Dataset Split' => $split, 'Performance' => $performance, 'Reference Records' => $records, 'Satellite Distribution' => $satellites, 'CV Search' => $cv, 'Feature Audit' => $audit, 'Pruning Path' => $pruning]);
    }

    private function xml(mixed $value): string
    {
        $clean = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $value) ?? '';
        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function workbook(array $sheets): string
    {
        $main = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $relationships = 'http://schemas.openxmlformats.org/package/2006/relationships';
        $office = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $files = [];
        $types = '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $workbook = '<workbook xmlns="'.$main.'" xmlns:r="'.$office.'"><sheets>';
        $rels = '<Relationships xmlns="'.$relationships.'">';
        $i = 0;
        foreach ($sheets as $name => $rows) {
            $i++;
            $workbook .= '<sheet name="'.$this->xml($name).'" sheetId="'.$i.'" r:id="rId'.$i.'"/>';
            $rels .= '<Relationship Id="rId'.$i.'" Type="'.$office.'/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
            $types .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $sheet = '<worksheet xmlns="'.$main.'"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="36" customWidth="1"/><col min="2" max="24" width="24" customWidth="1"/></cols><sheetData>';
            foreach ($rows as $r => $values) {
                $rowNumber = $r + 1;
                $longest = max([0, ...array_map(fn ($value) => is_string($value) ? mb_strlen($value) : 0, $values)]);
                $height = min(360, max(32, ceil($longest / 23) * 15));
                $sheet .= '<row r="'.$rowNumber.'" ht="'.$height.'" customHeight="1">';
                foreach ($values as $c => $value) {
                    $column = '';
                    for ($n = $c + 1; $n > 0; $n = intdiv($n - 1, 26)) {
                        $column = chr(65 + (($n - 1) % 26)).$column;
                    }
                    $cell = $column.$rowNumber;
                    $style = $r === 0 ? 1 : (($i === 1 && $c === 2 && $r <= 5) ? 2 : 0);
                    if ($i === 3 && $c === 1 && in_array($values[0] ?? '', ['Accuracy', 'Macro Precision', 'Macro Recall', 'Macro F1', 'Weighted Precision', 'Weighted Recall', 'Weighted F1', 'Majority Baseline Accuracy', 'CV Macro F1', 'Training Accuracy', 'Training Macro F1'], true)) {
                        $style = 2;
                    }
                    if ($i === 3 && count($values) === 5 && in_array($values[0], ResearchCartService::LABELS, true) && $c >= 1 && $c <= 3) {
                        $style = 2;
                    }
                    if (is_int($value) || is_float($value)) {
                        $sheet .= '<c r="'.$cell.'" s="'.$style.'"><v>'.$value.'</v></c>';
                    } else {
                        $sheet .= '<c r="'.$cell.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->xml($value ?? '').'</t></is></c>';
                    }
                }
                $sheet .= '</row>';
            }
            $sheet .= '</sheetData></worksheet>';
            $files['xl/worksheets/sheet'.$i.'.xml'] = $sheet;
        }
        $rels .= '<Relationship Id="rIdStyles" Type="'.$office.'/styles" Target="styles.xml"/></Relationships>';
        $files['[Content_Types].xml'] = $types.'</Types>';
        $files['_rels/.rels'] = '<Relationships xmlns="'.$relationships.'"><Relationship Id="rId1" Type="'.$office.'/officeDocument" Target="xl/workbook.xml"/></Relationships>';
        $files['xl/workbook.xml'] = $workbook.'</sheets></workbook>';
        $files['xl/_rels/workbook.xml.rels'] = $rels;
        $files['xl/styles.xml'] = '<styleSheet xmlns="'.$main.'"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF215847"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1" applyFont="1" applyFill="1"><alignment wrapText="1"/></xf><xf numFmtId="10" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
        return $this->zip($files);
    }

    /** Stored ZIP records avoid requiring the optional PHP ZipArchive extension. */
    private function zip(array $files): string
    {
        $body = $directory = '';
        foreach ($files as $name => $content) {
            $content = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.$content;
            $size = strlen($content);
            $crc = crc32($content);
            $offset = strlen($body);
            $body .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 33, $crc, $size, $size, strlen($name), 0).$name.$content;
            $directory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 33, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $offset).$name;
        }
        return $body.$directory.pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($directory), strlen($body), 0);
    }
}
