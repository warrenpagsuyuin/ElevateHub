<?php

namespace App\Http\Controllers;

use App\Models\Seeker;
use App\Services\SeekerArchiveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SeekerArchiveController extends Controller
{
    public function archive(Request $request, Seeker $seeker, SeekerArchiveService $service)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $seeker, $service, $data): void {
            $row = $service->archive($seeker, $request->user(), $data['note']);
            $this->rememberUndo($request, $row);
        }, 3);

        return back()->with('status', "{$seeker->name} moved to Archive.");
    }

    public function restore(Request $request, Seeker $seeker, SeekerArchiveService $service)
    {
        $data = $this->restoreData($request);
        $service->restore($seeker, $request->user(), $data['status'] ?? null, $request->boolean('keep_final'), $data['note'] ?? null);

        return back()->with('status', "{$seeker->name} restored to Active.");
    }

    public function bulk(Request $request, SeekerArchiveService $service)
    {
        $data = $request->validate([
            'action' => ['required', 'in:archive,restore'],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct', 'exists:seekers,id'],
            'note' => ['required_if:action,archive', 'nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(Seeker::STATUSES)],
            'keep_final' => ['nullable', 'boolean'],
        ]);
        DB::transaction(function () use ($data, $request, $service): void {
            foreach (Seeker::whereKey($data['ids'])->orderBy('id')->lockForUpdate()->get() as $seeker) {
                if ($data['action'] === 'archive') {
                    $service->archive($seeker, $request->user(), $data['note']);
                } else {
                    $service->restore($seeker, $request->user(), $data['status'] ?? null, $request->boolean('keep_final'), $data['note'] ?? null);
                }
            }
        }, 3);

        return back()->with('status', count($data['ids']).' selected seekers '.($data['action'] === 'archive' ? 'archived.' : 'restored.'));
    }

    public function rememberUndo(Request $request, Seeker $seeker): void
    {
        $log = DB::table('seeker_archive_log')->where('seeker_id', $seeker->id)->latest('id')->first();
        if (! $log || $log->action !== 'archived' || $log->actor_id !== $request->user()->id) {
            return;
        }
        $token = Str::random(40);
        $request->session()->put('seeker_archive_undo.'.$token, ['id' => $seeker->id, 'log' => $log->id, 'actor' => $request->user()->id]);
        $request->session()->flash('archive_undo', $token);
    }

    public function undo(Request $request, SeekerArchiveService $service)
    {
        $data = $request->validate(['token' => ['required', 'string']]);
        $key = 'seeker_archive_undo.'.$data['token'];
        $undo = $request->session()->get($key);
        abort_unless($undo && $undo['actor'] === $request->user()->id, 409, 'This Undo is no longer available in your session.');
        $service->restore(Seeker::findOrFail($undo['id']), $request->user(), null, true, 'Session Undo: keep status and return to Active.', $undo['log']);
        $request->session()->forget($key);

        return back()->with('status', 'Archive undone. The seeker is active; their status is unchanged.');
    }

    private function restoreData(Request $request): array
    {
        return $request->validate(['status' => ['nullable', Rule::in(Seeker::STATUSES)],
            'keep_final' => ['nullable', 'boolean'], 'note' => ['nullable', 'string', 'max:1000']]);
    }
}
