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
        $this->call(AdminSeeder::class);

        User::updateOrCreate([
            'email' => 'pagsuyuinwarren@gmail.com',
        ], [
            'name' => 'Warren Pagsuyuin',
            'role' => 'member',
            'password' => Hash::make('password'),
        ]);
    }
}
