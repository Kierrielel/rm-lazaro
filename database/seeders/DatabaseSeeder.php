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
    User::create([
        'username' => 'testuser',
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone_no' => '09123450000',
        'password' => Hash::make('changeme123'),
        'role' => 'customer',
        'province' => 'Bulacan',
        'city' => 'San Ildefonso',
        'unit' => '',
        'barangay' => '',
    ]);

    $this->call([
        RmLazaroSeeder::class,
    ]);
}
}
