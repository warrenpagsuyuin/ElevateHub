<?php

namespace App\Services;

class SentimentResearchExportService
{
    public function sheets(array $report): array
    {
        $pct=fn($value)=>$value===null ? 'UNTESTED' : number_format($value*100,2).'%';
        $distribution=[['Language','Positive','Neutral','Negative','Total']];
        foreach ($report['table11'] as $language=>$counts) $distribution[]=[$language,...array_values($counts),array_sum($counts)];
        $distribution[]=['TOTAL',...array_map(fn($label)=>array_sum(array_column($report['table11'],$label)),SentimentResearchMetrics::LABELS),$report['counts']['eligible_human_labeled']];
        $distribution[]=['Consent / eligibility enforced','Yes'];
        $distribution[]=['Engine',$report['engine']['name']];
        $distribution[]=['Model version',$report['engine']['version']];
        $distribution[]=['Method',$report['engine']['partition_method']];
        $distribution[]=['Evaluation ready',$report['counts']['evaluation_ready'] ? 'Yes' : 'No'];
        $distribution[]=['Dataset signature',$report['dataset_signature']];
        $distribution[]=['Approved period start',$report['period']['period_start'] ?? 'NOT PROVIDED'];
        $distribution[]=['Approved period end',$report['period']['period_end'] ?? 'NOT PROVIDED'];
        foreach ($report['counts'] as $name=>$count) $distribution[]=[ucwords(str_replace('_',' ',$name)),is_bool($count) ? ($count ? 'Yes' : 'No') : $count];
        foreach ($report['warnings'] as $warning) $distribution[]=['Coverage / readiness',$warning];
        $samples=[['Feedback Code','Language','Feedback','Human Reference','System Prediction']];
        foreach ($report['table12'] as $row) $samples[]=[$row['feedback_code'],$row['language_group'],$row['feedback_text'],$row['human_reference_sentiment'],$row['system_prediction']];
        if (!$report['table12']) $samples[]=['No eligible, independently reviewed and quote-approved samples'];
        $records=[['Feedback Code','Language','Human Reference','System Prediction','Correct','Partition']];
        foreach ($report['evaluation_records'] as $row) $records[]=[$row['feedback_code'],$row['language_group'],$row['human_reference_sentiment'],$row['system_prediction'],$row['correct'] ? 'YES' : 'NO',$row['partition']];
        $matrix=[['Actual/Predicted',...SentimentResearchMetrics::LABELS]];
        foreach ($report['table18'] as $label=>$counts) $matrix[]=[$label,...array_values($counts)];
        $overall=[['Class','Precision','Recall','F1','Support']];
        foreach ($report['table19']['per_class'] as $label=>$row) $overall[]=[$label,$pct($row['precision']),$pct($row['recall']),$pct($row['f1']),$row['support']];
        foreach (['macro'=>'Macro Average','weighted'=>'Weighted Average'] as $key=>$label) $overall[]=[$label,...array_map($pct,array_values($report['table19'][$key])),$report['table19']['n']];
        $overall[]=['Overall Accuracy','-','-',$pct($report['table19']['accuracy']),$report['table19']['n']];
        foreach ($report['table19']['per_class'] as $label=>$row) foreach ($row['flags'] as $flag) $overall[]=[$label,$flag];
        $languages=[['Language','Test n','Accuracy','Macro Precision','Macro Recall','Macro F1']];
        foreach ($report['table20'] as $language=>$row) $languages[]=[$language,$row['n'],$pct($row['accuracy']),...array_map($pct,array_values($row['macro']))];
        foreach ($report['table20'] as $language=>$row) {
            $languages[]=[$language.' class supports',...array_map(fn($label)=>$label.': '.$row['per_class'][$label]['support'],SentimentResearchMetrics::LABELS)];
            if ($row['caution']) $languages[]=[$language,$row['caution']];
        }
        return ['Human Label Distribution'=>$distribution,'Feedback Samples'=>$samples,'Evaluation Records'=>$records,'Confusion Matrix'=>$matrix,'Overall Metrics'=>$overall,'Language Metrics'=>$languages];
    }

    public function bytes(array $report): string { return $this->workbook($this->sheets($report)); }
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
                    $style = $r === 0 ? 1 : 0;
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
