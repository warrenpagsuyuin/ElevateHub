<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movement_conflict_resolutions', function (Blueprint $table) {
            $table->id();
            $table->string('conflict_code')->unique();
            $table->foreignId('checkpoint_id')->constrained('checkpoints');
            $table->foreignId('unit_id')->constrained('org_units');
            $table->unsignedBigInteger('actor_id');
            $table->json('chosen_values');
            $table->text('reason');
            $table->text('evidence_reference');
            $table->timestamp('created_at');
        });
        if (DB::getDriverName() === 'sqlite') {
            foreach (['UPDATE', 'DELETE'] as $op) {
                DB::unprepared('CREATE TRIGGER movement_resolution_'.strtolower($op)." BEFORE $op ON movement_conflict_resolutions BEGIN SELECT RAISE(ABORT, 'Resolution history is immutable'); END");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('movement_conflict_resolutions');
    }
};
