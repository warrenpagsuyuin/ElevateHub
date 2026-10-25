<?php

namespace Tests\Feature;

use App\Models\Satellite;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class SummaryPanelPresentationTest extends TestCase
{
    public function test_summary_rows_preserve_long_labels_large_values_zero_and_semantics(): void
    {
        $label = 'Insufficient Information / Not Yet Classified';
        $html = view('components.summary-panel', [
            'id' => 'summary-test', 'title' => 'Engagement summary',
            'items' => [['label' => $label, 'value' => 0], ['label' => 'Active', 'value' => 1250000]],
        ])->render();
        $document = new DOMDocument;
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//section[@aria-labelledby="summary-test"]/h3[@id="summary-test"]')->length);
        $this->assertSame($label, $xpath->query('//dl/div/dt')->item(0)->textContent);
        $this->assertSame('0', $xpath->query('//dl/div/dd')->item(0)->textContent);
        $this->assertSame('1,250,000', $xpath->query('//dl/div/dd')->item(1)->textContent);
        $this->assertSame(0, $xpath->query('//dl//a | //dl//*[@style] | //dl//*[contains(@class,"elevate-stat-card")]')->length);
    }

    public function test_dashboard_composes_both_summary_panels_without_recent_activity_or_totals(): void
    {
        $satellite = new Satellite(['name' => 'Pasig', 'slug' => 'pasig']);
        $satellite->id = 1;
        $html = view('satellites.dashboard-content', [
            'satellite' => $satellite, 'isLiveDashboard' => true, 'dashboardHasData' => true,
            'satelliteSummaryCards' => [], 'recentActivity' => [],
            'engagementSummary' => [['label' => 'Active', 'value' => 0]],
            'seekerSummary' => [['label' => 'Pending', 'value' => 0]],
        ])->render();
        $document = new DOMDocument;
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);
        $this->assertSame(2, $xpath->query('//section[@class="summary-panel"]/h3')->length);
        $this->assertSame(2, $xpath->query('//section[@class="summary-panel"]//div[@class="summary-row"]')->length);
        $this->assertStringNotContainsString('Recent Activity', $html);
        $this->assertStringNotContainsString('Total', $html);
        $this->assertStringContainsString('data-activity-history', $html);
    }
}
