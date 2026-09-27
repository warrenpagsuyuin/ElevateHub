<?php

namespace App\Services;

use InvalidArgumentException;

class SentimentResearchMetrics
{
    public const LABELS = ['Positive','Neutral','Negative'];
    public const LANGUAGES = ['English','Filipino','Filipino-English'];

    public function calculate(array $records): array
    {
        $matrix = array_fill_keys(self::LABELS, array_fill_keys(self::LABELS, 0));
        foreach ($records as $row) {
            if (!in_array($row['human_reference_sentiment'],self::LABELS,true) || !in_array($row['system_prediction'],self::LABELS,true)) {
                throw new InvalidArgumentException('Every evaluation record needs a valid human label and prediction.');
            }
            $matrix[$row['human_reference_sentiment']][$row['system_prediction']]++;
        }
        $n = count($records);
        $correct = 0;
        $macro = $weighted = ['precision'=>0.0,'recall'=>0.0,'f1'=>0.0];
        $classes = [];
        foreach (self::LABELS as $label) {
            $tp = $matrix[$label][$label];
            $correct += $tp;
            $support = array_sum($matrix[$label]);
            $predicted = array_sum(array_column($matrix,$label));
            $precision = $predicted ? $tp/$predicted : 0.0;
            $recall = $support ? $tp/$support : 0.0;
            $f1 = $precision+$recall ? 2*$precision*$recall/($precision+$recall) : 0.0;
            $classes[$label] = ['precision'=>$n ? $precision : null,'recall'=>$n ? $recall : null,'f1'=>$n ? $f1 : null,'support'=>$support,
                'flags'=>array_values(array_filter([$predicted ? null : 'No predictions were produced for this class.', $support ? null : 'No actual support for this class.']))];
            foreach (['precision'=>$precision,'recall'=>$recall,'f1'=>$f1] as $metric=>$value) {
                $macro[$metric] += $value/3;
                $weighted[$metric] += $n ? $value*$support/$n : 0;
            }
        }
        return ['status'=>$n ? 'Evaluated' : 'N/A – No eligible observations','n'=>$n,'correct'=>$correct,'incorrect'=>$n-$correct,'accuracy'=>$n ? $correct/$n : null,
            'confusion_matrix'=>$matrix,'per_class'=>$classes,'macro'=>$n ? $macro : array_fill_keys(array_keys($macro),null),
            'weighted'=>$n ? $weighted : array_fill_keys(array_keys($weighted),null),
            'caution'=>$n && $n<30 ? 'Interpret cautiously because of the small language-specific test sample.' : null];
    }
}
