<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recommendation_actions')) {
            return;
        }

        Schema::create('recommendation_actions', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->unique();
            $table->text('description');
            $table->json('condition_metadata')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_actions');
    }
};
