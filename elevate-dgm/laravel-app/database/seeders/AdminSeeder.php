<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('DEFAULT_ADMIN_EMAIL', 'admin@elevate.local');
        $name = (string) env('DEFAULT_ADMIN_NAME', 'Elevate Admin');
        $password = (string) env('DEFAULT_ADMIN_PASSWORD', 'password');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'role' => 'admin',
                'password' => Hash::make($password),
            ]
        );
    }
}
