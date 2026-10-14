<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MovementConflictController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['code' => 'required|string', 'alternative' => 'required|integer|min:0|max:1', 'reason' => 'required|string|min:10|max:2000', 'evidence_reference' => 'required|string|min:10|max:2000']);
        $conflict = collect(config('movement_analytics.declared_conflicts'))->firstWhere('code', $data['code']);
        abort_unless($conflict, 404);
        DB::transaction(function () use ($data, $conflict, $request) {
            $cp = DB::table('checkpoints')->where('key', $conflict['checkpoint'])->lockForUpdate()->first();
            if (! $cp || $cp->status === 'closed') {
                throw ValidationException::withMessages(['code' => 'Closed checkpoints require the audited correction workflow.']);
            }
            if (DB::table('movement_conflict_resolutions')->where('conflict_code', $data['code'])->exists()) {
                throw ValidationException::withMessages(['code' => 'This conflict already has a recorded resolution.']);
            }
            DB::table('movement_conflict_resolutions')->insert(['conflict_code' => $data['code'], 'checkpoint_id' => $cp->id, 'unit_id' => DB::table('org_units')->where('key', $conflict['unit'])->value('id'), 'actor_id' => $request->user()->id, 'chosen_values' => json_encode($conflict['alternatives'][$data['alternative']]), 'reason' => $data['reason'], 'evidence_reference' => $data['evidence_reference'], 'created_at' => now()]);
        });

        return response()->json(['message' => 'Resolution recorded. Original source records are preserved.'], 201);
    }
}
