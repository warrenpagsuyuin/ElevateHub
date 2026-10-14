<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;

/** Bulk Eloquent removal follows the same retention rule as instance removal. */
class SatelliteBuilder extends Builder
{
    public function delete()
    {
        $satellites = $this->get();
        foreach ($satellites as $satellite) {
            $satellite->archive(auth()->user());
        }

        return $satellites->count();
    }

    public function forceDelete()
    {
        return $this->delete();
    }
}
