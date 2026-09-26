<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::firstOrCreate(
            ['email' => 'admin@egycon.com'],
            [
                'name'     => 'EGYCON Admin',
                'password' => Hash::make('admin123'),
                'role'     => 'admin',
            ]
        );
    }
}
