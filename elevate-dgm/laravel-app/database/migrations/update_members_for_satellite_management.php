<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->unsignedBigInteger('satellite_id')->nullable()->index();
            $table->string('status')->default('Active')->index();
            $table->string('dgroup_role')->default('Member')->index();
            $table->string('last_name')->nullable()->index();
            $table->string('first_name')->nullable()->index();
            $table->string('full_name')->nullable()->index();
            $table->string('gender')->nullable();
            $table->string('email_address')->nullable()->index();
            $table->string('contact_number')->nullable();
            $table->string('facebook_link')->nullable();
            $table->date('birthdate')->nullable();
            $table->unsignedInteger('age')->nullable();
            $table->string('district')->nullable()->index();
            $table->string('area')->nullable()->index();
            $table->string('campus_school')->nullable()->index();
            $table->string('dgroup_leader_name')->nullable()->index();
            $table->unsignedInteger('active_disciples')->nullable();
            $table->unsignedInteger('year_joined')->nullable();
            $table->string('ministry_involvement')->nullable();
            $table->unsignedInteger('weekly_dgroups_led')->nullable();
            $table->string('generation')->nullable();
            $table->date('refresh_date')->nullable();
            $table->unsignedInteger('year')->nullable();
            $table->string('month')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropColumn([
                'satellite_id',
                'status',
                'dgroup_role',
                'last_name',
                'first_name',
                'full_name',
                'gender',
                'email_address',
                'contact_number',
                'facebook_link',
                'birthdate',
                'age',
                'district',
                'area',
                'campus_school',
                'dgroup_leader_name',
                'active_disciples',
                'year_joined',
                'ministry_involvement',
                'weekly_dgroups_led',
                'generation',
                'refresh_date',
                'year',
                'month',
            ]);
        });
    }
};
