<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DevUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'dev@lifeat.online'],
            [
                'name' => 'Developer',
                'password' => bcrypt('dev_password_2026!'),
                'role' => 'dev',
            ]
        );
    }
}
