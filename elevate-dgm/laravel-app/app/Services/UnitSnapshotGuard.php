<?php
namespace App\Services;

use App\Models\UnitSnapshot;

class UnitSnapshotGuard
{
    public static function hasClosedHistory(int $unitId): bool
    {
        return UnitSnapshot::where('unit_id',$unitId)->where(fn ($query) => $query->whereNotNull('closed_at')->orWhereHas('checkpoint',fn ($q) => $q->where('status','closed')))->exists();
    }
}
