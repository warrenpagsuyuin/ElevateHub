<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per issued code. Only an HMAC of the code is stored; the
        // legacy users.otp_code / otp_expires_at columns are no longer used.
        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('email_hash', 64)->index();
            $table->char('code_hash', 64);
            $table->unsignedInteger('generation')->default(1);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedSmallInteger('resend_count')->default(0);
            $table->timestamp('resend_window_started_at')->nullable();
            $table->timestamp('resend_available_at')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('delivery_failed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        // Devices that have signed in before, used for new-device login alerts.
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64);
            $table->string('user_agent', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'token_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('otp_challenges');
    }
};
