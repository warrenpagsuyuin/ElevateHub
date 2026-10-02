<?php

namespace App\Services;

/** Presentation-only privacy boundary. Never changes eligibility or metric values. */
class ThesisEvidencePortableReport
{
    public function prepare(array $report): array
    {
        $codes = [];
        foreach ($report['sentiment']['audit']['units'] as $unit) {
            $codes[$unit['feedback_code']] = $this->code('unit', $unit['id']);
        }
        $walk = function (mixed $value, string $key = '', bool $source = false) use (&$walk, $codes): mixed {
            if ($value === null) return null;
            if (in_array($key, ['table11_ids', 'table18_ids', 'record_ids', 'child_unit_ids'], true)) {
                return $this->identifiers($value);
            }
            if (is_array($value)) {
                $result = [];
                foreach ($value as $name => $child) {
                    // Do not expose database-backed review URLs in portable evidence.
                    if ($name === 'review_url') continue;
                    $result[$name] = $walk($child, (string) $name, $source || $key === 'source_feedback');
                }
                return $result;
            }
            if ($key === 'id') return $this->code($source ? 'source' : 'unit', $value);
            if ($key === 'feedback_id') return $this->code('source', $value);
            if (in_array($key, ['unit_id', 'parent_unit_id'], true)) return $this->code('unit', $value);
            if (in_array($key, ['feedback_code', 'parent_code'], true)) {
                return $codes[$value] ?? $this->code('code', $value);
            }
            if (is_string($value)) {
                $value = preg_replace_callback('/Duplicate of analysis unit (\d+)/', fn ($match) => 'Duplicate of analysis unit '.$this->code('unit', $match[1]), $value);
                if (str_starts_with($value, 'Duplicate of ')) {
                    $old = substr($value, strlen('Duplicate of '));
                    if (isset($codes[$old])) $value = 'Duplicate of '.$codes[$old];
                }
            }
            return $value;
        };
        $portable = $walk($report);
        // These fields come from persisted metadata, not a validated instrument.
        foreach (['candidates', 'review_queue'] as $key) {
            foreach ($portable['survey']['qualitative'][$key] as &$row) {
                if (!in_array($row['language_group'], SentimentResearchMetrics::LANGUAGES, true)) $row['language_group'] = null;
                $row['text'] = null;
            }
            unset($row);
        }
        $portable['privacy'] = 'No names, contacts, answers, free-text notes, raw record IDs or record URLs. References use pseudonymous codes only. Keep reports within authorized research access.';
        $portable['sentiment']['dataset_signature'] = hash('sha256', json_encode(array_diff_key($portable['sentiment'], ['dataset_signature'=>true]), JSON_THROW_ON_ERROR));
        return $portable;
    }

    private function code(string $kind, mixed $id): string
    {
        return strtoupper($kind).'-'.substr(hash_hmac('sha256', $kind.':'.$id, (string) config('app.key')), 0, 16);
    }

    private function identifiers(array $values): array
    {
        return array_map(fn ($value) => is_array($value) ? $this->identifiers($value) : $this->code('unit', $value), $values);
    }
}
