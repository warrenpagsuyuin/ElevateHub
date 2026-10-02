<?php

namespace App\Services;

use DOMElement;
use DOMNode;
use Illuminate\Support\Facades\Blade;

class ThesisEvidenceMarkdown
{
    public function render(array $report): string
    {
        // Outside resources/views: there is no product view or HTTP handler for this template.
        $html = Blade::render(file_get_contents(resource_path('research/thesis-evidence.blade.php')), [
            'report'=>$report, 'only'=>null,
        ], deleteCachedView: true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET);
            $markdown = $this->node($document->getElementsByTagName('body')->item(0));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $markdown = preg_replace('/\n[ \t]+\n/', "\n\n", $markdown);
        $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown);
        return "# Thesis Research Evidence\n\n".trim($markdown)."\n";
    }

    private function node(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) return preg_replace('/\s+/u', ' ', $node->textContent);
        if (!$node instanceof DOMElement) return '';
        if ($node->tagName === 'pre') {
            $fence = str_repeat('`', max(3, $this->longestBackticks($node->textContent) + 1));
            return "\n\n{$fence}json\n".$node->textContent."\n{$fence}\n\n";
        }
        if ($node->tagName === 'table') return $this->table($node);
        $content = '';
        foreach ($node->childNodes as $child) $content .= $this->node($child);
        return match ($node->tagName) {
            'h1', 'h2', 'h3' => "\n\n".str_repeat('#', (int) substr($node->tagName, 1)).' '.trim($content)."\n\n",
            'p', 'section', 'div' => "\n\n".trim($content)."\n\n",
            'strong' => '**'.trim($content).'**',
            'code' => '`'.trim($content).'`',
            default => $content,
        };
    }

    private function table(DOMElement $table): string
    {
        $rows = [];
        foreach ($table->getElementsByTagName('tr') as $tr) {
            $row = [];
            foreach ($tr->childNodes as $cell) {
                if (!$cell instanceof DOMElement || !in_array($cell->tagName, ['td','th'], true)) continue;
                $text = trim(preg_replace('/\s+/u', ' ', $cell->textContent));
                $row[] = str_replace('|', '\\|', $text);
                for ($i = 1; $i < (int) $cell->getAttribute('colspan'); $i++) $row[] = '';
            }
            if ($row) $rows[] = $row;
        }
        if (!$rows) return '';
        $width = max(array_map('count', $rows));
        $lines = [];
        foreach ($rows as $index => $row) {
            $lines[] = '| '.implode(' | ', array_pad($row, $width, '')).' |';
            if ($index === 0) $lines[] = '| '.implode(' | ', array_fill(0, $width, '---')).' |';
        }
        return "\n\n".implode("\n", $lines)."\n\n";
    }

    private function longestBackticks(string $text): int
    {
        preg_match_all('/`+/', $text, $matches);
        return $matches[0] ? max(array_map('strlen', $matches[0])) : 0;
    }
}
