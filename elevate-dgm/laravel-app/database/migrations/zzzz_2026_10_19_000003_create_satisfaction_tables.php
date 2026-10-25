<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('satisfaction_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('position');
            $table->boolean('active')->default(true);
        });
        Schema::create('satisfaction_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('satisfaction_categories')->restrictOnDelete();
            $table->string('text', 500);
            $table->unsignedInteger('position');
            $table->boolean('dgroup_only')->default(false);
            $table->boolean('active')->default(true);
            $table->index(['category_id', 'active', 'position']);
        });
        Schema::create('satisfaction_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('service_date')->index();
            $table->foreignId('satellite_id')->constrained('satellites')->restrictOnDelete();
            $table->string('hub')->index();
            $table->index(['service_date', 'satellite_id', 'hub']);
        });
        Schema::create('satisfaction_answers', function (Blueprint $table) {
            $table->uuid('response_id');
            $table->foreign('response_id')->references('id')->on('satisfaction_responses')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('satisfaction_questions')->restrictOnDelete();
            $table->boolean('yes');
            $table->primary(['response_id', 'question_id']);
            $table->index('question_id');
        });
        Schema::create('satisfaction_tokens', function (Blueprint $table) {
            $table->string('token', 64)->primary();
        });
        Schema::create('satisfaction_question_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('satisfaction_questions')->restrictOnDelete();
            $table->string('old_text', 500);
            $table->string('new_text', 500);
            $table->timestamp('edited_at');
        });

        $groups = [
            'Church experience' => [
                ['Did you feel welcome the last time you attended a service or gathering?', false],
                ['Is the message or teaching easy for you to understand?', false],
                ['Do you feel safe and comfortable in the place where we gather?', false],
                ['Do you feel you belong in this community?', false],
            ],
            'Attendance' => [
                ['Can you usually attend the services or gatherings you want to join?', false],
                ['Are the schedules and venues convenient for you?', false],
                ['Do you know when and where the next gathering is?', false],
                ['Do you feel someone from your DGroup would reach out to you if you missed a gathering?', true],
            ],
            'Activities' => [
                ['Are the activities and events well organized?', false],
                ['Are you able to take part in the activities you are interested in?', false],
                ['Does your DGroup meeting help you grow spiritually?', true],
                ['Does your DGroup leader support you when you need help?', true],
            ],
        ];
        $categoryPosition = 1;
        foreach ($groups as $name => $questions) {
            $categoryId = DB::table('satisfaction_categories')->insertGetId(['name' => $name, 'position' => $categoryPosition++]);
            foreach ($questions as $position => [$text, $dgroupOnly]) {
                DB::table('satisfaction_questions')->insert(['category_id' => $categoryId, 'text' => $text, 'position' => $position + 1, 'dgroup_only' => $dgroupOnly]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('satisfaction_question_edits');
        Schema::dropIfExists('satisfaction_tokens');
        Schema::dropIfExists('satisfaction_answers');
        Schema::dropIfExists('satisfaction_responses');
        Schema::dropIfExists('satisfaction_questions');
        Schema::dropIfExists('satisfaction_categories');
    }
};
