<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seekers', function (Blueprint $table): void {
            $table->string('last_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('gender')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('facebook_link')->nullable();
            $table->date('birthdate')->nullable();
            $table->unsignedInteger('age')->nullable();
            $table->string('area')->nullable();
            $table->string('active_disciples')->nullable();
            $table->string('year_joined')->nullable();
            $table->string('ministry_involvement')->nullable();
            $table->string('weekly_dgroups_led')->nullable();
            $table->string('generation')->nullable();
            $table->date('refresh_date')->nullable();
            $table->unsignedInteger('year')->nullable();
            $table->string('month')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seekers', function (Blueprint $table): void {
            $table->dropColumn([
                'last_name', 'first_name', 'gender', 'contact_number', 'facebook_link',
                'birthdate', 'age', 'area', 'active_disciples', 'year_joined',
                'ministry_involvement', 'weekly_dgroups_led', 'generation', 'refresh_date',
                'year', 'month',
            ]);
        });
    }
};
