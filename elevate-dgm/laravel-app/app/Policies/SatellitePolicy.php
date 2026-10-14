<?php

namespace App\Policies;

use App\Models\Satellite;
use App\Models\User;

class SatellitePolicy
{
    public function archive(User $user, Satellite $satellite): bool
    {
        return $user->normalizedRole() === 'admin';
    }

    public function restore(User $user, Satellite $satellite): bool
    {
        return $user->normalizedRole() === 'admin';
    }
}
