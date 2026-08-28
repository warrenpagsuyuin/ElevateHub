<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('satellites', function (Blueprint $table) {
            $table->string('hub')->default('MAIN')->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('satellites', function (Blueprint $table) {
            $table->dropColumn('hub');
        });
    }
};
