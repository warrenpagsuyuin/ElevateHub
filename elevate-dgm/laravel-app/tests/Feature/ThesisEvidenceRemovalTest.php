<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SentimentResearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ThesisEvidenceRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_bookmark_permanently_redirects_without_serving_evidence(): void
    {
        $this->get('/admin/research/thesis-evidence')->assertStatus(301)
            ->assertRedirect('/admin/analytics/sentiment');
        $this->head('/admin/research/thesis-evidence')->assertStatus(301);
        $this->assertFalse(Route::has('admin.thesis-evidence.index'));
        $this->assertFalse(Route::has('admin.thesis-evidence.export'));
    }

    public function test_exports_are_gone_and_sentiment_keeps_its_report_without_thesis_navigation(): void
    {
        $admin = User::factory()->create(['role'=>'admin']);
        $this->actingAs($admin);
        foreach (['', '?format=json', '?table=22'] as $query) {
            $this->get('/admin/research/thesis-evidence/export'.$query)->assertNotFound();
        }
        $before = app(SentimentResearchService::class)->report();
        $this->get(route('admin.analytics.sentiment'))->assertOk()->assertSee('Sentiment Analytics')
            ->assertDontSee('Thesis Evidence')->assertDontSee('Export Thesis Evidence Report')
            ->assertDontSee('Download audit JSON')
            ->assertViewHas('report', fn ($report) => $report === $before);
    }
}
