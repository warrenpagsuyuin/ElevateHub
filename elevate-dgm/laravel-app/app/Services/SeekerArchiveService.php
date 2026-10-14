<?php

namespace App\Services;

use App\Models\{Member, Satellite, Seeker, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeekerArchiveService
{
    public function __construct(private SeekerLeaderRequirement $leaderRequirement) {}

    public function authorize(User $actor, Seeker $seeker): void
    {
        abort_unless($actor->normalizedRole() === 'admin' ||
            ($seeker->satellite && $actor->canManageSeekers($seeker->satellite)), 403);
    }

    public function changeStatus(Seeker $seeker, string $status, User $actor, ?Satellite $scope = null, ?Member $validatedLeader = null): Seeker
    {
        return DB::transaction(function () use ($seeker, $status, $actor, $scope, $validatedLeader) {
            $row = Seeker::whereKey($seeker->id)->lockForUpdate()->firstOrFail();
            $this->authorizeStatusChange($actor, $row, $scope);
            $previous = $row->status;
            $final = in_array($status, config('seekers.final_statuses'), true);
            if ($final && !$validatedLeader) {
                $this->leaderRequirement->requireForFinalStatus($row);
            }
            if ($final || $row->archived_at) {
                $this->authorizeStatusChange($actor, $row, $scope);
            }
            if ($row->archived_at && $final && $previous !== $status) {
                throw ValidationException::withMessages(['status' => 'Restore this seeker before changing a final status.']);
            }
            $row->status = $status;
            if ($final && ! $row->archived_at) {
                $this->archiveRow($row, $actor, 'auto_final_status', $previous, null);
            } elseif (! $final && $row->archived_at) {
                $this->restoreRow($row, $actor, $previous, 'Restored by a non-final status change.');
            } else {
                $row->save();
            }

            return $row;
        }, 3);
    }

    private function authorizeStatusChange(User $actor, Seeker $seeker, ?Satellite $scope): void
    {
        if ($scope) {
            abort_unless($seeker->assigned_satellite_slug === $scope->slug, 404);
            abort_unless($actor->canChangeSeekerStatus($scope), 403);

            return;
        }

        abort_unless($actor->normalizedRole() === 'admin'
            || ($seeker->satellite && $actor->canManageSatellite($seeker->satellite)), 403);
    }

    /**
     * Automatically move every active seeker with a final (non-pending) status to the archive.
     */
    public function archiveFinalStatuses(User $actor): int
    {
        $count = 0;
        Seeker::query()
            ->whereNull('archived_at')
            ->whereIn('status', config('seekers.final_statuses'))
            ->orderBy('id')
            ->get()
            ->each(function (Seeker $seeker) use ($actor, &$count): void {
                if ($this->archive($seeker, $actor, '', true)->archived_at) {
                    $count++;
                }
            });

        return $count;
    }

    public function archive(Seeker $seeker, User $actor, string $note, bool $automatic = false): Seeker
    {
        return DB::transaction(function () use ($seeker, $actor, $note, $automatic) {
            $row = Seeker::whereKey($seeker->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $row);
            if (! $automatic && trim($note) === '') {
                throw ValidationException::withMessages(['note' => 'An archive reason is required.']);
            }
            if ($automatic && ! in_array($row->status, config('seekers.final_statuses'), true)) {
                return $row;
            }
            if (! $row->archived_at) {
                $this->archiveRow($row, $actor, $automatic ? 'auto_final_status' : 'manual', $row->status, $note);
            }

            return $row;
        }, 3);
    }

    public function restore(Seeker $seeker, User $actor, ?string $status, bool $keepFinal, ?string $note, ?int $expectedLog = null): Seeker
    {
        return DB::transaction(function () use ($seeker, $actor, $status, $keepFinal, $note, $expectedLog) {
            $row = Seeker::whereKey($seeker->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $row);
            if ($row->converted_member_id !== null) {
                throw ValidationException::withMessages(['status' => 'This seeker has moved to Members. Manage the linked member there.']);
            }
            if ($expectedLog !== null) {
                $latest = DB::table('seeker_archive_log')->where('seeker_id', $row->id)->latest('id')->first();
                abort_unless($latest && $latest->id === $expectedLog && $latest->action === 'archived', 409, 'This seeker changed since the archive action. Reload and review the current record.');
            }
            if (! $row->archived_at) {
                return $row;
            }
            $previous = $row->status;
            $next = $status ?: $previous;
            if (in_array($next, config('seekers.final_statuses'), true)) {
                $this->leaderRequirement->requireForFinalStatus($row);
            }
            if (in_array($next, config('seekers.final_statuses'), true) && ! $keepFinal) {
                throw ValidationException::withMessages(['status' => 'Choose a non-final status or confirm keep final status, stay active.']);
            }
            $row->status = $next;
            $this->restoreRow($row, $actor, $previous, $note);

            return $row;
        }, 3);
    }

    private function archiveRow(Seeker $row, User $actor, string $reason, string $previous, ?string $note): void
    {
        $row->forceFill(['archived_at' => now(), 'archived_by' => $actor->id, 'archive_reason' => $reason])->save();
        $this->log($row, 'archived', $actor, $previous, $note);
    }

    private function restoreRow(Seeker $row, User $actor, string $previous, ?string $note): void
    {
        $row->forceFill(['archived_at' => null, 'archived_by' => null, 'archive_reason' => null])->save();
        $this->log($row, 'restored', $actor, $previous, $note);
    }

    private function log(Seeker $row, string $action, User $actor, string $previous, ?string $note): void
    {
        DB::table('seeker_archive_log')->insert(['seeker_id' => $row->id, 'action' => $action,
            'actor_id' => $actor->id, 'created_at' => now(), 'previous_status' => $previous, 'note' => $note]);
    }
}
