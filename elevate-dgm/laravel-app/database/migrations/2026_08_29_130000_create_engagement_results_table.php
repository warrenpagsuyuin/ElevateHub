<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('engagement_results')) {
            return;
        }

        Schema::create('engagement_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id')->nullable()->unique()->index();
            $table->string('classification')->nullable()->comment('High, Medium, Low, Inactive');
            $table->decimal('confidence', 5, 2)->nullable()->comment('Classification confidence 0-1');
            $table->decimal('predicted_attendance', 5, 2)->nullable()->comment('Predicted attendance rate');
            $table->json('metrics')->nullable()->comment('CART decision path and metrics');
            $table->timestamps();
            $table->foreign('member_id')->references('id')->on('members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_results');
    }
};
