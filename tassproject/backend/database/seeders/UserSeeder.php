<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@tassproject.test'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'rep@tassproject.test'],
            [
                'name' => 'Sales Rep',
                'password' => Hash::make('password'),
                'role' => 'rep',
            ]
        );

        User::updateOrCreate(
            ['email' => 'readonly@tassproject.test'],
            [
                'name' => 'Read Only User',
                'password' => Hash::make('password'),
                'role' => 'readonly',
            ]
        );
    }
}
