<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkpoint_attestations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('checkpoint_id')->constrained()->restrictOnDelete();
            $t->foreignId('unit_id')->constrained('org_units')->restrictOnDelete();
            $t->string('basis');
            $t->string('fingerprint', 64);
            $t->unsignedBigInteger('actor_id');
            $t->text('reason');
            $t->text('evidence_reference');
            $t->timestamp('created_at');
            $t->index(['checkpoint_id', 'unit_id', 'basis']);
        });
        Schema::create('checkpoint_closures', function (Blueprint $t) {
            $t->id();
            $t->foreignId('checkpoint_id')->unique()->constrained()->restrictOnDelete();
            $t->string('basis');
            $t->string('fingerprint', 64);
            $t->unsignedBigInteger('actor_id');
            $t->text('reason');
            $t->text('evidence_reference');
            $t->json('review');
            $t->timestamp('created_at');
        });
        Schema::create('checkpoint_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('checkpoint_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->unsignedBigInteger('actor_id');
            $t->text('reason');
            $t->text('evidence_reference');
            $t->json('changes');
            $t->json('units');
            $t->timestamp('created_at');
            $t->unique(['checkpoint_id', 'version']);
        });
        if (DB::getDriverName() === 'sqlite') {
            foreach (['checkpoint_attestations', 'checkpoint_closures', 'checkpoint_revisions'] as $table) {
                foreach (['UPDATE', 'DELETE'] as $op) {
                    DB::unprepared("CREATE TRIGGER {$table}_immutable_{$op} BEFORE {$op} ON {$table} BEGIN SELECT RAISE(ABORT, 'Append-only checkpoint audit'); END");
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['checkpoint_revisions', 'checkpoint_closures', 'checkpoint_attestations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
