<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('feedback_analysis_units', fn(Blueprint $table)=>$table->string('reference_source')->nullable());
    }
    public function down(): void
    {
        Schema::table('feedback_analysis_units', fn(Blueprint $table)=>$table->dropColumn('reference_source'));
    }
};
