<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@elevate.local'],
            [
                'name' => 'Elevate Admin',
                'role' => 'admin',
                'password' => Hash::make(env('DEFAULT_ADMIN_PASSWORD', 'password')),
            ]
        );
    }
}
