<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
<<<<<<< HEAD
        // Call the admin seeder that seeds the local admin user
        $this->call(AdminUserSeeder::class);
>>>>>>> admin-dashboard

        User::updateOrCreate([
            'email' => 'pagsuyuinwarren@gmail.com',
        ], [
            'name' => 'Warren Pagsuyuin',
            'role' => 'member',
            'password' => Hash::make('password'),
        ]);
    }
}
