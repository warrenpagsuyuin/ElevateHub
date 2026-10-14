<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seekers', function (Blueprint $table): void {
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('archive_reason', ['auto_final_status', 'manual'])->nullable();
        });
        Schema::create('seeker_archive_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seeker_id')->constrained('seekers')->restrictOnDelete();
            $table->enum('action', ['archived', 'restored']);
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->string('previous_status');
            $table->text('note')->nullable();
            $table->index(['seeker_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seeker_archive_log');
        Schema::table('seekers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn('archive_reason');
        });
    }
};
