<?php

use App\Models\Recommendation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recommendations')) {
            return;
        }

        Schema::create('recommendations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('engagement_result_id')->constrained('engagement_results')->cascadeOnDelete();
            $table->foreignId('recommended_action_id')->constrained('recommendation_actions')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default(Recommendation::STATUS_PENDING)->index();
            $table->string('action_title_snapshot');
            $table->text('action_description_snapshot');
            $table->text('assignment_notes')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['member_id', 'status']);
            $table->index(['engagement_result_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
