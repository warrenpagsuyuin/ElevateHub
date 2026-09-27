<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('follow_ups')) {
            return;
        }

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seeker_id')->nullable()->index();
            $table->unsignedBigInteger('member_id')->nullable()->index();
            $table->unsignedBigInteger('satellite_id')->nullable()->index();
            $table->unsignedBigInteger('created_by_id')->nullable()->index();
            $table->date('follow_up_date')->nullable()->index();
            $table->string('method')->default('Phone')->comment('Phone, Email, In-Person, Other');
            $table->text('notes')->nullable();
            $table->string('status')->default('Pending')->index()->comment('Pending, Completed, Cancelled');
            $table->timestamps();
            $table->foreign('seeker_id')->references('id')->on('seekers')->nullOnDelete();
            $table->foreign('member_id')->references('id')->on('members')->nullOnDelete();
            $table->foreign('satellite_id')->references('id')->on('satellites')->nullOnDelete();
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
    }
};
