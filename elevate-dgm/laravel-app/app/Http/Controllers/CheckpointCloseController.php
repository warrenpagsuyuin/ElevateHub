<?php

namespace App\Http\Controllers;

use App\Models\Checkpoint;
use App\Services\CheckpointCloseService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CheckpointCloseController extends Controller
{
    public function index()
    {
        return view('analytics.checkpoint-review', ['checkpoints' => Checkpoint::orderBy('as_of_date')->get()]);
    }

    public function review(Request $request, Checkpoint $checkpoint, CheckpointCloseService $service)
    {
        $input = $request->validate(['basis' => ['sometimes', Rule::in(['system', 'legacy_import'])]]);

        return response()->json($service->review($request->user(), $checkpoint, $input['basis'] ?? 'system'))->header('Cache-Control', 'private, no-store');
    }

    public function attest(Request $request, Checkpoint $checkpoint, CheckpointCloseService $service)
    {
        $data = $request->validate($this->evidenceRules() + ['basis' => ['required', Rule::in(['system', 'legacy_import'])], 'unit' => ['required', 'string'], 'fingerprint' => ['required', 'string', 'size:64']]);

        return response()->json($service->attest($request->user(), $checkpoint, $data), 201);
    }

    public function close(Request $request, Checkpoint $checkpoint, CheckpointCloseService $service)
    {
        $data = $request->validate($this->evidenceRules() + ['basis' => ['required', Rule::in(['system', 'legacy_import'])], 'token' => ['required', 'string', 'size:64'],
            'confirmation' => ['required', 'string'], 'acknowledge_issues' => ['required', 'accepted']]);

        return response()->json($service->close($request->user(), $checkpoint, $data), 201);
    }

    public function correct(Request $request, Checkpoint $checkpoint, CheckpointCloseService $service)
    {
        $data = $request->validate($this->evidenceRules() + ['unit' => ['required', 'string'], 'expected_version' => ['required', 'integer', 'min:0'],
            'values' => ['required', 'array:'.implode(',', config('movement_analytics.metrics')), 'min:1'], 'values.*' => ['nullable', 'integer', 'min:0', 'max:2147483647']]);
        // JSON/form inputs share the same typed correction contract.
        $data['values'] = array_map(fn ($v) => $v === null ? null : (int) $v, $data['values']);

        return response()->json($service->correct($request->user(), $checkpoint, $data), 201);
    }

    private function evidenceRules(): array
    {
        return ['reason' => ['required', 'string', 'min:10', 'max:2000'], 'evidence_reference' => ['required', 'string', 'min:10', 'max:2000']];
    }
}
